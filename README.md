# AuchanAssetTracker — Sprint 2

GLPI plugin for IT equipment stock, physical containers, and allocation with user confirmation.

**Version:** 0.2.1 (Sprint 2)  
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

- Stock + containers (Sprint 1)
- Allocate Available → Awaiting validation (container cleared)
- User Confirm / Did not receive
- Overdue confirmation alerts (configurable **calendar days**, default 5)
- Allocation history + email / in-app event notices

## Rejection flow (Did not receive)

1. End user opens **Confirm receipt** and chooses **Did not receive**.
2. Allocation status → **rejected**.
3. Equipment status → **Available**; plugin restores the **previous physical container** when it still exists and belongs to the location.
4. Linked GLPI asset owner is cleared.
5. Allocator gets **one event notice** (email if configured, otherwise in-app):
   - If the shelf was restored → stock is fine; no Active alert.
   - If the previous shelf is missing/inactive → item stays Available **without** container and appears once under **Active alerts → Needs a container**.
6. **Active alerts** = live operational list (late confirmations + needs container).  
   **Notices** = one-shot events (reject / pending mail). They are not duplicated for the same overdue list.

## Requirements

- GLPI 11.0.2 (min 11.0.0)
- PHP 8.1+

## Install

1. Copy this folder to `plugins/` and keep **one** name only (recommended: `AuchanAssetTracker`).
   - On Linux the folder name is case-sensitive. Do **not** keep both `auchanassettracker` and `AuchanAssetTracker`.
   - The plugin syncs `glpi_plugins.directory` to the exact folder name on disk so menus and links stay aligned.
2. **Setup → Plugins** → Install → Enable (or open the page once after an update — self-heal finishes the upgrade).
3. Map roles under **Administration → Profiles → AuchanAssetTracker**.
4. Create locations and containers before receiving stock.

## Menu

Top-level **Auchan Asset Tracker** (not under Assets), with sub-pages by role.

## Acceptance check

Manager creates a shelf, adds a laptop, allocates to a user; user confirms (or marks did not receive).
