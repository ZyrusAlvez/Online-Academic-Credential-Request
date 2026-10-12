# 08 — Turnaround Prediction · Design

## Training (offline, `ml/`)

Python 3.11, scikit-learn. Training never touches the production database.

1. `ml/generate_dataset.py` simulates routed requests over the seeded credential types and
   workflows (per-office signing delay, holds, workload and peak-month effects) and writes
   `ml/data/raw_requests.csv`. **The data is simulated** (Chapter 1, 1.5) until the pilot has enough
   real requests; `ml/export_dataset.sql` produces the same columns from a real database.
2. `ml/train.py` validates and cleans the data, builds features, splits 80/20, compares a simple
   linear regression on processing days (baseline) and the average (reference) with multiple linear
   regression on all features (OLS and Ridge, 5-fold CV over alpha),
   evaluates MAE, RMSE and R² on the test set, draws the charts and writes the model JSON.

## Model file

`resources/ml/turnaround-model.json` (versioned in git):

```json
{ "version": "lr-2026-10-11", "target": "turnaround_days", "intercept": 1.23,
  "coefficients": { "has_college": 2.1, "...": 0.0 }, "residual_std": 1.4,
  "peak_months": [6, 7, 8], "metrics": { "mae": 1.1, "rmse": 1.5, "r2": 0.7 },
  "trained_on": "simulated", "trained_at": "2026-10-11" }
```

Coefficients are on the raw (unscaled) feature scale, so PHP needs only a weighted sum.

`residual_quantiles` holds the 0th to 100th percentiles of the model's errors (actual minus predicted)
from 5-fold cross-validation on the training set. The likely range is predicted + q10 to predicted + q90
(80%), the late probability is the share of errors larger than (days to due - predicted), and the
confidence is the late probability when the request is flagged at risk, otherwise one minus it.

## Features (all known at routing)

| Feature | Source |
|---|---|
| processing_days | credential type |
| requires_payment | request snapshot (0/1) |
| has_library, has_finance, has_student_affairs, has_college | workflow includes LIB / FIN / SAS / Student's College step (0/1) |
| copies | request |
| is_alumni | request student_status (0/1) |
| open_workload | in-progress requests at routing time |
| is_peak_month | routing month in `peak_months` (0/1) |

Office presence flags (instead of one-hot credential type) keep the model valid when the
registrar adds or edits credential types. A step count is left out on purpose: it equals the
office flags plus the Registrar step, so it would make the features perfectly collinear.

## Data

New table `turnaround_predictions` (`App\Models\TurnaroundPrediction`), insert-only:

| Column | Type | Notes |
|---|---|---|
| credential_request_id | FK restrict, unique | one prediction per request |
| model_version | string(40) | |
| predicted_days | decimal(6,2) | |
| predicted_ready_at | timestamp | routed_at + predicted_days |
| due_at | timestamp | copy of the request's due date |
| at_risk | boolean | predicted_ready_at > due_at; index |
| features | json | the inputs used |
| created_at | timestamp | |

## Code

- `App\Services\TurnaroundPredictor`: loads and validates the model JSON (cached per process),
  `features(CredentialType, Department college, int copies, StudentStatus, Carbon at)` and
  `predict(array features): float`. `isAvailable()` is false when the file is missing or invalid.
- `App\Listeners\RecordTurnaroundPrediction` on `RequestEventRecorded` (type `routed`, after commit).
  Prediction is not a status change, so it stays out of `RequestWorkflow`; a failure is logged
  and never blocks routing.
- API: Laravel Sanctum personal access tokens. `routes/api.php`, `Api\TurnaroundPredictionController`,
  `PredictTurnaroundRequest` form request, `TurnaroundPredictionResource`; middleware
  `auth:sanctum`, `role:registrar`, `throttle:60,1`. `php artisan app:api-token {email}` issues a
  token for an active registrar.
- Dashboard: `Registrar\Prediction` Livewire page (`/registrar/predictions`) with the predictor
  form, at-risk list and a predicted-versus-actual chart (inline SVG, no chart library).
