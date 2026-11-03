---
title: Assets (equipment)
audience: agents
---

**Configuration & assets → Assets** is the register of the organization's hardware: laptops, monitors, phones, printers, servers, network devices. For each asset it shows who has it, where it is, when its warranty ends, and how it left the organization.

Agents see every asset and hand equipment out and take it back. Asset managers and team leads keep the register: they add assets, change their details, retire them and dispose of them.

## Adding an asset

**New asset**. Choose the **category** first: its attributes appear, for example the memory of a laptop or the IMEI of a phone.

- The **asset tag** is the inventory number on the label. Leave it empty to get the next number (for example *AST-000124*), or enter the label already on the device. Two assets cannot have the same tag or the same serial number, whatever the case of the letters.
- Start as **in stock**, or **ordered** if it has not arrived yet.
- Under **Purchase and warranty**, record the supplier, the support or warranty **contract**, the purchase date and cost, the order and invoice numbers, the **warranty end** and when it should be **replaced**.
- Upload the invoice or the delivery note under **Documents** on the asset's page.

## Handing out and taking back

On the asset's page, under **Who has it**, search for the person by name or email, choose them, optionally a location and a note, and click **Hand out**. The asset is then **in use**. To give it to someone else, do the same: the previous person's period ends at that moment.

The person receives an email and confirms in the portal, on **My equipment**, that they received it. **Handover protocol** in the history opens the protocol: print it, or save it as PDF from the print dialog, and have it signed if your organization requires paper. It shows the electronic confirmation once given, and the return once the asset is back.

**Back in stock** takes it back. **Send for repair** keeps it with its holder; **Back from repair** returns it to them. The page keeps the full **history** of who had the asset, from when to when, and who handed it out.

## Putting infrastructure in service

Servers, switches, UPS units and printers work where they stand, not with a person. Under **Life cycle**, choose the location (for example the server room) and click **Put in service at the location**. The asset is then **in use** with no holder. If its location is already recorded, you can skip choosing it.

- It can go for repair and come back the same way.
- It can also be handed to a person later.

## Retiring, losing and disposing

- **Retire**: the asset is no longer used. Take it back first.
- **Report lost or stolen**: the current holder's period ends. If it turns up, put it **back in stock**.
- **Dispose**, for a retired or lost asset: record the date, how (sold, donated, recycled, destroyed, returned to the lessor) and whether its **data was wiped**, or that it stores no data. Upload the destruction or wiping certificate under **Documents**. Disposal is final: the asset stays in the register, for the record.

Prefer disposal to **Delete**: a deleted asset disappears from the lists.

## In the CMDB

A server or a switch is also a **configuration item** in the CMDB. Under **In the CMDB**, search for the item and click **Link**. An asset is one item at most. The item's page then shows the asset too.

## Warranties

Every morning, asset managers and team leads receive one email listing the warranties that end in the next 30 days. Each warranty is announced once; a new end date is announced again. **Warranty ending soon** in the list shows the same assets.

## Importing and exporting

**Import assets** fills the register from a spreadsheet (CSV or Excel), for the first inventory or a delivery. The page lists the columns; only the category is needed for a new asset.

- A row whose **asset tag** exists updates that asset; without a tag, a row whose **serial number** exists does. Other rows create assets.
- **holder** is the email of the person who has the device: the asset is handed to them, without an email. They confirm it next time they open **My equipment**.
- **location** is the full name, as `HQ > Store room`, or a name used only once. Dates are written `2026-01-31`.
- **Preview** shows what each row will do. If any row has an error, nothing is imported: fix the file and preview again.

**Export to Excel** prepares the whole register in the background; it appears above the list, for you only, for 7 days. Its first columns are those of the import, so you can correct the file and import it back.

## Labels

**Print labels** opens a sheet of labels with QR codes for the assets the list shows: filter the list first. Print at 100% scale on A4 label sheets (3 × 8) or on plain paper. Scanning a code opens the asset's page, which still asks for sign-in: a label shows nothing on its own.
