<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Sso;

/**
 * How the toolkit talks to an identity provider. Mirrored by core_sso_providers_protocol_check.
 */
enum SsoProtocol: string
{
    case Oidc = 'oidc';
    case Saml = 'saml';

    public function label(): string
    {
        return __('core::sso.protocols.'.$this->value);
    }
}
