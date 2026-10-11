"""Train the turnaround model: validate, engineer features, compare with a baseline, export.

Pipeline (each stage prints what it did):

    raw_requests.csv -> validation -> clean data -> features -> 80/20 split
        -> baseline (mean turnaround) vs linear regression (OLS, Ridge with 5-fold CV)
        -> evaluation (MAE, RMSE, R2, deadline-breach detection) -> charts -> model JSON

Outputs:
    ml/output/validation_report.csv   rows that failed a rule, with the reason
    ml/data/features.csv              the final dataset used for training
    ml/output/metrics.json            every score reported below
    ml/output/*.png                   charts for the documentation
    resources/ml/turnaround-model.json  the model the Laravel app loads

Usage: python ml/train.py
"""

from __future__ import annotations

import json
from datetime import date, datetime, timedelta
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402
from sklearn.dummy import DummyRegressor  # noqa: E402
from sklearn.inspection import permutation_importance  # noqa: E402
from sklearn.linear_model import LinearRegression, Ridge  # noqa: E402
from sklearn.metrics import (  # noqa: E402
    f1_score,
    mean_absolute_error,
    mean_squared_error,
    precision_score,
    r2_score,
    recall_score,
)
from sklearn.model_selection import GridSearchCV, KFold, cross_validate, train_test_split  # noqa: E402
from sklearn.pipeline import make_pipeline  # noqa: E402
from sklearn.preprocessing import StandardScaler  # noqa: E402

ROOT = Path(__file__).resolve().parent
RAW = ROOT / "data" / "raw_requests.csv"
FEATURES_CSV = ROOT / "data" / "features.csv"
OUT = ROOT / "output"
MODEL_JSON = ROOT.parent / "resources" / "ml" / "turnaround-model.json"

SEED = 42
PEAK_MONTHS = [6, 7, 8]
REQUIRED = ["reference_no", "credential_type", "college", "student_status", "copies", "requires_payment",
            "processing_days", "workflow", "open_workload", "routed_at", "ready_at"]
FEATURES = ["processing_days", "requires_payment", "has_library", "has_finance",
            "has_student_affairs", "has_college", "copies", "is_alumni", "open_workload", "is_peak_month"]
LABELS = {
    "processing_days": "Processing days", "requires_payment": "Paid credential",
    "has_library": "Library step", "has_finance": "Cashier step", "has_student_affairs": "Student Affairs step",
    "has_college": "College step", "copies": "Copies", "is_alumni": "Alumni requester",
    "open_workload": "Open requests at routing", "is_peak_month": "Peak month",
}

BRAND = "#012265"
MUTED = "#86A7DD"
INK = "#1F2937"


def section(title: str) -> None:
    print(f"\n=== {title} ===")


# ---------------------------------------------------------------- 1. validation (DES-04)

def validate(raw: pd.DataFrame) -> tuple[pd.DataFrame, pd.DataFrame]:
    """Apply the data quality rules. Returns (clean rows, rejected rows with a reason)."""
    missing_columns = [c for c in REQUIRED if c not in raw.columns]
    if missing_columns:  # Rule 1, schema: the file must have the expected structure
        raise SystemExit(f"Schema check failed, missing columns: {missing_columns}")

    df = raw.copy()
    df["reason"] = ""

    def reject(mask: pd.Series, reason: str) -> None:
        df.loc[mask & (df["reason"] == ""), "reason"] = reason

    text = df[REQUIRED].astype(str).apply(lambda s: s.str.strip())
    reject(text.drop(columns=["ready_at"]).isin(["", "nan"]).any(axis=1), "required field is empty")       # Rule 2
    reject(text["ready_at"].isin(["", "nan"]), "not finished yet (no ready_at)")                           # Rule 3, missing target

    copies = pd.to_numeric(df["copies"], errors="coerce")
    workload = pd.to_numeric(df["open_workload"], errors="coerce")
    reject(copies.isna() | workload.isna(), "copies or open_workload is not a number")                     # Rule 4, type
    reject(~copies.between(1, 10) | (workload < 0), "copies outside 1-10 or negative workload")            # Rule 5, range
    reject(~df["student_status"].isin(["current", "alumni"]), "student_status is not current or alumni")   # Rule 6, allowed values

    routed = pd.to_datetime(df["routed_at"], errors="coerce")
    ready = pd.to_datetime(df["ready_at"], errors="coerce")
    reject(routed.isna() | ((ready <= routed) & ready.notna()), "ready_at is not after routed_at")         # Rule 7, consistency
    reject(df.duplicated(subset=["reference_no"], keep="first"), "duplicate reference_no")                 # Rule 8, duplicates

    rejected = df[df["reason"] != ""]
    clean = df[df["reason"] == ""].drop(columns="reason").copy()
    clean["copies"] = clean["copies"].astype(int)
    clean["open_workload"] = clean["open_workload"].astype(int)
    return clean, rejected


