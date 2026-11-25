<?php

use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Events\RequestEventRecorded;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\RequestActivity;
use App\Notifications\WalkInActionText;
use App\Services\RequestWorkflow;
use App\Support\Sms\IprogSender;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Spec 36: [ACTION REQUIRED] notices are texted to the requester, online or walk-in, at a Philippine
| number, through IPROG SMS. tests/TestCase.php blanks the keys and stops stray HTTP
| calls.
*/

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->owner->forceFill(['contact_number' => '+639171234567'])->save();
    $this->registrar = User::factory()->registrar()->create();
});

/**
 * The event types of the notices the requester got that are texted.
 *
 * @return list<string>
 */
function textedEvents(User $owner): array
{
    return Notification::sent($owner, RequestActivity::class)
        ->filter(fn (RequestActivity $n) => in_array(SmsChannel::class, $n->via($owner), true))
        ->map(fn (RequestActivity $n) => $n->event->type->value)
        ->values()->all();
}

/** Returned to the requester for an office visit: an [ACTION REQUIRED] notice (REQ-06-18). */
function requireVisit(CredentialRequest $request): void
{
    workflow()->startEvaluation($request, test()->registrar);
    workflow()->recordCheck($request, test()->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->requireOfficeVisit($request, test()->registrar, ['Form 137'], 'Bring the original.');
}

function withIprog(): void
{
    config(['services.iprog.token' => 'test-token']);
}

test('REQ-36-02 11-digit Philippine numbers become E.164; anything else is left as it is', function () {
    $old = User::factory()->create();
    $foreign = User::factory()->create();
    DB::table('users')->where('id', $old->id)->update(['contact_number' => '09171234567']);
    DB::table('users')->where('id', $foreign->id)->update(['contact_number' => '+16502530000']);
    $request = submitRequest($this->owner, $this->org->tor);
    DB::table('credential_requests')->where('id', $request->id)->update(['contact_number' => '09181234567']);

    (require database_path('migrations/2026_11_24_000001_convert_contact_numbers.php'))->convertNumbers();

    expect($old->fresh()->contact_number)->toBe('+639171234567')
        ->and($foreign->fresh()->contact_number)->toBe('+16502530000')
        ->and($request->fresh()->contact_number)->toBe('+639181234567');
});

test('REQ-36-04 only [ACTION REQUIRED] notices are texted, whatever the email setting; staff never', function () {
    Notification::fake();
    $this->owner->forceFill(['email_notifications' => false])->save();

    $request = submitRequest($this->owner, $this->org->tor);
    requireVisit($request);
    workflow()->decline($request, $this->registrar, 'Not eligible');

    expect(textedEvents($this->owner))->toBe(['compliance_requested'])   // not submitted, evaluation_started or declined
        ->and(Notification::sent($this->registrar, RequestActivity::class)
            ->every(fn (RequestActivity $n) => ! in_array(SmsChannel::class, $n->via($this->registrar), true)))->toBeTrue();
});

test('REQ-36-05 a walk-in request with no account is texted its [ACTION REQUIRED] notices at its own number', function () {
    Notification::fake();
    $request = workflow()->submitWalkIn($this->registrar, ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null], [
        'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Villanueva', 'sex' => 'male', 'email' => 'pedro@example.com',
        'contact_number' => '+639181234567', 'academic_status' => AcademicStatus::Graduate->value, 'college_id' => $this->org->ccs->id,
        'program' => 'BS Computer Science', 'student_number' => null, 'attended_from' => 2018, 'attended_to' => 2022,
    ]);

    // Its recording is not texted; its claim notice is (REQ-06-18), as if the offices were done.
    $ready = $request->events()->create([
        'actor_id' => $this->registrar->id, 'type' => RequestEventType::ReadyForRelease,
        'from_status' => RequestStatus::InProgress, 'to_status' => RequestStatus::ReadyForRelease,
    ]);
    RequestEventRecorded::dispatch($ready);

    Notification::assertSentOnDemandTimes(WalkInActionText::class, 1);
    Notification::assertSentOnDemand(WalkInActionText::class, function (WalkInActionText $n, array $channels, AnonymousNotifiable $to) use ($request) {
        return $to->routes[SmsChannel::class] === '+639181234567'
            && $n->toSms($to) === "UPHSL: [ACTION REQUIRED] {$request->reference_no}: {$n->event->summary()}";
    });
});

