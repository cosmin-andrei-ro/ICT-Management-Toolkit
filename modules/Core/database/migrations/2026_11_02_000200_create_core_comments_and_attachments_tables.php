<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-cutting comments and attachments, polymorphic on any business entity
 * (CLAUDE.md §5.2). The parent's policy decides who may read or add them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuidMorphs('commentable');
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            // Internal by default: forgetting the flag must not expose a note to requesters.
            $table->boolean('is_internal')->default(true);
            $table->timestampTz('edited_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['commentable_type', 'commentable_id', 'created_at']);
        });

        Schema::create('core_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuidMorphs('attachable');
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_internal')->default(true);
            $table->string('disk', 64);
            // Generated name; the original is kept only for display and download.
            $table->string('path', 512)->unique();
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->char('checksum_sha256', 64);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_attachments');
        Schema::dropIfExists('core_comments');
    }
};
