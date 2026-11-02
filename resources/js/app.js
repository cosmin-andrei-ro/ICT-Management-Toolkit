// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

import ciGraph from './components/ci-graph';
import './navigation';

// Alpine runs in its CSP build: components are registered here, by name (ADR 0006).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('ciGraph', ciGraph);
});
