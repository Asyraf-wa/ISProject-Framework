<?php

namespace IsProject\Framework\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use IsProject\Framework\Models\Profile;
use Throwable;

/**
 * Profile photos.
 *
 * Reading is the hot path — the topbar asks on every page, and the users list
 * asks once per row — so a request-local cache keeps it to one query however
 * many times it is called.
 */
class Avatars
{
    /** Resolved paths for this request, keyed by user id. */
    private array $memo = [];

    /** The photo's URL, or null when there is none to show. */
    public function url(Model|Authenticatable|null $user): ?string
    {
        $path = $this->path($user);

        if ($path === null) {
            return null;
        }

        try {
            return Storage::disk($this->disk())->url($path);
        } catch (Throwable) {
            // A misconfigured disk should cost a photo, not the page.
            return null;
        }
    }

    /**
     * Two letters to stand in for a missing photo.
     *
     * Initials from the name where there is one — "Aisha Rahman" gives "AR",
     * which reads as a person rather than as the first two letters of a word.
     */
    public function initials(Model|Authenticatable|null $user): string
    {
        if (! $user) {
            return '?';
        }

        $name = trim((string) ($user->name ?? ''));

        if ($name !== '') {
            $words = preg_split('/\s+/', $name) ?: [];

            if (count($words) > 1) {
                return Str::upper(Str::substr($words[0], 0, 1).Str::substr(end($words), 0, 1));
            }

            return Str::upper(Str::substr($name, 0, 2));
        }

        return Str::upper(Str::substr((string) ($user->email ?? '?'), 0, 2));
    }

    /**
     * Store an upload against a user, replacing anything already there.
     *
     * The stored name is random rather than derived from the user id: these
     * files sit on a public disk, and a predictable name would let anyone walk
     * the ids and collect everybody's photograph.
     */
    public function store(Model|Authenticatable $user, UploadedFile $file): string
    {
        $this->remove($user);

        $path = $file->storeAs(
            $this->directory(),
            Str::random(40).'.'.Str::lower($file->getClientOriginalExtension()),
            ['disk' => $this->disk()],
        );

        Profile::query()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            ['avatar' => $path, 'updated_at' => now()],
        );

        unset($this->memo[$user->getAuthIdentifier()]);

        return $path;
    }

    /** Delete the photo and forget it. Safe to call when there is none. */
    public function remove(Model|Authenticatable $user): void
    {
        $id = $user->getAuthIdentifier();
        $path = $this->path($user);

        if ($path !== null) {
            try {
                Storage::disk($this->disk())->delete($path);
            } catch (Throwable) {
                // The row is being cleared either way; a file that has already
                // gone is not an error worth showing anyone.
            }
        }

        Profile::query()->where('user_id', $id)->update(['avatar' => null, 'updated_at' => now()]);

        unset($this->memo[$id]);
    }

    /**
     * Remove everything held for a user, for when the account itself goes.
     * Without this the file would outlive the person it belonged to.
     */
    public function forget(Model|Authenticatable $user): void
    {
        $this->remove($user);

        Profile::query()->where('user_id', $user->getAuthIdentifier())->delete();
    }

    private function path(Model|Authenticatable|null $user): ?string
    {
        if (! $user) {
            return null;
        }

        $id = $user->getAuthIdentifier();

        if (array_key_exists($id, $this->memo)) {
            return $this->memo[$id];
        }

        try {
            $path = Profile::query()->where('user_id', $id)->value('avatar');
        } catch (Throwable) {
            // The table may not exist yet — the shell renders before the first
            // migration, and an avatar is not worth a 500.
            $path = null;
        }

        return $this->memo[$id] = is_string($path) && $path !== '' ? $path : null;
    }

    private function disk(): string
    {
        return (string) config('isproject.settings.disk', 'public');
    }

    private function directory(): string
    {
        return trim((string) config('isproject.settings.directory', 'isproject'), '/').'/avatars';
    }
}
