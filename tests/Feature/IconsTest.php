<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Support\Str;
use IsProject\Framework\Support\Icons;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class IconsTest extends TestCase
{
    #[Test]
    public function the_index_matches_the_component(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/icon.blade.php');

        // Only the $paths array. Scanning the whole file would also match the
        // @props block, whose 'name' => 'circle' default is not an icon.
        $paths = Str::between($component, '$paths = [', '];');

        preg_match_all("/'([a-z0-9-]+)' => '/", $paths, $matches);

        $inComponent = $matches[1];
        sort($inComponent);

        $indexed = Icons::names();
        sort($indexed);

        // Icons::names() only lists what the component can draw. If this fails,
        // an icon was added to one and not the other — the settings picker would
        // otherwise offer a name that renders as the fallback circle.
        $this->assertSame(
            $inComponent,
            $indexed,
            'Icons::names() and icon.blade.php have drifted apart'
        );
    }

    #[Test]
    public function every_name_renders_its_own_glyph(): void
    {
        $fallback = $this->render('circle');

        foreach (Icons::names() as $name) {
            $svg = $this->render($name);

            $this->assertStringContainsString('<svg', $svg);

            if ($name !== 'circle') {
                $this->assertNotSame($fallback, $svg, "[{$name}] fell through to the fallback icon");
            }
        }
    }

    private function render(string $name): string
    {
        return trim((string) view()->make('isproject::components.icon', ['name' => $name, 'size' => 16])->render());
    }
}
