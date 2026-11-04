<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * The search in the page header. Each module returns only what the person may
 * see. The terms are not logged: people search for names of colleagues.
 */
final readonly class SearchController
{
    public function __construct(private LinkedRecords $records) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $term = $request->query('q');
        $term = is_string($term) ? trim(mb_substr($term, 0, 200)) : '';

        return view('core::search', [
            'term' => $term,
            'results' => $user instanceof User ? $this->records->searchEverywhere($term, $user) : [],
            'tooShort' => $term !== '' && mb_strlen($term) < LinkedRecords::MIN_SEARCH_LENGTH,
        ]);
    }
}
