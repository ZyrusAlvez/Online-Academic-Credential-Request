# 02 — Departments & Staff Accounts · Tasks

- [x] DepartmentType enum, departments migration, users.department_id migration
- [x] Department model, factory, seeder with the 15 UPHSL departments (REQ-02-01)
- [x] Departments page: list/search/filter, create/edit modal, activate/deactivate (REQ-02-02..05)
- [x] SaveStaffAccount + SetAccountStatus actions (REQ-02-08..11)
- [x] Users page: list/search/filter, staff modal, activate/deactivate (REQ-02-07..11)
- [x] Registrar navigation links
- [x] Pest tests for REQ-02-01..12
- [x] `composer qa` green
- [x] Master list offices: REG, VPF, CHAN, PRES + colleges; retired LIB/FIN/SAS deactivated (REQ-02-01, 13)
- [x] The 11-college list of 2026-11-06 in `DepartmentSeeder`; `replace_college_list` migration
      renames colleges, removes Law, replaces staff accounts (REQ-02-01, 14)
- [x] Demo data and `docs/credentials.md` on the new codes
- [x] Pest tests for REQ-02-14 and the changed REQ-02-01
- [x] `ChangeAccountRole` action + `UserPolicy::changeRole` (REQ-02-15, 16)
- [x] Accounts page: Change role menu item and modal; Edit no longer changes the role (REQ-02-09, 15)
- [x] Role change wording in the activity log (REQ-02-17)
- [x] Pest tests for REQ-02-15..17 and the changed REQ-02-09, 11
- [x] `RemoveCollegeOfLawSeeder` + `remove_college_of_law` migration; `ReplaceCollegeListSeeder`
      uses it (REQ-02-14, 18)
- [x] Active-only filter and pick lists on In progress, Records, Reports, Activity log, Programs
      (REQ-02-19)
- [x] Pest tests for REQ-02-18, 19 and the changed REQ-02-14
