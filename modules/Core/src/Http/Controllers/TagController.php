<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\Tag;
use Modules\Core\Domain\Models\User;

/**
 * Everything carrying a tag that the person may see. Each module decides what
 * that is, as it does for the global search.
 */
final readonly class TagController
{
    private const int PER_KIND = 100;

    public function __construct(
        private LinkedRecords $records,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, Tag $tag): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->gate->allows('view', $tag), 403);

        $results = [];

        /** @var list<string> $types */
        $types = DB::table('core_taggables')->where('tag_id', $tag->id)->distinct()->pluck('taggable_type')->all();

        foreach ($types as $type) {
            /** @var list<string> $ids */
            $ids = DB::table('core_taggables')->where('tag_id', $tag->id)->where('taggable_type', $type)->limit(self::PER_KIND)->pluck('taggable_id')->all();
            $found = $this->records->resolve($type, $ids, $user);

            if ($found !== []) {
                $results[$this->records->kindLabel($type)] = $found;
            }
        }

        ksort($results);

        return view('core::tag', ['tag' => $tag, 'results' => $results]);
    }
}
