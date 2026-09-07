<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Installing the site as an app.
 *
 * Everything here is driven by the settings screen, including the off switch —
 * and turning it off is the part that needs care. A service worker outlives the
 * page that registered it: dropping the registration script leaves the old one
 * running, still serving its caches, for as long as the browser keeps it. So
 * "disabled" is an instruction the browser has to be given, not the absence of
 * one. See killSwitch() and the partial that unregisters.
 */
class Pwa
{
    /** Icons below this are refused by browsers as an install icon. */
    public const MIN_ICON = 192;

    /** What Chrome wants before it will offer to install. */
    public const GOOD_ICON = 512;

    /** @var array<string, mixed>|null */
    private ?array $icon = null;

    public function enabled(): bool
    {
        return (bool) isproject_setting('pwa_enabled', false);
    }

    /**
     * The web app manifest.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $name = $this->name();

        $manifest = [
            // A stable id, so a later change of start_url does not make browsers
            // treat this as a different app and install it twice.
            'id' => '/?app=isproject',
            'name' => $name,
            'short_name' => $this->shortName(),
            // Both with a trailing slash: a scope of "https://example.test"
            // resolves to the parent of the root, and some browsers then treat
            // pages as outside it.
            'start_url' => $this->root(),
            'scope' => $this->root(),
            'display' => $this->display(),
            'orientation' => 'any',
            'theme_color' => $this->themeColor(),
            'background_color' => $this->backgroundColor(),
            'icons' => $this->icons(),
        ];

        if ($description = trim((string) isproject_setting('tagline', ''))) {
            $manifest['description'] = $description;
        }

        return $manifest;
    }

    /**
     * Manifest icon entries.
     *
     * One uploaded file, declared at the size it actually is rather than at the
     * size we wish it were: claiming 512x512 for a 64px image makes a browser
     * fetch it, measure it and refuse the install without saying why.
     *
     * @return array<int, array<string, string>>
     */
    public function icons(): array
    {
        $icon = $this->icon();

        if ($icon === null) {
            return [];
        }

        $entry = [
            'src' => $icon['url'],
            'sizes' => $icon['width'].'x'.$icon['height'],
            'type' => $icon['mime'],
        ];

        $icons = [$entry + ['purpose' => 'any']];

        // Only when it has been said to have padding. An unpadded icon declared
        // maskable gets its edges cropped off on Android.
        if (isproject_setting('pwa_icon_maskable', false)) {
            $icons[] = $entry + ['purpose' => 'maskable'];
        }

        return $icons;
    }

    /**
     * The uploaded icon, with the dimensions read off the file itself.
     *
     * @return array<string, mixed>|null
     */
    public function icon(): ?array
    {
        if ($this->icon !== null) {
            return $this->icon === [] ? null : $this->icon;
        }

        $path = (string) isproject_setting('pwa_icon', '');

        if ($path === '') {
            $this->icon = [];

            return null;
        }

        $disk = Storage::disk((string) config('isproject.settings.disk', 'public'));

        try {
            $url = $disk->url($path);
            // getimagesize needs a local path and no image extension loaded.
            // A remote disk simply reports unknown rather than failing.
            $size = method_exists($disk, 'path') ? @getimagesize($disk->path($path)) : false;
        } catch (Throwable) {
            $this->icon = [];

            return null;
        }

        $this->icon = [
            'url' => $url,
            'width' => $size[0] ?? 0,
            'height' => $size[1] ?? 0,
            'mime' => $size['mime'] ?? 'image/png',
            'measured' => $size !== false,
        ];

        return $this->icon;
    }

