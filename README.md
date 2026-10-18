# AuchanAssetTracker — Sprint 4

GLPI plugin for IT equipment stock, physical containers with QR labels, allocation with user confirmation, inter-location transfers, service via tickets, and write-off / lost / stolen.

**Version:** 0.4.1 (Sprint 4)  
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
- Public scan URL at `/plugins/<dir>/qr.php` (still inside the plugin — live DB)
- GLPI 11 anonymous access via Firewall `STRATEGY_NO_CHECK` + stateless path (see `setup.php`)
- Scan page: container name, code, location; table with Name, Serial, Type, Added on
- Scan = view only; edits stay in desktop GLPI
- Inactive / deleted containers → no data

## Profiles (auto-created)

On install / upgrade the plugin creates four GLPI profiles (central interface) if they do not already exist:

| Profile | Plugin role | What they see |
| --- | --- | --- |
| **Central admin** | `central_admin` | Full operations on **all** locations (stock, containers, allocation, transfers, write-off). |
| **Location manager** | `location_manager` | Same operations, limited to the user’s GLPI **Location**. |
| **Support technician** | `support_tech` | **New allocation** + service on assigned location (no transfers / stock receipt / bulk). Central interface; GLPI rights seeded from **Technician**. |
| **End user** | `user` | **Confirm receipt** only. **Helpdesk** (simplified) interface; GLPI rights seeded from **Self-Service**. |

Central admin / Location manager use the **central** interface and seed GLPI rights from **Admin**. Clients can still edit interface and rights under **Administration → Profiles**.

**Plugin Configuration** (alert thresholds) is available to **Super-Admin** only — not to the Central admin profile.

**Administration → Profiles → Auchan Asset Tracker** shows an editable rights matrix (no Role dropdown). Bundled profiles keep an internal role for location scope.  
Profile names/comments follow the active GLPI language (EN / RO).  
Assign users under **Administration → Users** (profile + Location for managers/techs).

### Alerts vs emails

- **Configuration** day thresholds → **Active alerts** lists only (late confirm / overdue transfer / long service). No email from the threshold alone.
- **Emails + in-app notices** → plugin `Mailhelper` on events (allocate pending, Did not receive, transfer reject). HTML Auchan template (RO-friendly). Not registered in Setup → Notifications / Notification templates (those are native GLPI `NotificationTarget` only).
- **Quick test:** allocate Available gear to a user with email → recipient gets notice + mail; open Confirm on End user (simplified) and reject with comment → allocator gets notice + mail.

## Upgrade from Sprint 3 / 0.4.0

1. Replace plugin files with this branch (keep folder name `AuchanAssetTracker` or `auchanassettracker` as installed).
2. Version becomes **0.4.1**. On next GLPI page load the plugin **self-heals**: runs `upgrade` + `ensure_schema`, creates/updates the four profiles if missing, seeds GLPI rights from Admin/Technician/Self-Service once, then updates `glpi_plugins.version`. Profiles → Auchan Asset Tracker shows an editable rights matrix; End user helpdesk uses only the Auchan Asset Tracker menu (Confirm receipt) and stays on the simplified interface.
3. You may briefly see **Upgrade** on Setup → Plugins if GLPI detects the version mismatch first — click it, or just reload; self-heal usually finishes the job automatically.
4. **No need to uninstall** or delete the four profiles — upgrade refreshes their GLPI rights/interface once.
5. Log out and back in after upgrade (especially End user / Support technician).
6. Check **Administration → Profiles** for **Central admin**, **Location manager**, **Support technician**, **End user**.
7. Open a container → use **Download PDF label** / public URL. Clear browser cache / GLPI cache if needed.

## Requirements

- GLPI 11.0.2 (min 11.0.0)
- PHP 8.1+ (GD recommended for QR PNG generation)

## Install

1. Copy this folder to `plugins/AuchanAssetTracker` (or your existing plugin folder name). Include the `public/` folder.
2. **Setup → Plugins** → Install → Enable (or Upgrade if already installed).
3. After enable, open **Administration → Profiles** — the four Auchan profiles should already exist. Assign users to them; set **Location** on each manager/tech user form. End user is not set as the GLPI default profile for new users.
4. Create locations and containers before receiving stock.
5. Open a container → check URL (left) + QR (right) → print the label; open/scan the public URL (no login) to verify the shelf view.
6. QR links use the host you open GLPI with (not a hardcoded localhost). Optionally set **Setup → General → URL of the application** as a fallback.

## Locale

Romanian translations: `locales/ro_RO.php` (loaded when GLPI language is `ro_RO`).
