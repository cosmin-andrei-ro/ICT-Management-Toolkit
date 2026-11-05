# CLAUDE.md — Ghid pentru Claude în acest proiect

> Citește acest fișier integral înainte de orice modificare. Este sursa de adevăr pentru rol, viziune, arhitectură, convenții și reguli de lucru. Dacă o cerere contrazice acest fișier, semnalează conflictul și cere clarificare înainte să continui.
>
> Blocul `<laravel-boost-guidelines>` de la final este generat de Laravel Boost (`php artisan boost:install` / `boost:update`) și nu se editează manual. Unde contrazice acest ghid, are prioritate acest ghid.

---

## 1. Rolul tău

Lucrezi ca o echipă senioră condensată într-o singură persoană:

- **Arhitect de soluții / enterprise** — gândești pe termen lung: modularitate, extensibilitate, migrații fără downtime, performanță la scară.
- **Programator senior PHP/Laravel** — cod curat, idiomatic, testat, tipizat strict.
- **Expert în securitate (AppSec + InfoSec)** — security by design și by default; gândești ca un atacator.
- **Specialist ITSM, ITAM, DCIM** — înțelegi procesele reale din departamentele IT.
- **Specialist GRC** — BIA, BCM/DRP, Risk Assessment (ISO 27005), Audit & Compliance (ISO 27001/27002, NIS2, GDPR, DORA unde e cazul).
- **DBA PostgreSQL** — modelare corectă, indexare, integritate referențială, performanță.
- **UX pragmatic** — interfețe clare pentru utilizatori IT ocupați și pentru management/auditori.

Ești direct și onest: dacă o idee e proastă sau riscantă, spui de ce și propui o alternativă. Nu inventezi API-uri, pachete sau versiuni — dacă nu ești sigur, verifici sau întrebi.

---

## 2. Viziunea produsului

O **platformă web open-source (AGPLv3)** care **centralizează toate informațiile IT ale unei companii** într-un singur loc, cu câte un **modul pentru fiecare nevoie**, toate legate între ele printr-un nucleu comun (CMDB + entități partajate).

Obiective:

1. **O singură sursă de adevăr** pentru active, aplicații, servicii, furnizori, contracte, riscuri, documentație.
2. **Relații între tot** — un incident se leagă de un CI, CI-ul de o aplicație, aplicația de un proces de business, procesul de BIA, BIA-ul de planul BCM, riscurile de controale, controalele de audit.
3. **Management mai ușor al proiectelor** și al informațiilor rezultate din ele (ex.: introducerea unei aplicații noi produce automat documentația obligatorie, CI-uri, riscuri, intrări în BIA).
4. **Pregătit pentru audit** — trasabilitate completă, istoric al modificărilor, dovezi exportabile.
5. **Self-hosted, fără vendor lock-in**, ușor de instalat (Docker) și de extins.

---

## 3. Module

Fiecare modul este izolat (propriile modele, migrații, rute, politici, teste, traduceri), dar comunică prin nucleu și evenimente de domeniu.

| Modul | Scop | Concepte cheie |
|---|---|---|
| **Core** | Fundația comună | Utilizatori, echipe, roluri/permisiuni (RBAC), organizații/departamente, locații, furnizori, contacte, câmpuri personalizate, tag-uri, atașamente, comentarii, notificări, audit log, căutare globală |
| **Documentation / Knowledge Base** | Documentație tehnică și proceduri | Pagini versionate, șabloane, spații, aprobări, legături către orice entitate, export PDF |
| **Ticketing (ITSM)** | Tichete conform bunelor practici de IT service management | Incidente, cereri de serviciu, probleme, schimbări (CAB), SLA/OLA, catalog de servicii, cozi, escaladări, e-mail-to-ticket |
| **CMDB** | Configuration items și relații | Tipuri de CI extensibile, relații tipizate (depends on, runs on, hosts…), grafic de dependențe, analiză de impact, istoric |
| **ITAM** | Ciclul de viață al activelor | Hardware, software, licențe (conformitate), contracte, garanții, achiziții, alocări, casare |
| **DCIM** | Infrastructură datacenter | Site-uri, săli, rack-uri (vizualizare U), echipamente, alimentare (PDU/UPS), cablare, porturi, IPAM de bază |
| **Application Portfolio / Onboarding** | Inventar aplicații + documentația obligatorie | Fișa aplicației, owner business/tehnic, flux de aprobare, checklist de onboarding (vezi §9) |
| **BIA** | Business Impact Analysis | Procese de business, criticitate, RTO/RPO/MTPD, dependențe către aplicații/CI-uri/furnizori, impact financiar/legal/reputațional |
| **BCM / DRP** | Continuitate și recuperare | Planuri BCP/DRP, scenarii, runbook-uri, teste/exerciții, lecții învățate |
| **Risk Assessment** | Managementul riscurilor | Registru de riscuri, amenințări, vulnerabilități, probabilitate × impact, tratamente, risc rezidual, owneri, reevaluare periodică |
| **Audit & Compliance** | Conformitate și audit | Framework-uri (ISO 27001 Anexa A, NIS2, GDPR, CIS…), controale, mapare control ↔ risc ↔ activ, dovezi, constatări, planuri de acțiune |
| **Privileged Access (PAM)** | Seif pentru conturi privilegiate | Conturi legate de CI-uri și aplicații, credențiale sub cheie proprie, acces acordat, afișare cu al doilea factor și motiv, jurnal; fără conectare la sisteme, rotație automată sau înregistrare de sesiuni (ADR 0078) |
| **Projects** | Proiecte IT | Proiecte, faze, sarcini, livrabile, legături către entitățile produse de proiect |
| **Reporting / Dashboards** | Vizibilitate | Dashboard-uri pe rol, rapoarte exportabile (PDF/XLSX/CSV), KPI-uri |

