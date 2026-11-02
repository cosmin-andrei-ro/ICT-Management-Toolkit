<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Sso;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Policies\SsoPolicy;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * An identity provider of an organization (Entra ID, Google Workspace,
 * Keycloak, Okta, ADFS…), through OpenID Connect or SAML 2.0. For SAML the
 * issuer is the IdP's entity ID. The OIDC client secret is encrypted at rest,
 * hidden from serialization and from the audit trail, and never shown again.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $issuer
 * @property SsoProtocol $protocol
 * @property ?string $client_id
 * @property ?string $client_secret
 * @property ?string $sso_url
 * @property ?string $certificate
 * @property ?string $email_attribute
 * @property ?string $name_attribute
 * @property string $scopes
 * @property string $groups_claim
 * @property list<string> $allowed_domains
 * @property bool $trust_mfa
 * @property bool $is_enabled
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('core_sso_providers')]
#[UsePolicy(SsoPolicy::class)]
#[Hidden(['client_secret'])]
#[Fillable(['protocol', 'name', 'issuer', 'client_id', 'client_secret', 'sso_url', 'certificate', 'email_attribute', 'name_attribute', 'scopes', 'groups_claim', 'allowed_domains', 'trust_mfa', 'is_enabled'])]
final class SsoProvider extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'protocol' => 'oidc',
        'scopes' => 'openid email profile',
        'groups_claim' => 'groups',
        'trust_mfa' => true,
        'is_enabled' => true,
    ];

    public function allowsEmail(string $email): bool
    {
        $domain = mb_strtolower(substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, array_map(mb_strtolower(...), $this->allowed_domains), true);
    }

    /**
     * @return HasMany<SsoGroupMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(SsoGroupMapping::class, 'provider_id');
    }

    protected function casts(): array
    {
        return [
            'protocol' => SsoProtocol::class,
            'client_secret' => 'encrypted',
            'allowed_domains' => 'array',
            'trust_mfa' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }
}
