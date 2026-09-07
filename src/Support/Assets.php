<?php

namespace IsProject\Framework\Support;

/**
 * URLs for the published assets, with a version on them.
 *
 * The published files sit at a fixed path, so upgrading the package and
 * re-publishing changes the bytes without changing the URL. Nothing then tells
 * a browser the file is different — and because the server sends no
 * Cache-Control on static files, browsers fall back to *heuristic* freshness:
 * they guess a lifetime from Last-Modified and serve the old copy without even
 * revalidating. The symptom is a stylesheet that only updates on a hard reload.
 *
 * A version derived from the file itself fixes that: new bytes, new URL, and
 * the old one can stay cached for as long as anything likes.
 *
 * It also fixes the same bug one layer down. The service worker caches these
 * paths cache-first, so without a changing URL an installed app would serve the
 * old stylesheet indefinitely — long after a browser would have given up on it.
 */
class Assets
{
    /** @var array<string, string> */
    private array $memo = [];

    /** Absolute URL for a published asset, versioned. */
    public function url(string $file): string
    {
        return asset($this->path($file));
    }

    /**
     * Root-relative path plus version, which is what the service worker wants:
     * it resolves a path against the origin the visitor is actually on, where
     * an absolute URL built from APP_URL may not be the same host.
     */
    public function path(string $file): string
    {
        return '/vendor/isproject/'.$file.$this->version($file);
    }

    /**
     * "?v=…" for a file that exists, and nothing at all for one that does not.
     *
     * A missing file means the assets have not been published yet. Appending a
     * version to a 404 helps nobody, and the page should still render.
     */
    private function version(string $file): string
    {
        if (array_key_exists($file, $this->memo)) {
            return $this->memo[$file];
        }

        $path = public_path('vendor/isproject/'.$file);

        if (! is_file($path)) {
            return $this->memo[$file] = '';
        }

        // Modification time and size rather than a hash of the contents: this
        // runs on every page render, and hashing a 664 KB chart library to
        // build a URL would be a poor trade for the collision resistance.
        $stamp = substr(md5(filemtime($path).'|'.filesize($path)), 0, 8);

        return $this->memo[$file] = '?v='.$stamp;
    }
}