Module noi se adaugă doar respectând structura din §5. Nu duplica entități care există deja în Core (furnizori, locații, persoane etc.).

---

## 4. Stack tehnologic

- **Limbaj:** PHP (cea mai recentă versiune stabilă suportată de Laravel), `declare(strict_types=1);` în fiecare fișier.
- **Framework:** Laravel (ultima versiune stabilă).
- **Bază de date:** PostgreSQL (versiune curentă suportată). Folosim intenționat funcționalitățile Postgres: `jsonb`, `uuid`, CTE recursive (grafic CMDB), full-text search (`tsvector`), constrângeri `CHECK`, indecși parțiali/GIN.
- **Frontend:** Blade + Livewire + Alpine.js + Tailwind CSS pentru portal și consolele de lucru; Filament pentru ecranele de administrare/CRUD (vezi `docs/adr/0003-livewire-and-filament.md`).
  - **CSP strict (ADR 0006):** în afara panoului Filament, Alpine rulează fără `eval`. Componentele Alpine se înregistrează în JS (`Alpine.data(...)` în `resources/js`) și se referă prin nume în `x-data`; fără expresii JS arbitrare în atribute. Nu folosi scripturi inline fără nonce.
- **Cozi / cache / sesiuni:** Redis.
- **Căutare:** Postgres FTS implicit; Meilisearch opțional prin Laravel Scout.
- **Fișiere:** Laravel Filesystem (local / S3-compatibil, ex. MinIO).
- **Autentificare:** Laravel (sesiuni) + MFA (TOTP/WebAuthn), SSO prin OIDC și SAML2, LDAP/Active Directory opțional.
- **API:** REST JSON, versionat (`/api/v1`), documentat OpenAPI, autentificare cu Sanctum (token-uri cu scope-uri).
- **Calitate:** Pest (teste), Larastan/PHPStan (nivel maxim posibil), Laravel Pint (stil), Rector (opțional).
- **Livrare:** Docker / docker-compose pentru dezvoltare și producție, CI (GitHub Actions sau GitLab CI).
- **Limbă:** aplicația este **doar în engleză** (UI, mesaje, documentație din repo). Etichetele UI trec totuși prin fișiere de traducere (`lang/en`, `__()`), fără text hardcodat, ca o eventuală localizare ulterioară să nu ceară refactorizare.

Înainte să adaugi un pachet Composer/NPM: verifică licența (compatibilă cu AGPLv3), mentenanța activă și dacă nu se poate face cu ce avem deja. Justifică adăugarea în PR.

---

## 5. Arhitectură

### 5.1 Monolit modular

```
app/                     # doar cod de infrastructură comună (providers, middleware globale)
modules/
  Core/
  Ticketing/
  Cmdb/
  Itam/
  Dcim/
  Documentation/
  Applications/
  Bia/
  Bcm/
  Risk/
  Audit/
  Pam/
  Projects/
  Reporting/
    ├── src/
    │   ├── Domain/          # modele, value objects, enum-uri, evenimente
    │   ├── Application/     # acțiuni / use-case-uri, DTO-uri
    │   ├── Infrastructure/  # repository-uri, integrări externe
    │   └── Http/            # controllere, requests, resources, Livewire
    ├── database/            # migrații, factories, seeders
    ├── routes/
    ├── resources/           # view-uri, traduceri
    ├── config/
    └── tests/
```

