<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Forms\FormSchema;

function laptopForm(): FormSchema
{
    return FormSchema::fromArray([
        ['key' => 'model', 'label' => 'Model', 'type' => 'select', 'required' => true, 'options' => ['Standard 14"', 'Developer, 16"']],
        ['key' => 'needed_by', 'label' => 'Needed by', 'type' => 'date', 'required' => true],
        ['key' => 'monitors', 'label' => 'Extra monitors', 'type' => 'number'],
        ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ['key' => 'policy_ok', 'label' => 'I accept the device policy', 'type' => 'checkbox', 'required' => true],
    ]);
}

it('round-trips its definition', function (): void {
    expect(FormSchema::fromArray(laptopForm()->toArray()))->toEqual(laptopForm());
});

it('validates answers and keeps labels and types with the values', function (): void {
    $answers = laptopForm()->answer([
        'model' => 'Developer, 16"',
        'needed_by' => '2026-11-20',
        'monitors' => '2',
        'policy_ok' => '1',
        'unexpected' => 'dropped',
    ], app('validator'));

    expect($answers)->toBe([
        ['key' => 'model', 'label' => 'Model', 'type' => 'select', 'value' => 'Developer, 16"'],
        ['key' => 'needed_by', 'label' => 'Needed by', 'type' => 'date', 'value' => '2026-11-20'],
        ['key' => 'monitors', 'label' => 'Extra monitors', 'type' => 'number', 'value' => 2],
        ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'value' => null],
        ['key' => 'policy_ok', 'label' => 'I accept the device policy', 'type' => 'checkbox', 'value' => true],
    ]);
});

it('rejects invalid answers, keyed by field', function (array $answers, string $field): void {
    try {
        laptopForm()->answer($answers, app('validator'));
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'unknown option' => [['model' => 'Gaming rig', 'needed_by' => '2026-11-20', 'policy_ok' => '1'], 'model'],
    'missing required' => [['model' => 'Standard 14"', 'policy_ok' => '1'], 'needed_by'],
    'bad date' => [['model' => 'Standard 14"', 'needed_by' => '20.11.2026', 'policy_ok' => '1'], 'needed_by'],
    'required checkbox unticked' => [['model' => 'Standard 14"', 'needed_by' => '2026-11-20', 'policy_ok' => '0'], 'policy_ok'],
    'not a number' => [['model' => 'Standard 14"', 'needed_by' => '2026-11-20', 'policy_ok' => '1', 'monitors' => 'two'], 'monitors'],
]);

it('rejects malformed definitions', function (array $definition): void {
    FormSchema::fromArray($definition);
})->with([
    'bad key' => [[['key' => 'Bad Key', 'label' => 'x', 'type' => 'text']]],
    'duplicate keys' => [[['key' => 'a', 'label' => 'A', 'type' => 'text'], ['key' => 'a', 'label' => 'B', 'type' => 'text']]],
    'select without options' => [[['key' => 'a', 'label' => 'A', 'type' => 'select']]],
    'unknown type' => [[['key' => 'a', 'label' => 'A', 'type' => 'file']]],
    'not a list' => [['a' => ['key' => 'a', 'label' => 'A', 'type' => 'text']]],
])->throws(InvalidArgumentException::class);
