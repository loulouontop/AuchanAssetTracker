# AuchanAssetTracker — Sprint 4

GLPI plugin for IT equipment stock, physical containers with QR labels, allocation with user confirmation, inter-location transfers, service via tickets, and write-off / lost / stolen.

**Version:** 0.4.0 (Sprint 4)  
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
- User Confirm / Did not receive (mandatory comment on reject)
- Overdue confirmation alerts (configurable days, default 5)
- Allocation history + email / in-app event notices
- Top-level **Auchan Asset Tracker** GLPI menu (native UI chrome)

## Sprint 3 scope

- Transfers: source → **In transit** → destination validates with mandatory container
- Sender can edit (destination / notes / equipment) or delete while in transit; destination can reject with comment
- Block allocate/transfer while In transit or In service
- Service hook: ticket on tracked gear → **In service** (GLPI asset Status aligned); solved/closed → **Allocated**
- Write-off / Lost / Stolen (reason + document; only manager can reintroduce)
- History / Documents tabs; Location physical containers + contents
- Alert thresholds: allocation / transfer / service days

## Sprint 4 scope

- Unique `qr_token` per physical container
- QR code preview on the container form (URL left, QR right) + printable PDF/HTML shelf label
- Public scan URL at `/plugins/<dir>/qr.php` (GLPI public file, no login on the LAN)
- Scan page: container name, code, location; table with Name, Serial, Type, Added on
- Scan = view only; edits stay in desktop GLPI
- Inactive / deleted containers → no data

## Profile role

Administration → Profiles → **Auchan Asset Tracker** → set **Role** (rights come from the role).  
Location scope for location managers / support techs uses the user’s native GLPI **User → Location**. Central admin has no location limit.

## Upgrade from Sprint 3

1. Replace plugin files with this branch (keep folder name `AuchanAssetTracker` or `auchanassettracker` as installed).
2. Version becomes **0.4.0**. On next GLPI page load the plugin **self-heals**: runs `upgrade` + `ensure_schema` (adds `qr_token` and unique index, backfills tokens), then updates `glpi_plugins.version`.
3. You may briefly see **Upgrade** on Setup → Plugins if GLPI detects the version mismatch first — click it, or just reload; self-heal usually finishes the job automatically.
4. Open a container → use **Download PDF label** / public URL. Clear browser cache / GLPI cache if needed.

## Requirements

- GLPI 11.0.2 (min 11.0.0)
- PHP 8.1+ (GD recommended for QR PNG generation)

## Install

1. Copy this folder to `plugins/AuchanAssetTracker` (or your existing plugin folder name). Include the `public/` folder.
2. **Setup → Plugins** → Install → Enable (or Upgrade if already installed).
3. Set the plugin **Role** under **Administration → Profiles → Auchan Asset Tracker**, and set each user’s **Location** on the GLPI user form when they need location scope.
4. Create locations and containers before receiving stock.
5. Open a container → check URL (left) + QR (right) → print the label; open/scan the public URL (no login) to verify the shelf view.
6. QR links use the host you open GLPI with (not a hardcoded localhost). Optionally set **Setup → General → URL of the application** as a fallback.

## Locale

Romanian translations: `locales/ro_RO.php` (loaded when GLPI language is `ro_RO`).
