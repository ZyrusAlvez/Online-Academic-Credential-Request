<div class="space-y-8">
    <x-page-header title="Turnaround Prediction" description="Estimate how long a request will take and spot requests likely to miss their due date." />

    @if ($model === null)
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.text>{{ __('The prediction model file is missing or invalid. Run ml/train.py to create resources/ml/turnaround-model.json.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-8 xl:grid-cols-2">
        <section aria-labelledby="predict-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <div>
                <h2 id="predict-heading" class="text-lg font-semibold text-primary">{{ __('Try a request') }}</h2>
                <p class="text-sm text-zinc-600">{{ __('Predicts the time from routing to ready for release if routed now.') }}</p>
            </div>

            <form wire:submit="predict" class="space-y-5">
                <flux:select wire:model="credentialTypeId" :label="__('Credential')">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->id">{{ $type->name }} ({{ trans_choice(':count working day|:count working days', $type->processing_days) }})</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="space-y-2">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="copies" type="number" min="1" max="10" inputmode="numeric" :label="__('Copies')" />
                        <flux:input wire:model="openWorkload" type="number" min="0" inputmode="numeric" :label="__('Open requests')" />
                    </div>
                    <p class="text-sm text-zinc-600">{{ __('Open requests starts at the number in progress right now; raise it to test a busy week.') }}</p>
                </div>

                <flux:radio.group wire:model="studentStatus" :label="__('Requester')" variant="segmented">
                    @foreach (\App\Enums\StudentStatus::cases() as $status)
                        <flux:radio :value="$status->value" :label="$status->label()" />
                    @endforeach
                </flux:radio.group>

                <flux:button type="submit" variant="primary" :disabled="$model === null">{{ __('Predict turnaround') }}</flux:button>
            </form>

            @if ($result)
                <div class="space-y-4 border-t border-secondary/40 pt-5" aria-live="polite">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-sm text-zinc-600">{{ __('Predicted turnaround') }}</p>
                            <p class="text-3xl font-bold text-primary tabular-nums">{{ trans_choice(':count day|:count days', round($result['days'], 1)) }}</p>
                        </div>
                        @if ($result['atRisk'])
                            <flux:badge color="red" icon="exclamation-triangle">{{ __('At risk of missing the due date') }}</flux:badge>
                        @else
                            <flux:badge color="green" icon="check-circle">{{ __('On track') }}</flux:badge>
                        @endif
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-zinc-600">{{ __('Predicted ready') }}</dt>
                            <dd class="font-semibold text-ink">{{ __('Around :date', ['date' => \Illuminate\Support\Carbon::parse($result['readyAt'])->format('D, M j')]) }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-600">{{ __('Due date') }}</dt>
                            <dd class="font-semibold text-ink">{{ \Illuminate\Support\Carbon::parse($result['dueAt'])->format('D, M j') }}</dd>
                        </div>
                    </dl>

                    <div>
                        <h3 class="text-sm font-semibold text-ink">{{ __('How :days days add up', ['days' => number_format($result['days'], 1)]) }}</h3>
                        <p class="text-xs text-zinc-600">{{ __('Days each input adds to the prediction.') }}@if (abs($result['intercept']) >= 0.1) {{ __('Base: :base days.', ['base' => number_format($result['intercept'], 1)]) }}@endif</p>
                        @php
                            $maxDays = max(0.01, collect($result['contributions'])->max(fn ($c) => abs($c['days'])));
                        @endphp
                        <ul class="mt-3 space-y-2">
                            @foreach ($result['contributions'] as $c)
                                <li class="grid grid-cols-[minmax(0,9rem)_1fr_3.5rem] items-center gap-3 text-sm sm:grid-cols-[minmax(0,11rem)_1fr_3.5rem]">
                                    <span class="truncate text-ink">{{ __($c['label']) }}@if (! in_array($c['value'], [0, 1], true) || in_array($c['feature'], ['copies', 'processing_days', 'open_workload'], true)) <span class="text-zinc-600">({{ $c['value'] }})</span>@endif</span>
                                    <span class="h-3 rounded-e bg-primary/10" aria-hidden="true">
                                        <span @class(['block h-3 rounded-e', 'bg-primary' => $c['days'] >= 0, 'bg-secondary' => $c['days'] < 0]) style="width: {{ round(abs($c['days']) / $maxDays * 100, 1) }}%"></span>
                                    </span>
                                    <span class="text-end font-semibold text-ink tabular-nums">{{ $c['days'] >= 0 ? '+' : '−' }}{{ number_format(abs($c['days']), 1) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </section>

        <section aria-labelledby="risk-heading" class="space-y-3">
            <div>
                <h2 id="risk-heading" class="text-lg font-semibold text-primary">{{ __('Predicted to miss the due date') }}</h2>
                <p class="text-sm text-zinc-600">{{ __('In-progress requests whose predicted ready date is after their due date.') }}</p>
            </div>
            @if ($atRisk->isEmpty())
                <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('No in-progress request is predicted to be late.') }}</p>
            @else
                <ul class="divide-y divide-secondary/40 rounded-xl border border-secondary/50">
                    @foreach ($atRisk as $request)
                        <li class="flex flex-wrap items-center justify-between gap-2 p-3">
                            <div class="min-w-0">
                                <a href="{{ route('registrar.requests.show', $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                                <p class="text-xs text-zinc-600">{{ $request->credentialType->name }} · {{ __('at :office', ['office' => $request->whereabouts()]) }}</p>
                            </div>
                            <div class="text-end text-xs">
                                <p class="font-semibold text-red-800">{{ __('Predicted :date', ['date' => $request->prediction->predicted_ready_at->format('M j')]) }}</p>
                                <p class="text-zinc-600">{{ __('Due :date', ['date' => $request->prediction->due_at->format('M j')]) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section aria-labelledby="accuracy-heading" class="space-y-3">
        <div>
            <h2 id="accuracy-heading" class="text-lg font-semibold text-primary">{{ __('Predicted vs actual turnaround') }}</h2>
            <p class="text-sm text-zinc-600">{{ __('Each dot is a request that reached ready for release. Dots on the line were predicted exactly; dots above it finished faster than predicted.') }}</p>
        </div>

        @if ($completed->isEmpty())
            <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('No predicted request has reached ready for release yet.') }}</p>
        @else
            @php
                $step = max(1, (int) ceil(max($completed->max('predicted'), $completed->max('actual')) / 4));
                $max = $step * 4;
                $w = 480; $h = 320; $pad = 44;
                $x = fn ($v) => round($pad + $v / $max * ($w - $pad - 12), 1);
                $y = fn ($v) => round($h - $pad - $v / $max * ($h - $pad - 12), 1);
                $mae = round($completed->avg(fn ($r) => abs($r['predicted'] - $r['actual'])), 1);
                $ticks = collect(range(0, 4))->map(fn ($i) => $i * $step);
            @endphp
            <figure class="rounded-xl border border-secondary/50 p-4">
                <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full max-w-2xl" role="img"
                    aria-label="{{ __('Scatter chart of :n requests. Average difference between predicted and actual turnaround: :mae days.', ['n' => $completed->count(), 'mae' => $mae]) }}">
                    @foreach ($ticks as $t)
                        <line x1="{{ $pad }}" x2="{{ $w - 12 }}" y1="{{ $y($t) }}" y2="{{ $y($t) }}" class="stroke-zinc-200" />
                        <text x="{{ $pad - 6 }}" y="{{ $y($t) + 4 }}" text-anchor="end" class="fill-zinc-600 text-[11px]">{{ $t }}</text>
                        <text x="{{ $x($t) }}" y="{{ $h - $pad + 16 }}" text-anchor="middle" class="fill-zinc-600 text-[11px]">{{ $t }}</text>
                    @endforeach
                    <line x1="{{ $x(0) }}" y1="{{ $y(0) }}" x2="{{ $x($max) }}" y2="{{ $y($max) }}" class="stroke-secondary" stroke-width="2" />
                    @foreach ($completed as $r)
                        <circle cx="{{ $x($r['actual']) }}" cy="{{ $y($r['predicted']) }}" r="4.5" class="fill-primary/70"><title>{{ $r['reference'] }}: {{ __('predicted :p, actual :a days', ['p' => $r['predicted'], 'a' => $r['actual']]) }}</title></circle>
                    @endforeach
                    <text x="{{ ($w + $pad) / 2 }}" y="{{ $h - 6 }}" text-anchor="middle" class="fill-ink text-[12px]">{{ __('Actual days') }}</text>
                    <text x="12" y="{{ ($h - $pad) / 2 }}" text-anchor="middle" transform="rotate(-90 12 {{ ($h - $pad) / 2 }})" class="fill-ink text-[12px]">{{ __('Predicted days') }}</text>
                </svg>
                <figcaption class="mt-2 text-sm text-zinc-600">{{ trans_choice(':count request|:count requests', $completed->count()) }} · {{ __('average difference :mae days', ['mae' => $mae]) }}</figcaption>
            </figure>
        @endif
    </section>

    @if ($model !== null)
        <section aria-labelledby="model-heading" class="space-y-3 border-t border-secondary/40 pt-6">
            <div>
                <h2 id="model-heading" class="text-lg font-semibold text-primary">{{ __('About this model') }}</h2>
                <p class="text-sm text-zinc-600">{{ $model['algorithm'] }} · {{ $model['version'] }} · {{ __('scored on requests it did not see during training') }}</p>
            </div>
            <dl class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-primary-50 p-4">
                    <dt class="text-sm text-zinc-600">{{ __('Average error') }}</dt>
                    <dd class="text-2xl font-bold text-primary tabular-nums">{{ trans_choice(':count day|:count days', round($model['metrics']['mae'] ?? 0, 1)) }}</dd>
                    @isset($model['baseline_metrics']['mae'])
                        <dd class="text-xs text-zinc-600">{{ __('vs :days days by always guessing the average', ['days' => number_format($model['baseline_metrics']['mae'], 1)]) }}</dd>
                    @endisset
                </div>
                <div class="rounded-xl bg-primary-50 p-4">
                    <dt class="text-sm text-zinc-600">{{ __('Variance explained (R²)') }}</dt>
                    <dd class="text-2xl font-bold text-primary tabular-nums">{{ number_format($model['metrics']['r2'] ?? 0, 2) }}</dd>
                    <dd class="text-xs text-zinc-600">{{ __('1.00 would be perfect; the average-guess baseline scores 0.00') }}</dd>
                </div>
                <div class="rounded-xl bg-primary-50 p-4">
                    <dt class="text-sm text-zinc-600">{{ __('Training data') }}</dt>
                    <dd class="text-2xl font-bold text-primary">{{ $model['trained_on'] === 'simulated' ? __('Simulated') : __('Pilot requests') }}</dd>
                    @if ($model['trained_on'] === 'simulated')
                        <dd class="text-xs text-zinc-600">{{ __('Retrain on real requests once the pilot has enough.') }}</dd>
                    @endif
                </div>
            </dl>
        </section>
    @endif
</div>
