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
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| Spec 28: the requester's sex and maiden name, and the wording of the statuses and the landing page.
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

test('REQ-28-04 the maiden name is asked only of a woman, and never saved for a man', function () {
    Livewire::actingAs($this->owner)->test(Profile::class)
        ->set('form.sex', 'male')->assertDontSeeHtml('data-maiden-name')
        ->set('form.sex', 'female')->assertSeeHtml('data-maiden-name')->assertSee('Maiden name (optional)');

    reviewSex(['sex' => 'female', 'maiden_name' => 'Bautista'])->set('form.privacy_consent', true)->call('save')->assertHasNoErrors();
    expect($this->owner->fresh())->sex->toBe(Sex::Female)->maiden_name->toBe('Bautista');

    reviewSex(['sex' => 'male', 'maiden_name' => 'Bautista'])->set('form.privacy_consent', true)->call('save')->assertHasNoErrors();
    expect($this->owner->fresh())->sex->toBe(Sex::Male)->maiden_name->toBeNull();
});

test('REQ-28-05 the summary shows the sex and, for a woman, the maiden name', function () {
    reviewSex(['sex' => 'female', 'maiden_name' => 'Bautista'])->assertSeeInOrder(['Sex', 'Female', 'Maiden name', 'Bautista']);
    reviewSex(['sex' => 'female', 'maiden_name' => ''])->assertSeeInOrder(['Maiden name', 'Not provided']);
    reviewSex(['sex' => 'male'])->assertSeeInOrder(['Sex', 'Male'])->assertDontSee('Maiden name');
});

test('REQ-28-06 each request keeps its own copy of the sex and maiden name, shown to the registrar', function () {
    $this->owner->update(['sex' => Sex::Female, 'maiden_name' => 'Bautista']);
    $request = submitRequest($this->owner, $this->org->tor);

    $this->owner->update(['maiden_name' => 'Changed']);

    expect($request->fresh())->sex->toBe(Sex::Female)->maiden_name->toBe('Bautista');

    workflow()->startEvaluation($request, $this->registrar);
    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $request->fresh()])
        ->assertSeeInOrder(['Sex', 'Female', 'Maiden name', 'Bautista']);
});

test('REQ-28-06 a walk-in request keeps the sex and maiden name the registrar entered', function () {
    $request = workflow()->submitWalkIn($this->registrar, ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null], [
        'first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Reyes', 'sex' => 'female', 'maiden_name' => 'Cruz',
        'email' => 'ana.reyes@example.com', 'contact_number' => '09171234567', 'academic_status' => 'graduate',
        'college_id' => $this->org->ccs->id, 'program' => 'BS Computer Science', 'student_number' => null,
        'attended_from' => 2018, 'attended_to' => 2022,
    ]);

    expect($request->fresh())->sex->toBe(Sex::Female)->maiden_name->toBe('Cruz');
});

test('REQ-28-07 changing the sex or maiden name sends the personal information back for verification', function () {
    $this->owner->update(['sex' => Sex::Female, 'maiden_name' => null]);
    evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $verified = fn (): bool => VerifiedDetails::current($this->owner->fresh(), CheckItem::Identity) !== null;

    expect($verified())->toBeTrue();

    $this->owner->update(['maiden_name' => 'Bautista']);
    expect($verified())->toBeFalse()
        ->and(VerifiedDetails::sections($this->owner->fresh())['personal'])->toBeFalse();

    $this->owner->update(['maiden_name' => null, 'sex' => Sex::Male]);
    expect($verified())->toBeFalse();
});
