// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

// Drop-down groups of the main menu are <details> elements: they open and close
// without script. This only makes them behave like menus: one open at a time,
// closed by a click elsewhere or by Escape.
const menus = () => document.querySelectorAll('details[data-nav-menu]');

document.addEventListener('toggle', (event) => {
    const opened = event.target;

    if (!(opened instanceof HTMLDetailsElement) || !opened.matches('[data-nav-menu]') || !opened.open) {
        return;
    }

    menus().forEach((menu) => {
        if (menu !== opened) {
            menu.open = false;
        }
    });
}, true);

document.addEventListener('click', (event) => {
    menus().forEach((menu) => {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    menus().forEach((menu) => {
        if (menu.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });
});
