<?php

namespace App\Livewire\Department;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Signature Queue')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.department.dashboard');
    }
}
