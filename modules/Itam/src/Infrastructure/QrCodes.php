<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Infrastructure;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;

/**
 * QR codes drawn on the server as SVG data URLs: no external service sees the
 * addresses, and the CSP needs nothing more than data: images.
 */
final class QrCodes
{
    public function svgDataUrl(string $text): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            // Medium correction: labels get scratched.
            'eccLevel' => EccLevel::M,
            'quietzoneSize' => 2,
        ]);

        $image = (new QRCode($options))->render($text);

        if (! is_string($image)) {
            throw new RuntimeException('The QR code could not be drawn.');
        }

        return $image;
    }
}
