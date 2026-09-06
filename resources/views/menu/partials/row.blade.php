{{--
    One row of the management tree. Recursive for the single level of nesting
    the manager allows; the drop zone below is rendered for parents even when
    empty, because an empty one is where the first sub-item gets dropped.
--}}
@php
    $isHeading = $item->type === \IsProject\Framework\Models\MenuItem::TYPE_HEADING;
@endphp

<li class="is-menu-row" data-id="{{ $item->id }}" data-parent="{{ $item->parent_id }}">
    <div class="is-menu-item{{ $item->is_active ? '' : ' is-menu-item-off' }}{{ $isHeading ? ' is-menu-item-heading' : '' }}">
        <span class="is-menu-grip" data-is-menu-grip title="Drag to reorder" aria-hidden="true">
            <x-isproject::icon name="dots" size="16" />
        </span>

        <span class="is-menu-icon">
            @if ($isHeading)
                <x-isproject::icon name="list" size="14" />
            @else
                <x-isproject::icon :name="$item->icon ?: 'circle'" size="16" />
            @endif
        </span>

        <span class="is-menu-label">
            <span class="fw-semibold">{{ $item->label }}</span>

            <span class="is-menu-meta">
                @if ($isHeading)
                    Heading
                @else
                    <code>{{ $item->destination() }}</code>
                @endif

                @if ($item->permission)
                    · needs <code>{{ $item->permission }}</code>
                @endif
            </span>
        </span>

        <span class="is-menu-tags">
            @if ($item->opens_in_new_tab)
                <span class="badge badge-soft-secondary">New tab</span>
            @endif

            @if ($item->badge)
                <span class="badge badge-soft-primary">{{ $item->badge }}</span>
            @endif

            @unless ($item->is_active)
                <span class="badge badge-soft-warning">Hidden</span>
            @endunless
        </span>

        <span class="is-menu-actions">
            {{-- The arrows are the accessible equivalent of the drag handle:
                 same outcome, reachable from a keyboard, and they work with
                 JavaScript switched off. --}}
            <form method="POST" action="{{ route('isproject.menu.move', $item) }}">
                @csrf
                <input type="hidden" name="direction" value="up">
                <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary border-0"
                        aria-label="Move {{ $item->label }} up">▲</button>
            </form>

            <form method="POST" action="{{ route('isproject.menu.move', $item) }}">
                @csrf
                <input type="hidden" name="direction" value="down">
                <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary border-0"
                        aria-label="Move {{ $item->label }} down">▼</button>
            </form>

            <form method="POST" action="{{ route('isproject.menu.toggle', $item) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary border-0"
                        aria-label="{{ $item->is_active ? 'Hide' : 'Show' }} {{ $item->label }}">
                    <x-isproject::icon name="eye" size="15" />
                </button>
            </form>

            <a href="{{ route('isproject.menu.edit', $item) }}"
               class="btn btn-sm btn-icon btn-outline-secondary border-0"
               aria-label="Edit {{ $item->label }}">
                <x-isproject::icon name="pencil" size="15" />
            </a>

            <form method="POST" action="{{ route('isproject.menu.destroy', $item) }}"
                  onsubmit="return confirm('Remove &quot;{{ $item->label }}&quot; from the menu?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger border-0"
                        aria-label="Delete {{ $item->label }}">
                    <x-isproject::icon name="trash" size="15" />
                </button>
            </form>
        </span>
    </div>

    @unless ($child || $isHeading)
        <ul class="is-menu-children" data-is-menu-list data-parent="{{ $item->id }}">
            @foreach ($item->children as $sub)
                @include('isproject::menu.partials.row', ['item' => $sub, 'child' => true])
            @endforeach
        </ul>
    @endunless
</li>