test('REQ-36-06 a requester with no contact number is not texted', function () {
    Notification::fake();
    $request = submitRequest($this->owner, $this->org->tor);   // a request needs a number; it is removed afterwards
    requireVisit($request);

    $this->owner->forceFill(['contact_number' => null])->save();

    expect(textedEvents($this->owner))->toBe([]);
});

test('REQ-36-07 texts go through IPROG SMS', function () {
    withIprog();
    Http::fake([IprogSender::URL => Http::response(['status' => 200, 'message_id' => 'iSms-1'])]);

    $request = submitRequest($this->owner, $this->org->tor);
    requireVisit($request);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $sent) => $sent->url() === IprogSender::URL
        && $sent['api_token'] === 'test-token'
        && $sent['phone_number'] === '639171234567'
        && str_starts_with((string) $sent['message'], "UPHSL: [ACTION REQUIRED] {$request->reference_no}: ")
        && str_ends_with((string) $sent['message'], route('student.requests.show', $request)));
});

test('REQ-36-06 a number from outside the Philippines is not texted, online or walk-in', function () {
    Notification::fake();
    $this->owner->forceFill(['contact_number' => '+16502530000'])->save();

    requireVisit(submitRequest($this->owner, $this->org->tor));

    $walkIn = workflow()->submitWalkIn($this->registrar, ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null], [
        'first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Reyes', 'sex' => 'female', 'email' => 'ana@example.com',
        'contact_number' => '+16502530001', 'academic_status' => AcademicStatus::Graduate->value, 'college_id' => $this->org->ccs->id,
        'program' => 'BS Computer Science', 'student_number' => null, 'attended_from' => 2018, 'attended_to' => 2022,
    ]);
    RequestEventRecorded::dispatch($walkIn->events()->create([
        'actor_id' => $this->registrar->id, 'type' => RequestEventType::ReadyForRelease,
        'from_status' => RequestStatus::InProgress, 'to_status' => RequestStatus::ReadyForRelease,
    ]));

    expect(textedEvents($this->owner))->toBe([]);
    Notification::assertSentTo($this->owner, RequestActivity::class);   // still told in-app and by email
    Notification::assertSentOnDemandTimes(WalkInActionText::class, 0);
});

test('REQ-36-07 without a provider\'s keys its texts are only logged', function () {
    Http::fake();
    Log::spy();

    requireVisit(submitRequest($this->owner, $this->org->tor));

    Http::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'SMS to +639171234567'))->once();
});

test('REQ-36-09 a text the provider refuses is logged and the in-app notification and email still go out', function () {
    withIprog();
    Http::fake([IprogSender::URL => Http::response(['status' => 500, 'message' => 'Insufficient credit'])]);
    Log::spy();
    $request = submitRequest($this->owner, $this->org->tor);
    $sent = collect();
    Event::listen(NotificationSent::class, fn (NotificationSent $event) => $sent->push($event->notifiable->is($this->owner) ? $event->channel : null));

    requireVisit($request);

    expect($sent->filter()->values()->all())->toContain('database', 'mail')
        ->and($this->owner->notifications()->where('data->event_type', 'compliance_requested')->exists())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Insufficient credit'))->once();
});

test('REQ-36-11 outside production every text goes to SMS_REDIRECT_TO, or only to the log', function () {
    withIprog();
    Http::fake([IprogSender::URL => Http::response(['status' => 200, 'message_id' => 'iSms-1'])]);
    config(['services.sms.redirect' => true, 'services.sms.redirect_to' => '+639990000000']);

    requireVisit(submitRequest($this->owner, $this->org->tor));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $sent) => $sent['phone_number'] === '639990000000');

    config(['services.sms.redirect_to' => null]);
    requireVisit(submitRequest(requester($this->org->ccs), $this->org->tor));

    Http::assertSentCount(1);
});
