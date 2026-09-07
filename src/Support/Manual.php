<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * The manual: Markdown files shipped with the package, rendered in the browser.
 *
 * Markdown rather than Blade so the chapters read as prose in the repository as
 * well as on screen, and so a lecturer can add course-specific pages by dropping
 * a file in a directory rather than learning the component set.
 *
 * A chapter's file name carries its order and its slug — 020-first-module.md is
 * the twentieth-ish chapter at /manual/first-module. Nothing has to be
 * registered, and inserting a chapter between two others means numbering it
 * between them.
 */
class Manual
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $chapters = null;

    /**
     * Every chapter, in file-name order.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function chapters(): Collection
    {
        return collect($this->load())->values();
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        $chapters = $this->load();

        if (! isset($chapters[$slug])) {
            return null;
        }

        $chapter = $chapters[$slug];

        // Keyed by the file's modification time, so editing a chapter shows up
        // at once rather than after a cache clear somebody has to know about.
        $key = 'isproject.manual.'.$slug.'.'.$chapter['modified'];

        $rendered = Cache::remember($key, now()->addDay(), function () use ($chapter) {
            $html = $this->render($this->body($chapter['path']));

            return ['html' => $html, 'contents' => $this->contentsOf($html)];
        });

        return $chapter + $rendered;
    }

    /** The chapter before and after this one, for walking the manual in order. */
    public function neighbours(string $slug): array
    {
        $slugs = array_keys($this->load());
        $index = array_search($slug, $slugs, true);

        if ($index === false) {
            return ['previous' => null, 'next' => null];
        }

        $chapters = $this->load();

        return [
            'previous' => $index > 0 ? $chapters[$slugs[$index - 1]] : null,
            'next' => $index < count($slugs) - 1 ? $chapters[$slugs[$index + 1]] : null,
        ];
    }

    /**
     * Chapters mentioning the query, with a line of context around the hit.
     *
     * Searching the Markdown source rather than the rendered HTML: it is the
     * same words without the tags, so a query never matches a class name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $results = [];

        foreach ($this->load() as $chapter) {
            $body = $this->body($chapter['path']);
            $position = mb_stripos($body, $query);

            if ($position === false) {
                continue;
            }

            $results[] = $chapter + [
                'snippet' => $this->snippet($body, $position, mb_strlen($query)),
                'hits' => mb_substr_count(mb_strtolower($body), mb_strtolower($query)),
            ];
        }

        // Most mentions first: a chapter that says the word once is usually
        // pointing at the one that says it eight times.
        usort($results, fn (array $a, array $b) => $b['hits'] <=> $a['hits']);

        return $results;
    }

    /**
     * Directories searched for chapters, later ones winning on a slug clash so
     * an application can replace a bundled chapter with its own.
     *
     * @return array<int, string>
     */
    private function directories(): array
    {
        $paths = (array) config('isproject.manual.paths', []);

        return array_values(array_filter(array_merge(
            [dirname(__DIR__, 2).'/resources/manual'],
            array_map(fn ($path) => (string) $path, $paths),
        ), 'is_dir'));
    }

    /** @return array<string, array<string, mixed>> */
    private function load(): array
    {
        if ($this->chapters !== null) {
            return $this->chapters;
        }

        $found = [];

        foreach ($this->directories() as $directory) {
            foreach (glob($directory.'/*.md') ?: [] as $path) {
                $name = basename($path, '.md');

                // "020-first-module" — digits order it, the rest is the slug.
                if (! preg_match('/^(\d+)[-_](.+)$/', $name, $matches)) {
                    continue;
                }

                $meta = $this->frontMatter($path);
                $slug = Str::slug($matches[2]);

                $found[$slug] = [
                    'slug' => $slug,
                    'order' => (int) $matches[1],
                    'path' => $path,
                    'modified' => (int) filemtime($path),
                    'title' => $meta['title'] ?? Str::headline($matches[2]),
                    'summary' => $meta['summary'] ?? '',
                    'icon' => $meta['icon'] ?? 'info',
                ];
            }
        }

        uasort($found, fn (array $a, array $b) => [$a['order'], $a['slug']] <=> [$b['order'], $b['slug']]);

        return $this->chapters = $found;
    }

    /**
     * The key: value block between the leading --- fences.
     *
     * Hand-parsed rather than pulling in a YAML component for three keys, and
     * the format is deliberately too simple to need one.
     *
     * @return array<string, string>
     */
    private function frontMatter(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $meta = [];
        $inside = false;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '---') {
                if ($inside) {
                    break;
                }

                $inside = true;

                continue;
            }

            if (! $inside) {
                break;
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $meta[trim($key)] = trim($value);
            }
        }

        fclose($handle);

        return $meta;
    }

    /** The file with its front matter and placeholders resolved away. */
    private function body(string $path): string
    {
        $contents = (string) file_get_contents($path);
        $contents = (string) preg_replace('/\A---\R.*?\R---\R/s', '', $contents);

        return strtr($contents, $this->placeholders());
    }

    /**
     * Values that differ per installation, so the manual describes the system
     * the reader is actually looking at rather than the defaults.
     *
     * The %%token%% form is the one the code generator's stubs already use.
     *
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        $path = fn (string $key, string $default) => '/'.trim((string) config($key, $default), '/');

        return [
            '%%app%%' => (string) isproject_setting('app_name', config('app.name', 'this system')),
            '%%settingsPath%%' => $path('isproject.settings.path', 'settings'),
            '%%accessPath%%' => $path('isproject.access.path', 'access'),
            '%%auditPath%%' => $path('isproject.audit.path', 'audit'),
            '%%activityPath%%' => $path('isproject.activity.path', 'activity'),
            '%%dashboardPath%%' => $path('isproject.dashboard.path', 'dashboard'),
            '%%menuPath%%' => $path('isproject.menu_admin.path', 'menu'),
            '%%manualPath%%' => $path('isproject.manual.path', 'manual'),
        ];
    }

    private function render(string $markdown): string
    {
        // The GitHub flavour, for its tables above all — the manual is full of
        // them, and plain CommonMark renders a pipe table as a paragraph of
        // pipes. Strikethrough and autolinks come along with it.
        $converter = new GithubFlavoredMarkdownConverter([
            // These files sit on the server and are as trusted as a Blade view,
            // but escaping raw HTML costs nothing here and means a manual
            // directory somebody made writable cannot become a script tag.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $html = (string) $converter->convert($markdown);

        // The page header already shows the chapter title, so the file's own h1
        // would say it twice. It stays in the Markdown, where it is what makes
        // the file readable on its own in the repository.
        $html = (string) preg_replace('/\A\s*<h1>.*?<\/h1>\s*/s', '', $html);

        return $this->callouts($this->anchors($html));
    }

    /** Give every h2 an id so the on-page contents can link to it. */
    private function anchors(string $html): string
    {
        return (string) preg_replace_callback(
            '/<h2>(.*?)<\/h2>/s',
            fn (array $m) => '<h2 id="'.Str::slug(strip_tags($m[1])).'">'.$m[1].'</h2>',
            $html,
        );
    }

    /**
     * GitHub-style alerts — "> [!NOTE]" — become callout boxes.
     *
     * Done on the rendered HTML rather than the Markdown because raw HTML in
     * the source is escaped, so there is no way to write the box by hand.
     */
    private function callouts(string $html): string
    {
        $kinds = ['NOTE' => 'note', 'TIP' => 'tip', 'WARNING' => 'warning', 'IMPORTANT' => 'important'];

        return (string) preg_replace_callback(
            '/<blockquote>\s*(.*?)\s*<\/blockquote>/s',
            function (array $m) use ($kinds) {
                if (! preg_match('/\[!([A-Z]+)\]\s*(?:<br\s*\/?>)?\s*/', $m[1], $found)) {
                    return $m[0];
                }

                $kind = $kinds[$found[1]] ?? null;

                if ($kind === null) {
                    return $m[0];
                }

                $body = str_replace($found[0], '', $m[1]);

                return '<div class="is-callout is-callout-'.$kind.'">'
                    .'<p class="is-callout-title">'.Str::title($kind).'</p>'
                    .$body
                    .'</div>';
            },
            $html,
        );
    }

    /**
     * The headings of a rendered chapter, for the contents beside it.
     *
     * @return array<int, array{id: string, title: string}>
     */
    private function contentsOf(string $html): array
    {
        preg_match_all('/<h2 id="([^"]+)">(.*?)<\/h2>/s', $html, $matches, PREG_SET_ORDER);

        return array_map(
            fn (array $match) => ['id' => $match[1], 'title' => strip_tags($match[2])],
            $matches,
        );
    }

    /**
     * A readable window around a search hit.
     *
     * The window is cut before the whitespace is collapsed: doing it the other
     * way round would shift every character after the first line break and put
     * the window somewhere other than the match.
     */
    private function snippet(string $body, int $position, int $length): string
    {
        $start = max(0, $position - 60);
        $snippet = mb_substr($body, $start, $length + 140);

        // Markdown punctuation reads as noise out of context: a snippet opening
        // "# Archiving" looks broken rather than like the start of a chapter.
        $snippet = (string) preg_replace('/^#+\s*|\s#+\s|[`*_]/m', ' ', $snippet);
        $snippet = trim((string) preg_replace('/\s+/', ' ', $snippet));

        return ($start > 0 ? '…' : '').$snippet.'…';
    }
}
