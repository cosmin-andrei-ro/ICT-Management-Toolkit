<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Enums;

/**
 * The columns of the task board, in order.
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';

    public function label(): string
    {
        return __('projects::projects.task_statuses.'.$this->value);
    }

    /**
     * The column to its left or right on the board, if any.
     */
    public function neighbour(int $direction): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);

        return $cases[(int) $index + $direction] ?? null;
    }
}
