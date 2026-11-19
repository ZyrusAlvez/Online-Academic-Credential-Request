<?php

use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\Sex;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Registrar\WalkIn;
use App\Livewire\Student\Profile;
use App\Livewire\Student\Requests\Create;
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Support\VerifiedDetails;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| Spec 28: the requester's sex and the maiden name captions, and the wording of the statuses and the landing page.
*/

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
    $this->owner = requester($this->org->ccs);
});

/** Settings → Profile for the owner, with the given personal details set, then reviewed. */
function reviewSex(array $fields): Testable
{
    $page = Livewire::actingAs(test()->owner)->test(Profile::class);

    foreach ($fields as $field => $value) {
        $page->set("form.{$field}", $value);
    }

    return $page->call('review');
}

test('REQ-28-01 the landing page asks for the proof of payment, not a receipt', function () {
    $this->get('/')->assertOk()
        ->assertSee('Pay the fee online and upload your proof of payment.')
        ->assertDontSee('receipt');
});

test('REQ-28-02 the statuses read Currently Enrolled, Graduated and Former student', function () {
    expect(array_map(fn (AcademicStatus $status) => $status->label(), AcademicStatus::cases()))
        ->toBe(['Currently Enrolled', 'Graduated', 'Former student']);

    Livewire::actingAs($this->owner)->test(Profile::class)
        ->assertSee('Currently Enrolled')->assertSee('Graduated')->assertDontSee('Current student');
});

test('REQ-28-03 New Request, Settings → Profile and Walk-in Request ask for the sex, and it is required', function () {
    Livewire::actingAs($this->owner)->test(Create::class)->assertSeeInOrder(['Personal Information', 'Sex', 'Male', 'Female']);
    Livewire::actingAs($this->registrar)->test(WalkIn::class)->assertSeeInOrder(['Personal Information', 'Sex', 'Male', 'Female']);

    reviewSex(['sex' => ''])->assertHasErrors(['form.sex' => 'required']);
    reviewSex(['sex' => 'other'])->assertHasErrors(['form.sex']);
    reviewSex(['sex' => 'female'])->assertHasNoErrors();
});

test('REQ-28-04 a woman sees "Maiden Name" under the middle and last name, with no separate field', function () {
    $html = Livewire::actingAs($this->owner)->test(Profile::class)
        ->set('form.sex', 'male')->assertDontSeeHtml('data-maiden-caption')
        ->set('form.sex', 'female')->assertSeeInOrder(['Middle name (optional)', 'Maiden Name', 'Last name', 'Maiden Name', 'Sex'])
        ->assertDontSee('Maiden name (optional)')
        ->html();

    expect(substr_count($html, 'data-maiden-caption'))->toBe(2);

    Livewire::actingAs($this->registrar)->test(WalkIn::class)
        ->set('profile.sex', 'female')->assertSeeHtml('data-maiden-caption');

    expect(Schema::hasColumn('users', 'maiden_name'))->toBeFalse()
        ->and(Schema::hasColumn('credential_requests', 'maiden_name'))->toBeFalse();
});

test('REQ-28-05 the summary shows the sex', function () {
    reviewSex(['sex' => 'female'])->assertSeeInOrder(['Sex', 'Female']);
    reviewSex(['sex' => 'male'])->assertSeeInOrder(['Sex', 'Male']);
});

test('REQ-28-06 each request keeps its own copy of the sex, shown to the registrar', function () {
    $this->owner->update(['sex' => Sex::Female]);
    $request = submitRequest($this->owner, $this->org->tor);

    $this->owner->update(['sex' => Sex::Male]);

    expect($request->fresh())->sex->toBe(Sex::Female);

    workflow()->startEvaluation($request, $this->registrar);
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh()])
        ->assertSeeInOrder(['Sex', 'Female']);
});

test('REQ-28-06 a walk-in request keeps the sex the registrar entered', function () {
    $request = workflow()->submitWalkIn($this->registrar, ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null], [
        'first_name' => 'Ana', 'middle_name' => 'Cruz', 'last_name' => 'Reyes', 'sex' => 'female',
        'email' => 'ana.reyes@example.com', 'contact_number' => '09171234567', 'academic_status' => 'graduate',
        'college_id' => $this->org->ccs->id, 'program' => 'BS Computer Science', 'student_number' => null,
        'attended_from' => 2018, 'attended_to' => 2022,
    ]);

    expect($request->fresh())->sex->toBe(Sex::Female);
});

test('REQ-28-07 changing the sex sends the personal information back for verification', function () {
    $this->owner->update(['sex' => Sex::Female]);
    evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $verified = fn (): bool => VerifiedDetails::current($this->owner->fresh(), CheckItem::Identity) !== null;

    expect($verified())->toBeTrue();

    $this->owner->update(['sex' => Sex::Male]);
    expect($verified())->toBeFalse()
        ->and(VerifiedDetails::sections($this->owner->fresh())['personal'])->toBeFalse();
});
