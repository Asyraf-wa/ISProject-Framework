<?php

namespace IsProject\Framework\Support;

/**
 * The accent colour, chosen from the settings screen.
 *
 * The stylesheet is compiled from Sass, so $primary is baked into hundreds of
 * declarations at build time and cannot simply be reassigned at runtime. What
 * this class emits instead is a small block of custom property overrides that
 * covers every place the accent actually surfaces — and the list is short only
 * because most of the framework's own layer already reads
 * var(--bs-primary-rgb) and var(--is-accent-text) rather than a literal.
 *
 * Nothing is emitted for the default: the compiled stylesheet is already
 * indigo, so choosing indigo costs an installation exactly nothing.
 *
 * Every preset is measured, not picked by eye. Each clears 4.5:1 three ways:
 * as text on a light card, as a white-labelled button, and as its dark-mode
 * tint on the dark panel. A palette that only works in one theme is how the
 * checkboxes, the dropdown highlight and the manual cards all went wrong.
 */
class Theme
{
    public const DEFAULT = 'indigo';

    /**
     * name => [hex, label, light contrast, dark contrast]
     *
     * The two ratios are the accent as text on a light card, and its 45% tint
     * as text on the dark panel. They are shown on the settings screen so the
     * choice is informed rather than decorative.
     *
     * @var array<string, array{0: string, 1: string, 2: float, 3: float}>
     */
    public const PRESETS = [
        'indigo' => ['#4338ca', 'Indigo', 7.9, 6.38],
        'violet' => ['#7c3aed', 'Violet', 5.7, 7.21],
        'blue' => ['#1d4ed8', 'Blue', 6.7, 6.81],
        'emerald' => ['#047857', 'Emerald', 5.48, 7.54],
        'amber' => ['#b45309', 'Amber', 5.02, 7.8],
        'rose' => ['#be123c', 'Rose', 6.29, 6.26],
        'slate' => ['#334155', 'Slate', 10.35, 6.02],
    ];

    /** The chosen preset's key, always one that exists. */
    public function current(): string
    {
        $chosen = (string) isproject_setting('accent', self::DEFAULT);

        return isset(self::PRESETS[$chosen]) ? $chosen : self::DEFAULT;
    }

    public function hex(): string
    {
        return self::PRESETS[$this->current()][0];
    }

    /** @return array<string, string> key => label, for the settings select */
    public static function options(): array
    {
        return array_map(fn (array $preset) => $preset[1], self::PRESETS);
    }

    /** Swatches for the settings screen. @return array<int, array<string, mixed>> */
    public static function swatches(): array
    {
        $swatches = [];

        foreach (self::PRESETS as $key => [$hex, $label, $light, $dark]) {
            $swatches[] = [
                'key' => $key,
                'hex' => $hex,
                'label' => $label,
                'light' => $light,
                'dark' => $dark,
            ];
        }

        return $swatches;
    }

    /**
     * The override block, or an empty string when the default is in force.
     *
     * Returned as CSS text rather than a stylesheet route so it costs no extra
     * request and can never be served stale from a browser cache — the accent
     * has to change the moment somebody saves the setting.
     */
    public function css(): string
    {
        $key = $this->current();

        if ($key === self::DEFAULT) {
            return '';
        }

        $base = self::PRESETS[$key][0];

        // Mirrors what the Sass does for the compiled default, so a chosen
        // accent lands in the same relationships the design was built on.
        $emphasis = $this->shade($base, 0.60);
        $subtleBg = $this->tint($base, 0.80);
        $subtleBorder = $this->tint($base, 0.60);
        $hover = $this->shade($base, 0.15);
        $active = $this->shade($base, 0.20);

        $tint25 = $this->tint($base, 0.25);
        $tint40 = $this->tint($base, 0.40);
        $tint45 = $this->tint($base, 0.45);
        $rgb = implode(', ', $this->rgb($base));
        $tint45rgb = implode(', ', $this->rgb($tint45));

        return <<<CSS
        :root {
            --bs-primary: {$base};
            --bs-primary-rgb: {$rgb};
            --bs-primary-text-emphasis: {$emphasis};
            --bs-primary-bg-subtle: {$subtleBg};
            --bs-primary-border-subtle: {$subtleBorder};
            --bs-link-color: {$base};
            --bs-link-color-rgb: {$rgb};
            --bs-link-hover-color: {$hover};
            --bs-focus-ring-color: rgba({$rgb}, .25);
            --is-accent-text: {$base};
            --is-control-checked: {$base};
            --is-nav-active-color: {$base};
            --is-nav-active-bg: rgba({$rgb}, .1);
            --is-chart-1: {$base};
        }

        [data-bs-theme="dark"] {
            --bs-primary-text-emphasis: {$tint40};
            --bs-link-color: {$tint40};
            --bs-link-color-rgb: {$tint45rgb};
            --bs-link-hover-color: {$tint45};
            --is-accent-text: {$tint45};
            --is-control-checked: {$tint25};
            --is-nav-active-color: {$tint45};
            --is-nav-active-bg: rgba({$tint45rgb}, .16);
            --is-chart-1: {$tint45};
        }

        /* Bootstrap's buttons read their own custom properties, so they can be
           redirected without touching the compiled rules. */
        .btn-primary {
            --bs-btn-bg: {$base};
            --bs-btn-border-color: {$base};
            --bs-btn-hover-bg: {$hover};
            --bs-btn-hover-border-color: {$hover};
            --bs-btn-active-bg: {$active};
            --bs-btn-active-border-color: {$active};
            --bs-btn-disabled-bg: {$base};
            --bs-btn-disabled-border-color: {$base};
        }

        .btn-outline-primary {
            --bs-btn-color: {$base};
            --bs-btn-border-color: {$base};
            --bs-btn-hover-bg: {$base};
            --bs-btn-hover-border-color: {$base};
            --bs-btn-active-bg: {$base};
            --bs-btn-active-border-color: {$base};
        }

        /* Compiled from Sass colour functions, so each needs saying again. */
        .badge-soft-primary {
            background-color: rgba({$rgb}, .12);
            color: {$emphasis};
        }

        [data-bs-theme="dark"] .badge-soft-primary {
            background-color: rgba({$rgb}, .2);
            color: {$tint45};
        }

        .form-control:focus,
        .form-select:focus,
        .form-check-input:focus,
        .ts-wrapper.focus {
            border-color: {$tint45};
        }

        .is-manual-card:hover,
        .is-menu-row .is-menu-item:focus-within {
            border-color: {$base};
        }
        CSS;
    }

    /** @return array<int, int> */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Mix toward white, the way Bootstrap's tint-color() does. */
    private function tint(string $hex, float $amount): string
    {
        return $this->mix($hex, 255, $amount);
    }

    /** Mix toward black. */
    private function shade(string $hex, float $amount): string
    {
        return $this->mix($hex, 0, $amount);
    }

    private function mix(string $hex, int $toward, float $amount): string
    {
        $mixed = array_map(
            fn (int $channel) => (int) round($channel + ($toward - $channel) * $amount),
            $this->rgb($hex),
        );

        return sprintf('#%02x%02x%02x', ...$mixed);
    }
}
