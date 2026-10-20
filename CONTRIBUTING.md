# Contributing to Ministry Platform

Thank you for helping make ministry technology more accessible. Contributions are welcome for bug fixes, accessibility, documentation, translations, security improvements, and new ministry features.

## Before opening a pull request

1. Search existing issues and pull requests.
2. For substantial behavior changes, open an issue first and describe the ministry problem being solved.
3. Read the relevant specification in `docs/` and update it when behavior changes.
4. Keep one ministry per installation and preserve server-side module and permission gates.
5. Do not include real ministry data, credentials, private media, or production configuration.

## Pull requests

All changes enter through pull requests. Maintainers review authorization, privacy, accessibility, migration safety, shared-hosting portability, and operational recovery before merging. The `main` branch is protected; passing CI is required but does not replace human review.

Describe the user-facing change, affected permissions and data, migration or deployment considerations, and how it was verified. Keep commits focused and update the owning documentation.

## Code and design expectations

Follow `AGENTS.md`, `docs/professional-build-standard.md`, and the applicable product documents. Do not add secrets to source control. Sensitive ministry data must remain installation-local and must not be sent to analytics or third-party services without an explicit provider boundary.

## Licensing contributions

By submitting a contribution, you agree that it may be distributed under this repository's GPLv2 license. You retain copyright in your contribution while granting the project the rights needed to use, modify, and distribute it with the platform.
