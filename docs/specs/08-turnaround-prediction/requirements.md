# 08 — Turnaround Prediction · Requirements

Thesis link: Chapter 1, Objective 4 (predictive deadline monitoring) and Objective 5 (evaluation
against average-based reporting).

## User stories

- **US-08-1** As the registrar, I want each routed request to get a predicted turnaround, so that
  I can see requests likely to miss their due date before they do.
- **US-08-2** As the registrar, I want to try a request profile and see its predicted turnaround,
  so that I can plan processing days per credential type.
- **US-08-3** As another system (e.g. a future student portal), I want to ask for a prediction over
  an API, so that I do not have to reimplement the model.

## Acceptance criteria

### Model
- **REQ-08-01** THE SYSTEM SHALL predict turnaround days (routing to ready for release) with a
  linear regression model whose coefficients are loaded from `resources/ml/turnaround-model.json`.
- **REQ-08-02** THE SYSTEM SHALL compute the model's inputs from data available at routing time
  only: processing days, payment required, whether the workflow
  includes the Library, Cashier, Student Affairs and the student's college, copies, student status,
  open in-progress requests, and whether the routing month is a peak month.
- **REQ-08-03** THE SYSTEM SHALL never predict fewer than 0 days.

### Stored predictions
- **REQ-08-04** WHEN a request is routed THE SYSTEM SHALL store one prediction for it (predicted
  days, predicted ready date, at-risk flag, model version and the input values).
- **REQ-08-05** THE SYSTEM SHALL mark a prediction at risk WHEN its predicted ready date is after the
  request's due date.
- **REQ-08-06** IF the model file is missing or invalid THEN THE SYSTEM SHALL still route the request
  and skip the prediction.

### Confidence
- **REQ-08-11** THE SYSTEM SHALL give each prediction an 80% likely range (low and high days), the
  probability that the request will be late, and a confidence in its at-risk call, all derived from the
  model's cross-validation errors stored in the model file.

### API
- **REQ-08-07** THE SYSTEM SHALL provide `POST /api/v1/predictions/turnaround` that returns predicted
  days, likely range, predicted ready date, due date, at-risk flag, late probability, confidence and
  model version as JSON.
- **REQ-08-08** THE SYSTEM SHALL require a registrar API token for the API, rate-limit it, and return
  401 without a token, 403 for other roles and 422 with field errors for invalid input.

### Dashboard
- **REQ-08-09** THE SYSTEM SHALL show the registrar a predictor panel: choose a credential type,
  copies, student status and number of open requests, and see the predicted days, due date and at-risk flag.
- **REQ-08-10** THE SYSTEM SHALL show the registrar the in-progress requests predicted to be at risk
  and a chart of predicted versus actual turnaround for completed requests.
