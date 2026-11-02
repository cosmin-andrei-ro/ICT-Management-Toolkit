<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Options;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Applications\Domain\Models\OptionValue;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The current organization's dropdown values. A list starts from the defaults
 * the first time it is used; once it exists, the administrators own it.
 */
final class OptionCatalog
{
    /** @var array<string, Collection<int, OptionValue>> */
    private array $loaded = [];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly OrganizationContext $context,
    ) {}

    /**
     * Every value of the list, active or not, in order.
     *
     * @return Collection<int, OptionValue>
     */
    public function values(string $list): Collection
    {
        $cacheKey = $this->context->requireId().':'.$list;

        return $this->loaded[$cacheKey] ??= $this->load($list);
    }

    /**
     * @return array<string, string> Key => label of the active values.
     */
    public function choices(string $list): array
    {
        return $this->values($list)->filter(fn (OptionValue $value): bool => $value->is_active)->mapWithKeys(fn (OptionValue $value): array => [$value->key => $value->label])->all();
    }

    public function isActiveKey(string $list, string $key): bool
    {
        return array_key_exists($key, $this->choices($list));
    }

    /**
     * The label of a stored value: an inactive value keeps its label; free
     * text ("Other") is shown as written.
     */
    public function label(string $list, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->values($list)->first(fn (OptionValue $option): bool => $option->key === $value)->label ?? $value;
    }

    public function forget(): void
    {
        $this->loaded = [];
    }

    /**
     * @return Collection<int, OptionValue>
     */
    private function load(string $list): Collection
    {
        $values = OptionValue::query()->where('list', $list)->orderBy('position')->orderBy('label')->get();

        if ($values->isNotEmpty() || ! isset(DefaultOptions::lists()[$list])) {
            return $values;
        }

        $organizationId = $this->context->requireId();

        $this->db->transaction(function () use ($list, $organizationId): void {
            $position = 0;

            foreach (DefaultOptions::lists()[$list] as $key => $label) {
                $this->db->table('applications_option_values')->insertOrIgnore([
                    'id' => (string) Str::uuid7(),
                    'organization_id' => $organizationId,
                    'list' => $list,
                    'key' => $key,
                    'label' => $label,
                    'position' => $position++,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return OptionValue::query()->where('list', $list)->orderBy('position')->orderBy('label')->get();
    }
}
