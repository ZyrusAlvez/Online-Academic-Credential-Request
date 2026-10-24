<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('registrar.credential-types')" wire:navigate>{{ __('Credential Types') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Route') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$credentialType->name" :description="__('The offices this credential passes through, in order, and what each one does. Changes apply to requests routed from now on; requests already in progress keep their current steps.')" />

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="steps-heading">
            <h2 id="steps-heading" class="sr-only">{{ __('Route steps') }}</h2>

            @if ($steps->isEmpty())
                <flux:callout icon="exclamation-triangle" color="amber" :heading="__('No route yet')">
                    <flux:callout.text>{{ __('Requesters cannot request this credential until it has at least one step.') }}</flux:callout.text>
                </flux:callout>
            @else
                <ol class="space-y-3">
                    @foreach ($steps as $step)
                        <li wire:key="step-{{ $step->id }}" class="relative flex gap-4 rounded-xl border border-secondary/50 bg-white p-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary font-semibold text-white" aria-hidden="true">{{ $step->position }}</span>

                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">
                                    <span class="sr-only">{{ __('Step :n:', ['n' => $step->position]) }}</span>
                                    {{ $step->label() }}
                                    <flux:badge size="sm" color="sky" class="ms-1">{{ $step->task->label() }}</flux:badge>
                                </p>
                                <p class="text-xs text-zinc-500">
                                    @if ($step->is_student_college)
                                        {{ __("Resolved to the requester's own college.") }}
                                    @else
                                        {{ $step->department->code }} · {{ $step->department->type->label() }}
                                        @unless ($step->department->is_active)
                                            · <span class="font-medium text-red-700">{{ __('Inactive') }}</span>
                                        @endunless
                                    @endif
                                </p>

                                @if ($editingStepId === $step->id)
                                    <form wire:submit="saveStep" class="mt-3 space-y-2">
                                        <flux:select wire:model="editingTask" :label="__('Task')">
                                            @foreach ($tasks as $task)
                                                <flux:select.option :value="$task->value">{{ $task->label() }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                        <flux:input wire:model="editingInstructions" :label="__('Instructions for this office')" />
                                        <div class="flex gap-2">
                                            <flux:button size="sm" type="submit" variant="primary">{{ __('Save') }}</flux:button>
                                            <flux:button size="sm" variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    </form>
                                @elseif ($step->instructions)
                                    <p class="mt-2 text-sm text-zinc-700">{{ $step->instructions }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-wrap items-start justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="move({{ $step->id }}, -1)" :disabled="$loop->first" :aria-label="__('Move :name up', ['name' => $step->label()])" />
                                <flux:button size="sm" variant="ghost" icon="arrow-down" wire:click="move({{ $step->id }}, 1)" :disabled="$loop->last" :aria-label="__('Move :name down', ['name' => $step->label()])" />
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editStep({{ $step->id }})" :aria-label="__('Edit task and instructions for :name', ['name' => $step->label()])" />
                                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-700!" wire:click="remove({{ $step->id }})" wire:confirm="{{ __('Remove :name from this route?', ['name' => $step->label()]) }}" :aria-label="__('Remove :name', ['name' => $step->label()])" />
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section aria-labelledby="add-step-heading" class="h-fit rounded-xl border border-secondary/50 bg-primary-50 p-5">
            <h2 id="add-step-heading" class="font-semibold text-primary">{{ __('Add a step') }}</h2>
            <p class="mt-1 text-sm text-zinc-600">{{ __('New steps are added at the end.') }}</p>

            <form wire:submit="addStep" class="mt-4 space-y-4">
                <flux:select wire:model="newStep" :label="__('Office')">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @unless ($hasCollegeStep)
                        <flux:select.option value="college">{{ __("Dean (the requester's college)") }}</flux:select.option>
                    @endunless
                    @foreach ($availableDepartments as $department)
                        <flux:select.option :value="$department->id">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="newTask" :label="__('Task')">
                    @foreach ($tasks as $task)
                        <flux:select.option :value="$task->value">{{ $task->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="newInstructions" :label="__('Instructions')" badge="Optional" :placeholder="__('e.g. Review and affix the required signature')" />

                <flux:button type="submit" variant="primary" icon="plus" class="w-full">{{ __('Add step') }}</flux:button>
            </form>
        </section>
    </div>
</div>
