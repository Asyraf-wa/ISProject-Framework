<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * What a page tells search engines and link previews about itself.
 *
 * The distinction this class exists to hold: **most of this application must
 * never be indexed**. The admin screens sit behind authentication, and putting
 * their titles and URLs into a search index leaks the shape of the system while
 * helping nobody — a crawler cannot see past the sign-in screen anyway. So
 * indexing is decided per layout, not once for the whole site:
 *
 *   layouts.app   — always noindex, whatever the setting says
 *   layouts.guest — offered for indexing, if the setting allows it
 */
class Seo
{
    /**
     * Everything the head partial needs for one page.
     *
     * @return array{
     *     indexable: bool, robots: string, title: string, description: ?string,
     *     image: ?string, canonical: ?string, siteName: string, verification: ?string
     * }
     */
    public function meta(string $title, bool $indexable): array
    {
        $siteName = (string) isproject_setting('app_name', config('app.name'));
        $allowed = $indexable && (bool) isproject_setting('seo_indexable', false);

        return [
            'indexable' => $allowed,

            // "noindex, nofollow" rather than bare noindex: there is nothing on
            // a sign-in screen worth following, and the admin pages behind it
            // are exactly what should not be discovered.
            'robots' => $allowed ? 'index, follow' : 'noindex, nofollow',

            'title' => trim($title) !== '' ? trim($title) : $siteName,
            'description' => $this->text('seo_description'),
            'image' => $this->image(),
            'canonical' => $allowed ? $this->canonical() : null,
            'siteName' => $siteName,
            'verification' => $this->text('seo_verification'),
        ];
    }

    /** Whether the site as a whole is offered to search engines. */
    public function indexable(): bool
    {
        return (bool) isproject_setting('seo_indexable', false);
    }

    /**
     * The share image, falling back to the logo — a link preview with the
     * wrong picture is better than one with none.
     */
    private function image(): ?string
    {
        $path = isproject_setting('seo_share_image') ?: isproject_setting('logo');

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            return Storage::disk((string) config('isproject.settings.disk', 'public'))->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The address search engines should credit.
     *
     * Built from the configured host plus the current path, so one setting
     * covers every page rather than needing one per screen. Query strings are
     * dropped: ?page=2 and ?sort=name are the same document as far as a search
     * engine should be concerned.
     */
    private function canonical(): ?string
    {
        $host = $this->text('seo_canonical_host');

        if ($host === null) {
            return url()->current();
        }

        return rtrim($host, '/').'/'.ltrim((string) request()?->path(), '/');
    }

    private function text(string $key): ?string
    {
        $value = isproject_setting($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim(Str::squish($value));

        return $value === '' ? null : $value;
    }
}
