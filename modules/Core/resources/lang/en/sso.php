<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'sign_in_with' => 'Sign in with :provider',
    'find_label' => 'Your organization uses single sign-on? Enter your work email',
    'find' => 'Continue with single sign-on',
    'no_provider' => 'No single sign-on is set up for this address. Sign in with your password, or ask your administrator.',
    'failed' => 'Single sign-on did not work. Try again, or ask your administrator whether your account may sign in this way.',
    'protocols' => [
        'oidc' => 'OpenID Connect',
        'saml' => 'SAML 2.0',
    ],
    'provider' => [
        'protocol' => 'Protocol',
        'entity_id' => 'IdP entity ID',
        'entity_id_help' => 'The identity provider\'s entity ID (issuer), as in its metadata, e.g. https://login.company.example/saml/metadata.',
        'sso_url' => 'Single sign-on URL',
        'sso_url_help' => 'The provider\'s SAML endpoint for the HTTP-Redirect binding, from its metadata.',
        'certificate' => 'Signing certificate',
        'certificate_help' => 'The provider\'s token-signing certificate (Base64 / PEM), from its metadata. Assertions must be signed with it.',
        'certificate_invalid' => 'This is not a valid certificate.',
        'email_attribute' => 'Email attribute',
        'email_attribute_help' => 'Leave empty to use the usual ones (the common email claims, mail, email) or the NameID when it is an email.',
        'name_attribute' => 'Name attribute',
        'groups_attribute' => 'Groups attribute',
        'sp_metadata' => 'Service provider metadata URL',
        'sp_metadata_help' => 'Give this address to the identity provider: it describes the toolkit (entity ID and assertion consumer service).',
        'sp_acs' => 'Assertion consumer service (reply URL)',
        'label' => 'Single sign-on provider',
        'plural_label' => 'Single sign-on',
        'name' => 'Name on the sign-in button',
        'name_help' => 'e.g. Company account.',
        'issuer' => 'Issuer URL',
        'issuer_help' => 'The provider\'s OpenID Connect issuer, e.g. https://login.company.example/realms/company. The toolkit reads its /.well-known/openid-configuration.',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
        'client_secret_keep' => 'Leave empty to keep the current secret. It is stored encrypted and never shown again.',
        'allowed_domains' => 'Email domains',
        'allowed_domains_help' => 'Only accounts of these domains sign in through this provider, e.g. company.com.',
        'scopes' => 'Scopes',
        'groups_claim' => 'Groups claim',
        'groups_claim_help' => 'The claim of the ID token listing the person\'s groups (usually "groups"). The groups are mapped to roles in Single sign-on group mappings.',
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
        'group_help' => 'As the provider sends it: a group id, name, path or email…',
        'role' => 'Role',
    ],
];
