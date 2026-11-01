<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Exports;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use LogicException;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Core\Infrastructure\Exports\SpreadsheetWriter;
use RuntimeException;
use Throwable;

/**
 * Generates an export's file on the private "exports" disk, in the export's organization.
 */
final class GenerateExport implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly string $exportId) {}

    public function handle(OrganizationContext $context, ExportRegistry $registry, Container $container, SpreadsheetWriter $writer, Filesystem $filesystem, Config $config): void
    {
        $export = Export::query()->withoutGlobalScope(OrganizationScope::class)->find($this->exportId);

        if ($export === null || $export->status !== ExportStatus::Pending) {
            return;
        }

        $context->runAs($export->organization_id, function () use ($export, $registry, $container, $writer, $filesystem, $config): void {
            $temporary = tempnam(sys_get_temp_dir(), 'export');

            try {
                $source = $container->make($registry->source($export->type));

                if (! $source instanceof ExportSource) {
                    throw new LogicException("Export source for [{$export->type}] does not implement ExportSource.");
                }

                $rows = $writer->write((string) $temporary, $export->format, $source->sheets($export));

                $disk = 'exports';
                $path = $export->organization_id.'/'.$export->id.'.'.$export->format->value;
                $stream = fopen((string) $temporary, 'rb') ?: throw new RuntimeException('Cannot read the generated export.');

                try {
                    $filesystem->disk($disk)->writeStream($path, $stream);
                } finally {
                    fclose($stream);
                }

                $days = $config->get('core.exports.retention_days');

                $export->forceFill([
                    'status' => ExportStatus::Ready,
                    'disk' => $disk,
                    'path' => $path,
                    'filename' => $source->filename($export).'.'.$export->format->value,
                    'row_count' => $rows,
                    'completed_at' => now(),
                    'expires_at' => now()->addDays(is_int($days) && $days > 0 ? $days : 7),
                ])->save();
            } catch (Throwable $exception) {
                $export->forceFill(['status' => ExportStatus::Failed, 'completed_at' => now()])->save();
                report($exception);
            } finally {
                if (is_string($temporary)) {
                    @unlink($temporary);
                }
            }
        });
    }
}
