<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Sheet;

/**
 * The chapters of the application technical documentation, following the
 * owner's template (docs/templates/application-technical-documentation.md).
 * Chapters are added here as they are built; forms, validation, completeness
 * and display all follow this definition.
 */
final class SheetSchema
{
    /** Chapter 0: document control, at the top of the template. */
    public const int DOCUMENT_CONTROL = 0;

    /**
     * @return array<int, list<Section>> By chapter number.
     */
    public static function chapters(): array
    {
        $chapters = [];

        foreach (self::sections() as $section) {
            $chapters[$section->chapter][] = $section;
        }

        return $chapters;
    }

    public static function section(string $key): ?Section
    {
        foreach (self::sections() as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        return null;
    }

    public static function chapterTitle(int $chapter): string
    {
        return __('applications::sheet.chapters.'.$chapter);
    }

    /**
     * @return list<Section>
     */
    public static function sections(): array
    {
        return [
            new Section('doc', self::DOCUMENT_CONTROL, [
                Field::user('document_owner', required: true),
                Field::text('document_location'),
            ]),
            new Section('1_1', 1, [
                Field::textarea('description', required: true),
            ]),
            new Section('1_2_1', 1, [
                Field::contacts('internal_support', 'support_teams'),
            ]),
            new Section('1_2_2', 1, [
                Field::supplier('supplier'),
                Field::contract('contract'),
                Field::url('support_portal'),
                Field::email('support_email'),
            ]),
            new Section('1_3', 1, [
                Field::option('status', 'application_status', required: true),
                Field::option('criticality', 'business_criticality', required: true),
                Field::choices('exposure', ['internal', 'internet', 'third_party']),
                Field::option('cartography', 'cartography_profile', allowOther: true),
            ]),
            new Section('1_4', 1, [
                Field::table('environments', [
                    Field::option('environment', 'environments', required: true, allowOther: true),
                    Field::url('url'),
                    Field::text('purpose'),
                    Field::text('access'),
                ], required: true),
            ]),
            new Section('1_5', 1, [
                Field::table('documents', [
                    Field::option('type', 'document_types', required: true, allowOther: true),
                    Field::text('name', required: true),
                    Field::url('link'),
                ]),
            ]),
            new Section('1_6', 1, [
                Field::text('availability'),
                Field::option('support_hours', 'support_hours', allowOther: true),
                Field::text('response_times'),
                Field::text('maintenance_window'),
                Field::text('change_freeze'),
                Field::text('maintenance_notification'),
            ]),
        ];
    }
}
