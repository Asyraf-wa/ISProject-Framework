<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Support\Seo;

/*
 * robots.txt, driven by the "Allow search engines to index this site" setting.
 *
 * A caveat worth knowing: a stock Laravel application ships public/robots.txt,
 * and the web server hands that file over before PHP is ever reached — so this
 * route does nothing until that file is deleted. The settings screen says so
 * when it finds one.
 *
 * The meta robots tag is the more important control anyway, and it is unaffected
 * by any of this: robots.txt asks a crawler not to *fetch* a page, which means a
 * disallowed page's "noindex" is never read. Disallowing everything is therefore
 * a poor way to stay out of an index; the tag is the right tool.
 */
Route::get('robots.txt', function (Seo $seo) {
    // No Sitemap line: this package does not generate one, and pointing at a
    // file that 404s is worse than saying nothing. An admin panel has one
    // public page; a sitemap for it would be ceremony.
    $lines = $seo->indexable()
        ? ['User-agent: *', 'Disallow:']
        : ['User-agent: *', 'Disallow: /'];

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('isproject.robots');
