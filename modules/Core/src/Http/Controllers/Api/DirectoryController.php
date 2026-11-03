<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Api\ApiQuery;

/**
 * The organization's directory, read-only (ADR 0065): people (name and
 * e-mail only, to resolve ids returned elsewhere), teams, departments and
 * locations.
 */
final readonly class DirectoryController
{
    public function __construct(private Gate $gate, private OrganizationContext $context) {}

    /**
     * List the organization's people. Filters: `filter[search]` (name or e-mail), `filter[team_id]`, `filter[active]` (true/false). Sort: `name`.
     */
    public function people(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('core.user.view');
        $organizationId = $this->context->requireId();

        return ApiQuery::collection(ApiQuery::paginate($request, User::query()->whereHas('organizations', fn (Builder $query) => $query->whereKey($organizationId))->whereNull('anonymized_at'), [
            'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', self::like($value))->orWhere('email', 'ilike', self::like($value))),
            'team_id' => fn (Builder $query, string $value) => $query->whereIn('id', fn (QueryBuilder $members) => $members->select('user_id')->from('core_team_user')->where('team_id', Str::isUuid($value) ? $value : null)),
            'active' => fn (Builder $query, string $value) => $query->where('is_active', $value === 'true'),
        ], ['name' => 'name'], 'name'), fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active]);
    }

    /**
     * List teams, with their lead and members' ids.
     */
    public function teams(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('core.team.view');

        return ApiQuery::collection(ApiQuery::paginate($request, Team::query()->with('members:id'), [
            'search' => fn (Builder $query, string $value) => $query->where('name', 'ilike', self::like($value)),
        ], ['name' => 'name'], 'name'), fn (Team $team): array => [
            'id' => $team->id, 'name' => $team->name, 'description' => $team->description, 'email' => $team->email, 'lead_id' => $team->lead_id,
            'member_ids' => $team->members->pluck('id')->all(),
        ]);
    }

    /**
     * List departments.
     */
    public function departments(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Department::class);

        return ApiQuery::collection(ApiQuery::paginate($request, Department::query(), [
            'search' => fn (Builder $query, string $value) => $query->where('name', 'ilike', self::like($value)),
        ], ['name' => 'name', 'code' => 'code'], 'name'), fn (Department $department): array => [
            'id' => $department->id, 'parent_id' => $department->parent_id, 'name' => $department->name, 'code' => $department->code, 'manager_id' => $department->manager_id,
        ]);
    }

    /**
     * List locations: sites, buildings, floors and rooms.
     */
    public function locations(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Location::class);

        return ApiQuery::collection(ApiQuery::paginate($request, Location::query(), [
            'kind' => fn (Builder $query, string $value) => $query->where('kind', $value),
            'parent_id' => fn (Builder $query, string $value) => $query->where('parent_id', Str::isUuid($value) ? $value : null),
            'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', self::like($value))->orWhere('code', 'ilike', self::like($value))),
        ], ['name' => 'name', 'code' => 'code'], 'name'), fn (Location $location): array => [
            'id' => $location->id, 'parent_id' => $location->parent_id, 'kind' => $location->kind, 'name' => $location->name, 'code' => $location->code,
            'address' => $location->address, 'is_active' => $location->is_active,
        ]);
    }

    private static function like(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }
}
