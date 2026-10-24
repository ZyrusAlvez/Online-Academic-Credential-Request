@props(['request'])

@php
    use App\Enums\RequestStatus as S;

    $status = $request->status;
    $accepted = $request->accepted_at !== null;
    $paid = $accepted || in_array($status, [S::ReadyForRelease, S::Released], true);
    $finalStage = in_array($status, [S::ForFinalApproval, S::ReadyForRelease, S::Released], true);
    $ready = in_array($status, [S::ReadyForRelease, S::Released], true);
    $closed = in_array($status, [S::Declined, S::Cancelled], true);

    // done | current | upcoming for each milestone (Proposed Workflow, sample customer portal).
    $milestones = [
        [__('Request submitted and evaluated'), __('Identity, academic records and documentary requirements checked.'),
            $request->requirements_completed_at ? 'done' : ($closed ? 'upcoming' : 'current')],
        [__('Payment verified'), __('Payment confirmed by authorized personnel.'),
            $paid ? 'done' : ($request->requirements_completed_at && ! $closed ? 'current' : 'upcoming')],
        [__('Processing in progress'), $accepted && ! $finalStage ? __('Currently with :office.', ['office' => $request->whereabouts()]) : __('With the designated processing offices.'),
            $finalStage ? 'done' : ($accepted && ! $closed ? 'current' : 'upcoming')],
        [__('Final approval and quality check'), __('Remaining signatories and the Registrar\'s final check.'),
            $ready ? 'done' : ($status === S::ForFinalApproval ? 'current' : 'upcoming')],
        [__('Ready for release'), $status === S::Released ? __('Released :date.', ['date' => $request->released_at?->format('M j, Y')]) : __('You will be notified when the credential is available.'),
            $status === S::Released ? 'done' : ($ready ? 'current' : 'upcoming')],
    ];
@endphp

<div {{ $attributes->class('space-y-5') }}>
    <ol class="space-y-4">
        @foreach ($milestones as [$title, $text, $state])
            <li class="flex gap-3">
                <span @class([
                    'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full',
                    'bg-green-700 text-white' => $state === 'done',
                    'bg-primary text-white ring-4 ring-primary-100' => $state === 'current',
                    'bg-zinc-200 text-zinc-500' => $state === 'upcoming',
                ]) aria-hidden="true">
                    @if ($state === 'done')<flux:icon.check variant="micro" />@endif
                </span>
                <div>
                    <p @class(['font-medium', 'text-ink' => $state !== 'upcoming', 'text-zinc-500' => $state === 'upcoming'])>
                        {{ $title }}
                        <span class="sr-only">({{ match ($state) { 'done' => __('done'), 'current' => __('current'), default => __('not yet') } }})</span>
                    </p>
                    <p class="text-sm text-zinc-600">{{ $text }}</p>
                </div>
            </li>
        @endforeach
    </ol>

    <dl class="grid gap-4 border-t border-secondary/50 pt-4 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('Processing period') }}</dt>
            <dd class="text-lg font-bold text-ink">{{ trans_choice(':count working day|:count working days', $request->credentialType->processing_days) }}</dd>
            <dd class="text-xs text-zinc-600">{{ __('Starts when your request is accepted.') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('Expected release date') }}</dt>
            @if ($request->effective_due_at)
                <dd class="text-lg font-bold text-ink">{{ $request->effective_due_at->format('l, M j, Y') }}</dd>
                <dd class="text-xs text-zinc-600">
                    {{ __('Based on the acceptance date (:date) and the working calendar.', ['date' => $request->accepted_at?->format('M j, Y')]) }}
                    @if ($request->adjusted_due_at)
                        {{ __('Originally :date; moved while waiting for your action.', ['date' => $request->due_at?->format('M j')]) }}
                    @endif
                </dd>
            @else
                <dd class="text-lg font-bold text-zinc-500">{{ __('Set on acceptance') }}</dd>
                <dd class="text-xs text-zinc-600">{{ __('Submitting a request does not mean it has been accepted for processing.') }}</dd>
            @endif
        </div>
    </dl>
</div>
