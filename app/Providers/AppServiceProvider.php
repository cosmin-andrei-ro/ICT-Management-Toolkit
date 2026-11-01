<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scramble's UI loads scripts from third-party CDNs, which a self-hosted
        // product should not make browsers contact (and which the CSP blocks).
        // Only the OpenAPI document is served; import it into any client.
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->info->title = 'ICT Management Toolkit API';
            $scheme = SecurityScheme::http('bearer');

            if ($scheme instanceof SecurityScheme) {
                $openApi->secure($scheme);
            }
        });

        Scramble::registerJsonSpecificationRoute('docs/api/openapi.json')->name('api.docs.openapi');
    }
}
