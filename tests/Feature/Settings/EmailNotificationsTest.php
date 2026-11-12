<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\RequestEventType;
use App\Livewire\Notifications\EmailSettings;
use App\Models\User;
use App\Notifications\DueReminder;
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

    Livewire::actingAs($this->owner)->test(EmailSettings::class)
        ->assertSet('enabled', true)
        ->set('enabled', false)
        ->assertDispatched('notify');

    expect($this->owner->fresh()->email_notifications)->toBeFalse();

    Livewire::actingAs($this->owner->fresh())->test(EmailSettings::class)
        ->assertSet('enabled', false)
        ->set('enabled', true);

    expect($this->owner->fresh()->email_notifications)->toBeTrue();
});

test('REQ-06-16 with email off the requester is still notified in-app but not emailed', function () {
    Notification::fake();
    $this->owner->forceFill(['email_notifications' => false])->save();

    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->requireOfficeVisit($request, $this->registrar, ['Form 137'], 'Bring the original.');
    workflow()->decline($request, $this->registrar, 'Not eligible');

    $sent = Notification::sent($this->owner, RequestActivity::class);
    expect($sent->map(fn (RequestActivity $n) => $n->event->type)->all())
        ->toContain(RequestEventType::Submitted, RequestEventType::ComplianceRequested, RequestEventType::Declined)
        ->and($sent->every(fn (RequestActivity $n) => $n->via($this->owner) === ['database']
            || $n->actionRequired($this->owner)))->toBeTrue()   // [ACTION REQUIRED] is still emailed (REQ-06-18)
        ->and($sent->first(fn ($n) => $n->event->type === RequestEventType::ComplianceRequested)->via($this->owner))->toBe(['database', 'mail'])
        ->and($sent->first(fn ($n) => $n->event->type === RequestEventType::Declined)->via($this->owner))->toBe(['database']);

    expect((new DueReminder($request, true))->via($this->owner))->toBe(['database']);
});

test('REQ-06-16 the requester\'s page says [ACTION REQUIRED] notifications are always emailed', function () {
    $this->actingAs($this->owner)->get(route('settings.notifications'))
        ->assertSee('Notifications marked [ACTION REQUIRED] are always emailed, even when this is turned off.');
});

test('REQ-06-16 verification codes are always emailed', function () {
    $this->owner->forceFill(['email_notifications' => false])->save();

    expect((new VerificationCode('123456'))->via($this->owner))->toBe(['mail']);
});

test('REQ-06-16 registrars and department staff turn their emails off; admins do not see the setting', function () {
    $dean = User::factory()->departmentStaff($this->org->ccs)->create();
    $admin = User::factory()->admin()->create();

    foreach ([$this->registrar, $dean] as $staff) {
        $this->actingAs($staff)->get(route('settings.notifications'))
            ->assertOk()
            ->assertSee('Email me about requests')
            ->assertDontSeeHtml('data-action-required-note');
        $this->actingAs($staff)->get(route('settings.password'))->assertSee('Email notifications');

        Livewire::actingAs($staff)->test(EmailSettings::class)->set('enabled', false);
        expect($staff->fresh()->wantsEmailNotifications())->toBeFalse();
    }

    $this->actingAs($admin)->get(route('settings.notifications'))->assertForbidden();
    $this->actingAs($admin)->get(route('settings.password'))->assertDontSee('Email notifications');
});
