<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Application\Collaboration\VirusScanner;
use Symfony\Component\Process\Process;

/**
 * A stand-in for the daemon: reads the INSTREAM protocol for real, checks that the
 * bytes arrived whole, and gives the answer it was told to.
 */
function fakeClamd(string $answer, string $expected = ''): array
{
    $script = <<<'PHP'
        $server = stream_socket_server('tcp://127.0.0.1:0');
        echo stream_socket_get_name($server, false), "\n";
        $client = stream_socket_accept($server, 10);
        $command = fread($client, 10);
        $body = '';
        while (true) {
            $head = stream_get_contents($client, 4);
            $length = unpack('N', $head)[1];
            if ($length === 0) { break; }
            $body .= stream_get_contents($client, $length);
        }
        $ok = $command === "zINSTREAM\0" && ($argv[2] === '' || $body === $argv[2]);
        fwrite($client, ($ok ? $argv[1] : 'stream: BAD PROTOCOL ERROR')."\0");
        PHP;

    $process = new Process([PHP_BINARY, '-r', $script, '--', $answer, $expected]);
    $process->start();
    $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, ':'));

    return ['tcp://'.trim($process->getOutput()), $process];
}

function scannedFile(string $content = 'just a text file'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('note.txt', $content);
}

it('does nothing when no scanner is set', function (): void {
    config()->set('core.attachments.clamd_address', '');

    expect(app(StoreAttachment::class)->assertAcceptable(scannedFile()))->toBe('text/plain');
});

it('sends the whole file and accepts a clean answer', function (): void {
    [$address, $process] = fakeClamd('stream: OK', 'just a text file');
    config()->set('core.attachments.clamd_address', $address);

    expect(app(StoreAttachment::class)->assertAcceptable(scannedFile()))->toBe('text/plain');
    $process->wait();
});

it('refuses a file with a threat', function (): void {
    [$address, $process] = fakeClamd('stream: Eicar-Signature FOUND');
    config()->set('core.attachments.clamd_address', $address);

    // Kept in a variable: the fake file is gone once nothing refers to it.
    $file = scannedFile();

    expect(fn () => app(VirusScanner::class)->assertClean($file->getRealPath()))
        ->toThrow(ValidationException::class, __('core::attachments.infected'));
    $process->wait();
});

it('refuses a file when the scanner gives no verdict', function (): void {
    [$address, $process] = fakeClamd('INSTREAM size limit exceeded. ERROR');
    config()->set('core.attachments.clamd_address', $address);

    $file = scannedFile();

    expect(fn () => app(VirusScanner::class)->assertClean($file->getRealPath()))
        ->toThrow(ValidationException::class, __('core::attachments.scan_unavailable'));
    $process->wait();
});

it('refuses a file when the scanner cannot be reached', function (): void {
    config()->set('core.attachments.clamd_address', 'tcp://127.0.0.1:1');

    $file = scannedFile();

    expect(fn () => app(StoreAttachment::class)->assertAcceptable($file))
        ->toThrow(ValidationException::class, __('core::attachments.scan_unavailable'));
});
