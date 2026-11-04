# ADR 0070 — Production Docker images

- **Status:** Accepted
- **Date:** 2026-11-04

## Context

`CLAUDE.md` §4 asks for Docker for development and production. Only the development stack (Laravel Sail) existed; production meant installing PHP, a web server and the background processes by hand. Sail is not meant for production: it runs the development server, mounts the source code and includes development tools.

## Decision

- **Two images from one `docker/production/Dockerfile`:** `app` (PHP-FPM with the code, built assets and production dependencies only) and `web` (Caddy with the `public` folder).
- **Caddy instead of nginx in front of PHP:** it obtains and renews HTTPS certificates by itself and speaks FastCGI directly, so one server does both jobs and the operator configures only a domain. Caddy is Apache-2.0 licensed.
- **One image, several roles:** `app`, `worker` and `scheduler` run the same image with different commands. A one-shot `migrate` service runs migrations and syncs permissions at every start, and the others wait for it.
- **Unprivileged containers:** `app` runs as `www-data`; all run with capabilities dropped and `no-new-privileges`.
- **Two networks:** PostgreSQL, Redis and Gotenberg are on an internal network, with no published ports and no route out. Redis requires a password.
- **Settings at run time:** nothing secret is in the image. `.env` is excluded from the build, and `php artisan optimize` runs when a container starts.
- **Images are built by the operator** from the source. No registry publishes them yet.
- **Its own Compose project name,** so it cannot replace the containers of a development stack on the same machine.

## Alternatives considered

- **nginx and PHP-FPM in one container under a process supervisor:** one image, but several processes per container, and certificates still need another tool.
- **A separate nginx container behind a TLS proxy:** three hops for nothing the toolkit needs.
- **A third-party PHP image or extension installer script:** fewer lines, but one more party to trust in the supply chain.
- **Publishing images to a registry:** easier updates, but it needs a release process, which does not exist yet.

## Consequences

- A server needs only Docker. Manual installation stays documented.
- CI builds the images, so a change that breaks the build is caught.
- Docker must keep the client's real address (the default on a Linux server). If it does not, for example on Docker Desktop, or behind another proxy, sign-in limits and the audit trail see one address. Another proxy in front also needs Caddy's `trusted_proxies` setting.
- Base images are pinned to a minor version, not a digest, so rebuilding picks up their security patches, and a rebuild can differ from the last one.
