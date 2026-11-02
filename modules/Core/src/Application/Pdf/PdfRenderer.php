<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Pdf;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Turns a self-contained HTML document into a PDF through Gotenberg (ADR 0030).
 * The document must carry everything it shows (styles inline, images as data
 * URIs): the Gotenberg container refuses every network fetch and runs no
 * JavaScript.
 */
final readonly class PdfRenderer
{
    public function __construct(
        private Config $config,
        private Http $http,
    ) {}

    public function isAvailable(): bool
    {
        return $this->url() !== '';
    }

    /**
     * @return string The PDF bytes.
     *
     * @throws PdfUnavailable when not configured or when the service fails
     */
    public function render(string $html): string
    {
        if (! $this->isAvailable()) {
            throw new PdfUnavailable('No PDF service is configured.');
        }

        $timeout = $this->config->get('core.pdf.timeout_seconds');

        try {
            $response = $this->http
                ->timeout(is_int($timeout) && $timeout > 0 ? $timeout : 60)
                ->attach('files', $html, 'index.html', ['Content-Type' => 'text/html'])
                // A4 with 1.5 cm margins; printed backgrounds keep table shading.
                ->post(rtrim($this->url(), '/').'/forms/chromium/convert/html', [
                    'paperWidth' => '8.27',
                    'paperHeight' => '11.7',
                    'marginTop' => '0.6',
                    'marginBottom' => '0.6',
                    'marginLeft' => '0.6',
                    'marginRight' => '0.6',
                    'printBackground' => 'true',
                ]);
        } catch (ConnectionException $exception) {
            throw new PdfUnavailable('The PDF service did not answer.', previous: $exception);
        }

        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF')) {
            throw new PdfUnavailable('The PDF service failed with status '.$response->status().'.');
        }

        return $response->body();
    }

    private function url(): string
    {
        $url = $this->config->get('core.pdf.gotenberg_url');

        return is_string($url) ? trim($url) : '';
    }
}
