# D-03 — Technical / architecture

Plugin name on disk: folder under `plugins/` (e.g. `AuchanAssetTracker`).  
Min GLPI: `11.0.0` (target **11.0.2**, max `11.9.99`). PHP: **8.1+** (GD recommended for QR PNG).

## Layout

| Path | Role |
| --- | --- |
| `setup.php` | Version, init, hooks, public QR firewall |
| `hook.php` | Install / upgrade / uninstall, schema heal, menu |
| `install/install.sql` | Tables |
| `inc/*.class.php` | Business logic |
| `front/*.php` | Screens (controllers) |
| `ajax/` | Containers / users / models helpers |
| `public/qr.php` | Public container scan page (no login) |
| `locales/` | EN (`en_GB`) + RO (`ro_RO` / `ro`) |
| `css/`, `js/`, `public/css`, `public/js` | UI assets |
| `tools/build_package.sh` | Installable `.tar.gz` (D-02) |

## Status flow (equipment)

```
Available ──allocate──► Awaiting validation ──confirm──► Allocated
    ▲                         │ reject                         │
    └─────────────────────────┘                                │
    │◄── transfer validate (dest) ◄── In transit ◄── transfer ─┤
    │◄── ticket solved/closed ◄── In service ◄── ticket open ──┤
    │
    └── reintroduce (manager) ◄── Written off / Lost / Stolen
```

| DB value | Label | Notes |
| --- | --- | --- |
| `available` | Available | Stock — **physical container required** |
| `awaiting_validation` | Awaiting validation | After allocate; container cleared |
| `allocated` | Allocated | User confirmed |
| `in_transit` | In transit | Transfer not yet validated |
| `in_service` | In service | Open ticket on tracked gear |
| `written_off` / `lost` / `stolen` | Final | Immutable until manager **reintroduce** → Available |

**Blocked ops:** allocate / transfer not allowed while `in_transit`, `in_service`, or `awaiting_validation`. Final statuses cannot leave except via reintroduce (container mandatory).

### Allocation statuses (`glpi_plugin_auchanassettracker_allocations`)

| DB value | Meaning |
| --- | --- |
| `pending` | Waiting Confirm / Did not receive |
| `confirmed` | User confirmed |
| `rejected` | Did not receive (comment required) |
| `returned` | Returned to stock path |

### Transfer statuses (`glpi_plugin_auchanassettracker_transfers`)

| DB value | Meaning |
| --- | --- |
| `in_transit` | Sent; destination must validate or reject/cancel |
| `completed` | Destination accepted items into containers |
| `cancelled` | Rejected/cancelled (comment when reject) → gear back Available at source |

## Data model

**Container** `glpi_plugin_auchanassettracker_containers`  
→ `locations_id`, unique `code`, unique `qr_token`, `is_active`, soft-delete (`is_deleted`), entity.

**Equipment** `glpi_plugin_auchanassettracker_equipments`  
→ optional GLPI asset (`itemtype` + `items_id`), type, manufacturer, `locations_id`, container (when Available), `users_id` (when allocated), `status`, final_* / service_* fields, unique `serial` when set.

**Allocation history** `glpi_plugin_auchanassettracker_allocations`  
→ equipment, recipient, allocator, previous container, dates, `allocation_status`, `reject_comment`.

**Audit log** `glpi_plugin_auchanassettracker_auditlogs`  
→ who / action / itemtype / items_id / details / date (append-only from UI).

**Also**

| Table | Purpose |
| --- | --- |
| `…_transfers` + `…_transferitems` | Inter-location moves + line items (dest container on validate) |
| `…_notices` | In-app notices (allocate pending, reject, transfer reject) |
| `…_configs` | Threshold keys (see below) |
| `…_equipmenttypes` / `…_manufacturers` | Catalog |
| `…_stockbylocations` | Cached counts per location |
| `…_profiles` | GLPI profile → plugin role (`central_admin`, `location_manager`, `support_tech`, `user`) |

```
Location 1──* Container 1──* Equipment (when Available)
User 1──* Allocation *──1 Equipment
Transfer 1──* TransferItem *──1 Equipment
Ticket (GLPI) ──service──► Equipment.status
Equipment / Container / … ──► Audit log
```

### Config keys (defaults)

| Key | Default | Used for |
| --- | --- | --- |
| `allocation_confirm_days` | 5 | Late pending → Active alerts |
| `transfer_validate_days` | 7 | Overdue in-transit → Active alerts |
| `service_max_days` | 30 | Long service → Active alerts |

Thresholds feed **Active alerts only** (no email from the day count alone).

## Roles & rights (bundled profiles)

| Profile | Role key | Scope | Main rights |
| --- | --- | --- | --- |
| Central admin | `central_admin` | All locations | Full equipment/container/bulk/allocation/transfer/confirm + **Configuration** |
| Location manager | `location_manager` | User’s GLPI Location | Same ops except Configuration |
| Support technician | `support_tech` | User’s Location | Allocation + limited equipment/container; **no** bulk / transfer / config |
| End user | `user` | Self | Confirm receipt only (Helpdesk interface) |

Rights fields: `plugin_auchanassettracker_equipment|container|bulk|allocation|transfer|confirm|config`.  
Editable matrix: **Administration → Profiles → Auchan Asset Tracker**.

## Menu (top-level **Auchan Asset Tracker**)

| Item | Who typically sees it |
| --- | --- |
| Dashboard | All plugin users |
| Equipment / Physical containers | Admin, manager, tech (by right) |
| Bulk accessories | Admin, manager |
| New allocation | Admin, manager, tech |
| Transfers | Admin, manager |
| Confirm receipt | All who have confirm (incl. End user) |
| Reports | Admin, manager, tech |
| Configuration | Central admin (+ Super-Admin) |

## GLPI integration

| Area | How |
| --- | --- |
| Assets | Plugin stock can create/link Computer (etc.); container field on native asset forms via `post_item_form` + add/update hooks |
| Tickets | Ticket / Item_Ticket → **In service**; solved/closed → **Allocated** |
| Users | Allocation recipient, confirm, notices/emails |
| Entities / Locations | Equipment & containers scoped; managers/techs limited by user Location |
| Profiles | Four bundled profiles + rights matrix; config for Central admin |
| Documents | Allowed on plugin Equipment & Container |
| Menu | Top-level sector via `redefine_menus` |

## Hooks used

- `csrf_compliant`, `post_init`, `redefine_menus`, `add_css`, `add_javascript`
- `config_page` (thresholds)
- `post_item_form`, `item_add` / `pre_item_update` / `item_update` on asset types
- `item_add` / `item_update` on Ticket + Item_Ticket
- Firewall `STRATEGY_NO_CHECK` + stateless path for `/qr.php`
