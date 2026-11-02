<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documentation and knowledge base (ADR 0040): spaces with their own
 * visibility and editors, pages in a tree written in Markdown, every saved
 * version kept, links to records of other modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_spaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            // staff: IT staff; everyone: every member of the organization; teams: the chosen teams.
            $table->string('visibility', 16)->default('staff');
            // Controlled spaces publish pages only once a space owner approved them.
            $table->boolean('requires_approval')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement("ALTER TABLE documentation_spaces ADD CONSTRAINT documentation_spaces_visibility_check CHECK (visibility IN ('staff', 'everyone', 'teams'))");
        DB::statement('CREATE UNIQUE INDEX documentation_spaces_name_unique ON documentation_spaces (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('documentation_space_owners', function (Blueprint $table): void {
            $table->uuid('space_id');
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['space_id', 'user_id']);
            $table->foreign('space_id')->references('id')->on('documentation_spaces')->cascadeOnDelete();
        });

        // Teams that read (visibility "teams") or edit the space.
        Schema::create('documentation_space_teams', function (Blueprint $table): void {
            $table->uuid('space_id');
            $table->foreignUuid('team_id')->constrained('core_teams')->cascadeOnDelete();
            $table->string('access', 8);

            $table->primary(['space_id', 'team_id']);
            $table->foreign('space_id')->references('id')->on('documentation_spaces')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE documentation_space_teams ADD CONSTRAINT documentation_space_teams_access_check CHECK (access IN ('read', 'edit'))");

        Schema::create('documentation_pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('space_id');
            $table->uuid('parent_id')->nullable();
            $table->string('title', 255);
            // The current text, the one readers see; every version is kept in documentation_page_versions.
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['space_id', 'parent_id', 'position']);
        });
        DB::statement('ALTER TABLE documentation_pages ADD CONSTRAINT documentation_pages_space_fk FOREIGN KEY (organization_id, space_id) REFERENCES documentation_spaces (organization_id, id) ON DELETE CASCADE');
        // Added once the table has its primary key.
        DB::statement('ALTER TABLE documentation_pages ADD CONSTRAINT documentation_pages_parent_fk FOREIGN KEY (parent_id) REFERENCES documentation_pages (id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE documentation_pages ADD CONSTRAINT documentation_pages_not_own_parent CHECK (parent_id IS NULL OR parent_id <> id)');
        // Full-text search across languages: the "simple" configuration does not stem English only.
        DB::statement(<<<'SQL'
            ALTER TABLE documentation_pages ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '')), 'A') || setweight(to_tsvector('simple', coalesce(body, '')), 'B')
            ) STORED
            SQL);
        DB::statement('CREATE INDEX documentation_pages_search ON documentation_pages USING GIN (search_vector)');

        Schema::create('documentation_page_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('documentation_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 255);
            $table->text('body');
            $table->string('summary', 500)->nullable();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');

            $table->unique(['page_id', 'version']);
        });

        Schema::create('documentation_page_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('documentation_pages')->cascadeOnDelete();
            // A record of another module (morph type and id, no foreign key: modules stay apart), its title kept.
            $table->string('linked_type', 64);
            $table->uuid('linked_id');
            $table->string('title', 255);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');

            $table->unique(['page_id', 'linked_type', 'linked_id']);
            $table->index(['linked_type', 'linked_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_page_links');
        Schema::dropIfExists('documentation_page_versions');
        Schema::dropIfExists('documentation_pages');
        Schema::dropIfExists('documentation_space_teams');
        Schema::dropIfExists('documentation_space_owners');
        Schema::dropIfExists('documentation_spaces');
    }
};
