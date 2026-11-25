<?php

namespace App\Livewire\Admin\Sms;

use App\Actions\Settings\SetSmsEnabled;
use App\Models\Setting;
use App\Support\Sms\SmsFailed;
use App\Support\Sms\SmsRouter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Administration → SMS (REQ-36-12, 13): the switch that turns texting requesters on or off, and
 * the IPROG SMS credits left, fetched after the page loads so a slow IPROG never delays it.
 */
#[Title('SMS')]
class Index extends Component
{
    public bool $enabled = false;

    /** The credits left; null until fetched, or when they could not be. */
    public ?float $balance = null;

    /** Why the balance is not shown: 'loading', 'no_key' or 'unreachable'; null once it is. */
    public ?string $balanceProblem = 'loading';

    public function mount(): void
    {
        $this->authorize('update', Setting::class);

        $this->enabled = Setting::enabled(Setting::SMS_ENABLED);
    }

    public function updatedEnabled(SetSmsEnabled $setSmsEnabled): void
    {
        $this->authorize('update', Setting::class);

        $setSmsEnabled->handle($this->enabled);

        $this->dispatch('notify', message: $this->enabled ? __('SMS notifications turned on.') : __('SMS notifications turned off.'));
    }

    /** Called by the page once it is shown, and by the refresh button (REQ-36-13). */
    public function loadBalance(SmsRouter $router): void
    {
        $this->authorize('update', Setting::class);

        $iprog = $router->iprog();

        if ($iprog === null) {
            [$this->balance, $this->balanceProblem] = [null, 'no_key'];

            return;
        }

        try {
            [$this->balance, $this->balanceProblem] = [$iprog->credits(), null];
        } catch (SmsFailed $e) {
            Log::warning($e->getMessage());
            [$this->balance, $this->balanceProblem] = [null, 'unreachable'];
        }
    }

    public function render(): View
    {
        return view('livewire.admin.sms.index', [
            'logsOnly' => ! config('services.sms.send'),
        ]);
    }
}
