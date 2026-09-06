/**
 * isproject/framework — application behaviour.
 *
 * Plain ES2020, no build step and no jQuery. Bootstrap's own bundle handles
 * dropdowns, collapses and alerts; this file owns the shell:
 *
 *   - collapsing the sidebar on desktop, remembered across visits
 *   - opening it as a drawer on small screens
 *   - the light / dark / system theme toggle
 *
 * The initial state is applied by the inline boot script in the layout, before
 * first paint. This file only handles interaction after the page has loaded.
 */
(function () {
    'use strict';

    var STORAGE_SIDEBAR = 'isproject.sidebar';
    var STORAGE_THEME = 'isproject.theme';
    var root = document.documentElement;

    // ------------------------------------------------------------ dropdowns

    // Generated index screens wrap their table in .table-responsive, which is
    // an overflow-x: auto scroller. Popper's default "absolute" strategy leaves
    // the menu inside that box, so it gets clipped at the card edge — the last
    // row's menu is the worst case, cut off almost entirely.
    //
    // The fixed strategy positions against the viewport, which no plain
    // overflow ancestor can clip, and a viewport boundary stops Popper from
    // squeezing the menu back inside the table it just escaped.
    //
    // Set on Default rather than per element: Bootstrap builds a Dropdown
    // instance lazily on first click, so every menu picks this up, including
    // ones students write by hand.
    if (window.bootstrap && window.bootstrap.Dropdown) {
        window.bootstrap.Dropdown.Default.popperConfig = { strategy: 'fixed' };
        window.bootstrap.Dropdown.Default.boundary = 'viewport';
    }

    /** localStorage is unavailable in some privacy modes; degrade quietly. */
    function store(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            /* not fatal — the preference just will not persist */
        }
    }

    function read(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    // -------------------------------------------------------------- sidebar

    function isDesktop() {
        return window.matchMedia('(min-width: 992px)').matches;
    }

    function setSidebar(state) {
        root.setAttribute('data-is-sidebar', state);
        store(STORAGE_SIDEBAR, state);
    }

    function toggleSidebar() {
        if (isDesktop()) {
            setSidebar(root.getAttribute('data-is-sidebar') === 'collapsed' ? 'expanded' : 'collapsed');
        } else {
            document.body.classList.toggle('is-sidebar-open');
        }
    }

    function closeDrawer() {
        document.body.classList.remove('is-sidebar-open');
    }

    // ---------------------------------------------------------------- theme

    /** 'light' | 'dark' | 'system' — 'system' follows the OS preference. */
    function resolveTheme(preference) {
        if (preference === 'light' || preference === 'dark') {
            return preference;
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(preference) {
        root.setAttribute('data-bs-theme', resolveTheme(preference));
        root.setAttribute('data-is-theme-preference', preference);
        store(STORAGE_THEME, preference);

        document.querySelectorAll('[data-is-theme-value]').forEach(function (option) {
            option.classList.toggle('active', option.getAttribute('data-is-theme-value') === preference);
        });
    }

    function cycleTheme() {
        var current = read(STORAGE_THEME) || 'system';

        applyTheme(resolveTheme(current) === 'dark' ? 'light' : 'dark');
    }

    // --------------------------------------------------------- announcement

    /**
     * Close the announcement bar for a while.
     *
     * Stored against the announcement's own id rather than a bare flag, so
     * editing the message brings it back at once for everyone — including
     * people who dismissed the previous one a minute ago.
     */
    function dismissAnnouncement(bar) {
        var hours = parseInt(bar.getAttribute('data-is-announcement-hours'), 10) || 1;

        store('isproject.announcement', JSON.stringify({
            id: bar.getAttribute('data-is-announcement-id'),
            until: Date.now() + hours * 3600 * 1000,
        }));

        // The attribute, not the element: the same rule hides it before paint
        // on the next page, so there is one way it can be hidden, not two.
        root.setAttribute('data-is-announcement', 'hidden');
    }

    // ----------------------------------------------------------- delegation

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-is-toggle="sidebar"]');

        if (toggle) {
            event.preventDefault();
            toggleSidebar();

            return;
        }

        if (event.target.closest('[data-is-toggle="theme"]')) {
            event.preventDefault();
            cycleTheme();

            return;
        }

        var dismiss = event.target.closest('[data-is-dismiss="announcement"]');

        if (dismiss) {
            event.preventDefault();
            dismissAnnouncement(dismiss.closest('.is-announcement'));

            return;
        }

        var choice = event.target.closest('[data-is-theme-value]');

        if (choice) {
            event.preventDefault();
            applyTheme(choice.getAttribute('data-is-theme-value'));

            return;
        }

        // Tapping the dimmed page, or any link inside the drawer, closes it.
        if (event.target.closest('.is-backdrop') || (!isDesktop() && event.target.closest('.is-sidebar a[href]'))) {
            closeDrawer();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

    // Leaving mobile width with the drawer open would strand the backdrop.
    window.addEventListener('resize', function () {
        if (isDesktop()) {
            closeDrawer();
        }
    });

    // Follow the OS while the preference is 'system'.
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if ((read(STORAGE_THEME) || 'system') === 'system') {
            applyTheme('system');
        }
    });

    // ------------------------------------------------------------- dropdowns

    // Tom Select turns a long <select> into one you can type into. Applied to
    // anything marked data-is-select, and to any select with more options than
    // the threshold — a two-option select does not need a search box, and a
    // timezone list of four hundred is unusable without one.
    //
    // The underlying <select> is left in the DOM and keeps its name, so forms
    // submit exactly as they would have and nothing server-side changes.
    function enhanceSelects() {
        // The script is loaded before this one, but a page that overrode the
        // layout may not carry it. Doing nothing leaves ordinary selects, which
        // work perfectly well.
        if (typeof TomSelect === 'undefined') {
            return;
        }

        var threshold = Number(root.getAttribute('data-is-select-threshold')) || 8;

        document.querySelectorAll('select').forEach(function (select) {
            var marked = select.getAttribute('data-is-select');

            if (marked === 'off' || select.tomselect) {
                return;
            }

            if (marked === null && select.options.length <= threshold) {
                return;
            }

            new TomSelect(select, {
                allowEmptyOption: true,
                maxOptions: null,
                plugins: select.multiple ? ['remove_button'] : [],

                // The dropdown is moved to <body> so an overflow: auto ancestor
                // — a scrolling table, a card with clipped corners — cannot cut
                // it off. The same clipping problem the row menus had.
                dropdownParent: 'body',

                placeholder: select.getAttribute('data-is-select-placeholder')
                    || (select.options[0] && select.options[0].value === '' ? select.options[0].text : null),

                render: {
                    no_results: function () {
                        return '<div class="no-results">Nothing matches that.</div>';
                    },
                },
            });
        });
    }

    // --------------------------------------------------------- menu manager

    // Only the fields the chosen kind of item actually uses. Every panel is in
    // the HTML and simply hidden, so the form still submits correctly — and
    // still makes sense — with this file blocked or not yet loaded.
    function syncMenuForm(form) {
        var select = form.querySelector('[data-is-menu-type]');

        if (!select) {
            return;
        }

        var type = select.value;

        form.querySelectorAll('[data-is-menu-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-is-menu-panel').split(' ').indexOf(type) === -1;
        });

        form.querySelectorAll('[data-is-menu-when]').forEach(function (element) {
            element.hidden = element.getAttribute('data-is-menu-when') !== type;
        });
    }

    var dragging = null;

    // Dragging starts from the grip, not from anywhere on the row: the row
    // carries buttons and a link, and making the whole thing draggable would
    // fight every attempt to click one of them.
    document.addEventListener('pointerdown', function (event) {
        var grip = event.target.closest('[data-is-menu-grip]');

        if (grip) {
            grip.closest('.is-menu-row').setAttribute('draggable', 'true');
        }
    });

    document.addEventListener('pointerup', function () {
        document.querySelectorAll('.is-menu-row[draggable]').forEach(function (row) {
            row.removeAttribute('draggable');
        });
    });

    document.addEventListener('dragstart', function (event) {
        var row = event.target.closest('.is-menu-row[draggable="true"]');

        if (!row) {
            return;
        }

        dragging = row;
        row.classList.add('is-menu-dragging');
        event.dataTransfer.effectAllowed = 'move';

        // Firefox ignores a drag that carries no data.
        event.dataTransfer.setData('text/plain', row.getAttribute('data-id'));
    });

    /** The row this one should be inserted before, or null for "at the end". */
    function rowAfter(list, y) {
        var closest = null;
        var closestOffset = Number.NEGATIVE_INFINITY;

        Array.prototype.forEach.call(list.children, function (row) {
            if (row === dragging || !row.classList.contains('is-menu-row')) {
                return;
            }

            var box = row.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closestOffset) {
                closestOffset = offset;
                closest = row;
            }
        });

        return closest;
    }

    document.addEventListener('dragover', function (event) {
        if (!dragging) {
            return;
        }

        var list = event.target.closest('[data-is-menu-list]');

        // Dropping a row into its own sub-list would make it its own parent.
        if (!list || dragging.contains(list)) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        var before = rowAfter(list, event.clientY);

        if (before) {
            list.insertBefore(dragging, before);
        } else {
            list.appendChild(dragging);
        }
    });

    /** The tree as a flat list of {id, parent} in the order it now reads. */
    function menuOrder(tree) {
        var order = [];

        tree.querySelectorAll('.is-menu-row').forEach(function (row) {
            var parent = row.parentElement.getAttribute('data-parent');

            order.push({
                id: Number(row.getAttribute('data-id')),
                parent: parent ? Number(parent) : null,
            });
        });

        return order;
    }

    function saveMenuOrder(tree) {
        var status = document.querySelector('[data-is-menu-status]');
        var token = document.querySelector('meta[name="csrf-token"]');

        if (status) {
            status.textContent = 'Saving…';
        }

        fetch(tree.getAttribute('data-is-menu-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({ order: menuOrder(tree) }),
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok) {
                    throw new Error(body.message || 'The new order was not saved.');
                }

                return body;
            });
        }).then(function (body) {
            if (status) {
                status.textContent = body.message;
            }
        }).catch(function (error) {
            if (status) {
                status.textContent = error.message + ' Reloading…';
            }

            // The server refused the shape, so what is on screen is now a lie.
            // Reloading is the honest thing to do: it shows the order that
            // actually holds rather than leaving a rejected one looking saved.
            window.setTimeout(function () {
                window.location.reload();
            }, 1200);
        });
    }

    document.addEventListener('dragend', function () {
        if (!dragging) {
            return;
        }

        dragging.classList.remove('is-menu-dragging');
        dragging.removeAttribute('draggable');
        dragging = null;

        var tree = document.querySelector('[data-is-menu-tree]');

        if (tree) {
            saveMenuOrder(tree);
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-is-menu-type]')) {
            syncMenuForm(event.target.closest('form'));
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(read(STORAGE_THEME) || 'system');

        // Mark the current page in the sidebar for assistive technology.
        document.querySelectorAll('.is-nav-link.active').forEach(function (link) {
            link.setAttribute('aria-current', 'page');
        });

        document.querySelectorAll('[data-is-menu-form]').forEach(syncMenuForm);

        enhanceSelects();
    });
})();
