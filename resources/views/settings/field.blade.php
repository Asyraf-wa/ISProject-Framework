{{--
    One setting, rendered from its definition. Expects $field (normalised by
    SettingsSchema) and $value (the value currently in force).
--}}
@php
    $key = $field['key'];
    $current = old($key, $field['type'] === 'boolean' ? (bool) $value : $value);
    $invalid = $errors->has($key) ? ' is-invalid' : '';

    // Prerequisites that live in .env, not in the database. A field missing
    // them is shown but not operable, with the variable names to add.
    $missing = app(\IsProject\Framework\Support\SettingsSchema::class)->unmetRequirements($key);
@endphp

<div class="{{ $field['width'] }} mb-3">
    @if ($field['type'] === 'image')
        <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>

        @if ($value)
            <div class="is-media-preview">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('isproject.settings.disk', 'public'))->url($value) }}"
                     alt="Current {{ \Illuminate\Support\Str::lower($field['label']) }}">

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remove-{{ $key }}" name="remove[]" value="{{ $key }}">
                    <label class="form-check-label small" for="remove-{{ $key }}">Remove on save</label>
                </div>
            </div>
        @endif

        <input type="file"
               class="form-control{{ $invalid }}"
               id="{{ $key }}"
               name="{{ $key }}"
               @if ($field['accept']) accept="{{ $field['accept'] }}" @endif>

    @elseif ($field['type'] === 'boolean')
        <label class="form-label d-block">{{ $field['label'] }}</label>
        <input type="hidden" name="{{ $key }}" value="0">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1"
                   @checked($current && ! $missing) @disabled((bool) $missing)>
            <label class="form-check-label" for="{{ $key }}">
                {{ $missing ? 'Not available yet' : 'Enabled' }}
            </label>
        </div>

        @if ($missing)
            <div class="is-requires">
                <x-isproject::icon name="alert-circle" size="14" />
                <span>
                    Add
                    @foreach ($missing as $env)
                        <code>{{ $env }}</code>@if (! $loop->last) and @endif
                    @endforeach
                    to your <code>.env</code>, then reload this page.
                </span>
            </div>
        @endif

    @elseif ($field['type'] === 'swatches')
        <span class="form-label d-block">{{ $field['label'] }}</span>

        {{-- Radios, not a select: the whole point is seeing the colour. The
             measured ratios travel with each one, so the choice is informed
             rather than decorative. --}}
        <div class="is-swatches" role="radiogroup" aria-label="{{ $field['label'] }}">
            @foreach (\IsProject\Framework\Support\Theme::swatches() as $swatch)
                <label class="is-swatch"
                       style="--is-swatch: {{ $swatch['hex'] }}; --is-swatch-dark: {{ $swatch['tint'] }}">
                    <input type="radio" name="{{ $key }}" value="{{ $swatch['key'] }}"
                           @checked((string) $current === $swatch['key'])>

                    {{-- Split diagonally: the accent as the light theme uses it,
                         and the tint the dark theme swaps in. Showing only the
                         first would hide half of what is being chosen. --}}
                    <span class="is-swatch-chip" aria-hidden="true">
                        <svg class="is-swatch-tick" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3.5 8.5 3 3 6-6" />
                        </svg>
                    </span>

                    <span class="is-swatch-body">
                        <span class="is-swatch-name">
                            {{ $swatch['label'] }}
                            @if ($swatch['default'])
                                <span class="is-swatch-tag">Default</span>
                            @endif
                        </span>
                        <span class="is-swatch-meta">
                            <span>{{ $swatch['light'] }}<abbr title="Contrast in the light theme">:1</abbr></span>
                            <span class="is-swatch-sep" aria-hidden="true"></span>
                            <span>{{ $swatch['dark'] }}<abbr title="Contrast in the dark theme">:1</abbr></span>
                        </span>
                    </span>
                </label>
            @endforeach
        </div>

        <div class="form-text">
            Each chip shows the accent on the left and the tint the dark theme uses on the right.
            The two figures are its contrast as text in each. Every option clears the 4.5:1 minimum
            in both, which is why this is a fixed set rather than a colour picker.
        </div>

    @elseif ($field['type'] === 'color')
        <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>

        {{-- The swatch picks it and the box beside it says what was picked:
             a colour input alone shows no value, so there is no way to read
             back or paste a brand hex. --}}
        <div class="is-color-field">
            <input type="color" class="form-control form-control-color{{ $invalid }}"
                   id="{{ $key }}" name="{{ $key }}"
                   value="{{ $current ?: ($field['default'] ?? '#000000') }}"
                   oninput="this.nextElementSibling.value = this.value">
            <input type="text" class="form-control" aria-label="{{ $field['label'] }} hex value"
                   value="{{ $current ?: ($field['default'] ?? '#000000') }}"
                   pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false"
                   oninput="if (/^#[0-9a-fA-F]{6}$/.test(this.value)) this.previousElementSibling.value = this.value">
        </div>

    @elseif ($field['type'] === 'select')
        <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>
        <select class="form-select{{ $invalid }}" id="{{ $key }}" name="{{ $key }}">
            <option value="">— None —</option>
            @foreach ($field['options'] as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>

    @elseif ($field['type'] === 'textarea')
        <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>
        <textarea class="form-control{{ $invalid }}" id="{{ $key }}" name="{{ $key }}" rows="3"
                  @if ($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endif>{{ $current }}</textarea>

    @else
        @php
            // The handful of field types that are just an <input> with a
            // different type attribute.
            $inputType = in_array($field['type'], ['number', 'email', 'url', 'date'], true)
                ? $field['type']
                : 'text';
        @endphp

        <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>
        <input type="{{ $inputType }}"
               class="form-control{{ $invalid }}"
               id="{{ $key }}"
               name="{{ $key }}"
               value="{{ $current }}"
               @if ($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endif>
    @endif

    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if ($field['help'])
        <div class="form-text">{{ $field['help'] }}</div>
    @endif
</div>