# ---------------------------------------------------------------- 2. feature engineering (DES-03)

def add_working_days(start: datetime, days: int) -> datetime:
    current = start
    while days > 0:
        current += timedelta(days=1)
        if current.weekday() < 5:
            days -= 1
    return current


def engineer(clean: pd.DataFrame) -> pd.DataFrame:
    df = clean.copy()
    routed = pd.to_datetime(df["routed_at"])
    ready = pd.to_datetime(df["ready_at"])
    offices = df["workflow"].str.split(">")

    # Office presence flags. A step count is not used: it equals the flags plus the
    # Registrar step, which would make the features perfectly collinear.
    df["has_library"] = offices.apply(lambda o: int("LIB" in o))
    df["has_finance"] = offices.apply(lambda o: int("FIN" in o))
    df["has_student_affairs"] = offices.apply(lambda o: int("SAS" in o))
    df["has_college"] = offices.apply(lambda o: int("COLLEGE" in o))
    df["is_alumni"] = (df["student_status"] == "alumni").astype(int)
    df["is_peak_month"] = routed.dt.month.isin(PEAK_MONTHS).astype(int)
    df["requires_payment"] = df["requires_payment"].astype(int)

    # Target: calendar days from routing to ready for release (same as DES-03 turnaround_days).
    df["turnaround_days"] = ((ready - routed).dt.total_seconds() / 86400).round(2)
    # Due date the system sets on routing (weekdays only), used to judge deadline breaches.
    due = [add_working_days(r.to_pydatetime(), int(p)) for r, p in zip(routed, df["processing_days"])]
    df["days_to_due"] = ((pd.Series(due, index=df.index) - routed).dt.total_seconds() / 86400).round(2)
    df["breached"] = (df["turnaround_days"] > df["days_to_due"]).astype(int)
    return df


def flag_outliers(df: pd.DataFrame) -> pd.Series:
    """Rule 9, outliers: IQR fences on turnaround. Flagged and reported, not removed;
    long holds are real events the model must learn from."""
    q1, q3 = df["turnaround_days"].quantile([0.25, 0.75])
    fence = q3 + 1.5 * (q3 - q1)
    return df["turnaround_days"] > fence


# ---------------------------------------------------------------- 3. modelling (DEV-01, DEV-02)

def scores(y_true: np.ndarray, y_pred: np.ndarray) -> dict[str, float]:
    return {
        "mae": round(float(mean_absolute_error(y_true, y_pred)), 3),
        "rmse": round(float(np.sqrt(mean_squared_error(y_true, y_pred))), 3),
        "r2": round(float(r2_score(y_true, y_pred)), 3),
    }


def breach_scores(actual: np.ndarray, predicted_days: np.ndarray, days_to_due: np.ndarray) -> dict[str, float]:
    flagged = (predicted_days > days_to_due).astype(int)
    return {
        "precision": round(float(precision_score(actual, flagged, zero_division=0)), 3),
        "recall": round(float(recall_score(actual, flagged, zero_division=0)), 3),
        "f1": round(float(f1_score(actual, flagged, zero_division=0)), 3),
        "flagged": int(flagged.sum()),
        "actual": int(actual.sum()),
    }


