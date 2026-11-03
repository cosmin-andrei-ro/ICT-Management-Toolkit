<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class LicenseSeatRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** A person, for licences counted per user. */
            'user_id' => ['required_without:asset_id', 'prohibits:asset_id', 'uuid'],
            /** A device, for licences counted per device. */
            'asset_id' => ['required_without:user_id', 'uuid'],
        ];
    }
}
