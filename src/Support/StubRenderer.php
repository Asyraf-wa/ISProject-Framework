<?php

namespace IsProject\Framework\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Loads a stub and substitutes its %%placeholders%%.
 *
 * Placeholders use %%name%% rather than Laravel's own {{ name }} because the
 * view stubs are Blade templates — {{ }} inside them belongs to the generated
 * app, not to the generator.
 *
 * Resolution order lets a project override any stub without forking the
 * package: config('isproject.stub_path') first, packaged stubs/ second.
 */
class StubRenderer
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $packageStubPath,
    ) {}

    public function render(string $stub, array $replacements): string
    {
        $contents = $this->files->get($this->locate($stub));

        foreach ($replacements as $key => $value) {
            $contents = str_replace('%%'.$key.'%%', (string) $value, $contents);
        }

        return $contents;
    }

    /** Absolute path of the stub actually in use, published copy winning. */
    public function locate(string $stub): string
    {
        $published = base_path(trim((string) config('isproject.stub_path', 'stubs/isproject'), '/').'/'.$stub);

        if ($this->files->exists($published)) {
            return $published;
        }

        $packaged = $this->packageStubPath.'/'.$stub;

        if (! $this->files->exists($packaged)) {
            throw new RuntimeException("Stub [{$stub}] not found in [{$packaged}].");
        }

        return $packaged;
    }
}
