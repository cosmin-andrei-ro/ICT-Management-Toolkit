<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

arch('application code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

it('starts every PHP source file with the SPDX license header', function (): void {
    $root = dirname(__DIR__, 2);
    $missing = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'modules', 'routes', 'tests'] as $directory) {
        if (! is_dir("{$root}/{$directory}")) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}", FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = $file->getPathname();

            if (! str_ends_with($path, '.php') || str_ends_with($path, '.blade.php') || str_contains($path, '/bootstrap/cache/')) {
                continue;
            }

            $head = (string) file_get_contents($path, length: 200);

            if (! str_contains($head, '// SPDX-License-Identifier: AGPL-3.0-or-later')) {
                $missing[] = substr($path, strlen($root) + 1);
            }
        }
    }

    expect($missing)->toBeEmpty();
});
