<?php

use App\Enums\StudentStatus;
use App\Livewire\Registrar\Predictions;
use App\Models\CredentialRequest;
use App\Models\TurnaroundPrediction;
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Services\TurnaroundPredictor;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = organisation();
    $this->workflow = app(RequestWorkflow::class);
    $this->registrar = User::factory()->registrar()->create();
    $this->student = User::factory()->student()->create();
});

/**
 * Swap in a model file with the given intercept (all coefficients zero unless given).
 *
 * @param  array<string, float>  $coefficients
 */
function useModel(float $intercept, array $coefficients = []): TurnaroundPredictor
{
    $path = tempnam(sys_get_temp_dir(), 'model');
    file_put_contents($path, json_encode([
        'version' => 'test',
        'intercept' => $intercept,
        'coefficients' => array_merge(array_fill_keys(TurnaroundPredictor::FEATURES, 0), $coefficients),
        'peak_months' => [6, 7, 8],
        'metrics' => ['mae' => 1.0, 'rmse' => 1.5, 'r2' => 0.5],
        'trained_on' => 'simulated',
    ]));

    $predictor = new TurnaroundPredictor($path);
    app()->instance(TurnaroundPredictor::class, $predictor);

    return $predictor;
}

function routeFreeRequest(): CredentialRequest
{
    $request = submitRequest(test()->student, test()->org->free, test()->org->ccs);
    test()->workflow->approve($request, test()->registrar);

    return $request->fresh();
}

test('REQ-08-01 the prediction is the intercept plus the weighted sum from the model file', function () {
    $predictor = app(TurnaroundPredictor::class);
    $model = json_decode((string) file_get_contents(resource_path('ml/turnaround-model.json')), true);
    $features = array_combine(TurnaroundPredictor::FEATURES, [10, 1, 1, 1, 0, 1, 2, 1, 20, 0]);

    $expected = $model['intercept'];
    foreach ($features as $name => $value) {
        $expected += $model['coefficients'][$name] * $value;
    }

    expect($predictor->isAvailable())->toBeTrue()
        ->and($predictor->version())->toBe($model['version'])
        ->and($predictor->predict($features))->toBe(round(max(0, $expected), 2));
});

test('REQ-08-02 features use only what is known at routing', function () {
    $this->org->tor->update(['processing_days' => 10]);
    $predictor = useModel(1.0);

    $features = $predictor->features($this->org->tor, 2, StudentStatus::Alumni, Carbon::parse('2026-07-15 09:00'), 12);

    expect($features)->toBe([
        'processing_days' => 10,
        'requires_payment' => 1,
        'has_library' => 1,
        'has_finance' => 1,
        'has_student_affairs' => 0,
        'has_college' => 1,
        'copies' => 2,
        'is_alumni' => 1,
        'open_workload' => 12,
        'is_peak_month' => 1,
    ]);

    $free = $predictor->features($this->org->free, 1, StudentStatus::Current, Carbon::parse('2026-10-12 09:00'), 0);
    expect($free['has_library'] + $free['has_college'] + $free['is_alumni'] + $free['is_peak_month'])->toBe(0);
});

test('REQ-08-03 predictions are never below zero', function () {
    $predictor = useModel(-50.0);

    expect($predictor->predict(array_fill_keys(TurnaroundPredictor::FEATURES, 1)))->toBe(0.0);
});

test('REQ-08-04 routing stores one prediction with its inputs', function () {
    useModel(2.0, ['copies' => 0.5]);

    $request = routeFreeRequest();
    $prediction = $request->prediction;

    expect($prediction)->toBeInstanceOf(TurnaroundPrediction::class)
        ->and($prediction->model_version)->toBe('test')
        ->and((float) $prediction->predicted_days)->toBe(2.5)
        ->and($prediction->predicted_ready_at->toDateTimeString())->toBe($request->routed_at->copy()->addHours(60)->toDateTimeString())
        ->and($prediction->due_at->toDateTimeString())->toBe($request->due_at->toDateTimeString())
        ->and($prediction->features['copies'])->toBe(1)
        ->and(TurnaroundPrediction::count())->toBe(1);
});

