<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Webhooks\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Core\Domain\Models\Webhook;
use Modules\Core\Http\Filament\Resources\Webhooks\WebhookResource;

final class ManageWebhooks extends ManageRecords
{
    protected static string $resource = WebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The secret is generated, never typed, and shown once.
            CreateAction::make()
                ->using($this->createWebhook(...))
                ->after(fn (Webhook $record) => WebhookResource::showSecret($record->secret)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createWebhook(array $data): Webhook
    {
        $webhook = new Webhook($data);
        $webhook->forceFill(['secret' => WebhookResource::newSecret()])->save();

        return $webhook;
    }
}
