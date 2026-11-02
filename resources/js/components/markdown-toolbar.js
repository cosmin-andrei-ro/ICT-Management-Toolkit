// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

// Buttons above a Markdown textarea (x-ref="body") that wrap the selection in
// the syntax they carry (data-before, data-after) or start each selected line
// with it (data-line). Registered by name for Alpine's CSP build (ADR 0006).
export default () => ({
    apply(event) {
        const { before = '', after = '', line = '', placeholder = '' } = event.currentTarget.dataset;
        const area = this.$refs.body;
        const start = area.selectionStart;
        const end = area.selectionEnd;
        const selected = area.value.slice(start, end) || placeholder;
        let text;

        if (line !== '') {
            text = selected.split('\n').map((row) => line + row).join('\n');
        } else {
            text = before + selected + after;
        }

        area.setRangeText(text, start, end, 'select');
        area.focus();
        // Tells Livewire's wire:model the text changed.
        area.dispatchEvent(new Event('input', { bubbles: true }));
    },
});
