<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Audit;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Tenancy\OrganizationContext;

final readonly class AuditTrail
{
    /**
     * Never written to the log, whatever the model declares.
     */
    private const array ALWAYS_EXCLUDED = ['password', 'remember_token', 'created_at', 'updated_at'];

    public function __construct(
        private AuthFactory $auth,
        private OrganizationContext $organization,
        private RequestContext $request,
    ) {}

    /**
     * $model is null only for security events without a known subject
     * (e.g. a failed login for an unknown email). $userId overrides the
     * authenticated user, for events fired before or after authentication.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(AuditEvent $event, ?Model $model, ?array $oldValues, ?array $newValues, ?string $userId = null): void
    {
        $organizationId = $model?->getAttribute('organization_id') ?? $this->organization->id();
        $userId ??= $this->auth->guard()->id();

        (new AuditLog)->forceFill([
            'organization_id' => is_string($organizationId) ? $organizationId : null,
            'user_id' => is_string($userId) ? $userId : null,
            'event' => $event,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $this->request->ipAddress,
            'user_agent' => $this->request->userAgent,
            'request_id' => $this->request->requestId,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function filter(Model $model, array $attributes): array
    {
        $excluded = [...self::ALWAYS_EXCLUDED, ...$model->getHidden()];

        if (method_exists($model, 'auditExclude')) {
            /** @var list<string> $modelExcluded */
            $modelExcluded = $model->auditExclude();
            $excluded = [...$excluded, ...$modelExcluded];
        }

        return array_diff_key($attributes, array_flip($excluded));
    }
}
