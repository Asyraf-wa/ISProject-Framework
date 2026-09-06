{{--
    A person's photo, falling back to their initials.

        <x-isproject::avatar :user="$user" />
        <x-isproject::avatar :user="$user" size="lg" />

    Used in the topbar and the users list, so a photo uploaded on the profile
    screen shows everywhere without each screen knowing how it is stored.
--}}
@props([
    'user' => null,
    'size' => null,
])

@php
    $avatars = app(\IsProject\Framework\Support\Avatars::class);
    $src = $avatars->url($user);
@endphp

@if ($src)
    <img {{ $attributes->class(['is-avatar', 'is-avatar-img', 'is-avatar-'.$size => $size]) }}
         src="{{ $src }}"
         alt="{{ $user->name ?? $user->email ?? 'Profile photo' }}">
@else
    <span {{ $attributes->class(['is-avatar', 'is-avatar-'.$size => $size]) }} aria-hidden="true">
        {{ $avatars->initials($user) }}
    </span>
@endif
