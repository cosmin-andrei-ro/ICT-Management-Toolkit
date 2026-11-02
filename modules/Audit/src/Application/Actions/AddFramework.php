<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Audit\Application\Frameworks\FrameworkCatalog;
use Modules\Audit\Domain\Models\Framework;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Adds a framework to the organization: one of the toolkit's catalog with
 * all its requirements, or its own (internal policy, customer requirements)
 * whose requirements it then enters.
 */
final readonly class AddFramework
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    /**
     * @throws InvalidArgumentException for an unknown or already added framework
     */
    public function fromCatalog(string $key): Framework
    {
        $data = FrameworkCatalog::get($key);

        if (Framework::query()->where('key', $key)->exists()) {
            throw new InvalidArgumentException('Already added.');
        }

        return $this->db->transaction(function () use ($data): Framework {
            $framework = $this->create($data['key'], $data['name'], $data['version'], $data['description'], $data['has_soa']);

            foreach ($data['requirements'] as $position => $requirement) {
                $this->db->table('audit_requirements')->insert([
                    'id' => (string) Str::uuid7(),
                    'organization_id' => $framework->organization_id,
                    'framework_id' => $framework->id,
                    'reference' => $requirement['reference'],
                    'title' => $requirement['title'],
                    'description' => $requirement['description'],
                    'group' => $requirement['group'],
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $framework;
        });
    }

    /**
     * @throws InvalidArgumentException for an empty name or one already used
     */
    public function custom(string $name, ?string $version, ?string $description, bool $hasSoa): Framework
    {
        $name = trim($name);
        $key = 'custom-'.Str::slug($name);

        if ($name === '' || Framework::query()->where('key', $key)->exists()) {
            throw new InvalidArgumentException('A framework needs a name not used yet.');
        }

        return $this->create($key, $name, $version, $description, $hasSoa);
    }

    private function create(string $key, string $name, ?string $version, ?string $description, bool $hasSoa): Framework
    {
        $framework = new Framework;
        $framework->forceFill([
            'organization_id' => $this->context->requireId(),
            'key' => mb_substr($key, 0, 64),
            'name' => mb_substr($name, 0, 255),
            'version' => $version !== null && trim($version) !== '' ? mb_substr(trim($version), 0, 64) : null,
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'has_soa' => $hasSoa,
        ])->save();

        return $framework;
    }
}
