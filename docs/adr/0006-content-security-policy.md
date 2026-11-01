# ADR 0006 — Strict Content Security Policy, relaxed only for the admin panel

- **Status:** Accepted
- **Date:** 2026-11-01

## Context

`CLAUDE.md` §6 requires a strict CSP. Livewire bundles Alpine.js, whose standard build evaluates expressions from HTML attributes (`x-data="…"`, `x-on:click="…"`) with `new Function`, which needs `'unsafe-eval'`. With `'unsafe-eval'` allowed, any HTML injection becomes script execution through Alpine attributes, even without a `<script>` tag.

Livewire ships an eval-free Alpine build (`csp_safe`), but it only supports simple property access in attributes. Filament (ADR 0003) renders many inline Alpine expressions and does not work with it.

## Decision

- Every response gets a CSP from `Modules\Core\Http\Middleware\SecurityHeaders`:
  - `script-src 'self' 'nonce-…'` with a fresh nonce per request (passed to Vite and Livewire), with no `'unsafe-inline'` and no `'unsafe-eval'`;
  - `object-src 'none'`, `base-uri 'none'`, `form-action 'self'`, `frame-ancestors 'none'`;
  - `style-src` allows `'unsafe-inline'`, because Livewire and Alpine set inline style attributes. Style injection is a much smaller risk than script injection.
- The application (portal and working consoles) runs Livewire with `csp_safe = true`.
- The Filament panel adds `Modules\Core\Http\Middleware\AllowUnsafeEval`. For those responses only, it adds `'unsafe-eval'` and serves Livewire's standard Alpine build. Only users with `core.admin.access` reach the panel.
- Livewire's assets are published to `public/vendor/livewire` (composer `post-autoload-dump`), so the script URL points at the right build for each request. With the default asset route, the build would be chosen by global configuration when the file is served.
- The other headers are `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and HSTS on HTTPS.

## Rule for frontend code

Outside the admin panel, Alpine components must be registered in JavaScript (`Alpine.data('name', () => ({ … }))` in `resources/js`) and referenced by name (`x-data="name"`). Attributes may only reference properties and methods, not arbitrary expressions. Livewire directives (`wire:*`) are unaffected.

## Rejected alternatives

- **`'unsafe-eval'` everywhere:** simplest, but it turns HTML injection into script execution across the whole application.
- **Dropping Filament:** a strict CSP everywhere, at the cost of hand-writing every configuration screen.

## Consequences

- Developers must follow the Alpine rule above, or components fail silently in the browser under the CSP build.
- `AllowUnsafeEval` changes `livewire.csp_safe` for the request and restores it in `terminate()`. Livewire injects its script after the middleware pipeline returns, so restoring earlier would serve the wrong build. Tests cover both the panel and the application.
