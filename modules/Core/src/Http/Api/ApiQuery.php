<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Api;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Validation\ValidationException;

/**
 * The list conventions of every API endpoint (ADR 0061):
 *
 * - `?filter[name]=value` - only the filters the endpoint declares;
 * - `?sort=name,-other` - only the declared sorts, "-" for descending;
 * - `?per_page=1..100` (25 by default) and `?page=n`.
 *
 * Anything else is refused with a 422 naming the parameter, so a typo in an
 * integration fails loudly instead of returning everything.
 */
final class ApiQuery
{
    private const int DEFAULT_PER_PAGE = 25;

    private const int MAX_PER_PAGE = 100;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, Closure(Builder<TModel>, string): mixed>  $filters  Name => applies the value.
     * @param  array<string, string>  $sorts  Name => column.
     * @param  string  $defaultSort  Same syntax as the parameter, e.g. "-created_at".
     * @return LengthAwarePaginator<int, TModel>
     */
    public static function paginate(Request $request, Builder $query, array $filters, array $sorts, string $defaultSort): LengthAwarePaginator
    {
        $filter = $request->query('filter', []);

        if (! is_array($filter)) {
            throw ValidationException::withMessages(['filter' => __('core::api.filter_shape')]);
        }

        foreach ($filter as $name => $value) {
            if (! is_string($name) || ! isset($filters[$name])) {
                throw ValidationException::withMessages(['filter' => __('core::api.unknown_filter', ['name' => (string) $name, 'allowed' => implode(', ', array_keys($filters)) ?: '—'])]);
            }

            if (! is_string($value) || mb_strlen($value) > 200) {
                throw ValidationException::withMessages(["filter.{$name}" => __('core::api.filter_value')]);
            }

            ($filters[$name])($query, $value);
        }

        $sort = $request->query('sort', $defaultSort);

        foreach (explode(',', is_string($sort) && $sort !== '' ? $sort : $defaultSort) as $field) {
            $descending = str_starts_with($field, '-');
            $name = ltrim($field, '-');

            if (! isset($sorts[$name])) {
                throw ValidationException::withMessages(['sort' => __('core::api.unknown_sort', ['name' => $name, 'allowed' => implode(', ', array_keys($sorts)) ?: '—'])]);
            }

            $query->orderBy($sorts[$name], $descending ? 'desc' : 'asc');
        }

        $perPage = $request->query('per_page', (string) self::DEFAULT_PER_PAGE);

        if (! is_string($perPage) || preg_match('/^\d{1,3}$/', $perPage) !== 1 || (int) $perPage < 1 || (int) $perPage > self::MAX_PER_PAGE) {
            throw ValidationException::withMessages(['per_page' => __('core::api.per_page', ['max' => self::MAX_PER_PAGE])]);
        }

        // A stable order across pages, even when the sort column has ties.
        return $query->orderBy($query->getModel()->getQualifiedKeyName())->paginate((int) $perPage)->withQueryString();
    }

    /**
     * A page of records as JSON, each mapped to an array, with the same
     * `data` / `links` / `meta` shape as API resources: for read-only lists
     * that need no resource class.
     *
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $page
     * @param  Closure(TModel): array<string, mixed>  $map
     */
    public static function collection(LengthAwarePaginator $page, Closure $map): AnonymousResourceCollection
    {
        if ($page instanceof AbstractPaginator) {
            $items = [];

            foreach ($page->items() as $model) {
                $items[] = $map($model);
            }

            $page->setCollection(collect($items));
        }

        return JsonResource::collection($page);
    }
}
