<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Publishing and review (ADR 0041): readers see the published text, editors
 * the latest; in controlled spaces a version is published once a space owner
 * approved it. Pages are reviewed at an interval set per space.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentation_spaces', function (Blueprint $table): void {
            $table->unsignedSmallInteger('review_months')->default(12);
        });
        DB::statement('ALTER TABLE documentation_spaces ADD CONSTRAINT documentation_spaces_review_months_check CHECK (review_months BETWEEN 1 AND 60)');

        Schema::table('documentation_pages', function (Blueprint $table): void {
            // What readers see; null until a version is published.
            $table->string('published_title', 255)->nullable();
            $table->text('published_body')->nullable();
            $table->unsignedInteger('published_version')->nullable();
            $table->timestampTz('published_at')->nullable();
            // The version waiting for a space owner's approval, and who asked.
            $table->unsignedInteger('pending_version')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->date('review_on')->nullable();
            $table->index('review_on');
        });

        // Pages written so far were readable at once: their text is published as it is.
        DB::statement('UPDATE documentation_pages SET published_title = title, published_body = body, published_version = version, published_at = updated_at, review_on = (updated_at + interval \'12 months\')::date');

        Schema::table('documentation_page_versions', function (Blueprint $table): void {
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
        });

        // Search covers the published text only: drafts of controlled spaces stay out.
        DB::statement('DROP INDEX documentation_pages_search');
        DB::statement('ALTER TABLE documentation_pages DROP COLUMN search_vector');
        DB::statement(<<<'SQL'
            ALTER TABLE documentation_pages ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(published_title, '')), 'A') || setweight(to_tsvector('simple', coalesce(published_body, '')), 'B')
            ) STORED
            SQL);
        DB::statement('CREATE INDEX documentation_pages_search ON documentation_pages USING GIN (search_vector)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX documentation_pages_search');
        DB::statement('ALTER TABLE documentation_pages DROP COLUMN search_vector');
        DB::statement(<<<'SQL'
            ALTER TABLE documentation_pages ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '')), 'A') || setweight(to_tsvector('simple', coalesce(body, '')), 'B')
            ) STORED
            SQL);
        DB::statement('CREATE INDEX documentation_pages_search ON documentation_pages USING GIN (search_vector)');

        Schema::table('documentation_page_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });

        Schema::table('documentation_pages', function (Blueprint $table): void {
            $table->dropIndex(['review_on']);
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['published_title', 'published_body', 'published_version', 'published_at', 'pending_version', 'submitted_at', 'rejection_note', 'review_on']);
        });

        DB::statement('ALTER TABLE documentation_spaces DROP CONSTRAINT documentation_spaces_review_months_check');

        Schema::table('documentation_spaces', function (Blueprint $table): void {
            $table->dropColumn('review_months');
        });
    }
};
