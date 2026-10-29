# Full plugin presentation plan — Auchan Asset Tracker

**Audience:** Auchan IT stakeholders (project, ops, UAT)  
**Product:** AuchanAssetTracker **0.5.0** (Sprints 1–5 + Phase 6 docs)  
**Platform:** GLPI **11.0.2** · PHP **8.1+** · locales **EN + RO**  
**Suggested length:** ~30–40 min + live demo + Q&A  
**Deck:** [Google Slides — Full Delivery](https://docs.google.com/presentation/d/1vUwWxFFCJe8wSs7RqSvsD4l4P7xWqBYn6IHnwDesn2U/edit) (built from this plan; Canva AI generation unavailable on the connected team)  

---

## Purpose

Present the **completed** IT equipment stock & traceability plugin: what was built, how roles use it, how it integrates with GLPI, and what remains for UAT / go-live (inventory prep, install on UAT/prod, December freeze).

## Narrative arc

Hook (problem of scattered stock) → Solution (plugin in GLPI) → Lifecycle & roles → Feature walkthrough by sprint → Ops (alerts, QR, reports) → Deliverables & readiness → Ask / next steps.

## Timing

| Block | Minutes | Slides |
| --- | --- | --- |
| Context & scope | 4 | 1–3 |
| Lifecycle & roles | 5 | 4–5 |
| Feature walkthrough | 12 | 6–11 |
| Dashboards, reports, alerts | 5 | 12–13 |
| Docs, deploy, readiness | 5 | 14–15 |
| Q&A / next steps | 5+ | 16 |

Live demo (optional, +15–20 min): container + QR → receive → allocate → confirm → transfer → ticket/service → dashboard/report.

---

## Slide plan

### Slide 1 — Title

**Title:** Auchan Asset Tracker — Full delivery  
**Bullets:**

- GLPI plugin for IT stock, containers, allocation, transfers, service, write-off
- Version 0.5.0 · Sprints 1–5 complete · Phase 6 documentation ready
- Presenter: Lokmane Benaziza

**Speaker notes:** Align with kickoff goals: equipment managed inside GLPI before go-live; plugin named to differentiate from prior work.

### Slide 2 — Agenda

1. Why this plugin  
2. End-to-end equipment lifecycle  
3. Roles & access  
4. Features by sprint  
5. Dashboards, reports, alerts  
6. Documentation & go-live readiness  
7. Questions  

### Slide 3 — Problem & outcome

**Goal:** One sentence on business value.

- Before: stock and movements hard to trust across locations  
- After: container-bound stock, user confirmation, transfers, ticket-driven service, audits  
- Outcome: single Auchan Asset Tracker menu in GLPI (EN/RO)

### Slide 4 — Equipment lifecycle

Status flow (speak from diagram):

```
Available → Awaiting validation → Allocated
                ↘ reject → Available
Allocated → In transit → Available (dest) / reject
Allocated → In service (ticket) → Allocated
Any allowed path → Written off / Lost / Stolen → Reintroduce → Available
```

Rules to stress: Available requires a physical container; In transit / In service / Awaiting validation block allocate & transfer; final statuses immutable until manager reintroduce.

### Slide 5 — Four roles

| Profile | Scope | Main screens |
| --- | --- | --- |
| Central admin | All locations | Dashboard, stock, transfers, reports, Configuration |
| Location manager | Own Location | Same ops, scoped |
| Support technician | Own Location | New allocation + service; no stock receipt / transfers by default |
| End user | Self | Dashboard + Confirm receipt (Helpdesk UI) |

Profiles auto-created on install/upgrade; Location required on managers/techs.

### Slide 6 — Sprint 1: Stock foundation

- Physical containers (CRUD, per location, soft-delete)  
- Equipment receipt (single + bulk accessories) → **Available**  
- Mandatory container in stock  
- Audit log on create/update  
- EN + RO locales  

### Slide 7 — Sprint 2: Allocation & confirmation

- Allocate Available → **Awaiting validation** (container cleared)  
- End user: Confirm / Did not receive (comment mandatory on reject)  
- Overdue confirmation alerts (configurable days, default 5)  
- Allocation history + email / in-app notices  
- Top-level **Auchan Asset Tracker** menu  

### Slide 8 — Sprint 3: Transfers, service, write-off

- Transfers: source → **In transit** → dest validates with container (or reject + comment)  
- Sender may edit/delete while in transit  
- Ticket on tracked gear → **In service**; solved/closed → **Allocated**  
- Write-off / Lost / Stolen (+ reason/document); manager reintroduce only  
- History / Documents tabs; alert thresholds for transfer & service days  

### Slide 9 — Sprint 4: QR shelf labels

- Unique `qr_token` per container  
- Form preview (URL + QR) + printable label  
- Public scan URL `/plugins/<dir>/qr.php` (no login; Firewall no-check)  
- Scan shows container + contents (view only)  
- Inactive / deleted containers → no data  

### Slide 10 — Sprint 5: Dashboards & reports

- Role home dashboards (admin totals/alerts/stock/moves; manager/tech stock & to-resolve; end user pending + my gear)  
- Reports + **CSV / Excel**: inventory by location, gear per user, movements, stock by type, issues over thresholds, inventory by container  
- Configuration panel: 3 day thresholds (Central admin)  

### Slide 11 — Alerts vs emails (clarify)

- Thresholds → **Active alerts** lists only (no mail from threshold alone)  
- Event notices + HTML mail (allocate pending, Did not receive, transfer reject) via plugin Mailhelper  
- Not registered as native GLPI NotificationTarget templates  

### Slide 12 — Demo path (script)

1. Create/open container → print/scan QR  
2. Receive equipment into container → Available  
3. Allocate to end user → confirm on Helpdesk  
4. Transfer to another location → validate with container  
5. Open ticket → In service → close → Allocated  
6. Open Dashboard + one report export  

### Slide 13 — Deliverables (Phase 6)

| ID | Content |
| --- | --- |
| D-01 / D-02 | Source + installable package (`tools/build_package.sh`) |
| D-03 | Architecture / data model |
| D-04 | Install / upgrade / rollback |
| D-05 | API / hooks / QR |
| D-06–D-08 | User manuals (all roles) |
| D-09–D-12 | Tests / QA / defects / regression |
| D-13 | Troubleshooting |

Folder: `doc/en/` and `doc/ro/`.

### Slide 14 — Deploy & go-live readiness

From kickoff (21.09.2026):

- Inventory/data prep on Auchan side in parallel with delivery  
- UAT install: Alexandru; production: Auchan infra  
- December freeze ~10 Dec — options: ready early Dec (02–03.12) or stabilize for first week of January  

Checklist: package 0.5.0 → UAT enable → profiles + locations → smoke (QR, allocate/confirm, transfer, dashboard/report) → prod.

### Slide 15 — Risks & asks

- Inventory quality before go-live  
- UAT window before freeze  
- Confirm prod folder name (`AuchanAssetTracker`) and public QR URL reachability  
- Ask: preferred go-live window (early Dec vs early Jan)  

### Slide 16 — Thank you / Q&A

- Contact / next meeting  
- Links: repo, `doc/`, Canva deck, UAT URL (when available)  

---

## Demo environment checklist

- [ ] GLPI 11.0.2 UAT with plugin **0.5.0** enabled  
- [ ] Four profiles assigned; sample Location + containers  
- [ ] Sample users: admin, manager, tech, end user (with email for notice test)  
- [ ] Phone/browser ready for QR scan  
- [ ] One open ticket template for service demo  

## After the meeting

1. Share Canva link + PDF export  
2. Share `doc/` package or built `.tar.gz`  
3. Log decisions on go-live date and UAT install date  
4. Track open defects with D-11 template in `testing.md`
