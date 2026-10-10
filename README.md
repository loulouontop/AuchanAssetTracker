# Auchan Asset Tracker — Sprint 3

GLPI plugin for IT equipment stock, physical containers, allocation with user confirmation, inter-location transfers, service via tickets, and write-off / lost / stolen.

**Version:** 0.3.0 (Sprint 3)  
**Author:** Lokmane BENAZIZA  
**License:** Auchan RO  

## Sprint 1 scope

- Physical containers (CRUD, per location, soft-delete)
- Equipment receipt (single + bulk accessories) → status **Available**
- Mandatory container when equipment is in stock
- Four roles: Central admin, Location manager, Support technician, End user
- Audit log on create/update
- Locales: English + Romanian (`ro_RO`)

## Sprint 2 scope

- Allocate Available → Awaiting validation (container cleared)
- User Confirm / Did not receive
- Overdue confirmation alerts (configurable days, default 5)
- Allocation history + email / in-app notices

## Sprint 3 scope

- Transfers: source → **In transit** → destination validates with mandatory container
- Block allocate/transfer while In transit or In service
- Service hook: ticket on tracked gear → **In service**; solved/closed → **Allocated**
- Write-off / Lost / Stolen (reason required; only Central Admin can reintroduce)
- Alert thresholds config: allocation / transfer / service days

## Requirements

- GLPI 11.0.2 (min 11.0.0)
- PHP 8.1+

## Install

1. Copy this folder to `plugins/auchanassettracker` (include `public/`).
2. **Setup → Plugins** → Install → Enable.
3. Map roles under **Administration → Profiles → Auchan Asset Tracker**.
4. Create locations and containers before receiving stock.

## Locale

Romanian translations: `locales/ro_RO.php` (loaded when GLPI language is `ro_RO`).
