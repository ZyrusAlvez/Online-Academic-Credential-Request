# 23 — Database Baseline and Reset · Tasks

- [ ] Rewrite the 16 kept migrations to the final shape; delete the other 22 (REQ-23-01)
- [ ] Compare the structure built by the old and the new migrations: identical (REQ-23-01)
- [ ] Remove the one-time seeders, `install()`, `RENAMES`, `RETIRED`; base seeders only add what is missing (REQ-23-02, 03)
- [ ] `app:reset`; `app:reset-demo` built on it (REQ-23-04..07)
- [ ] Superseded: REQ-02-13, 02-14, 02-18, 03-12, 12-11, 12-12, 15-14, 21-07; reworded REQ-12-02, 13-02
- [ ] Pest tests for every REQ-23 criterion; remove the tests of the superseded criteria
- [ ] Docs: deployment guide (seeding, reset), environments, credentials