test('REQ-08-05 a prediction is at risk when its ready date is after the due date', function () {
    $this->org->free->update(['processing_days' => 3]);

    useModel(30.0);
    expect(routeFreeRequest()->prediction->at_risk)->toBeTrue();

    useModel(0.5);
    expect(routeFreeRequest()->prediction->at_risk)->toBeFalse();
});

test('REQ-08-06 routing still works without a model and skips the prediction', function () {
    app()->instance(TurnaroundPredictor::class, new TurnaroundPredictor(storage_path('missing-model.json')));

    $request = routeFreeRequest();

    expect($request->status->value)->toBe('in_progress')
        ->and($request->prediction)->toBeNull();
});

test('REQ-08-07 the API returns a prediction as JSON', function () {
    useModel(4.0);
    Sanctum::actingAs($this->registrar);
    Carbon::setTestNow('2026-10-12 09:00:00');

    $this->postJson(route('api.v1.predictions.turnaround'), [
        'credential_type_id' => $this->org->tor->id,
        'copies' => 1,
        'student_status' => 'current',
        'open_workload' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.predicted_days', 4)
        ->assertJsonPath('data.credential_type.name', 'Transcript of Records')
        ->assertJsonPath('data.model_version', 'test')
        ->assertJsonPath('data.at_risk', false)
        ->assertJsonPath('data.features.open_workload', 5)
        ->assertJsonStructure(['data' => ['predicted_days', 'routed_at', 'predicted_ready_at', 'due_at', 'at_risk', 'features', 'model_version']]);

    Carbon::setTestNow();
});

test('REQ-08-08 the API needs an active registrar token and valid input', function () {
    $url = route('api.v1.predictions.turnaround');
    $valid = ['credential_type_id' => $this->org->tor->id, 'copies' => 1, 'student_status' => 'alumni'];

    $this->postJson($url, $valid)->assertUnauthorized();

    Sanctum::actingAs($this->student);
    $this->postJson($url, $valid)->assertForbidden();

    $inactive = User::factory()->registrar()->create();
    $inactive->forceFill(['is_active' => false])->save();
    Sanctum::actingAs($inactive);
    $this->postJson($url, $valid)->assertForbidden();

    Sanctum::actingAs($this->registrar);
    $this->postJson($url, ['credential_type_id' => 999, 'copies' => 25, 'student_status' => 'visitor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credential_type_id', 'copies', 'student_status']);

    $this->postJson($url, $valid)->assertOk()->assertHeader('X-RateLimit-Limit', 60);
});

test('REQ-08-09 the registrar can try a request profile and see why', function () {
    useModel(1.0, ['has_college' => 2.0, 'copies' => 0.5]);

    Livewire::actingAs($this->registrar)->test(Predictions::class)
        ->set('credentialTypeId', $this->org->tor->id)
        ->set('copies', 2)
        ->set('studentStatus', 'alumni')
        ->set('openWorkload', 3)
        ->call('predict')
        ->assertHasNoErrors()
        ->assertSet('result.days', 4.0)
        ->assertSee('Predicted turnaround')
        ->assertSee('College step')
        ->assertSee('On track');

    Livewire::actingAs($this->registrar)->test(Predictions::class)
        ->set('copies', 0)
        ->call('predict')
        ->assertHasErrors(['copies']);

    $this->actingAs($this->student)->get(route('registrar.predictions'))->assertForbidden();
});

test('REQ-08-10 the page lists at-risk requests and compares predicted with actual', function () {
    $this->org->free->update(['processing_days' => 1]);
    useModel(20.0);
    $late = routeFreeRequest();

    $this->actingAs($this->registrar)->get(route('registrar.predictions'))
        ->assertOk()
        ->assertSee($late->reference_no)
        ->assertSee('No predicted request has reached ready for release yet.');

    Carbon::setTestNow(now()->addDays(2));
    $this->workflow->sign($late->currentStep()->firstOrFail(), $this->registrar);
    Carbon::setTestNow();

    $this->actingAs($this->registrar)->get(route('registrar.predictions'))
        ->assertOk()
        ->assertSee('Scatter chart of 1 requests', false)
        ->assertSee('No in-progress request is predicted to be late.');
});