    /**
     * Whether everything a browser needs is in place, with the reason when not.
     *
     * Shown on the settings screen rather than blocking the toggle: the missing
     * piece is usually HTTPS, which nobody can fix from a form.
     *
     * @return array<int, array{ok: bool, label: string, detail: string}>
     */
    public function checks(): array
    {
        $icon = $this->icon();
        $secure = $this->isSecureContext();

        $checks = [[
            'ok' => $secure,
            'label' => 'Served over HTTPS',
            'detail' => $secure
                ? 'Browsers will accept a service worker here.'
                : 'Browsers refuse to install an app, or run a service worker, on a plain http:// address. localhost is the one exception, which is why this works in development and stops working the moment you deploy.',
        ]];

        if ($icon === null) {
            $checks[] = [
                'ok' => false,
                'label' => 'An app icon',
                'detail' => 'Upload a square PNG below. Without one there is nothing to put on a home screen, and Chrome will not offer to install.',
            ];

            return $checks;
        }

        $square = $icon['width'] === $icon['height'];
        $bigEnough = min($icon['width'], $icon['height']) >= self::GOOD_ICON;

        if (! $icon['measured']) {
            $checks[] = [
                'ok' => true,
                'label' => 'An app icon',
                'detail' => 'Uploaded. Its dimensions could not be read from this disk, so check it is a square image of at least '.self::GOOD_ICON.'px yourself.',
            ];

            return $checks;
        }

        $checks[] = [
            'ok' => $square && $bigEnough,
            'label' => 'An app icon of at least '.self::GOOD_ICON.'px square',
            'detail' => match (true) {
                ! $square => "Yours is {$icon['width']}×{$icon['height']}. A home screen icon is square, and a browser will not stretch one for you.",
                ! $bigEnough => "Yours is {$icon['width']}×{$icon['height']}. It will still be used, but Chrome wants at least ".self::GOOD_ICON.'px before it offers to install.',
                default => "Yours is {$icon['width']}×{$icon['height']}.",
            },
        ];

        return $checks;
    }

    /** Whether every check passed. */
    public function ready(): bool
    {
        foreach ($this->checks() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * A short token that changes whenever anything the service worker holds
     * changes, so an update replaces the old cache instead of joining it.
     */
    public function version(): string
    {
        $parts = [
            $this->name(),
            (string) isproject_setting('pwa_icon', ''),
            $this->themeColor(),
        ];

        foreach ($this->precache() as $url) {
            $parts[] = $url;
        }

        return substr(md5(implode('|', $parts)), 0, 12);
    }

    /**
     * What the worker stores up front.
     *
     * The offline page and the stylesheet, and nothing else: no HTML from the
     * application itself. Every screen here is behind sign-in and filtered by
     * role, and these are shared lab machines — a cached page could show the
     * next person what the last one was looking at.
     *
     * Paths, not absolute URLs. A service worker resolves a relative path
     * against its own origin, which is always the host the visitor actually
     * typed — whereas asset() and route() build on APP_URL. Where those differ
     * (a tunnel, a staging alias, a site reached by IP) every absolute URL here
     * would be cross-origin, and caching one silently stores nothing at all.
     *
     * @return array<int, string>
     */
    public function precache(): array
    {
        $paths = [$this->pathOf(route('isproject.pwa.offline'))];

        // Versioned, and deliberately so: the worker caches these first-hand,
        // so a path that never changes would have an installed app serving the
        // old stylesheet long after a browser had given up on it.
        foreach (['isproject.css', 'isproject.js', 'bootstrap.bundle.min.js'] as $file) {
            $paths[] = app(Assets::class)->path($file);
        }

        if ($icon = $this->icon()) {
            $paths[] = $this->pathOf($icon['url']);
        }

        return array_values(array_filter($paths));
    }

    /** The path part of a URL this application built. */
    public function pathOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_PATH) ?: '/');
    }

    public function name(): string
    {
        return trim((string) isproject_setting('pwa_name', ''))
            ?: (string) isproject_setting('app_name', config('app.name', 'Application'));
    }

    public function shortName(): string
    {
        $short = trim((string) isproject_setting('pwa_short_name', ''));

        // Home screens truncate at about a dozen characters, so a long name
        // becomes an unreadable stub. Better to cut it deliberately.
        return $short !== '' ? $short : Str::limit($this->name(), 12, '');
    }

    public function themeColor(): string
    {
        return $this->colour('pwa_theme_color', '#4338ca');
    }

    public function backgroundColor(): string
    {
        return $this->colour('pwa_background_color', '#ffffff');
    }

    public function display(): string
    {
        $display = (string) isproject_setting('pwa_display', 'standalone');

        return in_array($display, ['standalone', 'minimal-ui', 'fullscreen', 'browser'], true)
            ? $display
            : 'standalone';
    }

    /** Whether this request can host a service worker at all. */
    public function isSecureContext(): bool
    {
        $request = request();

        if ($request === null) {
            return false;
        }

        // Matches the browsers' own rule: TLS, or a loopback host.
        return $request->isSecure()
            || in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true);
    }

    /** The site root, always with its trailing slash. */
    private function root(): string
    {
        return rtrim(url('/'), '/').'/';
    }

    private function colour(string $key, string $fallback): string
    {
        $value = trim((string) isproject_setting($key, ''));

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $fallback;
    }
}
