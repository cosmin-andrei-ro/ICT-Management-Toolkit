<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Applications\Domain\Models\Sheet;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\Dependency;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Core\Domain\Events\RiskSignalsChanged;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Itam\Domain\Models\License;
use Modules\Risk\Domain\Models\Suggestion;
use Modules\Risk\Tests\Support\RiskScenario;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    $this->risks = RiskScenario::create();
});

/**
 * @return list<string> Keys of the signals of one source.
 */
function signalKeys(string $prefix): array
{
    return array_values(array_filter(
        array_map(fn (RiskSignal $signal): string => $signal->key, app(RiskSignals::class)->collect()['signals']),
        fn (string $key): bool => str_starts_with($key, $prefix.'.'),
    ));
}

it('suggests risks from the documentation of applications in force', function (): void {
    $app = $this->risks->payrollApp;
    (new Sheet)->forceFill([
        'organization_id' => $app->organization_id, 'application_id' => $app->id, 'status' => 'approved', 'is_major' => true, 'major' => 1, 'minor' => 0, 'approved_at' => now(),
        'content' => [
            '1_3' => ['exposure' => ['internet']],
            '2_1' => ['categories' => ['personal' => ['name'], 'payment_card' => ['pan']]],
            '3_1' => ['mfa' => 'no'],
            '6_6' => ['libraries' => [['name' => 'Old framework', 'support_until' => today()->subYear()->toDateString()], ['name' => 'Current one', 'support_until' => today()->addYear()->toDateString()]]],
            '8_1' => ['certificates' => [['name' => 'payroll.company.local', 'expires_on' => today()->addDays(10)->toDateString()]]],
        ],
    ])->save();

    expect(signalKeys('applications'))->toEqualCanonicalizing([
        'applications.data:'.$app->id, 'applications.no_mfa:'.$app->id, 'applications.certificates:'.$app->id, 'applications.unsupported:'.$app->id,
    ]);
});

it('suggests risks from contracts ending, supplier dependencies, plans, incidents and licences', function (): void {
    Contract::factory()->create(['supplier_id' => $this->risks->vendor->id, 'end_date' => today()->addDays(20), 'auto_renews' => false]);
    Contract::factory()->create(['supplier_id' => $this->risks->vendor->id, 'end_date' => today()->addDays(20), 'auto_renews' => true]);
    expect(signalKeys('core'))->toHaveCount(1);

    // A tier-1 process depending on a supplier, assessed and not covered by any plan.
    $process = BusinessProcess::factory()->create(['name' => 'Salaries', 'rto_minutes' => 240, 'tier' => 1, 'approved_at' => now()]);
    $assessment = Assessment::factory()->create(['process_id' => $process->id, 'status' => 'approved', 'decided_at' => now(), 'rto_minutes' => 240]);
    (new Dependency)->forceFill(['organization_id' => $process->organization_id, 'assessment_id' => $assessment->id, 'kind' => 'supplier', 'supplier_id' => $this->risks->vendor->id])->save();
    expect(signalKeys('bia'))->toContain('bia.supplier:'.$process->id.':'.$this->risks->vendor->id)
        ->and(signalKeys('bcm'))->toContain('bcm.no_plan:'.$process->id);

    // Three incidents on the same CMDB item within 90 days.
    foreach (range(1, 3) as $index) {
        $ticket = Ticket::factory()->create(['requester_id' => $this->risks->employee->id]);
        (new CiLink)->forceFill(['organization_id' => $ticket->organization_id, 'configuration_item_id' => $this->risks->payrollDb->id, 'linkable_type' => 'ticketing.ticket', 'linkable_id' => $ticket->id])->save();
    }
    expect(signalKeys('ticketing'))->toBe(['ticketing.recurring:'.$this->risks->payrollDb->id]);

    License::factory()->create(['expires_on' => today()->addDays(5)]);
    License::factory()->create(['expires_on' => today()->addDays(90)]);
    expect(signalKeys('itam'))->toHaveCount(1);
});

it('refreshes the suggestions when an application\'s documentation comes into force', function (): void {
    $app = $this->risks->payrollApp;
    $sheet = (new Sheet)->forceFill(['organization_id' => $app->organization_id, 'application_id' => $app->id, 'status' => 'approved', 'is_major' => true, 'major' => 1, 'minor' => 0, 'approved_at' => now(),
        'content' => ['1_3' => ['exposure' => ['internet']], '3_1' => ['mfa' => 'no']]]);
    $sheet->save();
    expect(Suggestion::query()->count())->toBe(0);

    // What SheetInForce announces once a version is in force.
    event(new RiskSignalsChanged($app->organization_id));

    expect(Suggestion::query()->where('key', 'applications.no_mfa:'.$app->id)->exists())->toBeTrue();
});
