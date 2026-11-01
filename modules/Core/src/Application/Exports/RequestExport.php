<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Exports;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use stdClass;

/**
 * Records an export request (audited) and queues its generation.
 */
final readonly class RequestExport
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private ExportRegistry $registry,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters  Validated by the caller.
     */
    public function handle(User $user, string $type, ExportFormat $format, array $parameters): Export
    {
        if (! $user->can($this->registry->permission($type))) {
            throw new AuthorizationException;
        }

        return $this->db->transaction(function () use ($user, $type, $format, $parameters): Export {
            $export = new Export;
            $export->forceFill([
                'organization_id' => $this->context->requireId(),
                'user_id' => $user->id,
                'type' => $type,
                'format' => $format,
                // An empty PHP array would be stored as a JSON list; the column holds an object.
                'parameters' => $parameters === [] ? new stdClass : $parameters,
            ])->save();

            $this->trail->record(AuditEvent::Exported, $export, null, ['type' => $type, 'format' => $format->value, 'parameters' => $parameters], $user->id);
            GenerateExport::dispatch($export->id)->afterCommit();

            return $export;
        });
    }
}
