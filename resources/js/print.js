// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

// Buttons marked data-print open the browser's print dialog, where "Save as PDF"
// is also offered. Inline handlers are not allowed by the CSP (ADR 0006).
document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('[data-print]')) {
        window.print();
    }
});
