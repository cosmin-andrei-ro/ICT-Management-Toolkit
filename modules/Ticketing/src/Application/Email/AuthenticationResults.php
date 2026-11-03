<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

/**
 * One Authentication-Results header (RFC 8601), read only for what it says
 * about the From domain. An SPF pass concerns the envelope sender, which anyone
 * can set to a domain of their own: it counts only for that same domain.
 * Comments and quoted strings are dropped first, because they can hold text the
 * sender chose, such as "dmarc=pass".
 */
final readonly class AuthenticationResults
{
    /**
     * @param  list<string>  $methods  The results, without comments or quoted strings.
     */
    private function __construct(
        public string $authservId,
        private array $methods,
    ) {}

    public static function parse(string $header): self
    {
        $parts = explode(';', self::withoutCommentsAndQuotes($header));
        // "mx.example.com 1": the authserv-id comes first, maybe with a version.
        $authservId = preg_split('/\s+/', trim(array_shift($parts))) ?: [''];

        return new self(mb_strtolower($authservId[0]), array_map(trim(...), $parts));
    }

    public function failed(): bool
    {
        foreach ($this->methods as $method) {
            if (preg_match('/^(spf|dmarc)\s*=\s*fail\b/i', $method) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * A DMARC pass, unless it names another domain, or an SPF pass for this domain.
     */
    public function confirms(string $fromDomain): bool
    {
        foreach ($this->methods as $method) {
            if (preg_match('/^dmarc\s*=\s*pass\b/i', $method) === 1) {
                $domain = self::domainIn($method, 'header\.from');

                if ($domain === null || $domain === $fromDomain) {
                    return true;
                }
            }

            if (preg_match('/^spf\s*=\s*pass\b/i', $method) === 1 && self::domainIn($method, 'smtp\.mailfrom') === $fromDomain) {
                return true;
            }
        }

        return false;
    }

    private static function domainIn(string $method, string $property): ?string
    {
        if (preg_match('/\s'.$property.'\s*=\s*(\S*)/i', $method, $matches) !== 1) {
            return null;
        }

        $at = strrpos($matches[1], '@');

        return mb_strtolower($at === false ? $matches[1] : substr($matches[1], $at + 1));
    }

    private static function withoutCommentsAndQuotes(string $header): string
    {
        $kept = '';
        $depth = 0;
        $quoted = false;

        for ($i = 0, $length = strlen($header); $i < $length; $i++) {
            $character = $header[$i];

            if ($character === '\\' && ($quoted || $depth > 0)) {
                $i++;
            } elseif ($quoted) {
                $quoted = $character !== '"';
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')' && $depth > 0) {
                $depth--;
            } elseif ($depth === 0) {
                if ($character === '"') {
                    $quoted = true;
                } else {
                    $kept .= $character;
                }
            }
        }

        return $kept;
    }
}
