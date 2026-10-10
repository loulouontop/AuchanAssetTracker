# AuchanAssetTracker — Sprint 3

GLPI plugin for IT equipment stock, physical containers, allocation with user confirmation, inter-location transfers, service via tickets, and write-off / lost / stolen.

**Version:** 0.3.1 (Sprint 3)  
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
- Overdue confirmation alerts (configurable **calendar days**, default 5)
- Allocation history + email / in-app event notices
- Top-level **Auchan Asset Tracker** GLPI menu (native UI chrome)

## Sprint 3 scope

- Transfers: source → **In transit** → destination validates with mandatory container
- Block allocate/transfer while In transit or In service
- Service hook: ticket on tracked gear → **In service**; solved/closed → **Allocated**
- Write-off / Lost / Stolen (reason required; only Central Admin can reintroduce)
- Alert thresholds: allocation / transfer / service days

## Profile rights (GLPI-style)

Administration → Profiles → **Auchan Asset Tracker** tab shows a rights matrix (same columns as GLPI assets: View all, Update all, Create, Delete, Purge, notes, assigned, owned) plus Location scope in the same table UI.  
One **Save** updates rights and location together. Unchecked rights **hide** the matching menu tab / add button.

## Upgrade from Sprint 2

1. Replace plugin files with this branch (keep folder name `AuchanAssetTracker` or `auchanassettracker` as installed).
2. Version becomes **0.3.1**. On next GLPI page load the plugin **self-heals**: runs `upgrade` + `ensure_schema` (adds transfer tables and `final_*` / `service_*` columns), migrates legacy roles into ProfileRights, then updates `glpi_plugins.version`.
3. You may briefly see **Upgrade** on Setup → Plugins if GLPI detects the version mismatch first — click it, or just reload; self-heal usually finishes the job automatically.
4. Clear browser cache / GLPI cache if the menu does not refresh. Re-login (or switch profile) so session rights refresh.

## Requirements

- GLPI 11.0.2 (min 11.0.0)
- PHP 8.1+

## Install

1. Copy this folder to `plugins/AuchanAssetTracker` (or your existing plugin folder name).
2. **Setup → Plugins** → Install → Enable (or Upgrade if already installed).
3. Set rights and location scope under **Administration → Profiles → Auchan Asset Tracker**.
4. Create locations and containers before receiving stock.

## Locale

Romanian translations: `locales/ro_RO.php` (loaded when GLPI language is `ro_RO`).
