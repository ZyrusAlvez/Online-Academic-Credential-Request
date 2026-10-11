# UPHSL Credential Tracker

**Online Academic Credential Request, Tracking & Monitoring** for the Office of the Registrar,
University of Perpetual Help System.

Students request academic credentials such as the Transcript of Records online, then follow
the paper document from office to office like a parcel. The system is a **digital ledger**:
it records every hand-off and signature. The documents themselves are never sent digitally.

## Who uses it

| Role | What they do |
|---|---|
| **Student** | Requests a credential, pays online for paid ones (e.g. TOR) and uploads the receipt, sees where the document is right now and its full history, gets notified of every update |
| **Department** (colleges and offices) | Sees the requests waiting for their signature, signs them, or puts them on hold with a reason (e.g. an unreturned library book) |
| **Registrar** (admin) | Verifies receipts, approves and routes requests, releases finished documents, adds departments, and decides which offices sign each credential and in what order |

Only the student's own college signs, not every college. Each credential's signing order and
fee can be changed by the registrar at any time.

## Run it

```bash
composer dev
```

Then open **http://localhost:8000**. Demo accounts and testing steps are in
[docs/credentials.md](docs/credentials.md).

## Documentation

- [Credentials & testing](docs/credentials.md)
- [Data dictionary](docs/data-dictionary.md)
- [Design documents DES-01 to DES-04](docs/des/) (Word files; upload to Google Drive to open as Google Docs)
- [Specifications](docs/specs/00-overview.md) (spec-driven development, one folder per feature)
