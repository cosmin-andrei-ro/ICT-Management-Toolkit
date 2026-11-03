<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Api\ApiQuery;
use Modules\Documentation\Application\SpaceAccess;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;

/**
 * Documentation, read-only (ADR 0065): the spaces the token's user may read,
 * and their published pages - never drafts. Bodies are Markdown.
 */
final readonly class DocumentationController
{
    public function __construct(private Gate $gate, private SpaceAccess $access) {}

    /**
     * List the spaces the user may read.
     */
    public function spaces(Request $request): AnonymousResourceCollection
    {
        $user = $this->user($request);

        return ApiQuery::collection(ApiQuery::paginate($request, Space::query()->tap(fn (Builder $query) => $this->access->readable($query, $user)), [], ['name' => 'name'], 'name'), fn (Space $space): array => [
            'id' => $space->id, 'name' => $space->name, 'description' => $space->description, 'visibility' => $space->visibility->value,
        ]);
    }

    /**
     * List published pages of readable spaces. Filters: `filter[space_id]`, `filter[search]` (title). Sorts: `title`, `published_at`.
     */
    public function pages(Request $request): AnonymousResourceCollection
    {
        $user = $this->user($request);

        return ApiQuery::collection(ApiQuery::paginate($request, Page::query()->whereNotNull('published_version')->whereHas('space', fn (Builder $query) => $this->access->readable($query, $user)), [
            'space_id' => fn (Builder $query, string $value) => $query->where('space_id', Str::isUuid($value) ? $value : null),
            'search' => fn (Builder $query, string $value) => $query->where('published_title', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%'),
        ], ['title' => 'published_title', 'published_at' => 'published_at'], 'title'), fn (Page $page): array => [
            'id' => $page->id, 'space_id' => $page->space_id, 'parent_id' => $page->parent_id, 'title' => $page->published_title,
            'version' => $page->published_version, 'published_at' => $page->published_at?->toIso8601String(), 'review_on' => $page->review_on?->toDateString(),
        ]);
    }

    /**
     * Show a published page, its Markdown body included.
     */
    public function page(Request $request, Page $page): JsonResponse
    {
        $this->gate->authorize('view', $page);
        abort_if($page->published_version === null, 404);

        return response()->json(['data' => [
            'id' => $page->id, 'space_id' => $page->space_id, 'parent_id' => $page->parent_id, 'title' => $page->published_title, 'body' => $page->published_body,
            'version' => $page->published_version, 'published_at' => $page->published_at?->toIso8601String(), 'review_on' => $page->review_on?->toDateString(),
        ]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