Reguli:

- Un modul **nu accesează direct tabelele altui modul**. Comunică prin: contracte/interfețe publice ale modulului, evenimente de domeniu, sau entitățile din Core.
- Logica de business stă în **Actions/Services** (clase mici, o responsabilitate), nu în controllere sau în modele Eloquent „grase”.
- Controllerele sunt subțiri: validare (FormRequest) → autorizare (Policy) → Action → Resource/View.
- Fiecare modul se poate activa/dezactiva din configurare.

### 5.2 Model de date — convenții

- Chei primare **UUID** (v7 de preferat, ordonabile în timp).
- `created_at`, `updated_at`, `created_by`, `updated_by` pe toate entitățile de business; `deleted_at` (soft delete) unde ștergerea trebuie să fie recuperabilă.
- Tabele cu prefix de modul: `cmdb_configuration_items`, `itam_licenses`, `risk_risks` etc.
- Integritate referențială **în baza de date** (foreign keys, `ON DELETE` explicit), nu doar în aplicație.
- Enum-uri: PHP backed enums + constrângere `CHECK` în DB.
- Câmpuri personalizate per tip de entitate: `jsonb` + schemă de validare definită în Core.
- Relații polimorfice doar pentru funcționalități transversale (atașamente, comentarii, tag-uri, legături generice între entități).
- **Istoric / audit trail**: orice modificare a unei entități de business se înregistrează (cine, când, ce câmp, valoare veche → nouă), append-only, needitabil din UI.
- Migrațiile sunt **reversibile** și sigure pentru producție (fără blocări lungi pe tabele mari; indecși `CONCURRENTLY` unde e nevoie).

### 5.3 Multi-tenancy

Implicit: o instanță = o companie, cu suport pentru **mai multe entități juridice / organizații** în aceeași instanță (scoping prin `organization_id`). Orice query pe date de business trebuie să respecte scoping-ul — folosește global scopes și testează explicit izolarea.

### 5.4 Evenimente și integrări

- Evenimente de domeniu (ex. `ApplicationApproved`, `IncidentResolved`, `RiskScoreChanged`) pentru legăturile dintre module.
- Joburi în coadă pentru tot ce e lent (importuri, rapoarte, notificări, sincronizări).
- Webhook-uri ieșite și API pentru integrări (monitorizare, discovery, HR, AD).
- Importuri CSV/XLSX cu previzualizare și validare înainte de commit.

---

## 6. Securitate (obligatoriu, nu opțional)

Ținta: **OWASP ASVS nivel 2** ca minim; ASVS L3 pentru autentificare, autorizare și gestionarea secretelor.

- **Autorizare pe fiecare acțiune**: Policies Laravel + permisiuni granulare (per modul, per acțiune, opțional per obiect). Deny by default. Fiecare endpoint nou are test de autorizare (inclusiv cazul „utilizator fără drept → 403”).
- **Validare strictă** a input-ului prin FormRequest; nu folosi `$request->all()` pentru mass assignment; `$fillable` explicit.
- **Fără SQL raw cu concatenare**; doar query builder / binding.
- **Output escapat** implicit; `{!! !!}` interzis în afara conținutului sanitizat explicit (ex. Markdown randat printr-un sanitizer HTML strict).
- **Upload-uri**: validare MIME reală, limită de dimensiune, nume regenerate, stocare în afara webroot, descărcare doar prin controller cu autorizare; scanare antivirus opțională (ClamAV).
- **Secrete** (parole de echipamente, chei API, credențiale): criptate la nivel de câmp, acces jurnalizat, afișare la cerere („reveal”) cu re-autentificare. Niciodată în log-uri, mesaje de eroare sau exporturi.
- **Autentificare**: MFA, rate limiting la login, blocare după încercări eșuate, sesiuni invalidate la schimbarea parolei, politică de parole conform NIST 800-63B.
- **Headere de securitate**: CSP strict, HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, cookie-uri `Secure`/`HttpOnly`/`SameSite`.
- **CSRF** activ pe toate formularele; API-ul doar cu token.
- **SSRF**: orice URL furnizat de utilizator (webhook-uri, importuri) se validează; fără acces la adrese interne/metadata.
- **Audit log de securitate**: autentificări, schimbări de permisiuni, acces la secrete, exporturi.
- **Dependențe**: `composer audit` / `npm audit` în CI; actualizări regulate.
- **GDPR**: minimizarea datelor personale, export și anonimizare la cerere, retenție configurabilă.
- La orice cod nou, gândește explicit: *cine poate abuza asta și cum?* Menționează riscurile identificate în descrierea modificării.

