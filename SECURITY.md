# Security policy

Ministry Platform handles member, pastoral, attendance, and provider configuration data. Please do not report a vulnerability in a public issue and do not include personal or ministry data in a report.

## Reporting a vulnerability

Until a dedicated security contact is configured, report security issues privately to the repository maintainers through GitHub's private vulnerability reporting or the project owner. Include the affected version, reproduction steps, impact, and a safe remediation suggestion where possible.

Maintainers will acknowledge reports, investigate privately, and coordinate a fix and disclosure timeline. Security fixes may be released before a full feature release.

## Deployment safety

Keep `.env`, provider credentials, private media, backups, signing keys, and real ministry data outside Git. Rotate any credential that may have been exposed and preserve audit records when investigating an incident.
