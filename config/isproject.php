<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Blade layout that every generated view extends. The bundled layout is
    | self-hosted (Bootstrap 5, MIT) with a responsive sidebar and dark mode.
    | Point this at your own layout with ISPROJECT_LAYOUT=layouts.app once you
    | have one; it needs a "content" section and a "title" section.
    |
    */

    'layout' => env('ISPROJECT_LAYOUT', 'isproject::layouts.app'),

    'guest_layout' => env('ISPROJECT_GUEST_LAYOUT', 'isproject::layouts.guest'),

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Shown in the sidebar and on the sign-in screen. 'icon' is any name from
    | the bundled icon set — see resources/views/components/icon.blade.php.
    |
    */

    'brand' => [
        'name' => env('ISPROJECT_BRAND', null), // null falls back to config('app.name')
        'icon' => 'grid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar menu
    |--------------------------------------------------------------------------
    |
    | Each entry is one of:
    |
    |   ['heading' => 'Section title']
    |   ['label' => 'Products', 'icon' => 'box', 'route' => 'products.index']
    |   ['label' => 'Reports', 'icon' => 'list', 'children' => [ ...entries ]]
    |
    | Optional keys: 'url' instead of 'route', 'active' (one or more patterns
    | for request()->is()), 'can' (a Gate ability, or [ability, Model::class]),
    | and 'badge'.
    |
    | Entries whose route does not exist yet are skipped rather than throwing,
    | so you can list a module before generating it. `isproject:crud` prints the
    | entry to paste here after it runs.
    |
    | This array is the interim step towards the menu management module: the
    | renderer already consumes a normalised tree, so only the source changes.
    |
    */

    'menu' => [
        ['heading' => 'Main'],
        ['label' => 'Dashboard', 'icon' => 'home', 'url' => '/', 'active' => '/'],

        ['heading' => 'Manage'],
        // Generated modules go here, e.g.
        // ['label' => 'Products', 'icon' => 'box', 'route' => 'products.index'],

        // 'can' hides an entry from anyone whose roles do not grant it. Safe to
        // leave in place before RBAC is set up: with no roles defined there is
        // nothing to enforce, so every entry shows.
        ['heading' => 'System'],
        ['label' => 'Users', 'icon' => 'people', 'route' => 'isproject.users.index', 'can' => 'isproject.users.index'],
        ['label' => 'Roles', 'icon' => 'check-circle', 'route' => 'isproject.roles.index', 'can' => 'isproject.roles.index'],
        ['label' => 'Audit trail', 'icon' => 'list', 'route' => 'isproject.audit.index', 'can' => 'isproject.audit.index'],
        ['label' => 'Menu', 'icon' => 'menu', 'route' => 'isproject.menu.index', 'can' => 'isproject.menu.index'],
        ['label' => 'Settings', 'icon' => 'settings', 'route' => 'isproject.settings.index', 'can' => 'isproject.settings.index'],

        ['heading' => 'Help'],
        ['label' => 'Manual', 'icon' => 'inbox', 'route' => 'isproject.manual.index'],

        ['heading' => 'Development'],
        // Only resolves while the generator is enabled; skipped otherwise.
        ['label' => 'Generator', 'icon' => 'grid', 'route' => 'isproject.generator.index'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route generation
    |--------------------------------------------------------------------------
    |
    | Generated resource routes are appended to the routes file below, wrapped
    | in the given middleware. Set 'routes_file' to null to skip route writing
    | entirely and register them yourself.
    |
    | Note: routes/web.php already runs inside the "web" middleware group, so
    | only list the extra middleware here.
    |
    */

    'routes_file' => 'routes/web.php',
    'route_prefix' => '',
    'middleware' => ['auth'],

    /*
    |--------------------------------------------------------------------------
    | Generation targets
    |--------------------------------------------------------------------------
    |
    | Everything the crud generator can emit. Trim this list to change the
    | default for `isproject:crud`; the --only flag overrides it per run.
    |
    */

    'generate' => ['model', 'controller', 'requests', 'views', 'report', 'factory', 'policy', 'routes'],

    /*
    |--------------------------------------------------------------------------
    | Generator web UI
    |--------------------------------------------------------------------------
    |
    | A page that lists the database tables and generates a module from a
    | button, for students who are not comfortable at the command line.
    |
    | It writes PHP files into the application, so treat it as a development
    | tool: by default it exists ONLY in the local environment. Setting
    | ISPROJECT_GENERATOR=true switches it on elsewhere — do not do that on a
    | shared or public server. The `gate` ability below is checked on top of
    | the middleware; define it in a policy to limit the page to lecturers.
    |
    */

    'generator' => [
        'enabled' => env('ISPROJECT_GENERATOR', null), // null = local environment only
        'path' => 'isproject/generator',
        'middleware' => ['web', 'auth'],
        'gate' => null, // e.g. 'use-isproject-generator'
    ],

    /*
    |--------------------------------------------------------------------------
    | Site configuration screen
    |--------------------------------------------------------------------------
    |
    | A settings page backed by the isproject_settings table, so the site name,
    | logo and favicon can be changed without editing .env or redeploying.
    |
    | Unlike the generator this page is meant to work in production, so it is
    | only as protected as you make it. With 'gate' => null any signed-in user
    | can change these values and clear caches. On anything shared, define an
    | ability and name it here:
    |
    |     Gate::define('manage-settings', fn ($user) => $user->is_lecturer);
    |
    | Add your own settings by adding entries below — no migration needed. Each
    | field takes: type (text, textarea, email, url, number, select, boolean,
    | image), label, help, default, width, and either an options array or one of
    | the built-in lists '@icons', '@timezones', '@themes'.
    |
    | Options must not be closures: config:cache cannot serialise them.
    |
    */

    'settings' => [
        'path' => 'settings',
        'middleware' => ['web', 'auth'],
        'gate' => null, // e.g. 'manage-settings'
        'disk' => 'public',
        'directory' => 'isproject',

        // Cache buttons offered on the page. Drop any you would rather students
        // could not press; 'optimize' clears the lot.
        'cache_actions' => ['cache', 'view', 'config', 'route'],

        'groups' => [

            'general' => [
                'label' => 'General',
                'icon' => 'info',
                'description' => 'Identity of the system, shown in the browser tab, the sidebar and the sign-in screen.',
                'fields' => [
                    'app_name' => [
                        'label' => 'System name',
                        'default' => '@config:app.name',
                        'help' => 'Appears in the sidebar, the browser tab and on the sign-in screen.',
                        'rules' => ['required', 'string', 'max:60'],
                    ],
                    'app_tagline' => [
                        'label' => 'Tagline',
                        'help' => 'One short line under the name on the sign-in screen.',
                        'rules' => ['nullable', 'string', 'max:120'],
                    ],
                    'support_email' => [
                        'type' => 'email',
                        'label' => 'Support email',
                        'help' => 'Shown in the footer so users know who to contact.',
                    ],
                    'footer_text' => [
                        'label' => 'Footer text',
                        'help' => 'Left blank, the footer shows the system name and the year.',
                        'width' => 'col-12',
                    ],
                ],
            ],

            'appearance' => [
                'label' => 'Appearance',
                'icon' => 'grid',
                'description' => 'Branding and the theme new visitors see first.',
                'fields' => [
                    'brand_icon' => [
                        'type' => 'select',
                        'label' => 'Brand icon',
                        'options' => '@icons',
                        'default' => 'grid',
                        'help' => 'Used in the sidebar when no logo is uploaded.',
                    ],
                    'default_theme' => [
                        'type' => 'select',
                        'label' => 'Default theme',
                        'options' => '@themes',
                        'default' => 'system',
                        'help' => 'What a first-time visitor sees. Their own choice always wins afterwards.',
                    ],
                    // SVG is deliberately absent from both. An SVG can carry
                    // script, these files are served from the application's own
                    // origin, and with no gate configured any signed-in user
                    // can upload one — that combination is stored XSS. Raster
                    // formats and .ico cover the need without it.
                    'logo' => [
                        'type' => 'image',
                        'label' => 'Logo',
                        'accept' => 'image/png,image/jpeg,image/webp',
                        'rules' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                        'help' => 'Replaces the brand icon in the sidebar. A wide, transparent PNG works best.',
                    ],
                    'favicon' => [
                        'type' => 'image',
                        'label' => 'Favicon',
                        'accept' => 'image/png,image/x-icon,image/vnd.microsoft.icon',
                        'rules' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
                        'help' => 'The small icon in the browser tab. A square PNG of 32x32 or larger, or an .ico file.',
                    ],
                ],
            ],

            'sign_in' => [
                'label' => 'Sign in',
                'icon' => 'user',
                'description' => 'How people get into the system.',
                'fields' => [
                    'auth_registration_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Allow self-registration',
                        'help' => 'Adds a "Create an account" link to the sign-in screen. Off means accounts are made on the Users screen only.',
                    ],

                    // 'requires' names config keys that must hold a value before
                    // the box can be ticked. The settings screen disables it and
                    // says which ones are missing; the controller refuses the
                    // change as well, so a hand-made POST cannot switch on a
                    // flow that would only send people to a Google error page.
                    'auth_google_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Allow Google sign-in',
                        'requires' => [
                            'isproject.auth.google.client_id' => 'GOOGLE_CLIENT_ID',
                            'isproject.auth.google.client_secret' => 'GOOGLE_CLIENT_SECRET',
                        ],
                        'help' => 'Adds a "Continue with Google" button to the sign-in screen.',
                    ],
                    'auth_google_register' => [
                        'type' => 'boolean',
                        'label' => 'Google may create accounts',
                        'requires' => [
                            'isproject.auth.google.client_id' => 'GOOGLE_CLIENT_ID',
                            'isproject.auth.google.client_secret' => 'GOOGLE_CLIENT_SECRET',
                        ],
                        'help' => 'Off means Google can only sign in people who already have an account here — usually what a course roster wants.',
                    ],
                ],
            ],

            'announcement' => [
                'label' => 'Announcement bar',
                'icon' => 'inbox',
                'description' => 'A strip across the top of every page. Visitors can close it; it comes back after the interval below, as long as it is still running.',
                'fields' => [
                    'announcement_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Show the announcement bar',
                    ],
                    'announcement_tone' => [
                        'type' => 'select',
                        'label' => 'Tone',
                        'options' => '@tones',
                        'default' => 'primary',
                    ],
                    'announcement_text' => [
                        'type' => 'textarea',
                        'label' => 'Message',
                        'width' => 'col-12',
                        'help' => 'Plain text. Editing this makes the bar reappear for everyone, including people who had closed the previous one.',
                        'rules' => ['nullable', 'string', 'max:300'],
                    ],
                    'announcement_url' => [
                        'label' => 'Link',
                        'placeholder' => 'https://example.edu/notice  or  /faq',
                        // Not the "url" rule: a site-relative path is valid here
                        // too. The pattern is what keeps "javascript:" out of an
                        // href — see SiteNotices::safeUrl().
                        'rules' => ['nullable', 'string', 'max:255', 'regex:#^(https?://|/)#i'],
                        'help' => 'Optional. An address makes the whole bar clickable; external links open in a new tab.',
                    ],
                    'announcement_until' => [
                        'type' => 'date',
                        'label' => 'Show until',
                        'help' => 'Optional. The bar stops appearing after this date, without anyone having to remember to switch it off.',
                    ],
                    'announcement_dismiss_hours' => [
                        'type' => 'number',
                        'label' => 'Hide for (hours) after closing',
                        'default' => 1,
                        'rules' => ['nullable', 'integer', 'min:1', 'max:720'],
                        'help' => 'How long the bar stays closed for someone who dismissed it.',
                    ],
                ],
            ],

            'ribbon' => [
                'label' => 'Corner ribbon',
                'icon' => 'box',
                'description' => 'A small diagonal banner in the top-right corner. Hidden on narrow screens, where it would cover the account menu.',
                'fields' => [
                    'ribbon_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Show the ribbon',
                    ],
                    'ribbon_tone' => [
                        'type' => 'select',
                        'label' => 'Tone',
                        'options' => '@tones',
                        'default' => 'danger',
                    ],
                    'ribbon_text' => [
                        'label' => 'Text',
                        'placeholder' => 'Beta',
                        'help' => 'Two or three words. The ribbon is small by design.',
                        'rules' => ['nullable', 'string', 'max:30'],
                    ],
                    'ribbon_url' => [
                        'label' => 'Link',
                        'placeholder' => 'https://example.edu  or  /faq',
                        'rules' => ['nullable', 'string', 'max:255', 'regex:#^(https?://|/)#i'],
                        'help' => 'Optional. Without one the ribbon is a label rather than a link.',
                    ],
                ],
            ],

            'seo' => [
                'label' => 'Search engines',
                'icon' => 'search',
                'description' => 'How this site appears to search engines and when its link is shared. Signed-in pages are never indexed whatever is set here.',
                'fields' => [
                    'seo_indexable' => [
                        'type' => 'boolean',
                        'label' => 'Allow search engines to index this site',
                        'width' => 'col-12',
                        'help' => 'Off by default, and off is right until the system is live. Only the signed-out pages are ever offered for indexing — the admin screens carry "noindex" regardless.',
                    ],
                    'seo_description' => [
                        'type' => 'textarea',
                        'label' => 'Meta description',
                        'width' => 'col-12',
                        'rules' => ['nullable', 'string', 'max:200'],
                        'help' => 'The sentence under your link in search results, and the preview text when the link is pasted into a chat. Around 155 characters is what gets shown.',
                    ],
                    'seo_share_image' => [
                        'type' => 'image',
                        'label' => 'Share image',
                        'accept' => 'image/png,image/jpeg,image/webp',
                        'rules' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                        'help' => 'Shown when the link is shared. 1200x630 works everywhere. Falls back to your logo.',
                    ],
                    'seo_canonical_host' => [
                        'label' => 'Canonical address',
                        'placeholder' => 'https://portal.example.edu',
                        'rules' => ['nullable', 'string', 'max:255', 'regex:#^https?://#i'],
                        'help' => 'Optional. Set this if the site answers on more than one address, so search engines credit one of them rather than treating them as duplicates.',
                    ],
                    'seo_verification' => [
                        'label' => 'Google verification code',
                        'width' => 'col-12',
                        'rules' => ['nullable', 'string', 'max:255'],
                        'help' => 'The content value from Search Console\'s HTML tag method — the code only, not the whole tag.',
                    ],
                ],
            ],

            // Installing the site as an app. The screen shows a readiness
            // panel beside these: the toggle is never blocked, because the
            // usual missing piece is HTTPS and nobody can fix that from a form.
            'app' => [
                'label' => 'Install as an app',
                'icon' => 'box',
                'description' => 'Let people add this system to a phone home screen or a desktop, and keep working when the connection drops.',
                'fields' => [
                    'pwa_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Offer to install this as an app',
                        'width' => 'col-12',
                        'help' => 'Adds the app manifest and registers a service worker. Switching this off unregisters it again on every device that installed it — it does not simply stop offering.',
                    ],
                    'pwa_name' => [
                        'type' => 'text',
                        'label' => 'App name',
                        // No @config: placeholder here — only 'default' is
                        // resolved through that, so it would print literally.
                        'help' => 'Shown on the install prompt. Defaults to the system name.',
                    ],
                    'pwa_short_name' => [
                        'type' => 'text',
                        'label' => 'Short name',
                        'rules' => ['nullable', 'string', 'max:12'],
                        'help' => 'What fits under a home screen icon — about 12 characters.',
                    ],
                    'pwa_icon' => [
                        'type' => 'image',
                        'label' => 'App icon',
                        'accept' => 'image/png,image/webp',

                        // Square and at least 192px is the point below which a
                        // browser will not use the file at all, so it is refused
                        // rather than stored to fail silently later. The 512px
                        // Chrome prefers is a warning on the screen, not a rule.
                        'rules' => [
                            'nullable', 'file', 'mimes:png,webp', 'max:2048',
                            'dimensions:min_width=192,min_height=192,ratio=1',
                        ],
                        'messages' => [
                            'dimensions' => 'The app icon must be square and at least 192×192. Use 512×512 or larger so browsers offer to install it.',
                            'mimes' => 'The app icon must be a PNG or a WebP. An SVG can carry script, and an ICO is not accepted as an app icon.',
                        ],
                        'help' => 'A square PNG, 512×512 or larger. Not an SVG or an ICO: neither is accepted as an install icon.',
                    ],
                    'pwa_icon_maskable' => [
                        'type' => 'boolean',
                        'label' => 'The icon has padding around it',
                        'help' => 'Android crops home screen icons to a circle or a squircle. Tick this only if your artwork keeps clear of its own edges, or the crop will cut into it.',
                    ],
                    'pwa_theme_color' => [
                        'type' => 'color',
                        'label' => 'Theme colour',
                        'default' => '#4338ca',
                        'help' => 'Tints the browser toolbar and the app title bar.',
                    ],
                    'pwa_background_color' => [
                        'type' => 'color',
                        'label' => 'Splash background',
                        'default' => '#ffffff',
                        'help' => 'Shown for the moment between tapping the icon and the first paint.',
                    ],
                    'pwa_display' => [
                        'type' => 'select',
                        'label' => 'How it opens',
                        'default' => 'standalone',
                        'options' => [
                            'standalone' => 'Its own window, no browser controls',
                            'minimal-ui' => 'Its own window, with back and reload',
                            'fullscreen' => 'Fullscreen',
                            'browser' => 'An ordinary browser tab',
                        ],
                    ],
                ],
            ],

            'regional' => [
                'label' => 'Regional',
                'icon' => 'list',
                'description' => 'Applied to every date the application renders.',
                'fields' => [
                    'timezone' => [
                        'type' => 'select',
                        'label' => 'Timezone',
                        'options' => '@timezones',
                        'default' => '@config:app.timezone',
                        'help' => 'Overrides the app.timezone value from config for display and storage.',
                    ],
                ],
            ],

        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Sign in, sign out, registration, forgotten passwords and a profile screen,
    | using Laravel's conventional route names. Set 'enabled' to false if you
    | use Breeze, Jetstream or Fortify — the routes are then never registered
    | and yours are left alone.
    |
    | Google sign-in needs two values in .env, and nothing else:
    |
    |     GOOGLE_CLIENT_ID=...
    |     GOOGLE_CLIENT_SECRET=...
    |
    | Until both are present, the toggle on the settings screen stays disabled
    | and says why — a switch that turns on a broken flow is worse than no
    | switch. The redirect URI defaults to /auth/google/callback on this host;
    | set GOOGLE_REDIRECT_URI when a proxy or tunnel makes that wrong, and
    | register the same value in the Google console.
    |
    */

    'auth' => [
        'enabled' => env('ISPROJECT_AUTH', true),

        // Where signing in lands when there was no intended URL.
        'home' => env('ISPROJECT_AUTH_HOME', '/'),

        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URI'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit trail
    |--------------------------------------------------------------------------
    |
    | Add the Auditable trait to a model and every create, update, delete and
    | restore is recorded with who did it and what changed:
    |
    |     use IsProject\Framework\Concerns\Auditable;
    |
    | Generated models get it automatically while 'generated_models' is true.
    |
    | It listens to Eloquent model events, so it sees what goes through
    | Eloquent. Product::query()->update([...]) fires no events and is not
    | recorded — that is Eloquent's behaviour, not a gap here, and it is why
    | this is a record of what the application did rather than a guarantee
    | about what the database contains.
    |
    | 'redacted_attributes' is the important list. Without it a User model
    | with the trait would copy its password hash into a table built for people
    | to browse. Matched with Str::is(), so "*_token" works.
    |
    */

    'audit' => [
        'enabled' => env('ISPROJECT_AUDIT', true),
        'generated_models' => true,

        'path' => 'audit',
        'middleware' => ['web', 'auth'],

        // Written as "••••••••" rather than dropped: that a password changed
        // is exactly the sort of thing an audit trail exists to show.
        'redacted_attributes' => [
            'password',
            'password_confirmation',
            'remember_token',
            '*_token',
            '*_secret',
            'secret',
        ],

        // Recorded but not interesting. Timestamps move on every write and
        // would bury the change somebody actually made.
        //
        // deleted_at earns its place here: restore() saves the model as well as
        // firing "restored", so without it a single restore produces both an
        // "updated deleted_at" row and a "restored" row saying the same thing.
        'ignored_attributes' => [
            'created_at',
            'updated_at',
            'deleted_at',

            // The Archivable trait records explicit "archived" / "unarchived"
            // events, so logging the column as well would double up.
            'archived_at',

            'remember_token',
        ],

        // First of these that holds a string names the record in the listing.
        'label_attributes' => ['name', 'title', 'label', 'email', 'sku', 'reference', 'code'],

        // Rows older than this are removed by `isproject:audit-prune`. Null
        // keeps everything, which is a decision, not a default to drift into.
        'retention_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu management
    |--------------------------------------------------------------------------
    |
    | The screen at /menu, which edits the isproject_menu_items table. While
    | that table is empty the sidebar reads the 'menu' array above, so adding
    | this changes nothing until somebody imports it from that screen.
    |
    | Guarded by the same gate as the settings screen.
    |
    */

    'menu_admin' => [
        'path' => 'menu',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Searchable dropdowns
    |--------------------------------------------------------------------------
    |
    | Tom Select (Apache-2.0, no jQuery, self-hosted with the other assets)
    | turns a long <select> into one you can type into.
    |
    | It is applied to any select with more than 'threshold' options, and to any
    | select marked data-is-select whatever its length. A select marked
    | data-is-select="off" is always left alone. The original <select> stays in
    | the page and keeps its name, so nothing server-side changes and a form
    | still submits correctly with JavaScript switched off.
    |
    | Set 'enabled' to false to drop the script entirely and use plain selects.
    |
    */

    'select' => [
        'enabled' => true,

        // Below this a search box is noise: a five-option select is quicker to
        // use as it is.
        'threshold' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | The manual
    |--------------------------------------------------------------------------
    |
    | Chapters are Markdown files. The file name carries the order and the slug:
    | 020-first-module.md is the second chapter, at /manual/first-module.
    |
    | Add directories to 'paths' to write course-specific chapters, or to
    | replace a bundled one — later directories win on a slug clash:
    |
    |     'paths' => [resource_path('manual')],
    |
    | Signed in by default. Drop 'auth' from the middleware to make the manual
    | readable to anyone, which is reasonable for a public teaching site and
    | unwise for one holding real coursework.
    |
    */

    'manual' => [
        'path' => 'manual',
        'middleware' => ['web', 'auth'],
        'paths' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Users and roles (RBAC)
    |--------------------------------------------------------------------------
    |
    | Permissions are not written by hand: the application's named routes are
    | scanned and each becomes one permission, so a module generated by
    | `isproject:crud` brings its own permissions with it. Rescan from the roles
    | screen or with `php artisan isproject:permissions`.
    |
    | Enforcement is opt-in, because an application has routes that must stay
    | open. Add the middleware to the groups you want guarded:
    |
    |     Route::resource('products', ProductController::class)
    |         ->middleware(['auth', 'isproject.permission']);
    |
    | The 'middleware' key further down this file puts it on everything the
    | generator writes.
    |
    | 'unknown_routes' decides what happens on a route that exists but has not
    | been scanned yet. 'allow' keeps a forgotten rescan from turning into a 403
    | nobody can explain; 'deny' is the stricter choice for production.
    |
    */

    'access' => [
        'path' => 'access',
        'middleware' => ['web', 'auth'],
        'unknown_routes' => env('ISPROJECT_UNKNOWN_ROUTES', 'allow'),

        // Routes that should never appear in the matrix. Signing in and out
        // cannot be permission-controlled without locking everyone out, and
        // framework tooling is noise on a screen a lecturer has to read.
        'ignored_routes' => [
            'login',
            'isproject.robots',

            // The manifest, the service worker and the offline page. All three
            // are fetched by the browser before anybody signs in, so they can
            // no more be permission-controlled than the sign-in screen itself.
            'isproject.pwa.*',
            'logout',
            'register',
            'password.*',
            'verification.*',
            'sanctum.*',
            'ignition.*',
            'horizon.*',
            'telescope.*',
            'livewire.*',
            'debugbar.*',
            'storage.local*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema introspection
    |--------------------------------------------------------------------------
    |
    | Columns never surfaced in forms, tables or validation rules, and tables
    | that `isproject:crud-all` must never touch.
    |
    */

    'ignored_columns' => [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'archived_at',
        'remember_token',
        'email_verified_at',
        'password',
    ],

    'ignored_tables' => [
        'migrations',

        // Matched with Str::is(): one pattern covers every table this package
        // owns — settings, roles, permissions, audits, the pivots — including
        // any it adds later. Nobody wants "generate CRUD for
        // isproject_permission_role" offered to them.
        'isproject_*',

        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'personal_access_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],

    /*
    |--------------------------------------------------------------------------
    | Index screen behaviour
    |--------------------------------------------------------------------------
    |
    | 'searchable_types' decides which column types land in the keyword search
    | WHERE clause. 'max_index_columns' keeps generated tables readable on wide
    | tables — the remaining columns still appear on the show/edit screens.
    |
    */

    'per_page' => 15,

    // Offered in the page-size selector on generated index screens. Kept to a
    // few round, obviously different numbers: 20/30/40/50 is four near-identical
    // choices to read through, which is a worse decision than three clear ones.
    'per_page_options' => [15, 25, 50, 100],

    // What "All" means. Unbounded, it is a hung browser and an exhausted memory
    // limit on any real table, so it is a ceiling: beyond it the pagination
    // links reappear and say how much more there is.
    'max_per_page' => 200,

    'searchable_types' => ['string', 'text'],
    'max_index_columns' => 6,

    /*
    |--------------------------------------------------------------------------
    | Stub overrides
    |--------------------------------------------------------------------------
    |
    | Run `php artisan vendor:publish --tag=isproject-stubs` to copy the stubs
    | into the path below; the generator prefers those over the packaged ones,
    | so students can reshape generated code without forking the package.
    |
    */

    'stub_path' => 'stubs/isproject',

    /*
    |--------------------------------------------------------------------------
    | Output paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'model' => 'app/Models',
        'controller' => 'app/Http/Controllers',
        'request' => 'app/Http/Requests',
        'policy' => 'app/Policies',
        'factory' => 'database/factories',
        'views' => 'resources/views',
    ],

    'namespaces' => [
        'model' => 'App\\Models',
        'controller' => 'App\\Http\\Controllers',
        'request' => 'App\\Http\\Requests',
        'policy' => 'App\\Policies',
        'factory' => 'Database\\Factories',
    ],

];
