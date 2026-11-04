<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'label' => 'Tag',
    'plural_label' => 'Tags',
    'linked_kind' => 'Tag',
    'name' => 'Name',
    'uses' => 'Records',
    'title' => 'Tags',
    'add' => 'Add',
    'add_label' => 'Add a tag',
    'remove' => 'Remove the tag :name',
    'none' => 'No tags yet.',
    'page_title' => 'Tag: :name',
    'page_intro' => 'Everything with this tag that you may see.',
    'page_none' => 'Nothing with this tag that you may see.',
    'errors' => [
        'invalid' => 'Use letters, digits, spaces, dots, dashes or underscores, up to 50 characters, starting with a letter or digit.',
        'too_many' => 'A record can have at most :max tags.',
        'taken' => 'This tag already exists.',
    ],
];
