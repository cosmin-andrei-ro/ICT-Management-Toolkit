<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Security settings',
    'composition' => 'Require upper and lower case letters, a digit and a symbol in passwords',
    'composition_help' => 'Only if an audit or a contract demands it. NIST SP 800-63B advises against such rules, because people answer them with predictable patterns. Applies to passwords chosen from now on, and to anyone who also belongs to another organization. Accounts signing in through Active Directory or single sign-on are not affected.',
    'mfa_roles' => 'Roles that must use two-factor authentication',
    'mfa_roles_help' => 'People holding one of these roles must set up an authenticator app or a security key before they can work. Administrators always must. Leaving out the IT roles weakens protection against stolen passwords.',
    'instance' => 'Set for the whole installation by its operator: accounts lock after :count failed sign-ins in a row (0: never); new passwords known from breaches are :breached.',
    'refused' => 'refused',
    'accepted' => 'accepted',
    'save' => 'Save',
    'saved' => 'Security settings saved.',
];
