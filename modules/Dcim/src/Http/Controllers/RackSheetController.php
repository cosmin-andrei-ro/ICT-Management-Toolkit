<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Application\Pdf\PdfDownload;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Application\Elevation;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\PowerConnection;
use Modules\Dcim\Domain\Models\Rack;
use Symfony\Component\HttpFoundation\Response;

/**
 * A rack on paper (ADR 0051): both sides, what is mounted, its power and its
 * cables - to take into the room, or as a PDF recorded as an export.
 */
final readonly class RackSheetController
{
    public function __construct(
        private Gate $gate,
        private PdfDownload $pdf,
        private PhysicalAssets $assets,
    ) {}

    public function show(Rack $rack): View
    {
        $this->gate->authorize('view', $rack);

        return view('dcim::print.rack', [...$this->data($rack), 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Rack $rack, Request $request): Response
    {
        $this->gate->authorize('view', $rack);
        $html = view('dcim::print.rack', [...$this->data($rack), 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();
        $user = $request->user();

        return $this->pdf->respond($html, 'rack-'.Str::slug($rack->name), route('dcim.racks.print', $rack), $rack, ['sheet' => 'rack'], $user instanceof User ? $user : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Rack $rack): array
    {
        $rack->load(['location', 'mounts']);
        $mountIds = $rack->mounts->modelKeys();
        $cables = Cable::query()->with('ends.port.mount.rack')
            ->whereHas('ends.port', fn ($query) => $query->whereIn('mount_id', $mountIds))
            ->orderByRaw('lower(label) NULLS LAST')
            ->get();
        $assetIds = $rack->mounts->map(fn (Mount $mount): string => $mount->asset_id)->all();

        foreach ($cables as $cable) {
            foreach ($cable->ends as $end) {
                $assetIds[] = $end->port->mount->asset_id;
            }
        }

        $supplies = [];

        foreach (PowerConnection::query()->with('source')->whereIn('mount_id', $mountIds)->get() as $connection) {
            $supplies[$connection->mount_id][] = $connection->source->name.($connection->outlet !== null ? ' ('.$connection->outlet.')' : '');
        }

        $grid = PowerGrid::read();

        return [
            'rack' => $rack,
            'room' => Location::paths(false)[$rack->location_id] ?? $rack->location->name,
            'front' => Elevation::of($rack, MountFace::Front),
            'rear' => Elevation::of($rack, MountFace::Rear),
            'mounts' => $rack->mounts->sortByDesc(fn (Mount $mount): int => $mount->position_u ?? 0)->values(),
            'assets' => $this->assets->find(array_values(array_unique($assetIds))),
            'supplies' => $supplies,
            'redundant' => array_combine($mountIds, array_map(fn (string $id): ?bool => $grid->isPowered($id) ? $grid->isRedundant($id) : null, $mountIds)),
            'cables' => $cables,
            'used' => $rack->usedUnits(),
            'power' => $rack->mounts->sum(fn (Mount $mount): int => $mount->power_watts ?? 0),
        ];
    }
}