def raw_coefficients(model) -> tuple[float, dict[str, float]]:
    """Fold the scaler into the weights so the app needs only intercept + sum(w * x)."""
    scaler = model.named_steps["standardscaler"]
    reg = model.named_steps[[k for k in model.named_steps if k != "standardscaler"][0]]
    weights = reg.coef_ / scaler.scale_
    intercept = float(reg.intercept_ - np.sum(weights * scaler.mean_))
    return intercept, {f: float(w) for f, w in zip(FEATURES, weights)}


# ---------------------------------------------------------------- 4. charts (DEV-03)

def style(ax, title: str) -> None:
    ax.set_title(title, loc="left", fontsize=12, color=INK, fontweight="bold")
    ax.spines[["top", "right"]].set_visible(False)
    ax.tick_params(colors=INK)


def chart_predicted_vs_actual(y_test, y_pred, path: Path) -> None:
    fig, ax = plt.subplots(figsize=(6.4, 5.2))
    ax.scatter(y_test, y_pred, s=14, alpha=0.55, color=BRAND, edgecolors="none")
    lim = [0, max(y_test.max(), y_pred.max()) * 1.05]
    ax.plot(lim, lim, color=MUTED, linewidth=1.5, label="Perfect prediction")
    ax.set_xlim(lim)
    ax.set_ylim(lim)
    ax.set_xlabel("Actual turnaround (days)")
    ax.set_ylabel("Predicted turnaround (days)")
    ax.legend(frameon=False)
    style(ax, "Predicted vs actual turnaround (test set)")
    fig.tight_layout()
    fig.savefig(path, dpi=160)
    plt.close(fig)


def chart_residuals(y_test, y_pred, path: Path) -> None:
    fig, ax = plt.subplots(figsize=(6.4, 4.2))
    ax.hist(y_test - y_pred, bins=40, color=BRAND, alpha=0.85)
    ax.axvline(0, color=MUTED, linewidth=1.5)
    ax.set_xlabel("Actual minus predicted (days)")
    ax.set_ylabel("Requests")
    style(ax, "Prediction errors (test set)")
    fig.tight_layout()
    fig.savefig(path, dpi=160)
    plt.close(fig)


def chart_bars(values: dict[str, float], title: str, xlabel: str, path: Path, errors: dict[str, float] | None = None) -> None:
    items = sorted(values.items(), key=lambda kv: abs(kv[1]))
    names = [LABELS.get(k, k) for k, _ in items]
    vals = [v for _, v in items]
    err = [errors[k] for k, _ in items] if errors else None
    fig, ax = plt.subplots(figsize=(7.2, 4.8))
    ax.barh(names, vals, xerr=err, color=[BRAND if v >= 0 else MUTED for v in vals], capsize=3)
    ax.axvline(0, color=INK, linewidth=0.8)
    for i, v in enumerate(vals):
        end = v + (err[i] if err else 0) if v >= 0 else v
        ax.text(end, i, f"  {v:+.2f}" if errors is None else f"  {v:.2f}", va="center",
                ha="left" if v >= 0 else "right", fontsize=9, color=INK)
    ax.set_xlabel(xlabel)
    style(ax, title)
    fig.tight_layout()
    fig.savefig(path, dpi=160)
    plt.close(fig)


def chart_baseline(results: dict[str, dict[str, float]], path: Path) -> None:
    models = list(results)
    fig, axes = plt.subplots(1, 3, figsize=(9.6, 3.6))
    for ax, metric, better in zip(axes, ["mae", "rmse", "r2"], ["lower", "lower", "higher"]):
        vals = [results[m][metric] for m in models]
        ax.bar(models, vals, color=[MUTED] + [BRAND] * (len(models) - 1))
        for i, v in enumerate(vals):
            ax.text(i, v, f"{v:.2f}", ha="center", va="bottom", fontsize=9, color=INK)
        style(ax, f"{metric.upper()} ({better} is better)")
        ax.tick_params(axis="x", labelsize=8)
    fig.tight_layout()
    fig.savefig(path, dpi=160)
    plt.close(fig)


