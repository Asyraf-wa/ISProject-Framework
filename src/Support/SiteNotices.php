<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * The corner ribbon and the announcement bar.
 *
 * Both are driven entirely from the settings screen, and both need the same
 * three questions answered — is it switched on, does it have anything to say,
 * and is it still current — so they answer them in one place rather than in
 * two Blade templates.
 */
class SiteNotices
{
    /** Tones offered for both notices, mapped to the palette. */
    public const TONES = [
        'primary' => 'Brand',
        'info' => 'Info',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Urgent',
    ];

    /**
     * The ribbon, or null when there is nothing to show.
     *
     * @return array{text: string, url: ?string, tone: string, external: bool}|null
     */
    public function ribbon(): ?array
    {
        if (! isproject_setting('ribbon_enabled', false)) {
            return null;
        }

        $text = trim((string) isproject_setting('ribbon_text', ''));

        if ($text === '') {
            return null;
        }

        $url = $this->safeUrl(isproject_setting('ribbon_url'));

        return [
            'text' => $text,
            'url' => $url,
            'tone' => $this->tone('ribbon_tone'),

            // An absolute link leaves the application, so it opens in a new tab
            // and gets rel="noopener"; a relative one is a page of this site and
            // stays where it is.
            'external' => $url !== null && Str::startsWith($url, ['http://', 'https://']),
        ];
    }

    /**
     * The announcement bar, or null when there is nothing to show.
     *
     * @return array{text: string, url: ?string, tone: string, external: bool, id: string, hours: int}|null
     */
    public function announcement(): ?array
    {
        if (! isproject_setting('announcement_enabled', false)) {
            return null;
        }

        $text = trim((string) isproject_setting('announcement_text', ''));

        if ($text === '' || $this->expired()) {
            return null;
        }

        $url = $this->safeUrl(isproject_setting('announcement_url'));

        return [
            'text' => $text,
            'url' => $url,
            'tone' => $this->tone('announcement_tone'),
            'external' => $url !== null && Str::startsWith($url, ['http://', 'https://']),

            // Dismissal is remembered against this, not against "the
            // announcement" in general. Edit the wording and the id changes, so
            // a new notice appears at once even for someone who closed the last
            // one a minute ago — which is the whole point of announcing it.
            'id' => substr(md5($text.'|'.$url.'|'.$this->tone('announcement_tone')), 0, 12),

            'hours' => $this->dismissHours(),
        ];
    }

    /** Whether an end date has been set and has passed. */
    private function expired(): bool
    {
        $until = isproject_setting('announcement_until');

        if (! is_string($until) || $until === '') {
            return false;
        }

        try {
            // Inclusive of the day itself: "until 3 June" reads as showing
            // through the third, not up to midnight at its start.
            return Carbon::parse($until)->endOfDay()->isPast();
        } catch (Throwable) {
            // An unparseable date should not silence an announcement.
            return false;
        }
    }

    private function dismissHours(): int
    {
        $hours = (int) isproject_setting('announcement_dismiss_hours', 1);

        // Zero would mean the close button does nothing, and a year would mean
        // it is permanent. Neither is what anyone typing a number intended.
        return max(1, min($hours, 720));
    }

    private function tone(string $key): string
    {
        $tone = (string) isproject_setting($key, 'primary');

        return array_key_exists($tone, self::TONES) ? $tone : 'primary';
    }

    /**
     * Only http(s) links and site-relative paths.
     *
     * This is what stops "javascript:..." reaching an href. The settings screen
     * validates the same shape, but the check belongs here too: with the
     * default open settings gate, any signed-in user can set this value, and a
     * link that runs script for every visitor is stored XSS.
     */
    private function safeUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        return preg_match('#^(https?://|/)#i', $url) === 1 ? $url : null;
    }
}
