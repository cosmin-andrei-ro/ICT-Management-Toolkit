<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\CreateTicket;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\ChangeDetails;
use Modules\Ticketing\Domain\Models\ChangeTemplate;
use Modules\Ticketing\Domain\Models\Ticket;

final readonly class CreateChange
{
    public function __construct(
        private ConnectionInterface $db,
        private CreateTicket $createTicket,
    ) {}

    /**
     * A normal or emergency change starts as a draft to be submitted to a CAB.
     */
    public function handle(ChangeType $type, string $title, string $description, ChangePlan $plan, User $actor): Ticket
    {
        if ($type === ChangeType::Standard) {
            throw new InvalidArgumentException('Standard changes are created from a template.');
        }

        return $this->create($type, $title, $description, $plan, null, $actor);
    }

    /**
     * A standard change is pre-approved: its plans come from the template.
     */
    public function fromTemplate(ChangeTemplate $template, string $description, ?CarbonImmutable $plannedStart, ?CarbonImmutable $plannedEnd, User $actor): Ticket
    {
        if (! $template->is_active) {
            throw new InvalidArgumentException('This standard change template is inactive.');
        }

        $plan = new ChangePlan($template->risk, $template->implementation_plan, $template->rollback_plan, (string) $template->test_plan, $plannedStart, $plannedEnd);
        $description = trim($description) !== '' ? $description : $template->description;

        return $this->create(ChangeType::Standard, $template->name, $description, $plan, $template, $actor);
    }

    private function create(ChangeType $type, string $title, string $description, ChangePlan $plan, ?ChangeTemplate $template, User $actor): Ticket
    {
        return $this->db->transaction(function () use ($type, $title, $description, $plan, $template, $actor): Ticket {
            $change = $this->createTicket->handle(new NewTicketData(
                type: TicketType::Change,
                title: $title,
                description: $description,
                impact: match ($plan->risk) {
                    ChangeRisk::High => Impact::High,
                    ChangeRisk::Medium => Impact::Medium,
                    ChangeRisk::Low => Impact::Low,
                },
                urgency: $type === ChangeType::Emergency ? Urgency::High : Urgency::Low,
                channel: TicketChannel::Agent,
            ), $actor, $actor);

            (new ChangeDetails)->forceFill([
                'ticket_id' => $change->id,
                'organization_id' => $change->organization_id,
                'change_type' => $type,
                'risk' => $plan->risk,
                'template_id' => $template?->id,
                'implementation_plan' => self::orNull($plan->implementationPlan),
                'rollback_plan' => self::orNull($plan->rollbackPlan),
                'test_plan' => self::orNull($plan->testPlan),
                'planned_start' => $plan->plannedStart,
                'planned_end' => $plan->plannedEnd,
                'approval_status' => $template !== null ? ApprovalStatus::Approved : ApprovalStatus::Draft,
                'approved_at' => $template !== null ? now() : null,
            ])->save();

            return $change->load('changeDetails');
        });
    }

    public static function orNull(string $text): ?string
    {
        $text = trim($text);

        return $text !== '' ? $text : null;
    }
}
