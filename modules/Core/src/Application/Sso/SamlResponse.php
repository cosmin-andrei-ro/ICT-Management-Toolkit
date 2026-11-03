<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

use OneLogin\Saml2\Response;

/**
 * A SAML response that also reads how the person signed in, from the signed
 * assertion only. The response around it is not signed, so an authentication
 * context added elsewhere in the document (e.g. in samlp:Extensions) must not
 * count as a second factor.
 */
final class SamlResponse extends Response
{
    /**
     * @return list<string>
     */
    public function authnContexts(): array
    {
        $contexts = [];

        foreach ($this->_queryAssertion('/saml:AuthnStatement/saml:AuthnContext/saml:AuthnContextClassRef') as $node) {
            $contexts[] = trim((string) $node->nodeValue);
        }

        return $contexts;
    }
}
