<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Collaboration;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * Optional scan of uploaded files by a ClamAV daemon (ADR 0073), over its INSTREAM
 * protocol. Off without an address. Once on, a file that cannot be scanned is
 * refused: an outage of the scanner must not become a way past it.
 */
final readonly class VirusScanner
{
    private const int CHUNK_BYTES = 1024 * 1024;

    public function __construct(
        private Config $config,
        private LoggerInterface $log,
    ) {}

    /**
     * @throws ValidationException keyed to "file".
     */
    public function assertClean(string $path): void
    {
        $address = $this->config->get('core.attachments.clamd_address');

        if (! is_string($address) || $address === '') {
            return;
        }

        $answer = $this->ask($address, $path);

        // "stream: OK", "stream: <name> FOUND"; anything else, such as an error about the size, is no verdict.
        if ($answer === null || ($answer !== 'stream: OK' && ! str_ends_with($answer, ' FOUND'))) {
            $this->log->error('Antivirus scan failed; the file was refused.', ['answer' => $answer]);

            throw ValidationException::withMessages(['file' => __('core::attachments.scan_unavailable')]);
        }

        if ($answer !== 'stream: OK') {
            $this->log->warning('Antivirus found a threat; the file was refused.', ['answer' => mb_substr($answer, 0, 200), 'sha256' => hash_file('sha256', $path)]);

            throw ValidationException::withMessages(['file' => __('core::attachments.infected')]);
        }
    }

    private function ask(string $address, string $path): ?string
    {
        $timeout = $this->config->get('core.attachments.clamd_timeout');
        $seconds = is_int($timeout) && $timeout > 0 ? $timeout : 30;
        $socket = @stream_socket_client($address, $code, $message, $seconds);
        $file = @fopen($path, 'rb');

        if ($socket === false || $file === false) {
            return null;
        }

        try {
            stream_set_timeout($socket, $seconds);
            fwrite($socket, "zINSTREAM\0");

            while (! feof($file)) {
                $chunk = fread($file, self::CHUNK_BYTES);

                if ($chunk === false) {
                    return null;
                }

                if ($chunk !== '' && fwrite($socket, pack('N', strlen($chunk)).$chunk) === false) {
                    return null;
                }
            }

            fwrite($socket, pack('N', 0));
            $answer = stream_get_line($socket, 4096, "\0");

            return is_string($answer) && $answer !== '' ? trim($answer) : null;
        } finally {
            fclose($socket);
            fclose($file);
        }
    }
}
