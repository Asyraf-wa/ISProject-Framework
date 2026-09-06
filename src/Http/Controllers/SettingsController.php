<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Support\SettingsSchema;
use Throwable;

/**
 * Site configuration: the values in config('isproject.settings.groups'), stored
 * in the database so they can be changed without a redeploy.
 *
 * Authorisation is the route group's job — see AuthorizeSettings.
 */
class SettingsController extends Controller
{
    /**
     * Artisan commands the cache panel may run, and how each is described.
     *
     * A fixed map, not a request value passed to Artisan: whatever arrives from
     * the browser only ever selects a key from this list.
     */
    private const CACHE_ACTIONS = [
        'cache' => ['command' => 'cache:clear', 'label' => 'Application cache', 'help' => 'Anything the application stored with Cache::put, including these settings.'],
        'view' => ['command' => 'view:clear', 'label' => 'Compiled views', 'help' => 'Forces every Blade file to be recompiled. Safe to run at any time.'],
        'config' => ['command' => 'config:clear', 'label' => 'Config cache', 'help' => 'Reverts to reading config files directly. Slower until you run config:cache again.'],
        'route' => ['command' => 'route:clear', 'label' => 'Route cache', 'help' => 'Reverts to loading route files directly. Slower until you run route:cache again.'],
        'optimize' => ['command' => 'optimize:clear', 'label' => 'Everything', 'help' => 'Runs all of the above in one go.'],
    ];

    public function index(Settings $settings, SettingsSchema $schema): View
    {
        return view('isproject::settings.index', [
            'groups' => $schema->groups(),
            'values' => $settings->all(),
            'cacheActions' => $this->cacheActions(),
            // Uploads are served through the storage symlink; without it the
            // logo would save successfully and then render as a broken image.
            'storageLinked' => File::exists(public_path('storage')),

            // A stock Laravel app ships public/robots.txt, and the web server
            // hands that file over before PHP is reached — so our route, and
            // with it the indexing setting, is silently overruled.
            'robotsFileShadows' => File::exists(public_path('robots.txt')),
        ]);
    }

    public function update(Request $request, Settings $settings, SettingsSchema $schema): RedirectResponse
    {
        $images = $schema->imageKeys();

        $validated = $request->validate(
            $schema->rules() + [
                'remove' => ['nullable', 'array'],
                'remove.*' => ['string', 'in:'.implode(',', $images ?: ['-'])],
            ],
            $schema->messages(),
            $schema->attributes(),
        );

        $remove = (array) ($validated['remove'] ?? []);
        $values = [];

        foreach ($schema->fields() as $key => $field) {
            if (in_array($key, $images, true)) {
                continue;
            }

            // A checkbox that is not ticked posts nothing at all, so an absent
            // key means false rather than "leave it alone".
            $value = $field['type'] === 'boolean'
                ? (bool) ($validated[$key] ?? false)
                : ($validated[$key] ?? null);

            // Disabling the input in the form is a courtesy; this is the
            // control. A setting whose prerequisites are missing can never be
            // switched on, however the request was made.
            if ($value && ! $schema->isAvailable($key)) {
                $missing = implode(', ', $schema->unmetRequirements($key));

                return back()->withErrors([
                    $key => "Set {$missing} in your .env file first, then enable this.",
                ]);
            }

            $values[$key] = $value;
        }

        foreach ($images as $key) {
            $file = $request->file($key);

            // An upload wins over the remove checkbox: someone who picked a new
            // file and left the tick behind meant to replace, not to delete.
            if ($file instanceof UploadedFile) {
                $values[$key] = $this->storeUpload($key, $file, $settings);
            } elseif (in_array($key, $remove, true)) {
                $this->deleteFile($settings->get($key));
                $values[$key] = null;
            }
        }

        $settings->set($values);

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Run one of the cache commands. The settings cache is always flushed too:
     * a student pressing "clear cache" means "make the site reflect what I
     * changed", and leaving our own entry behind would look like the button
     * did nothing.
     */
    public function clearCache(Request $request, Settings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:'.implode(',', array_keys($this->cacheActions()))],
        ]);

        $action = $this->cacheActions()[$validated['action']];

        try {
            Artisan::call($action['command']);
        } catch (Throwable $e) {
            return back()->with('error', "Could not run {$action['command']}: {$e->getMessage()}");
        }

        $settings->flush();

        return back()->with('success', "Cleared: {$action['label']}.");
    }

    /**
     * Save an upload and drop the file it replaces.
     *
     * The stored name carries a timestamp so a replaced logo is not served from
     * the browser's cache under the old URL.
     */
    private function storeUpload(string $key, UploadedFile $file, Settings $settings): string
    {
        $this->deleteFile($settings->get($key));

        $name = $key.'-'.now()->format('YmdHis').'.'.Str::lower($file->getClientOriginalExtension());

        return $file->storeAs($this->directory(), $name, ['disk' => $this->disk()]);
    }

    private function deleteFile(mixed $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        try {
            Storage::disk($this->disk())->delete($path);
        } catch (Throwable) {
            // The row is being replaced either way; a missing file is not an
            // error worth showing anyone.
        }
    }

    /**
     * The cache buttons this installation offers. Config chooses which appear;
     * the order is the one declared above, so the list reads the same everywhere.
     *
     * @return array<string, array<string, string>>
     */
    private function cacheActions(): array
    {
        $wanted = (array) config('isproject.settings.cache_actions', ['cache', 'view']);

        return array_intersect_key(self::CACHE_ACTIONS, array_flip($wanted));
    }

    private function disk(): string
    {
        return (string) config('isproject.settings.disk', 'public');
    }

    private function directory(): string
    {
        return (string) config('isproject.settings.directory', 'isproject');
    }
}
