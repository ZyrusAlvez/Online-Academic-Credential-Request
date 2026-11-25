<?php

namespace App\Actions\Settings;

use App\Models\Setting;

/**
 * The Admin turns texting requesters on or off for the whole system (REQ-36-12). The change is
 * recorded in the activity log by the model.
 */
class SetSmsEnabled
{
    public function handle(bool $enabled): void
    {
        Setting::set(Setting::SMS_ENABLED, $enabled);
    }
}
