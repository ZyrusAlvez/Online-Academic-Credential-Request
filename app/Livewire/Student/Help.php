<?php

namespace App\Livewire\Student;

use App\Enums\HelpTopic;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * One help topic: its frequently asked questions (REQ-22-02..05).
 */
class Help extends Component
{
    public string $topic;

    public function mount(string $topic): void
    {
        abort_if(HelpTopic::tryFrom($topic) === null, 404);

        $this->topic = $topic;
    }

    public function render(): View
    {
        $topic = HelpTopic::from($this->topic);

        return view('livewire.student.help', ['help' => $topic])
            ->title(__('Help: :topic', ['topic' => __($topic->label())]));
    }
}