# ---------------------------------------------------------------- main

def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)

    section("1. Raw data")
    raw = pd.read_csv(RAW, dtype=str, keep_default_na=False)
    print(f"{len(raw)} rows, {len(raw.columns)} columns from {RAW.name}")

    section("2. Validation")
    clean, rejected = validate(raw)
    rejected.to_csv(OUT / "validation_report.csv", index=False)
    for reason, n in rejected["reason"].value_counts().items():
        print(f"  rejected {n:>3}  {reason}")
    print(f"{len(clean)} clean rows kept, {len(rejected)} rejected -> output/validation_report.csv")

    section("3. Feature engineering")
    data = engineer(clean)
    outliers = flag_outliers(data)
    data["is_outlier"] = outliers.astype(int)
    data[["reference_no", *FEATURES, "turnaround_days", "days_to_due", "breached", "is_outlier"]].to_csv(FEATURES_CSV, index=False)
    print(f"{len(FEATURES)} features: {', '.join(FEATURES)}")
    print(f"target turnaround_days: mean {data['turnaround_days'].mean():.2f}, median {data['turnaround_days'].median():.2f}")
    print(f"outliers flagged (kept): {int(outliers.sum())}; requests that breached their due date: {int(data['breached'].sum())}")

    section("4. Train/test split")
    X, y = data[FEATURES], data["turnaround_days"]
    X_train, X_test, y_train, y_test, due_train, due_test, br_train, br_test = train_test_split(
        X, y, data["days_to_due"], data["breached"], test_size=0.2, random_state=SEED)
    print(f"train {len(X_train)} rows, test {len(X_test)} rows (80/20, random_state={SEED})")

    section("5. Baseline (DEV-01): always predict the training mean")
    baseline = DummyRegressor(strategy="mean").fit(X_train, y_train)
    base_pred = baseline.predict(X_test)
    print(f"predicts {baseline.constant_[0][0]:.2f} days for every request")

    section("6. Linear regression (DEV-02)")
    cv = KFold(n_splits=5, shuffle=True, random_state=SEED)
    ols = make_pipeline(StandardScaler(), LinearRegression())
    ols_cv = cross_validate(ols, X_train, y_train, cv=cv, scoring=("neg_mean_absolute_error", "r2"))
    print(f"OLS 5-fold CV: MAE {-ols_cv['test_neg_mean_absolute_error'].mean():.3f}, R2 {ols_cv['test_r2'].mean():.3f}")
    ols.fit(X_train, y_train)

    grid = GridSearchCV(make_pipeline(StandardScaler(), Ridge()),
                        {"ridge__alpha": [0.01, 0.1, 1, 3, 10, 30, 100, 300, 1000]},
                        cv=cv, scoring="neg_mean_absolute_error")
    grid.fit(X_train, y_train)
    best_alpha = grid.best_params_["ridge__alpha"]
    print(f"Ridge 5-fold CV over alpha: best alpha={best_alpha}, CV MAE {-grid.best_score_:.3f}")

    candidates = {"OLS": ols, "Ridge": grid.best_estimator_}
    cv_mae = {"OLS": -ols_cv["test_neg_mean_absolute_error"].mean(), "Ridge": -grid.best_score_}
    chosen_name = min(cv_mae, key=cv_mae.get)
    model = candidates[chosen_name]
    print(f"chosen on cross-validation (not the test set): {chosen_name}")

    section("7. Evaluation on the held-out test set")
    results = {"Baseline (mean)": scores(y_test, base_pred)}
    for name, m in candidates.items():
        results[f"Linear ({name})"] = scores(y_test, m.predict(X_test))
    for name, r in results.items():
        print(f"  {name:<18} MAE {r['mae']:.3f}  RMSE {r['rmse']:.3f}  R2 {r['r2']:.3f}")

    y_pred = np.clip(model.predict(X_test), 0, None)
    breach = {
        "Baseline (mean)": breach_scores(br_test.values, base_pred, due_test.values),
        "Linear regression": breach_scores(br_test.values, y_pred, due_test.values),
    }
    print("deadline-breach early warning (predicted ready date after due date):")
    for name, b in breach.items():
        print(f"  {name:<18} precision {b['precision']:.3f}  recall {b['recall']:.3f}  F1 {b['f1']:.3f}  "
              f"({b['flagged']} flagged, {b['actual']} actually breached)")

    section("8. Explainability (DEV-03)")
    reg = model.named_steps[[k for k in model.named_steps if k != "standardscaler"][0]]
    standardized = {f: round(float(c), 3) for f, c in zip(FEATURES, reg.coef_)}
    perm = permutation_importance(model, X_test, y_test, n_repeats=20, random_state=SEED, scoring="neg_mean_absolute_error")
    importance = {f: round(float(m), 3) for f, m in zip(FEATURES, perm.importances_mean)}
    importance_std = {f: round(float(s), 3) for f, s in zip(FEATURES, perm.importances_std)}
    for f in sorted(importance, key=importance.get, reverse=True):
        print(f"  {LABELS[f]:<26} +{importance[f]:.3f} days MAE when shuffled   (std. coef {standardized[f]:+.3f})")

    chart_predicted_vs_actual(y_test.values, y_pred, OUT / "predicted_vs_actual.png")
    chart_residuals(y_test.values, y_pred, OUT / "residuals.png")
    chart_bars(standardized, "Standardized coefficients (days per 1 std. dev.)", "Effect on predicted days", OUT / "coefficients.png")
    chart_bars(importance, "Permutation importance (test set)", "Increase in MAE when the feature is shuffled (days)",
               OUT / "permutation_importance.png", importance_std)
    chart_baseline(results, OUT / "baseline_vs_model.png")
    print("charts written to output/")

    section("9. Export")
    intercept, weights = raw_coefficients(model)
    residual_std = float(np.std(y_test.values - y_pred))
    version = f"lr-{date.today().isoformat()}"
    MODEL_JSON.parent.mkdir(parents=True, exist_ok=True)
    MODEL_JSON.write_text(json.dumps({
        "version": version,
        "algorithm": f"Linear regression ({chosen_name}{', alpha=' + str(best_alpha) if chosen_name == 'Ridge' else ''})",
        "target": "turnaround_days",
        "intercept": round(intercept, 6),
        "coefficients": {k: round(v, 6) for k, v in weights.items()},
        "residual_std": round(residual_std, 4),
        "peak_months": PEAK_MONTHS,
        "metrics": results[f"Linear ({chosen_name})"],
        "baseline_metrics": results["Baseline (mean)"],
        "trained_on": "simulated",
        "training_rows": int(len(X_train)),
        "trained_at": date.today().isoformat(),
    }, indent=2) + "\n")
    (OUT / "metrics.json").write_text(json.dumps({
        "rows": {"raw": len(raw), "clean": len(clean), "rejected": len(rejected), "train": len(X_train), "test": len(X_test)},
        "regression": results, "breach_detection": breach, "chosen": chosen_name, "ridge_alpha": best_alpha,
        "cv_mae": {k: round(float(v), 3) for k, v in cv_mae.items()},
        "standardized_coefficients": standardized, "permutation_importance": importance,
    }, indent=2) + "\n")
    print(f"model {version} -> {MODEL_JSON.relative_to(ROOT.parent)}")

    section("10. Sample prediction")
    sample = pd.DataFrame([{"processing_days": 10, "requires_payment": 1, "has_library": 1, "has_finance": 1,
                            "has_student_affairs": 0, "has_college": 1, "copies": 2, "is_alumni": 1,
                            "open_workload": 20, "is_peak_month": 0}])
    routed = datetime(2026, 10, 12, 9)
    due_days = (add_working_days(routed, 10) - routed).days
    predicted = max(float(model.predict(sample)[0]), 0.0)
    print(f"TOR, 2 copies, alumni, 20 open requests, routed Mon 12 Oct: {predicted:.1f} days predicted, "
          f"due in {due_days} calendar days -> {'AT RISK' if predicted > due_days else 'on track'}")


if __name__ == "__main__":
    main()
