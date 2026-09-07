<?php

use Illuminate\Database\Eloquent\Model;
use IsProject\Framework\Models\Activity;
use IsProject\Framework\Support\ActivityLogger;
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

if (! function_exists('isproject_activity')) {
    /**
     * Record something somebody did.
     *
     *     isproject_activity('invoice.exported', 'Exported the March invoices');
     *     isproject_activity('order.shipped', 'Marked it shipped', [], $order);
     *
     * The event is a free string, so an application logs its own alongside the
     * ones this package raises. Returns the row, or null when logging is off or
     * the write failed — it never throws, because a log must not be able to take
     * down the thing it is watching.
     *
     * @param  array<string, mixed>  $properties
     */
    function isproject_activity(
        string $event,
        string $description,
        array $properties = [],
        ?Model $subject = null,
    ): ?Activity {
        return app(ActivityLogger::class)->log($event, $description, $properties, $subject);
    }
}
