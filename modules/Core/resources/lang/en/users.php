<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'label' => 'Person',
    'plural_label' => 'People',
    'name' => 'Name',
    'email' => 'Email',
    'source' => 'Account',
    'sources' => [
        'local' => 'Local',
        'ldap' => 'Active Directory',
    ],
    'roles' => 'Roles in this organization',
    'roles_from_directory' => 'Set by the AD group mappings.',
    'mfa' => 'Two-factor',
    'active' => 'Active',
    'active_help' => 'A deactivated person cannot sign in. Only for people who belong to this organization alone; otherwise remove them from it.',
    'last_login' => 'Last sign-in',
    'never' => 'Never',
    'invite' => 'Invite',
    'invite_help' => 'They receive a link to choose their password. Someone who already has an account (another organization or Active Directory) is added to this organization instead.',
    'invite_failed' => 'The person could not be added. Check the roles.',
    'invitation_sent' => 'Invitation sent.',
    'shared_account' => 'This account also belongs to another organization, so its name cannot be changed here, nor its second factor reset or its lock lifted. The person lifts a lock themselves with Forgot your password? on the sign-in page. For anything else, ask the operator of this installation.',
    'resend_invitation' => 'Send the invitation again',
    'reset_mfa' => 'Reset two-factor',
    'reset_mfa_help' => 'For someone who lost their phone and their recovery codes. Check who is asking first. They set it up again at their next sign-in.',
    'mfa_reset_done' => 'Two-factor authentication reset.',
    'locked' => 'Locked',
    'unlock' => 'Unlock',
    'unlock_help' => 'The account was locked after too many failed sign-ins in a row. Check who is asking first: the failures may have been someone guessing the password.',
    'unlocked' => 'Account unlocked.',
    'remove' => 'Remove from organization',
    'remove_help' => 'Their roles in this organization are removed. Their account and history stay.',
];
