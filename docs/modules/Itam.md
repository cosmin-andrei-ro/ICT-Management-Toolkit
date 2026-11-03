# IT asset management (ITAM) module

Hardware assets (who has them, where they are, their warranty and how they left the organization) and software licences (seats bought and used, keys, renewals). Code: `modules/Itam/`. Decisions: [ADR 0020](../adr/0020-it-asset-management.md), [ADR 0021](../adr/0021-asset-import-handover-labels.md), [ADR 0022](../adr/0022-software-licenses.md).

## Concepts

- **Category** (`itam_categories`): a kind of asset with its attributes (Core form schema). Ten defaults per organization, created on first use: laptop, desktop, monitor, phone, tablet, printer, server, network device, storage, peripheral.
- **Asset** (`itam_assets`): asset tag (automatic `AST-000001` or entered), name, manufacturer, model, serial number, status, location, holder, supplier, contract, purchase date and cost, order and invoice numbers, warranty end, planned replacement, attribute values (`properties`), notes, disposal details, documents (Core attachments).
- **Assignment** (`itam_asset_assignments`): who had an asset, from when to when, and when the holder confirmed receipt; one open assignment per asset.

- **Software product** (`itam_software_products`) and **licence** (`itam_licenses`): per user, per device or site; quantity, key (encrypted, hidden), supplier, contract, cost, validity, documents.
- **Seat** (`itam_license_assignments`): a person or a device using a licence.

## Life cycle

`AssetStatus::next()`: ordered → in stock or in use; in stock → in use, in repair, retired, lost; in use → in stock, in repair, lost; in repair → in stock, in use (back to its holder), retired; retired → in stock, disposed; lost → in stock, disposed; disposed is final. Actions: `SaveAsset` (details), `AssignAsset`, `ChangeAssetStatus`, `DisposeAsset`.

## Screens

- `/assets`: list with search (tag, serial number, name, model, attribute values) and filters by category, status, location and warranty ending.
- `/assets/new`, `/assets/{id}/edit`: form; the attribute fields follow the category.
- `/assets/{id}`: holder and hand-out, life cycle and disposal, CMDB link, details, purchase and warranty, history of holders, documents.
- `/assets/import`: CSV/XLSX import with preview; all rows or none ([ADR 0021](../adr/0021-asset-import-handover-labels.md)).
- `/assets/labels`: printable QR labels for the assets the list shows.
- `/assets/handovers/{assignment}`: printable handover (and return) protocol, for the holder and IT staff.
- `/my-equipment`: the signed-in person's equipment, with **I received it** to confirm a hand-out (menu entry shown only to people who have some).
- The asset list exports the register to Excel or CSV, with the import's columns.
- `/licenses`: licences grouped by product with seats bought and used, over-used products flagged; `/licenses/new`, `/licenses/{id}` (seats, key on request, documents), `/licenses/{id}/edit`. Import and export as for assets.
- Administration → Asset management → Software products.
- Administration → Asset management → Asset categories.
- The CMDB item page lists linked assets; the contract page lists the assets it covers.

## Background

`itam:warranties:remind`, daily at 07:15: one email per organization to people with `itam.asset.manage` about warranties ending within `ITAM_WARRANTY_REMINDER_DAYS` (default 30), once per end date. `itam:licenses:remind`, daily at 07:20: the same for licences expiring within `ITAM_LICENSE_REMINDER_DAYS` (default 60), to people with `itam.license.manage`.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `itam.asset.view` | agent, team-lead, asset-manager | See assets |
| `itam.asset.assign` | agent, team-lead, asset-manager | Hand out, put in service, take back, send for repair |
| `itam.asset.manage` | team-lead, asset-manager | Create and edit, retire, report lost, dispose, delete, link to the CMDB, documents, import, export, labels |
| `itam.category.manage` | team-lead, asset-manager | Asset categories and their attributes |
| `itam.license.view` | agent, team-lead, asset-manager | See licences, seats and compliance |
| `itam.license.assign` | agent, team-lead, asset-manager | Give and release seats |
| `itam.license.manage` | team-lead, asset-manager | Create and edit licences and products, documents, import, export |
| `itam.license.reveal_key` | team-lead, asset-manager | Show a licence key, after an authenticator code (audited) |

Administrators have every permission.

## API (ADR 0062)

Abilities: `assets:read`, `assets:write`. Permissions are the screens' (`itam.asset.*`, `itam.license.*`): agents may hand assets out and back, asset managers may also register, change and retire them.

| Method | Path | |
|---|---|---|
| GET | `/api/v1/assets/categories` | Categories and their attribute schemas |
| GET | `/api/v1/assets` | Filters `category`, `status`, `holder_id`, `location_id`, `search`; sorts `asset_tag`, `name`, `created_at`, `updated_at`, `warranty_ends_on` |
| POST | `/api/v1/assets` | Through `SaveAsset`; status `ordered` or `in_stock` only |
| GET / PATCH | `/api/v1/assets/{id}` | PATCH changes only the fields sent; `status` is refused there |
| POST | `/api/v1/assets/{id}/assign` | `user_id`, `location_id`, `notes`, `notify` (`AssignAsset`, keeps the history) |
| POST | `/api/v1/assets/{id}/status` | `in_stock` (returned), `deployed` (back from repair; without a holder, put in service at `location_id` or the asset's own location), `in_repair`, `retired`, `lost` (`ChangeAssetStatus`); disposal stays on screen |
| GET / POST | `/api/v1/licenses` | Filters `product_id`, `model`, `expires_before`, `search`; `seats_used` counted |
| GET / PATCH | `/api/v1/licenses/{id}` | `license_key` and `remove_key` are write-only: the key is never returned (`has_key`) |
| POST | `/api/v1/licenses/{id}/seats` | `user_id` (per-user) or `asset_id` (per-device) |
| DELETE | `/api/v1/licenses/{id}/seats/{seat}` | |

## Webhook events (ADR 0066)

`asset.assigned`, `asset.returned`; the holder is not named (`ItamWebhooks`, from model events).
