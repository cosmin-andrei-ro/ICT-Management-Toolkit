<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Applications\ApplicationsServiceProvider;
use Modules\Audit\AuditServiceProvider;
use Modules\Bcm\BcmServiceProvider;
use Modules\Bia\BiaServiceProvider;
use Modules\Cmdb\CmdbServiceProvider;
use Modules\Core\CoreServiceProvider;
use Modules\Dcim\DcimServiceProvider;
use Modules\Documentation\DocumentationServiceProvider;
use Modules\Itam\ItamServiceProvider;
use Modules\Projects\ProjectsServiceProvider;
use Modules\Reporting\ReportingServiceProvider;
use Modules\Risk\RiskServiceProvider;
use Modules\Ticketing\TicketingServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Modules
    |--------------------------------------------------------------------------
    |
    | Each module is registered through its own service provider, which must
    | extend App\Support\Modules\ModuleServiceProvider. A module listed here
    | is booted only when its flag is true, so a module can be switched off
    | per installation without touching code. Core must stay enabled:
    | every other module depends on it.
    |
    */

    'modules' => [
        'core' => [
            'provider' => CoreServiceProvider::class,
            'enabled' => true,
        ],
        'ticketing' => [
            'provider' => TicketingServiceProvider::class,
            'enabled' => true,
        ],
        'cmdb' => [
            'provider' => CmdbServiceProvider::class,
            'enabled' => true,
        ],
        'itam' => [
            'provider' => ItamServiceProvider::class,
            'enabled' => true,
        ],
        'bia' => [
            'provider' => BiaServiceProvider::class,
            'enabled' => true,
        ],
        'bcm' => [
            'provider' => BcmServiceProvider::class,
            'enabled' => true,
        ],
        'applications' => [
            'provider' => ApplicationsServiceProvider::class,
            'enabled' => true,
        ],
        'risk' => [
            'provider' => RiskServiceProvider::class,
            'enabled' => true,
        ],
        'audit' => [
            'provider' => AuditServiceProvider::class,
            'enabled' => true,
        ],
        'documentation' => [
            'provider' => DocumentationServiceProvider::class,
            'enabled' => true,
        ],
        'dcim' => [
            'provider' => DcimServiceProvider::class,
            'enabled' => true,
        ],
        'projects' => [
            'provider' => ProjectsServiceProvider::class,
            'enabled' => true,
        ],
        'reporting' => [
            'provider' => ReportingServiceProvider::class,
            'enabled' => true,
        ],
    ],

];
