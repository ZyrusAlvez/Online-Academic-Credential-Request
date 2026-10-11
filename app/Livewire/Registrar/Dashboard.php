<?php

namespace App\Livewire\Registrar;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Registrar Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.registrar.dashboard');
    }
}
