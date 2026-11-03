<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Invitations',
    'intro' => 'Organizations that asked you to join them. Your account stays the same: accept only invitations you expected.',
    'none' => 'No invitation is waiting for you.',
    'from' => 'Invited by :name, :date',
    'roles' => 'Roles',
    'expires' => 'Expires :date',
    'accept' => 'Join',
    'decline' => 'Decline',
    'accepted' => 'You joined :organization. Switch to it from the organization menu.',
    'declined' => 'Invitation declined.',
    'refused_sso' => 'You sign in through the single sign-on of another organization, so you cannot join this one with this account. Ask its administrator for a separate account.',
    'mail' => [
        'subject' => 'Join an organization in :app',
        'line' => ':organization invited you to join it in :app, with your existing account.',
        'action' => 'See the invitation',
        'expiry' => 'Sign in to accept or decline it. It expires in :days days.',
        'ignore' => 'If you did not expect it, decline it or ignore this e-mail: nothing changes unless you accept.',
    ],
];
