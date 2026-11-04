#!/bin/sh
# SPDX-License-Identifier: AGPL-3.0-or-later
# Copyright (C) 2026 ICT Management Toolkit contributors
set -e

# Settings come from the environment at run time, so they are cached here and not in the image
php artisan optimize --quiet

exec "$@"
