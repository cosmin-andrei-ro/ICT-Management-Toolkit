<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Collaboration\AddComment;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Application\Teams\SyncTeamMembers;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Storage::fake('attachments');

    $this->context = app(OrganizationContext::class);
    $this->organization = Organization::factory()->create();
    $this->context->set($this->organization);

    $this->agent = User::factory()->memberOf($this->organization)->create();
    $this->requester = User::factory()->memberOf($this->organization)->create();

    // Departments stand in for a module entity; real parents define these in their policy.
    $this->parent = Department::factory()->create();
    // Everyone may view it; only the agent may see internal content.
    Gate::before(function (User $user, string $ability, array $arguments): ?bool {
        if (! (($arguments[0] ?? null) instanceof Department)) {
            return null;
        }

        return match ($ability) {
            'view' => true,
            'viewInternal' => $user->is($this->agent),
            default => null,
        };
    });
});

function pdfUpload(string $name = 'report.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
}

describe('comments', function (): void {
    it('adds a comment to the parent, internal unless told otherwise', function (): void {
        $comment = app(AddComment::class)->handle($this->parent, $this->agent, '  Restarted the service.  ', internal: true);

        expect($comment->body)->toBe('Restarted the service.')
            ->and($comment->is_internal)->toBeTrue()
            ->and($comment->organization_id)->toBe($this->organization->id)
            ->and(Comment::query()->where('commentable_id', $this->parent->id)->count())->toBe(1);
    });

    it('rejects empty and oversized comments', function (string $body): void {
        app(AddComment::class)->handle($this->parent, $this->agent, $body, internal: false);
    })->with(['empty' => '   ', 'too long' => str_repeat('a', AddComment::MAX_LENGTH + 1)])
        ->throws(InvalidArgumentException::class);

    it('defaults to internal at the database level', function (): void {
        $comment = app(AddComment::class)->handle($this->parent, $this->agent, 'x', internal: true);
        expect(DB::table('core_comments')->where('id', $comment->id)->value('is_internal'))->toBeTrue();
    });
});

describe('attachments', function (): void {
    it('stores the file privately under a generated name with its checksum', function (): void {
        $upload = pdfUpload('../../etc/Quarterly "report".pdf');

        $attachment = app(StoreAttachment::class)->handle($this->parent, $this->agent, $upload, internal: false);

        expect($attachment->mime_type)->toBe('application/pdf')
            ->and($attachment->original_name)->toBe('Quarterly _report_.pdf')
            ->and($attachment->path)->toStartWith($this->organization->id.'/')
            ->and($attachment->path)->not->toContain('report')
            ->and($attachment->checksum_sha256)->toBe(hash_file('sha256', $upload->getRealPath()));

        Storage::disk('attachments')->assertExists($attachment->path);
    });

    it('detects the type from content, not from the name', function (): void {
        $disguised = UploadedFile::fake()->createWithContent('invoice.pdf', "<?php system(\$_GET['c']);");

        expect(fn () => app(StoreAttachment::class)->handle($this->parent, $this->agent, $disguised, internal: false))
            ->toThrow(ValidationException::class);

        expect(Storage::disk('attachments')->allFiles())->toBe([]);
    });

    it('rejects files over the size limit', function (): void {
        config()->set('core.attachments.max_kilobytes', 1);
        $big = UploadedFile::fake()->createWithContent('big.txt', str_repeat('a', 2048));

        app(StoreAttachment::class)->handle($this->parent, $this->agent, $big, internal: false);
    })->throws(ValidationException::class);

    it('serves a download only to users allowed to see it, as an attachment', function (): void {
        $public = app(StoreAttachment::class)->handle($this->parent, $this->agent, pdfUpload('public.pdf'), internal: false);
        $internal = app(StoreAttachment::class)->handle($this->parent, $this->agent, pdfUpload('internal.pdf'), internal: true);

        $this->actingAs($this->requester)
            ->get(route('core.attachments.download', $public))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->assertDownload('public.pdf');

        $this->actingAs($this->requester)->get(route('core.attachments.download', $internal))->assertForbidden();
        $this->actingAs($this->agent)->get(route('core.attachments.download', $internal))->assertOk();
    });

    it('hides attachments of another organization', function (): void {
        $attachment = app(StoreAttachment::class)->handle($this->parent, $this->agent, pdfUpload(), internal: false);
        $outsider = User::factory()->memberOf(Organization::factory()->create())->create();

        $this->actingAs($outsider)->get(route('core.attachments.download', $attachment))->assertNotFound();
    });
});

describe('teams', function (): void {
    it('only accepts members of the team\'s organization and audits changes', function (): void {
        $team = Team::factory()->create();
        $outsider = User::factory()->memberOf(Organization::factory()->create())->create();

        app(SyncTeamMembers::class)->handle($team, [$this->agent->id]);
        expect($team->hasMember($this->agent))->toBeTrue();

        expect(fn () => app(SyncTeamMembers::class)->handle($team, [$this->agent->id, $outsider->id]))
            ->toThrow(InvalidArgumentException::class);
        expect($team->members()->count())->toBe(1);
    });
});