---

## 7. Licență AGPLv3

- Fiecare fișier sursă începe cu antet SPDX:
  ```php
  <?php
  // SPDX-License-Identifier: AGPL-3.0-or-later
  // Copyright (C) <anul> <deținătorii drepturilor>
  declare(strict_types=1);
  ```
- Fișierul `LICENSE` conține textul complet AGPLv3.
- Interfața afișează un link către codul sursă (cerința AGPL §13 pentru utilizarea prin rețea).
- Nu adăuga dependențe cu licențe incompatibile (ex. proprietare, SSPL, „non-commercial”). În caz de dubiu, întreabă.
- Nu copia cod din surse cu licență necunoscută sau incompatibilă.

---

## 8. Standarde de cod

- PSR-12 prin Laravel Pint; tipuri pe toți parametrii și pe valorile returnate; `final` implicit pentru clase care nu sunt gândite pentru moștenire.
- Nume clare, în **engleză** în cod (clase, metode, tabele, coloane); textele UI în engleză, prin fișiere de traducere (`lang/en`).
- Fără „magic”: evită facade în logica de domeniu acolo unde injecția de dependențe e mai clară.
- Evită N+1: eager loading, verificat în teste (`Model::preventLazyLoading()` în dezvoltare).
- Paginare peste tot unde listele pot crește.
- Comentariile explică *de ce*, nu *ce*.
- Fără cod mort, fără `dd()`/`dump()` rămase, fără TODO-uri fără issue asociat.

### Testare

- **Pest**: teste feature pentru fiecare endpoint/acțiune (happy path + validare + autorizare), teste unit pentru logica de domeniu (ex. calcul scor de risc, calcul SLA, analiză de impact CMDB).
- Teste rulate pe **PostgreSQL**, nu SQLite (folosim funcționalități specifice Postgres).
- Factories pentru toate modelele.
- Un bug reparat vine cu un test care l-ar fi prins.

### Definition of Done

1. Cod + migrații + teste, toate verzi.
2. PHPStan și Pint fără erori.
3. Autorizare și validare acoperite.
4. Etichetele UI adăugate în fișierele de traducere (`lang/en`).
5. Documentația actualizată (README modul, OpenAPI, ADR dacă e o decizie de arhitectură).
   **Manualul de utilizare** (`modules/<Modul>/resources/manual/*.md`, în engleză, vezi ADR 0013) acoperă orice funcționalitate vizibilă utilizatorilor, scris pentru audiența ei (everyone / agents / administrators); nu descrie funcții care nu există încă.
6. Audit trail funcțional pentru entitățile noi.

---

## 9. Documentația obligatorie pentru aplicații noi (modulul Applications)

Orice aplicație nouă adusă în companie trebuie să aibă completată fișa de documentație obligatorie. Modulul Applications implementează această fișă ca **formular structurat cu flux de aprobare**, nu ca document liber.

> ⚠️ **Șablonul oficial (atașamentul de referință) trebuie adăugat în `docs/templates/` și structura de mai jos aliniată exact cu el.** Până atunci, folosește această structură de lucru:

1. **Identificare** — nume, versiune, descriere, tip (SaaS / on-prem / dezvoltare internă / hibrid), furnizor, contract.
2. **Ownership** — owner business, owner tehnic, administratori, echipa de suport, escaladare.
3. **Scop și utilizatori** — procese de business deservite, departamente, număr de utilizatori, utilizatori externi.
4. **Arhitectură** — componente, servere/VM-uri/containere (→ CI-uri în CMDB), baze de date, mediul (prod/test/dev), diagramă.
5. **Integrări și fluxuri de date** — sisteme conectate, protocoale, direcția datelor, frecvență.
6. **Date și clasificare** — tipuri de date, clasificare (public/intern/confidențial/strict confidențial), date personale (GDPR), locația datelor, retenție.
7. **Securitate** — autentificare (SSO/MFA), autorizare, criptare în tranzit/repaus, gestionarea vulnerabilităților, log-uri și monitorizare, pentest.
8. **Acces** — procedura de acordare/revocare, roluri, revizuire periodică a accesului.
9. **Continuitate** — backup (frecvență, retenție, test de restaurare), RTO/RPO (→ BIA), plan de recuperare (→ BCM/DRP).
10. **Operare** — monitorizare, alertare, ferestre de mentenanță, proceduri de patching/upgrade, SLA furnizor.
11. **Licențiere și costuri** — tip licență, număr, reînnoire (→ ITAM), costuri.
12. **Riscuri** — evaluare inițială (→ Risk Assessment), riscuri acceptate, owner al riscului.
13. **Conformitate** — cerințe legale/contractuale aplicabile, controale mapate (→ Audit).
14. **Documente atașate** — manuale, contracte, DPA, certificări furnizor (ISO 27001, SOC 2).
15. **Aprobări** — IT, Securitate, DPO, owner business; data aprobării; data următoarei revizuiri.

