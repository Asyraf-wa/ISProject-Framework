<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Str;

/**
 * The shape of the settings screen: which fields exist, how each is rendered,
 * validated and cast.
 *
 * Everything comes from config('isproject.settings.groups'), so a lecturer adds
 * a setting by adding an array entry — no migration, no controller change. This
 * class is the single place that knows what a field definition means.
 */
class SettingsSchema
{
    /** Field types the settings form can render. */
    public const TYPES = ['text', 'textarea', 'email', 'url', 'number', 'date', 'select', 'boolean', 'image', 'color', 'swatches'];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $groups = null;

    /**
     * Groups in display order, each with its fields normalised.
     *
     * @return array<int, array<string, mixed>>
     */
    public function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $groups = [];

        foreach ((array) config('isproject.settings.groups', []) as $key => $group) {
            $fields = [];

            foreach ((array) ($group['fields'] ?? []) as $name => $field) {
                $fields[$name] = $this->normalise($name, (array) $field);
            }

            if ($fields === []) {
                continue;
            }

            $groups[] = [
                'key' => (string) $key,
                'label' => (string) ($group['label'] ?? Str::headline((string) $key)),
                'icon' => (string) ($group['icon'] ?? 'settings'),
                'description' => $group['description'] ?? null,
                'fields' => $fields,
            ];
        }

        return $this->groups = $groups;
    }

    /**
     * Every field, flattened and keyed by setting name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return array_merge(...array_column($this->groups(), 'fields')) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function field(string $key): ?array
    {
        return $this->fields()[$key] ?? null;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->fields());
    }

    /**
     * Uploads are handled separately from scalars: they arrive as files, are
     * validated as files, and are replaced rather than edited.
     *
     * @return array<int, string>
     */
    public function imageKeys(): array
    {
        return array_keys(array_filter(
            $this->fields(),
            fn (array $field) => $field['type'] === 'image'
        ));
    }

    /**
     * Value used when nothing has been saved yet. A null default on a field
     * that names a config key falls back to that key, so the settings screen
     * opens showing what the application is actually using.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields() as $key => $field) {
            $default = $field['default'];

            // "@config:app.name" lets a setting start out showing the value the
            // application is already using, so the screen opens telling the
            // truth rather than showing a blank box.
            $defaults[$key] = is_string($default) && str_starts_with($default, '@config:')
                ? config(substr($default, 8))
                : $default;
        }

        return $defaults;
    }

    /**
     * Validation rules for the whole form.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->fields() as $key => $field) {
            $rules[$key] = $field['rules'];
        }

        return $rules;
    }

    /**
     * Labels so validation messages read "Application name", not "app_name".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_map(fn (array $field) => Str::lower($field['label']), $this->fields());
    }

    /**
     * Per-field validation messages, keyed "field.rule".
     *
     * Laravel's own wording for some rules says only that something is wrong —
     * "has invalid image dimensions" leaves somebody guessing which dimension
     * and what it should be. A field may say so itself:
     *
     *     'messages' => ['dimensions' => 'The icon must be square and at least…'],
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ((array) config('isproject.settings.groups', []) as $group) {
            foreach ((array) ($group['fields'] ?? []) as $name => $field) {
                foreach ((array) ($field['messages'] ?? []) as $rule => $message) {
                    $messages[$name.'.'.$rule] = (string) $message;
                }
            }
        }

        return $messages;
    }

    /**
     * Turn the stored string back into the type the application expects.
     * Unknown keys pass through untouched — a setting removed from config
     * keeps its row rather than throwing.
     */
    public function cast(string $key, mixed $raw): mixed
    {
        $field = $this->field($key);

        if ($field === null || $raw === null) {
            return $raw;
        }

        return match ($field['type']) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($raw) ? $raw + 0 : null,
            default => (string) $raw,
        };
    }

    /**
     * Fill in everything a field definition may leave out, so the views and the
     * controller can read the keys without defensive checks.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function normalise(string $name, array $field): array
    {
        $type = (string) ($field['type'] ?? 'text');

        if (! in_array($type, self::TYPES, true)) {
            $type = 'text';
        }

        return [
            'key' => $name,
            'type' => $type,
            'label' => (string) ($field['label'] ?? Str::headline($name)),
            'help' => $field['help'] ?? null,
            'placeholder' => $field['placeholder'] ?? null,
            'default' => $field['default'] ?? null,
            'options' => $this->resolveOptions($field['options'] ?? []),
            'rules' => $this->rulesFor($type, $field),
            'accept' => $field['accept'] ?? null,
            'width' => (string) ($field['width'] ?? 'col-md-6'),
            'requires' => (array) ($field['requires'] ?? []),
        ];
    }

    /**
     * Config keys a field needs before it may be switched on, mapped to the
     * .env name to tell someone about. Empty means no prerequisites.
     *
     * @return array<string, string>
     */
    public function unmetRequirements(string $key): array
    {
        $field = $this->field($key);

        if ($field === null || $field['requires'] === []) {
            return [];
        }

        return array_filter(
            $field['requires'],
            fn (string $env, string $configKey) => trim((string) config($configKey)) === '',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** Whether this field's prerequisites are all satisfied. */
    public function isAvailable(string $key): bool
    {
        return $this->unmetRequirements($key) === [];
    }

    /**
     * Option lists are either written out in config, or named with a leading
     * "@" and built here.
     *
     * Deliberately not closures: `php artisan config:cache` serialises the
     * config array with var_export(), which cannot represent a closure, so a
     * closure anywhere in config breaks that command for the whole application.
     *
     * @param  array<mixed>|string  $options
     * @return array<string, string>
     */
    private function resolveOptions(array|string $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        return match ($options) {
            '@icons' => Icons::options(),
            '@accents' => Theme::options(),
            '@timezones' => array_combine(
                timezone_identifiers_list(),
                timezone_identifiers_list(),
            ),
            '@themes' => ['system' => 'Follow the device', 'light' => 'Light', 'dark' => 'Dark'],
            '@tones' => SiteNotices::TONES,
            default => [],
        };
    }

    /**
     * Explicit rules win. Otherwise the type implies a sane rule set, so a
     * lecturer adding ['type' => 'email'] gets email validation for free.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function rulesFor(string $type, array $field): array
    {
        if (isset($field['rules'])) {
            return is_string($field['rules']) ? explode('|', $field['rules']) : (array) $field['rules'];
        }

        return match ($type) {
            'boolean' => ['nullable', 'boolean'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'url' => ['nullable', 'string', 'url', 'max:255'],
            'number' => ['nullable', 'numeric'],
            'date' => ['nullable', 'date'],
            'textarea' => ['nullable', 'string', 'max:2000'],
            // An <input type="color"> always submits something, and always in
            // this shape, so anything else came from somewhere other than the
            // form and is refused rather than written into a stylesheet.
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'swatches' => ['nullable', 'string', 'max:40'],
            // Images are validated by the controller, which knows whether a
            // file was actually uploaded on this request.
            'image' => [],
            default => ['nullable', 'string', 'max:255'],
        };
    }
}
