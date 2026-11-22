<?php

namespace App\Support;

final class RoleCatalog
{
    /** @var array<string, string> */
    public const INSTALLATION_ROLES = [
        'ministry_administrator' => 'Ministry Administrator',
        'content_media_editor' => 'Content & Media Editor',
        'ministry_operations_coordinator' => 'Ministry Operations Coordinator',
        'prayer_team' => 'Prayer Team',
        'care_staff' => 'Care Staff',
        'finance_staff' => 'Finance Staff',
        'service_production_team' => 'Service & Production Team',
        'childrens_team' => 'Children’s Team',
        'security_administrator' => 'Security Administrator',
        'super_administrator' => 'Super Administrator',
    ];

    /**
     * These roles are safe to grant through the current installation-wide
     * invitation workflow. Service production and children's access require a
     * resource assignment flow before they can be granted.
     *
     * @var list<string>
     */
    public const ROUTINE_INVITATION_ROLES = [
        'ministry_administrator',
        'content_media_editor',
        'ministry_operations_coordinator',
        'prayer_team',
        'care_staff',
        'finance_staff',
    ];

    /** @var list<string> */
    public const MFA_REQUIRED_ROLES = [
        'security_administrator',
        'super_administrator',
    ];

    /** @var array<string, list<string>> */
    public const PERMISSIONS = [
        'content_media_editor' => ['content.manage', 'content.publish'],
        'ministry_operations_coordinator' => ['community.manage', 'departments.manage', 'services.manage', 'reports.view', 'school.manage', 'school.instruct', 'birthdays.read'],
        'prayer_team' => ['prayer.read'],
        'care_staff' => ['care.manage', 'care.notes.write'],
        'finance_staff' => ['finance.view', 'finance.reconcile', 'giving.manage'],
        // These are enforced only through a future service/meeting assignment
        // workflow; they intentionally have no installation-wide invitation.
        'service_production_team' => ['services.manage'],
        // Children records remain excluded until the safeguarding specification
        // is accepted (O-005), so this role currently has no data grants.
        'childrens_team' => [],
        'security_administrator' => ['roles.manage', 'audit.read'],
    ];
}
