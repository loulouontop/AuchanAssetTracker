# D-03 — Technical / architecture

Plugin name on disk: folder under `plugins/` (e.g. `AuchanAssetTracker`).  
Version: `0.5.0`. Min GLPI: `11.0.0` (target `11.0.2`). PHP: `8.1+`.

## Layout

| Path | Role |
| --- | --- |
| `setup.php` | Version, init, hooks, public QR firewall |
| `hook.php` | Install / upgrade / uninstall, schema heal, menu |
| `install/install.sql` | Tables |
| `inc/*.class.php` | Business logic |
| `front/*.php` | Screens (controllers) |
| `ajax/` | Small helpers (containers, users, models) |
| `public/qr.php` | Public container scan page (no login) |
| `locales/` | EN + RO strings |
| `css/`, `js/` | UI assets |

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

- Stock (**Available**) needs a **physical container**.
- **In transit** / **In service** / **Awaiting validation**: no allocate / transfer.
- Final statuses (**Written off / Lost / Stolen**): immutable until manager **reintroduce** to stock.

## Data model (ERD — text)

**Container** `glpi_plugin_auchanassettracker_containers`  
→ Location (`locations_id`), unique `code`, unique `qr_token`, soft-delete.

**Equipment** `glpi_plugin_auchanassettracker_equipments`  
→ optional link to GLPI asset (`itemtype` + `items_id`), type, manufacturer, Location, Container (when in stock), User (when allocated), `status`, final_* / service_* fields.

**Allocation history** `glpi_plugin_auchanassettracker_allocations`  
→ Equipment, recipient, allocator, previous container, `allocation_status` (pending / confirmed / rejected / returned).

**Audit log** `glpi_plugin_auchanassettracker_auditlogs`  
→ who / action / itemtype / items_id / details / date (append-only from UI).

Also: transfers + transfer items, notices, configs (thresholds), types, manufacturers, stock-by-location cache, profile↔role mapping.

```
Location 1──* Container 1──* Equipment (when Available)
User 1──* Allocation *──1 Equipment
Transfer 1──* TransferItem *──1 Equipment
Ticket (GLPI) ──service──► Equipment.status
Equipment / Container / … ──► Audit log
```

## GLPI integration

| Area | How |
| --- | --- |
| Assets | Plugin stock can create/link Computer (etc.); container field on native asset forms via `post_item_form` + add/update hooks |
| Tickets | Ticket / Item_Ticket hooks → **In service**; solved/closed → **Allocated** |
| Users | Allocation recipient, confirm, notices/emails |
| Entities / Locations | Equipment & containers scoped; managers/techs limited by user Location |
| Profiles | Four bundled profiles + rights matrix tab on Profile; config for Central admin |
| Documents | Allowed on plugin Equipment & Container |
| Menu | Top-level **Auchan Asset Tracker** (`redefine_menus`) |

## Hooks used

- `csrf_compliant`, `post_init`, `redefine_menus`, `add_css`, `add_javascript`
- `config_page` (thresholds)
- `post_item_form`, `item_add` / `pre_item_update` / `item_update` on asset types
- `item_add` / `item_update` on Ticket + Item_Ticket
- Firewall `STRATEGY_NO_CHECK` + stateless path for `/qr.php`
