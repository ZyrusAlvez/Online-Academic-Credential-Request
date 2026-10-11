"""Generate the simulated request dataset used to train the turnaround model.

SIMULATED DATA. The pilot does not yet have enough completed requests to train on
(Chapter 1, section 1.5), so this script simulates routed requests over the system's
seeded credential types and workflows (database/seeders/CredentialTypeSeeder.php).
Each office takes a random number of working days to sign, sometimes puts the request
on hold, and works slower when many requests are open or during peak months.

The output has the same columns as ml/export_dataset.sql, so the training script runs
unchanged on real data once the pilot has collected enough of it.

A small share of rows is deliberately made dirty (blanks, duplicates, impossible values)
so the validation step in train.py has something to catch, as real exports do.

Usage: python ml/generate_dataset.py [--rows 2000] [--seed 42]
"""

from __future__ import annotations

import argparse
from datetime import datetime, timedelta
from pathlib import Path

import numpy as np
import pandas as pd

OUT = Path(__file__).parent / "data" / "raw_requests.csv"

# Mirrors CredentialTypeSeeder: name, requires payment, processing days, workflow, share of requests.
CREDENTIAL_TYPES = [
    ("Transcript of Records", True, 10, ["LIB", "FIN", "COLLEGE", "REG"], 0.30),
    ("Certificate of Enrollment", False, 3, ["REG"], 0.20),
    ("Certificate of Grades", False, 5, ["COLLEGE", "REG"], 0.15),
    ("Good Moral Certificate", False, 5, ["SAS", "REG"], 0.12),
    ("Honorable Dismissal / Transfer Credentials", True, 10, ["LIB", "FIN", "SAS", "COLLEGE", "REG"], 0.08),
    ("Diploma - Certified True Copy", True, 7, ["FIN", "REG"], 0.08),
    ("CAV - Certification, Authentication and Verification", True, 10, ["COLLEGE", "REG"], 0.07),
]

COLLEGES = ["CAS", "CBA", "CCS", "CRIM", "COED", "COE", "CIHM", "CME", "LAW", "GS", "CAA"]
COLLEGE_SHARE = [0.10, 0.14, 0.13, 0.09, 0.10, 0.12, 0.08, 0.07, 0.04, 0.05, 0.08]

# Working days an office usually takes to sign, and how often it puts a request on hold.
OFFICE_DAYS = {"LIB": 0.6, "FIN": 0.9, "SAS": 0.8, "COLLEGE": 1.6, "REG": 1.3}
HOLD_RATE = {"LIB": 0.15, "FIN": 0.18, "SAS": 0.08, "COLLEGE": 0.10, "REG": 0.05}
HOLD_DAYS = {"LIB": (2, 5), "FIN": (2, 6), "SAS": (1, 3), "COLLEGE": (1, 4), "REG": (1, 2)}

PEAK_MONTHS = {6, 7, 8}  # graduation and enrollment season (assumption until the registrar confirms)
START = datetime(2024, 6, 3)
END = datetime(2026, 9, 30)


def add_working_days(start: datetime, days: float) -> datetime:
    """Move forward by a fractional number of working days, skipping weekends."""
    current = start
    whole = int(days)
    while whole > 0:
        current += timedelta(days=1)
        if current.weekday() < 5:
            whole -= 1
    current += timedelta(days=days - int(days))
    while current.weekday() >= 5:
        current += timedelta(days=1)
    return current


def random_routing_time(rng: np.random.Generator) -> datetime:
    """A weekday between 08:00 and 17:00 inside the simulated period."""
    span = (END - START).days
    while True:
        day = START + timedelta(days=int(rng.integers(0, span)))
        if day.weekday() < 5:
            return day + timedelta(hours=int(rng.integers(8, 17)), minutes=int(rng.integers(0, 60)))


def simulate(rows: int, rng: np.random.Generator) -> pd.DataFrame:
    shares = np.array([t[4] for t in CREDENTIAL_TYPES])
    records = []

    for i in range(rows):
        name, paid, processing_days, workflow, _ = CREDENTIAL_TYPES[rng.choice(len(CREDENTIAL_TYPES), p=shares)]
        routed_at = random_routing_time(rng)
        peak = routed_at.month in PEAK_MONTHS
        workload = int(rng.poisson(28 if peak else 14))
        alumni = rng.random() < 0.55
        copies = int(rng.choice([1, 1, 1, 1, 2, 2, 3, 5]))

        days = 0.0
        for office in workflow:
            days += rng.gamma(2.0, OFFICE_DAYS[office] / 2.0)
            if rng.random() < HOLD_RATE[office]:
                days += rng.uniform(*HOLD_DAYS[office])
        days += 0.6 if alumni else 0.0          # archived records take longer to pull
        days += 0.15 * (copies - 1)             # each extra copy is printed and certified
        days *= 1 + 0.015 * workload            # busy offices are slower
        days *= 1.2 if peak else 1.0

        ready_at = add_working_days(routed_at, days)

        records.append({
            "reference_no": f"SIM-{routed_at.year}-{i + 1:06d}",
            "credential_type": name,
            "college": rng.choice(COLLEGES, p=COLLEGE_SHARE),
            "student_status": "alumni" if alumni else "current",
            "copies": copies,
            "requires_payment": int(paid),
            "processing_days": processing_days,
            "workflow": ">".join(workflow),
            "open_workload": workload,
            "routed_at": routed_at.strftime("%Y-%m-%d %H:%M:%S"),
            "ready_at": ready_at.strftime("%Y-%m-%d %H:%M:%S"),
        })

    return pd.DataFrame(records)


def add_dirty_rows(df: pd.DataFrame, rng: np.random.Generator) -> pd.DataFrame:
    """Inject the kinds of problems real exports have (about 3% of rows)."""
    n = len(df)
    pick = lambda k: rng.choice(n, size=k, replace=False)  # noqa: E731

    df = df.astype({"copies": "object", "open_workload": "object"})
    df.loc[pick(12), "student_status"] = ""                 # missing value
    df.loc[pick(8), "copies"] = 0                           # out of range
    df.loc[pick(5), "copies"] = "two"                       # wrong type
    df.loc[pick(6), "ready_at"] = ""                        # never finished (still in progress)
    bad_order = pick(5)                                     # ready before routed
    df.loc[bad_order, "ready_at"] = (pd.to_datetime(df.loc[bad_order, "routed_at"]) - timedelta(days=1)).dt.strftime("%Y-%m-%d %H:%M:%S")
    df.loc[pick(4), "open_workload"] = -3                   # impossible count
    df.loc[pick(4), "student_status"] = "visitor"           # not an allowed value

    duplicates = df.sample(10, random_state=int(rng.integers(0, 1_000_000)))
    return pd.concat([df, duplicates], ignore_index=True)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--rows", type=int, default=2000)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()

    rng = np.random.default_rng(args.seed)
    df = add_dirty_rows(simulate(args.rows, rng), rng)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    df.to_csv(OUT, index=False)
    print(f"Wrote {len(df)} rows (simulated) to {OUT.relative_to(Path.cwd()) if OUT.is_relative_to(Path.cwd()) else OUT}")


if __name__ == "__main__":
    main()
