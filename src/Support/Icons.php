<?php

namespace IsProject\Framework\Support;

/**
 * The names in the bundled icon set, so they can be offered in a picker.
 *
 * The SVG paths themselves stay in resources/views/components/icon.blade.php —
 * that file is the one that renders, and splitting the paths out would leave
 * two places to edit. This list is only an index of it, and
 * IconsTest::the_index_matches_the_component fails if the two drift apart, so
 * adding an icon means adding its name here too.
 */
class Icons
{
    /** @return array<int, string> */
    public static function names(): array
    {
        return [
            'alert-circle',
            'arrow-left',
            'box',
            'check-circle',
            'chevron-right',
            'circle',
            'dots',
            'eye',
            'google',
            'grid',
            'home',
            'inbox',
            'info',
            'list',
            'logout',
            'menu',
            'moon',
            'pencil',
            'people',
            'plus',
            'save',
            'search',
            'settings',
            'sun',
            'trash',
            'user',
        ];
    }

    /**
     * Keyed by name for a <select>, with the name shown as its own label —
     * "check-circle" is more use to someone picking an icon than "Check Circle".
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(self::names(), self::names());
    }
}
