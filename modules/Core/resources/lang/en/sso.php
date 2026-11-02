<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'sign_in_with' => 'Sign in with :provider',
    'failed' => 'Single sign-on did not work. Try again, or ask your administrator whether your account may sign in this way.',
    'provider' => [
        'label' => 'Single sign-on provider',
        'plural_label' => 'Single sign-on',
        'name' => 'Name on the sign-in button',
        'name_help' => 'e.g. Microsoft, Google, Company account.',
        'issuer' => 'Issuer URL',
        'issuer_help' => 'The provider\'s OpenID Connect issuer, e.g. https://login.microsoftonline.com/<tenant id>/v2.0 or https://keycloak.example.com/realms/company. The toolkit reads its /.well-known/openid-configuration.',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
        'client_secret_keep' => 'Leave empty to keep the current secret. It is stored encrypted and never shown again.',
        'allowed_domains' => 'Email domains',
        'allowed_domains_help' => 'Only accounts of these domains sign in through this provider, e.g. company.com.',
        'scopes' => 'Scopes',
        'groups_claim' => 'Groups claim',
        'groups_claim_help' => 'The claim of the ID token listing the person\'s groups (Entra ID and Keycloak: "groups"). The groups are mapped to roles in Single sign-on group mappings.',
        'trust_mfa' => 'Trust the provider\'s two-factor authentication',
        'trust_mfa_help' => 'When the provider says it asked for a second factor (the "amr" claim), the toolkit does not ask for its own. Otherwise it does.',
        'is_enabled' => 'Enabled',
        'redirect_uri' => 'Redirect URI',
        'redirect_uri_help' => 'Register this address at the provider as the redirect (reply) URI of the application.',
    ],
    'mapping' => [
        'label' => 'Single sign-on group mapping',
        'plural_label' => 'Single sign-on group mappings',
        'provider' => 'Provider',
        'group' => 'Group',
        'group_help' => 'As the provider sends it: an Entra ID group object id, a Keycloak group name or path, a Google group email…',
        'role' => 'Role',
    ],
];
