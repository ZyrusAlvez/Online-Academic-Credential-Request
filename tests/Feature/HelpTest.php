<?php

use App\Enums\HelpTopic;
use App\Models\IdentityDocument;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
});

/** The Help group's tag when it is expanded, whatever the attribute order. */
const HELP_OPEN = '/<ui-disclosure(?=[^>]*data-help-nav)(?=[^>]*\sopen[\s>])[^>]*>/';

/** The sidebar's Help group, from its opening tag to the end of the sidebar list. */
function helpNav(string $html): string
{
    return Str::after($html, 'data-help-nav');
}

// ── REQ-22-01, 02: navigation and pages ─────────────────────────────────────────────────────

test('REQ-22-01 the requester sidebar has an expandable Help group of the topics, after Request Tracker', function () {
    $html = $this->actingAs($this->owner)->get(route('student.dashboard'))->assertOk()->getContent();
    $nav = helpNav($html);

    expect(Str::before($html, 'data-help-nav'))->toContain('Request Tracker')
        ->and($html)->toMatch('/<ui-disclosure[^>]*data-help-nav/')
        ->and($nav)->toContain('Help');

    // The topics, in order, each linking to its page.
    $positions = array_map(fn (HelpTopic $topic) => strpos($nav, e($topic->label())), HelpTopic::cases());
    expect($positions)->not->toContain(false)
        ->and($positions)->toBe(collect($positions)->sort()->values()->all())
        ->and(array_map(fn (HelpTopic $topic) => $topic->label(), HelpTopic::cases()))->toBe([
            'Getting Started', 'Your Details & Valid ID', 'Requesting a Credential', 'Payment', 'Tracking & Statuses', 'Claiming a Credential',
        ]);

    // Each item links to its page and stays inside the sidebar: a label too long ends in "…",
    // shown in full on hover.
    foreach (HelpTopic::cases() as $topic) {
        expect($nav)->toMatch('#<a(?=[^>]*href="'.preg_quote(route('student.help', $topic->value), '#').'")(?=[^>]*title="'.preg_quote(e($topic->label()), '#').'")(?=[^>]*class="[^"]*\[&(?:amp;)?_\[data-content\]\]:truncate)[^>]*>#');
    }

    // Collapsed away from Help.
    expect($html)->toMatch('/<ui-disclosure(?=[^>]*data-help-nav)[^>]*>/')
        ->not->toMatch(HELP_OPEN);
});

test('REQ-22-01 on a help page the group is open with that topic current', function () {
    $html = $this->actingAs($this->owner)->get(route('student.help', HelpTopic::Payment->value))->assertOk()->getContent();

    // The current link carries a data-current attribute (the class names only mention it).
    $current = fn (HelpTopic $topic): string => '#<a(?=[^>]*href="'.preg_quote(route('student.help', $topic->value), '#').'")(?=[^>]*\sdata-current(?=[\s>="]))[^>]*>#';

    expect($html)->toMatch(HELP_OPEN)
        ->and($html)->toMatch($current(HelpTopic::Payment))
        ->and($html)->not->toMatch($current(HelpTopic::Tracking));
});

test('REQ-22-01 staff do not see Help', function () {
    foreach ([User::factory()->registrar()->create(), User::factory()->admin()->create()] as $staff) {
        $this->actingAs($staff)->get(route('records'))->assertOk()->assertDontSeeHtml('data-help-nav');
    }
});

test('REQ-22-02 each topic has its page of collapsible questions; /student/help opens the first', function () {
    $this->actingAs($this->owner)->get('/student/help')->assertRedirect('/student/help/'.HelpTopic::first()->value);

    foreach (HelpTopic::cases() as $topic) {
        $html = $this->actingAs($this->owner)->get(route('student.help', $topic->value))
            ->assertOk()
            ->assertSee($topic->label())
            ->assertSee($topic->description())
            ->getContent();

        // Native disclosure: closed at first, keyboard and screen-reader friendly, pointer cursor.
        expect($html)->toContain('data-help-topic="'.$topic->value.'"')
            ->toMatch('/<details[^>]*data-faq-item>\s*<summary class="[^"]*cursor-pointer/')
            ->not->toMatch('/<details[^>]*\sopen[^>]*data-faq-item/');
    }
});

test('REQ-22-02 an unknown topic is 404 and staff get 403', function () {
    $this->actingAs($this->owner)->get('/student/help/nothing-here')->assertNotFound();
    $this->actingAs(User::factory()->registrar()->create())->get(route('student.help', HelpTopic::first()->value))->assertForbidden();
});

