---
title: Webhooks
audience: administrators
---

A webhook tells another system, such as a chat channel, a monitoring tool or another service desk, when something happens here, for example when a ticket is created. Administrators set them up in **Administration → Integrations → Webhooks**.

## Adding a webhook

1. Choose **New webhook**.
2. Give it a name, the receiver's address and the events it should hear about.
   - The address must start with `https://` and point to a public address. Your administrator can allow addresses on your internal network.
3. Save. A **signing secret** appears once. Copy it to the receiver: it uses the secret to check that each call really comes from here.
   - If the secret is lost or exposed, choose **New secret** and give the new one to the receiver.

**Send a test** sends a test event straight away, so you can check the receiver works.

## What is sent

Each event carries:

- its type, for example `ticket.created`;
- the record's number and title;
- the record's address in the API.

Descriptions, comments and the people involved are never sent. The receiver reads the details through the API, with its own token and only what that token allows.

## Ticket and change events

- Ticket created, assigned, status changed, resolved.
- Ticket close to its SLA target, ticket SLA breached.
- Change submitted for approval, approved, rejected.

## Deliveries

**Administration → Integrations → Webhook deliveries** lists every event sent, with its status and the receiver's answer.

- When the receiver does not answer with success, the event is sent again after 1 minute, 5 minutes, 30 minutes, 2 hours and 12 hours.
- After the last attempt the delivery is marked **Failed**. Choose **Send again** once the receiver is fixed.

Deliveries are kept for one year. You can change this under data retention.

Pausing a webhook (unticking **Active**) stops new events. Deliveries still waiting to be retried are marked **Failed**, and you can send them again later.
