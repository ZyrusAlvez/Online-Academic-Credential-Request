<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * A system-wide switch the Admin sets (REQ-36-12). A missing row is the default.
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    use LogsActivity;

    /** Texting requesters (spec 36); off until the Admin turns it on. */
    public const SMS_ENABLED = 'sms_enabled';

    /** How each setting reads in the activity log. */
    private const LABELS = [
        self::SMS_ENABLED => 'SMS notifications',
    ];

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /** Whether a switch is on; off when it was never set. */
    public static function enabled(string $key): bool
    {
        return (bool) static::query()->where('key', $key)->value('value');
    }

    public static function set(string $key, mixed $value): void
    {
        $setting = static::query()->firstOrNew(['key' => $key]);
        $setting->value = $value;
        $setting->save();
    }

    public function activityCategory(): ActivityCategory
    {
        return ActivityCategory::Setup;
    }

    public function activityNoun(): string
    {
        return 'setting';
    }

    public function activityLabel(): string
    {
        return self::LABELS[$this->key] ?? $this->key;
    }

    /**
     * The key is in the label; only the value is a change.
     *
     * @return list<string>
     */
    protected function activityIgnored(): array
    {
        return ['key'];
    }
}
