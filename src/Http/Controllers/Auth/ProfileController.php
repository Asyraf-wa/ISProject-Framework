<?php

namespace IsProject\Framework\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use IsProject\Framework\Models\SocialAccount;
use IsProject\Framework\Support\Avatars;

/**
 * The signed-in user's own account.
 *
 * Separate from the users screen on purpose: that one is administration, this
 * one is a person editing themselves, and it needs no permission.
 */
class ProfileController extends Controller
{
    public function edit(Request $request, Avatars $avatars): View
    {
        $user = $request->user();

        return view('isproject::auth.profile', [
            'user' => $user,
            'hasAvatar' => $avatars->url($user) !== null,
            'linked' => SocialAccount::query()
                ->where('user_id', $user->getKey())
                ->get(),
        ]);
    }

    public function update(Request $request, Avatars $avatars): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique($user->getTable(), 'email')->ignore($user->getKey()),
            ],
            // SVG is absent on purpose, as it is for the logo: an SVG can carry
            // script and these files are served from the application's own
            // origin, so accepting one would be stored XSS that any signed-in
            // user could upload.
            'avatar' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ], [
            'avatar.mimes' => 'The photo must be a PNG, JPEG or WebP image.',
            'avatar.max' => 'The photo must be smaller than 2 MB.',
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ])->save();

        // A new photo wins over the remove checkbox: someone who chose a file
        // and left the tick behind meant to replace, not to delete.
        if ($file = $request->file('avatar')) {
            $avatars->store($user, $file);
        } elseif ($request->boolean('remove_avatar')) {
            $avatars->remove($user);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            // Proving you know the current password is what stops someone who
            // walked up to an unlocked screen from taking the account over.
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        return back()->with('success', 'Password changed.');
    }

    /** Detach a linked Google identity. */
    public function unlink(Request $request, int $account): RedirectResponse
    {
        $link = SocialAccount::query()
            ->where('user_id', $request->user()->getKey())
            ->whereKey($account)
            ->firstOrFail();

        $link->delete();

        return back()->with('success', 'Google account disconnected.');
    }
}
