# Working in the Ministry Platform repository

Read `docs/professional-build-standard.md`, `docs/00-product-master.md`, and `docs/06-build-plan.md` before implementing features. Then read the documents that own the affected area: architecture and module layout (`01`), data (`02`), contracts (`03`), system and permission rules (`05`), interface and performance (`07`), or operations and deployment (`08`). Consult `docs/09-decisions.md` before treating an open choice as settled.

## Core rules

- One ministry owns one installation. Multiple branches are within that installation. Do not introduce a central cross-ministry member database.
- A large public service is a broadcast experience; a small interactive gathering is a meeting. Keep their scaling and attendance rules distinct.
- No application-wide hard cap on live viewers. Capacity depends on the chosen media distribution and the installation's tested infrastructure. Never claim infinite capacity.
- Do not infer an individual's attendance from an anonymous page or stream view.
- Keep giving, pastoral care, and children's data in separate permission domains. Audit sensitive access and changes.
- Module disablement is enforced server-side as well as hidden in the UI.
- External providers are configured per installation; avoid requiring a vendor-controlled central service.
- Preserve deployment portability: documented configuration, backup, restore, export, and upgrade paths.
- If documents conflict, identify the owning document and record a proposed resolution in `docs/09-decisions.md` before implementing the disputed behavior.

## Implementation workflow

Implement in the order of `docs/06-build-plan.md`. Work across affected layers in the same change, including migration, domain logic, interface, and meaningful tests. Validate each phase against its quality gate. Do not present a phase as complete because code compiles. Keep API contract changes synchronized with consumers and update the owning document when behavior changes.

Specifications in this repository are a baseline, not evidence that any feature has already been built or deployed. Inspect the code and tests before continuing implementation so work is not repeated or mistaken for a passed quality gate.
