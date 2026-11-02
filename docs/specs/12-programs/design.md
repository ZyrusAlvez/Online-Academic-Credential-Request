# 12 — Programs & Strands · Design

## Data

`programs`: `department_id` (the college, `RESTRICT`), `name`, `is_active`; unique on
(`department_id`, `name`), which also serves the lookup by college. Programs are deactivated, never
deleted. See `docs/data-dictionary.md`.

`users.program` stays a text column holding the chosen name, and `credential_requests.program`
keeps its copy. A foreign key would touch every place that shows a program and rewrite the name on
past requests after a rename; a request must keep what was true when it was made.

## Code

- `Registrar\Programs\Index` (`/registrar/programs`, "Programs" under Administration) follows
  `Registrar\Departments\Index`: `ProgramForm`, `ProgramPolicy`, a modal form, deactivate instead
  of delete.
- `ProgramSeeder` holds the published list by college code and only creates missing rows, so a
  rename or deactivation by the registrar survives. A migration runs it, because deploys only
  migrate (as `install_credential_master_list` does).
- `Student\Profile` loads the chosen college's active programs and clears the program when the
  college changes. `RequesterProfileForm` checks the name with an `exists` rule scoped to the
  college and to active programs.

## Source

The published list comes from https://uphsl.edu.ph/academic-programs/. The site's college names
differ from the client's offices, so `ProgramSeeder` maps them to the existing college codes.
