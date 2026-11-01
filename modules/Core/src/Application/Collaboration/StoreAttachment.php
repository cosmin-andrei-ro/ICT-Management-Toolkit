<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Collaboration;

use finfo;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Exceptions\CrossOrganizationAccess;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\User;
use Throwable;

/**
 * Stores an uploaded file for any entity (CLAUDE.md §6, uploads): type detected from
 * content and checked against an allowlist, size limit, generated storage name,
 * private disk, SHA-256 checksum. Authorization is the caller's job.
 */
final readonly class StoreAttachment
{
    public function __construct(
        private Config $config,
        private Filesystem $filesystem,
    ) {}

    /**
     * @throws ValidationException keyed to "file".
     */
    public function handle(Model $parent, User $uploader, UploadedFile $file, bool $internal): Attachment
    {
        $organizationId = $parent->getAttribute('organization_id');

        if (! is_string($organizationId)) {
            throw CrossOrganizationAccess::onCreate(Attachment::class);
        }

        $mimeType = $this->assertAcceptable($file);

        $configuredDisk = $this->config->get('core.attachments.disk');
        $disk = is_string($configuredDisk) && $configuredDisk !== '' ? $configuredDisk : 'attachments';
        $id = (string) Str::uuid7();
        // No extension and no client-supplied name on disk: nothing to execute or traverse.
        $path = $organizationId.'/'.now()->format('Y/m').'/'.$id;

        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages(['file' => __('core::attachments.invalid')]);
        }

        try {
            $this->filesystem->disk($disk)->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        $attachment = new Attachment;
        $attachment->forceFill([
            'id' => $id,
            'organization_id' => $organizationId,
            'attachable_type' => $parent->getMorphClass(),
            'attachable_id' => $parent->getKey(),
            'uploaded_by' => $uploader->id,
            'is_internal' => $internal,
            'disk' => $disk,
            'path' => $path,
            'original_name' => self::cleanName($file->getClientOriginalName()),
            'mime_type' => $mimeType,
            'size' => (int) $file->getSize(),
            'checksum_sha256' => (string) hash_file('sha256', $file->getRealPath()),
        ]);

        try {
            $attachment->save();
        } catch (Throwable $exception) {
            $this->filesystem->disk($disk)->delete($path);

            throw $exception;
        }

        return $attachment;
    }

    /**
     * Checks a file against the attachment rules without storing it, for callers
     * that must know up front (e.g. email intake listing refused files).
     *
     * @return string The detected MIME type.
     *
     * @throws ValidationException keyed to "file".
     */
    public function assertAcceptable(UploadedFile $file): string
    {
        $mimeType = $this->detectMimeType($file);
        $this->validate($file, $mimeType);

        return $mimeType;
    }

    /**
     * For display and the download name only; never used as a path.
     */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"<>:\/\\\\|?*]+/u', '_', $name);
        $name = trim($name, " .\t");

        return mb_substr($name !== '' ? $name : 'file', 0, 200);
    }

    private function detectMimeType(UploadedFile $file): string
    {
        // Content sniffing (finfo), not the client's Content-Type or extension.
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        return (string) $finfo->file($file->getRealPath());
    }

    private function validate(UploadedFile $file, string $mimeType): void
    {
        /** @var list<string> $allowed */
        $allowed = $this->config->get('core.attachments.allowed_mime_types', []);
        $maxKilobytes = $this->config->get('core.attachments.max_kilobytes');
        $maxBytes = (is_int($maxKilobytes) && $maxKilobytes > 0 ? $maxKilobytes : 20480) * 1024;

        if (! $file->isValid() || $file->getSize() === false || $file->getSize() === 0) {
            throw ValidationException::withMessages(['file' => __('core::attachments.invalid')]);
        }

        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['file' => __('core::attachments.too_large', ['max' => intdiv($maxBytes, 1024 * 1024)])]);
        }

        if (! in_array($mimeType, $allowed, true)) {
            throw ValidationException::withMessages(['file' => __('core::attachments.type_not_allowed')]);
        }
    }
}
