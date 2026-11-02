---
title: Problems and known errors
audience: agents
---

When many people report the same thing ("email is slow"), each report is an **incident**, and their common cause is a **problem**. Problem management finds and removes the cause, so the incidents stop.

## Linking incidents to a problem

On an incident, use the **Problem** panel on the right:

- choose an open problem and click **Link**;
- or click **Open a problem from this incident** to create a new problem. It starts with the incident's summary and details, and the incident is linked to it.

**Unlink** removes the link. Problems get numbers such as `PRB-000003`, and you find them on the service desk page with the **Type** filter.

## Investigating

On the problem, fill in the **Analysis**:

- **Root cause:** why it happens.
- **Workaround:** what people can do meanwhile.

Click **Mark as known error** once the workaround is documented. A known error must keep its workaround.

## The known error database

**Service desk → Known errors** in the menu lists every open known error with its workaround, most widespread first. Search with the symptom or the error text the user reports.

When you link an incident to a known error, its workaround is added to the incident as an **internal note**, ready for you to apply or pass on.

## Resolving a problem

When the cause is fixed, resolve the problem as usual. The option **Also resolve the open linked incidents** is ticked by default. It resolves every linked incident that is still open, with a note saying it was resolved together with the problem. Each requester gets the usual email and can reopen if it is not fixed for them.

Problems are internal: requesters never see them, and they have no SLA clocks.
