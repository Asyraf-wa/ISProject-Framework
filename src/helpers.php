<?php

use IsProject\Framework\Support\Settings;

if (! function_exists('isproject_setting')) {
    /**
     * Read a site configuration value.
     *
     *     isproject_setting('app_name', config('app.name'))
     *     isproject_setting()->all()
     *
     * A helper rather than a facade because its main caller is Blade, where
     * {{ isproject_setting('app_name') }} reads better than a class reference.
     * Never throws: see Settings for why the shell must render before the first
     * migration has run.
     */
    function isproject_setting(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}