// ── REQ-22-03..05: content ──────────────────────────────────────────────────────────────────

test('REQ-22-03 each topic answers its questions', function (HelpTopic $topic, array $questions) {
    $html = $this->actingAs($this->owner)->get(route('student.help', $topic->value))->assertOk()->getContent();

    foreach ($questions as $question) {
        expect($html)->toContain(e($question));
    }
})->with([
    'getting started' => [HelpTopic::GettingStarted, [
        'What can I do here?', 'Who can use it?', 'How do I create an account?', "I didn't get the email verification code.",
        'I forgot my password.', 'Can I change my email address?', 'It says my account has been deactivated.', 'Can I delete my account?',
    ]],
    'details and ID' => [HelpTopic::DetailsAndId, [
        'Do I have to give my details and upload my ID on every request?', 'How do I know whether my details are verified?',
        'Can I still change my details after they are verified?', 'I updated my details. Why does my earlier request still show the old ones?',
        'Which IDs and files are accepted?', 'How do I use a different ID?', 'What if my ID is not accepted?',
        'Who can see my ID and the files I upload?', 'Why do I tick the data privacy box every time?',
    ]],
    'requesting' => [HelpTopic::Requesting, [
        'How do I request a credential?', "Where do I see a credential's fee, processing time and requirements?", "My purpose isn't in the list.",
        'Why does it ask for a receiving school?', 'Do I have to upload the documentary requirements?', 'How do I request more than one copy?',
        'What happens after I submit?', 'It says “Visit the Office of the Registrar”. What do I do?', 'Can I change a request after submitting it?', 'Can I cancel a request?',
    ]],
    'payment' => [HelpTopic::Payment, [
        'When do I pay?', 'How do I pay?', 'How much do I pay?', 'What happens after I upload my proof of payment?',
        'My proof of payment was not accepted.', 'My credential is free. Do I need to do anything?',
    ]],
    'tracking' => [HelpTopic::Tracking, [
        'Where do I see my requests?', 'What does each status mean?', 'When does the processing period start? When will my credential be ready?',
        'Who has my document right now?', 'How will I know when something changes?', 'My request is overdue. What happens now?',
    ]],
    'representatives and release' => [HelpTopic::RepresentativesAndRelease, [
        'How do I get my credential?', 'Can someone else claim it or go to the office for me?', 'What is an SPA?',
        'When can I name a representative?', 'What does my representative bring?', 'Can my representative use my account?',
    ]],
]);

test('REQ-22-03 the details topic explains giving and verifying details once, and editing them again', function () {
    $html = $this->actingAs($this->owner)->get(route('student.help', HelpTopic::DetailsAndId->value))->getContent();

    expect($html)->toContain('You fill in your personal information, academic information and valid ID once')
        ->toContain('verifies them only once')
        ->toContain('Verified')->not->toContain('For verification')
        ->toContain('loses its <strong>Verified</strong> badge and is checked again on your next request');
});

test('REQ-22-04 answers link to the pages they name and read the same settings as the forms', function () {
    $get = fn (HelpTopic $topic): string => $this->actingAs($this->owner)->get(route('student.help', $topic->value))->getContent();

    $details = $get(HelpTopic::DetailsAndId);
    expect($details)->toContain('href="'.route('student.requests.create').'"')
        ->toContain('href="'.route('settings.profile').'"');

    foreach (IdentityDocument::ID_TYPES as $idType) {
        expect($details)->toContain('<li>'.e($idType).'</li>');
    }

    config(['uphsl.payment_url' => 'https://pay.example.test/portal']);
    expect($get(HelpTopic::Payment))->toContain('href="https://pay.example.test/portal"');

    $tracking = $get(HelpTopic::Tracking);
    expect($tracking)->toContain('href="'.route('student.dashboard').'"')
        ->toContain('href="'.route('settings.notifications').'"');

    expect($get(HelpTopic::Requesting))->toContain('up to '.RequestDocument::MAX_PER_REQUIREMENT.' files per requirement');
});

test('REQ-22-05 every help page ends with where to ask anything else', function () {
    foreach (HelpTopic::cases() as $topic) {
        $html = $this->actingAs($this->owner)->get(route('student.help', $topic->value))->getContent();

        expect(Str::after($html, 'data-help-topic='))->toContain('data-help-contact')
            ->and($html)->toContain('Still have a question? Ask the Office of the Registrar');
    }
});
