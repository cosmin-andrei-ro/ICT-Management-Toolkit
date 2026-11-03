<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'menu' => 'API tokens',
    'title' => 'API tokens',
    'intro' => 'A token lets a script or another system use the API as you, in this organization, within the abilities you choose. It can never do more than you may do yourself.',
    'new' => 'New token',
    'create' => 'Create token',
    'yours' => 'Your tokens in this organization',
    'none' => 'You have no tokens.',
    'copy_now' => 'Copy your token now',
    'copy_help' => 'It is shown only this once. Keep it in a password manager or the secret store of the system that uses it; never in code or e-mail.',
    'copied' => 'I have copied it',
    'last_used' => 'last used :date',
    'never_used' => 'never used',
    'expires' => 'expires :date',
    'revoke' => 'Revoke',
    'revoke_confirm' => 'Revoke this token? Systems using it stop working at once.',
    'needs_two_factor' => 'Set up two-factor authentication first: creating a token asks for a code from your authenticator app.',
    'docs' => 'Send the token as "Authorization: Bearer <token>". Lists accept filter[...], sort and per_page; the API description is available to administrators.',
    'fields' => [
        'name' => 'Name',
        'name_placeholder' => 'e.g. Monitoring integration',
        'abilities' => 'Abilities',
        'abilities_help' => 'Choose only what the system needs.',
        'days' => 'Valid for (days)',
        'code' => 'Code from your authenticator app',
    ],
    'errors' => [
        'code' => 'The code is not right.',
    ],
    'admin' => [
        'title' => 'API tokens',
        'owner' => 'Owner',
        'name' => 'Name',
        'abilities' => 'Abilities',
        'last_used' => 'Last used',
        'expires' => 'Expires',
        'never' => 'Never',
        'revoke' => 'Revoke',
        'revoke_help' => 'Systems using this token stop working at once. Tell its owner.',
        'revoked' => 'Token revoked.',
    ],
];