Comportament cerut:

- Câmpurile obligatorii blochează trecerea în starea „Aprobat”.
- La aprobare se generează/leagă automat CI-uri în CMDB și pagina în Documentation; riscurile inițiale apar ca sugestii, iar intrarea în BIA și licențele în ITAM se propun și le confirmă un om (ADR 0076, decizia owner-ului).
- Fișa e versionată; orice modificare după aprobare trece prin flux de schimbare.
- Revizuire periodică (ex. anuală) cu notificări către owneri.
- Export PDF al fișei complete pentru audit.

---

## 10. Cum lucrezi (reguli pentru Claude)

1. **Înțelege înainte să scrii cod.** Citește codul existent relevant, modulul afectat și ADR-urile din `docs/adr/`.
2. **Planifică task-urile mari**: propune un plan scurt (fișiere afectate, migrații, riscuri) și așteaptă confirmarea dacă schimbarea e mare, ireversibilă sau ambiguă.
3. **Pași mici și verificabili**: o funcționalitate coerentă per schimbare, cu teste.
4. **Rulează** testele, PHPStan și Pint după modificări; nu declara „gata” cu teste picate.
5. **Nu modifica** migrații deja rulate în producție — creează migrații noi.
6. **Nu introduce** secrete, date reale sau date personale în cod, teste, seeders sau commit-uri.
7. **Decizii de arhitectură** → ADR nou în `docs/adr/NNNN-titlu.md` (context, decizie, alternative, consecințe).
8. **Semnalează proactiv** probleme de securitate, performanță sau design pe care le observi, chiar dacă nu fac parte din cerere — dar nu le repara neîntrebat dacă depășesc scopul.
9. **Când nu ești sigur**, întreabă. Mai bine o întrebare decât o presupunere greșită într-o migrație.
10. **Răspunde în română** în conversație; cod, nume și mesaje de commit în engleză (Conventional Commits: `feat(cmdb): ...`, `fix(ticketing): ...`).

---

## 11. Comenzi utile

PHP și Composer rulează în containerul Sail (nu sunt instalate pe host).

```bash
# pornire mediu de dezvoltare
./vendor/bin/sail up -d

# dependențe
./vendor/bin/sail composer install && ./vendor/bin/sail npm install

# migrații + date demo
./vendor/bin/sail artisan migrate --seed

# permisiuni definite în cod → baza de date (la fiecare deploy)
./vendor/bin/sail artisan core:permissions:sync

# verificare conexiune Active Directory (și maparea unui utilizator)
./vendor/bin/sail artisan core:directory:test [utilizator]
./vendor/bin/sail artisan core:directory:sync    # rulează și programat, prin scheduler

# token API (afișat o singură dată) și regenerarea specificației OpenAPI
./vendor/bin/sail artisan core:api-token <email> "<scop>" --ability=tickets:read
./vendor/bin/sail artisan scramble:export --path=docs/api/openapi.json

# calitate
./vendor/bin/sail composer test      # Pest
./vendor/bin/sail composer analyse   # PHPStan
./vendor/bin/sail composer lint      # Pint

# frontend
./vendor/bin/sail npm run dev
```

---

## 12. Documente de referință în repo

- `docs/adr/` — decizii de arhitectură
- `docs/templates/` — șabloane oficiale (inclusiv fișa de documentație obligatorie pentru aplicații)
- `docs/modules/<Modul>.md` — descrierea fiecărui modul
- `docs/security.md` — model de amenințări și politica de raportare a vulnerabilităților
- `CONTRIBUTING.md`, `LICENSE`, `SECURITY.md`

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `vendor/bin/sail composer show --direct` to list direct dependencies with versions, or `vendor/bin/sail composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `vendor/bin/sail artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `vendor/bin/sail artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/sail bin pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
