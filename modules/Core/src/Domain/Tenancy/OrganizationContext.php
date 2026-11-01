<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Tenancy;

use Modules\Core\Domain\Exceptions\MissingOrganizationContext;
use Modules\Core\Domain\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

/**
 * The organization the current request or job acts for.
 *
 * Registered as a scoped binding, so it is reset between requests and queued jobs.
 * Organization-scoped models return nothing while no organization is set, which
 * makes a forgotten context fail closed instead of leaking data.
 */
final class OrganizationContext
{
    private ?string $organizationId = null;

    public function __construct(private readonly PermissionRegistrar $permissions) {}

    public function set(Organization|string $organization): void
    {
        $this->organizationId = $organization instanceof Organization ? $organization->id : $organization;

        // Roles and permissions are team-scoped by organization (ADR 0004).
        $this->permissions->setPermissionsTeamId($this->organizationId);
    }

    public function clear(): void
    {
        $this->organizationId = null;
        $this->permissions->setPermissionsTeamId(null);
    }

    public function id(): ?string
    {
        return $this->organizationId;
    }

    public function has(): bool
    {
        return $this->organizationId !== null;
    }

    /**
     * @throws MissingOrganizationContext
     */
    public function requireId(): string
    {
        return $this->organizationId ?? throw new MissingOrganizationContext;
    }

    /**
     * Run a callback for another organization (e.g. in a queued job or command),
     * restoring the previous context afterwards.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Organization|string $organization, callable $callback): mixed
    {
        $previous = $this->organizationId;
        $this->set($organization);

        try {
            return $callback();
        } finally {
            $previous === null ? $this->clear() : $this->set($previous);
        }
    }
}
