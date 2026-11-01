<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Manual;

use InvalidArgumentException;

/**
 * The user manual, assembled from Markdown pages shipped by each module in
 * resources/manual (ADR 0013). A page starts with a front matter block:
 *
 *     ---
 *     title: Getting started
 *     audience: everyone
 *     ---
 *
 * The file name sets the order and the URL: "10-tickets.md" → /manual/tickets.
 */
final class Manual
{
    /** @var list<string> */
    private array $directories = [];

    /** @var array<string, ManualPage>|null */
    private ?array $pages = null;

    public function addDirectory(string $directory): void
    {
        $this->directories[] = $directory;
        $this->pages = null;
    }

    /**
     * @return array<string, list<ManualPage>> audience value => pages, in reading order
     */
    public function contents(): array
    {
        $grouped = [];

        foreach (ManualAudience::cases() as $audience) {
            $pages = array_values(array_filter($this->pages(), fn (ManualPage $page): bool => $page->audience === $audience));

            if ($pages !== []) {
                $grouped[$audience->value] = $pages;
            }
        }

        return $grouped;
    }

    public function find(string $slug): ?ManualPage
    {
        return $this->pages()[$slug] ?? null;
    }

    /**
     * @return array<string, ManualPage>
     */
    public function pages(): array
    {
        if ($this->pages !== null) {
            return $this->pages;
        }

        $pages = [];

        foreach ($this->directories as $directory) {
            foreach (glob($directory.'/*.md') ?: [] as $file) {
                $page = self::parse($file);

                if (isset($pages[$page->slug])) {
                    throw new InvalidArgumentException("Two manual pages share the slug [{$page->slug}].");
                }

                $pages[$page->slug] = $page;
            }
        }

        uasort($pages, fn (ManualPage $a, ManualPage $b): int => [$a->order, $a->slug] <=> [$b->order, $b->slug]);

        return $this->pages = $pages;
    }

    private static function parse(string $file): ManualPage
    {
        $name = basename($file, '.md');

        if (preg_match('/\A(\d+)-([a-z0-9-]+)\z/', $name, $parts) !== 1) {
            throw new InvalidArgumentException("Manual page [{$name}] must be named like 10-some-topic.md.");
        }

        $content = (string) file_get_contents($file);

        if (preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $content, $matches) !== 1) {
            throw new InvalidArgumentException("Manual page [{$name}] needs a front matter block.");
        }

        $meta = [];
        foreach (explode("\n", $matches[1]) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $meta[trim($key)] = trim($value);
            }
        }

        $audience = ManualAudience::tryFrom($meta['audience'] ?? '')
            ?? throw new InvalidArgumentException("Manual page [{$name}] has no valid audience.");

        if (($meta['title'] ?? '') === '') {
            throw new InvalidArgumentException("Manual page [{$name}] has no title.");
        }

        return new ManualPage($parts[2], $meta['title'], $audience, (int) $parts[1], trim($matches[2]));
    }
}
