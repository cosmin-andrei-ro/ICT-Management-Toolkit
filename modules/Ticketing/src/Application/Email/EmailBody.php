<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use DOMDocument;
use DOMNode;

/**
 * Turns an email body into the text stored on a ticket: HTML becomes plain text
 * (nothing of the markup survives), and the quoted earlier conversation is cut.
 */
final class EmailBody
{
    /**
     * Printed at the top of ticket emails and searched for in replies. A protocol
     * token rather than a translated label: changing it would break threading of
     * emails already sent.
     */
    public const string MARKER = '— Reply above this line —';

    public const int MAX_LENGTH = 20_000;

    public static function fromHtml(string $html): string
    {
        $document = new DOMDocument;
        // LIBXML_NONET: never fetch anything referenced by the markup.
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        foreach (['script', 'style', 'head', 'title'] as $tag) {
            while (($node = $document->getElementsByTagName($tag)->item(0)) !== null) {
                $node->parentNode?->removeChild($node);
            }
        }

        $text = self::textOf($document);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * The new part of a message: before our marker or the client's quote header.
     */
    public static function newPart(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $kept = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);

            if (str_contains($line, self::MARKER)
                || preg_match('/^-{2,}\s*Original Message\s*-{2,}$/i', $trimmed) === 1
                || preg_match('/^_{20,}$/', $trimmed) === 1
                || preg_match('/^On .{1,200}wrote:$/', $trimmed) === 1
                // Desktop clients: "From: ..." followed by "Sent:" or "Date:".
                || (preg_match('/^From:\s/i', $trimmed) === 1 && preg_match('/^(Sent|Date):\s/i', trim($lines[$index + 1] ?? '')) === 1)) {
                break;
            }

            $kept[] = $line;
        }

        // Trailing quoted lines ("> ...") left above the cut.
        while ($kept !== [] && (trim(end($kept)) === '' || str_starts_with(ltrim(end($kept)), '>'))) {
            array_pop($kept);
        }

        return mb_substr(trim(implode("\n", $kept)), 0, self::MAX_LENGTH);
    }

    private static function textOf(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return (string) preg_replace('/[ \t\n\r]+/', ' ', (string) $node->textContent);
        }

        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= self::textOf($child);
        }

        return match (strtolower($node->nodeName)) {
            'br' => "\n",
            'p', 'div', 'tr', 'table', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol' => "\n".trim($text)."\n",
            'li' => "\n- ".trim($text),
            default => $text,
        };
    }
}
