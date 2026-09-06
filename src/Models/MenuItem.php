<?php

namespace IsProject\Framework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use IsProject\Framework\Support\MenuManager;

/**
 * One row of the sidebar.
 *
 * The important method is definition(): it emits the same array shape that
 * config('isproject.menu') uses, so Menu, the sidebar and the nav-item
 * component never learn where an entry came from.
 */
class MenuItem extends Model
{
    /** A label with a rule under it, not a link. */
    public const TYPE_HEADING = 'heading';

    /** A named route, resolved at render time. */
    public const TYPE_ROUTE = 'route';

    /** A path within this application. */
    public const TYPE_INTERNAL = 'internal';

    /** Somewhere else entirely. */
    public const TYPE_EXTERNAL = 'external';

    public const TYPES = [
        self::TYPE_ROUTE => 'Link to a page in this system',
        self::TYPE_INTERNAL => 'Link to a path',
        self::TYPE_EXTERNAL => 'Link to another website',
        self::TYPE_HEADING => 'Heading',
    ];

    protected $table = 'isproject_menu_items';

    protected $fillable = [
        'parent_id', 'type', 'label', 'icon', 'route_name', 'url',
        'permission', 'badge', 'opens_in_new_tab', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'opens_in_new_tab' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Any change reorders or renames what everyone sees, and the menu is read
     * on every page. Bumping the version here means no caller has to remember to.
     */
    protected static function booted(): void
    {
        static::saved(fn () => MenuManager::flush());
        static::deleted(fn () => MenuManager::flush());
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Whether this row may hold children: only headings and top-level rows may not. */
    public function canHaveChildren(): bool
    {
        return $this->parent_id === null && $this->type !== self::TYPE_HEADING;
    }

    public function isExternal(): bool
    {
        return $this->type === self::TYPE_EXTERNAL;
    }

    /**
     * The row as the menu renderer wants it.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    public function definition(array $children = []): array
    {
        if ($this->type === self::TYPE_HEADING) {
            return ['heading' => $this->label];
        }

        $entry = [
            'label' => $this->label,
            'icon' => $this->icon ?: 'circle',
            'children' => $children,
        ];

        if ($this->permission) {
            $entry['can'] = $this->permission;
        }

        if ($this->badge) {
            $entry['badge'] = $this->badge;
        }

        if ($this->type === self::TYPE_ROUTE) {
            $entry['route'] = $this->route_name;
        } elseif ($this->url) {
            $entry['url'] = $this->url;
        }

        if ($this->opens_in_new_tab || $this->isExternal()) {
            $entry['target'] = '_blank';
        }

        return $entry;
    }

    /** Where this row points, for showing on the management screen. */
    public function destination(): string
    {
        return match ($this->type) {
            self::TYPE_HEADING => '—',
            self::TYPE_ROUTE => (string) $this->route_name,
            default => (string) $this->url,
        };
    }
}
