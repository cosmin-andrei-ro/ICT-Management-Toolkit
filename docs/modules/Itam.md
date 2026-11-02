# IT asset management (ITAM) module

Hardware assets: who has them, where they are, their warranty and how they left the organization. Code: `modules/Itam/`. Decisions: [ADR 0020](../adr/0020-it-asset-management.md).

## Concepts

- **Category** (`itam_categories`): a kind of asset with its attributes (Core form schema). Ten defaults per organization, created on first use: laptop, desktop, monitor, phone, tablet, printer, server, network device, storage, peripheral.
- **Asset** (`itam_assets`): asset tag (automatic `AST-000001` or entered), name, manufacturer, model, serial number, status, location, holder, supplier, contract, purchase date and cost, order and invoice numbers, warranty end, planned replacement, attribute values (`properties`), notes, disposal details, documents (Core attachments).
- **Assignment** (`itam_asset_assignments`): who had an asset, from when to when; one open assignment per asset.

## Life cycle

`AssetStatus::next()`: ordered → in stock or in use; in stock → in use, in repair, retired, lost; in use → in stock, in repair, lost; in repair → in stock, in use (back to its holder), retired; retired → in stock, disposed; lost → in stock, disposed; disposed is final. Actions: `SaveAsset` (details), `AssignAsset`, `ChangeAssetStatus`, `DisposeAsset`.

## Screens

- `/assets`: list with search (tag, serial number, name, model, attribute values) and filters by category, status, location and warranty ending.
- `/assets/new`, `/assets/{id}/edit`: form; the attribute fields follow the category.
- `/assets/{id}`: holder and hand-out, life cycle and disposal, CMDB link, details, purchase and warranty, history of holders, documents.
- `/my-equipment`: the signed-in person's equipment (menu entry shown only to people who have some).
- Administration → Asset management → Asset categories.
- The CMDB item page lists linked assets; the contract page lists the assets it covers.

## Background

`itam:warranties:remind`, daily at 07:15: one email per organization to people with `itam.asset.manage` about warranties ending within `ITAM_WARRANTY_REMINDER_DAYS` (default 30), once per end date.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `itam.asset.view` | agent, team-lead, asset-manager | See assets |
| `itam.asset.assign` | agent, team-lead, asset-manager | Hand out, take back, send for repair |
| `itam.asset.manage` | team-lead, asset-manager | Create and edit, retire, report lost, dispose, delete, link to the CMDB, documents |
| `itam.category.manage` | team-lead, asset-manager | Asset categories and their attributes |

Administrators have every permission.
