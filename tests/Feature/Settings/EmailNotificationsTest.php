<?php

use App\Enums\RequestEventType;
use App\Livewire\Student\EmailNotifications;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Notifications\DueReminder;
use App\Notifications\IdentityReviewed;
use App\Notifications\RequestActivity;
use App\Notifications\VerificationCode;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
});

test('REQ-06-16 email notifications are on for new and existing accounts', function () {
    expect($this->owner->fresh()->email_notifications)->toBeTrue()
        ->and(User::factory()->create()->fresh()->wantsEmailNotifications())->toBeTrue();
});

test('REQ-06-16 a requester turns email notifications off and on in settings', function () {
    $this->actingAs($this->owner)->get(route('settings.notifications'))
        ->assertOk()
        ->assertSee('Email me about my requests');

    Livewire::actingAs($this->owner)->test(EmailNotifications::class)
        ->assertSet('enabled', true)
        ->set('enabled', false)
        ->assertDispatched('notify');

    expect($this->owner->fresh()->email_notifications)->toBeFalse();

    Livewire::actingAs($this->owner->fresh())->test(EmailNotifications::class)
        ->assertSet('enabled', false)
        ->set('enabled', true);

    expect($this->owner->fresh()->email_notifications)->toBeTrue();
});

test('REQ-06-16 with email off the requester is still notified in-app but not emailed', function () {
    Notification::fake();
    $this->owner->forceFill(['email_notifications' => false])->save();

    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->decline($request, $this->registrar, 'Not eligible');

    $sent = Notification::sent($this->owner, RequestActivity::class);
    expect($sent->map(fn (RequestActivity $n) => $n->event->type)->all())
        ->toContain(RequestEventType::Submitted, RequestEventType::Declined)
        ->and($sent->every(fn (RequestActivity $n) => $n->via($this->owner) === ['database']))->toBeTrue();

    expect((new DueReminder($request, true))->via($this->owner))->toBe(['database'])
        ->and((new IdentityReviewed(IdentityDocument::factory()->make()))->via($this->owner))->toBe(['database']);
});

test('REQ-06-16 verification codes are always emailed', function () {
    $this->owner->forceFill(['email_notifications' => false])->save();

    expect((new VerificationCode('123456'))->via($this->owner))->toBe(['mail']);
});

test('REQ-06-16 staff do not see the email notification setting', function () {
    $this->actingAs($this->registrar)->get(route('settings.notifications'))->assertForbidden();
    $this->actingAs($this->registrar)->get(route('settings.password'))->assertDontSee('Email notifications');

    $this->actingAs($this->owner)->get(route('settings.password'))->assertSee('Email notifications');
});
