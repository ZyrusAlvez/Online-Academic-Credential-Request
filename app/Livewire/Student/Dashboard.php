<?php

namespace App\Livewire\Student;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My Requests')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.student.dashboard');
    }
}
