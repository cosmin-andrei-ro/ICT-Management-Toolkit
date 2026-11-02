<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Modules\Audit\Domain\Enums\TestResult;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Core\Domain\Models\User;

/**
 * Records a test of a control, optionally with the document it produced, and
 * sets the next test from the control's interval.
 */
final readonly class RecordControlTest
{
    public function __construct(
        private ConnectionInterface $db,
        private ManageEvidence $evidence,
    ) {}

    /**
     * @throws InvalidArgumentException for a test in the future
     */
    public function handle(Control $control, CarbonImmutable $testedOn, TestResult $result, string $method, ?string $notes, ?UploadedFile $document, User $actor): ControlTest
    {
        if ($testedOn->isAfter(CarbonImmutable::today())) {
            throw new InvalidArgumentException('A test is recorded once done.');
        }

        return $this->db->transaction(function () use ($control, $testedOn, $result, $method, $notes, $document, $actor): ControlTest {
            $test = new ControlTest;
            $test->forceFill([
                'organization_id' => $control->organization_id,
                'control_id' => $control->id,
                'tested_on' => $testedOn,
                'tester_id' => $actor->id,
                'result' => $result,
                'method' => trim($method),
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            ])->save();

            if ($document !== null) {
                $this->evidence->upload($control, $document, __('audit::evidence.tests.document_title', ['date' => $testedOn->toDateString()]), null, null, $actor, $test);
            }

            // An older test recorded late does not move the schedule back.
            if ($control->last_tested_on === null || $testedOn->gte($control->last_tested_on)) {
                $control->forceFill([
                    'last_tested_on' => $testedOn,
                    'next_test_on' => $control->test_interval_months !== null ? $testedOn->addMonthsNoOverflow($control->test_interval_months) : null,
                ])->save();
            }

            return $test;
        });
    }
}
