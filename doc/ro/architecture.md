# D-03 — Tehnic / arhitectură

Nume pe disc: folder în `plugins/` (ex. `AuchanAssetTracker`).  
GLPI min: `11.0.0` (țintă **11.0.2**, max `11.9.99`). PHP: **8.1+** (GD recomandat pentru QR PNG).

## Structură

| Cale | Rol |
| --- | --- |
| `setup.php` | Versiune, init, hooks, firewall QR public |
| `hook.php` | Install / upgrade / uninstall, schema, meniu |
| `install/install.sql` | Tabele |
| `inc/*.class.php` | Logică |
| `front/*.php` | Ecrane |
| `ajax/` | Helpers (containere, useri, modele) |
| `public/qr.php` | Pagină scan container (fără login) |
| `locales/` | EN (`en_GB`) + RO (`ro_RO` / `ro`) |
| `css/`, `js/`, `public/css`, `public/js` | UI |
| `tools/build_package.sh` | Pachet instalabil `.tar.gz` (D-02) |

## Flux status (echipament)

```
Disponibil ──alocare──► Așteaptă validare ──confirmare──► Alocat
    ▲                         │ respingere                      │
    └─────────────────────────┘                                 │
    │◄── validare transfer (dest) ◄── În tranzit ◄── transfer ──┤
    │◄── ticket rezolvat/închis ◄── În service ◄── ticket ──────┤
    │
    └── reintroducere (manager) ◄── Casat / Pierdut / Furat
```

| Valoare DB | Etichetă | Note |
| --- | --- | --- |
| `available` | Disponibil | Stoc — **container fizic obligatoriu** |
| `awaiting_validation` | Așteaptă validare | După alocare; container golit |
| `allocated` | Alocat | User a confirmat |
| `in_transit` | În tranzit | Transfer încă nevalidat |
| `in_service` | În service | Ticket deschis pe echipament urmărit |
| `written_off` / `lost` / `stolen` | Final | Imutabil până la **reintroducere** → Disponibil |

**Operații blocate:** alocare / transfer interzise pe `in_transit`, `in_service` sau `awaiting_validation`. Statusurile finale ies doar prin reintroducere (container obligatoriu).

### Statusuri alocare (`glpi_plugin_auchanassettracker_allocations`)

| Valoare DB | Semnificație |
| --- | --- |
| `pending` | Așteaptă Confirmare / Nu am primit |
| `confirmed` | Confirmat de user |
| `rejected` | Nu am primit (comentariu obligatoriu) |
| `returned` | Returnat în stoc |

### Statusuri transfer (`glpi_plugin_auchanassettracker_transfers`)

| Valoare DB | Semnificație |
| --- | --- |
| `in_transit` | Trimis; destinația validează sau respinge/anulează |
| `completed` | Destinația a acceptat elementele în containere |
| `cancelled` | Respins/anulat (comentariu la respingere) → echipament Disponibil la sursă |

## Model de date

**Container** `glpi_plugin_auchanassettracker_containers`  
→ `locations_id`, `code` unic, `qr_token` unic, `is_active`, soft-delete (`is_deleted`), entitate.

**Echipament** `glpi_plugin_auchanassettracker_equipments`  
→ legătură opțională asset GLPI (`itemtype` + `items_id`), tip, producător, `locations_id`, container (când Disponibil), `users_id` (când alocat), `status`, câmpuri final_* / service_*, `serial` unic când e setat.

**Istoric alocare** `glpi_plugin_auchanassettracker_allocations`  
→ echipament, destinatar, alocator, container anterior, date, `allocation_status`, `reject_comment`.

**Jurnal audit** `glpi_plugin_auchanassettracker_auditlogs`  
→ cine / acțiune / itemtype / items_id / detalii / dată (append-only din UI).

**De asemenea**

| Tabel | Scop |
| --- | --- |
| `…_transfers` + `…_transferitems` | Mutări între locații + linii (container dest. la validare) |
| `…_notices` | Notificări in-app (alocare pending, respingere, respingere transfer) |
| `…_configs` | Chei praguri (vezi mai jos) |
| `…_equipmenttypes` / `…_manufacturers` | Catalog |
| `…_stockbylocations` | Contori cache pe locație |
| `…_profiles` | Profil GLPI → rol plugin (`central_admin`, `location_manager`, `support_tech`, `user`) |

```
Locație 1──* Container 1──* Echipament (când Disponibil)
User 1──* Alocare *──1 Echipament
Transfer 1──* TransferItem *──1 Echipament
Ticket (GLPI) ──service──► Equipment.status
Echipament / Container / … ──► Jurnal audit
```

### Chei config (implicite)

| Cheie | Implicit | Folosit pentru |
| --- | --- | --- |
| `allocation_confirm_days` | 5 | Pending întârziat → Alerte active |
| `transfer_validate_days` | 7 | În tranzit depășit → Alerte active |
| `service_max_days` | 30 | Service lung → Alerte active |

Pragurile alimentează **doar Alerte active** (fără email doar din numărul de zile).

## Roluri & drepturi (profile incluse)

| Profil | Cheie rol | Domeniu | Drepturi principale |
| --- | --- | --- | --- |
| Central admin | `central_admin` | Toate locațiile | Full echipament/container/bulk/alocare/transfer/confirm + **Configurare** |
| Location manager | `location_manager` | Locația userului GLPI | Aceleași ops fără Configurare |
| Support technician | `support_tech` | Locația userului | Alocare + echipament/container limitat; **fără** bulk / transfer / config |
| End user | `user` | Self | Doar confirmare (interfață Helpdesk) |

Câmpuri drepturi: `plugin_auchanassettracker_equipment|container|bulk|allocation|transfer|confirm|config`.  
Matrice editabilă: **Administrare → Profile → Auchan Asset Tracker**.

## Meniu (nivel top **Auchan Asset Tracker**)

| Element | Cine îl vede de obicei |
| --- | --- |
| Dashboard | Toți userii plugin |
| Echipament / Containere fizice | Admin, manager, tech (după drept) |
| Accesorii bulk | Admin, manager |
| Alocare nouă | Admin, manager, tech |
| Transferuri | Admin, manager |
| Confirmare primire | Toți cu drept confirm (incl. End user) |
| Rapoarte | Admin, manager, tech |
| Configurare | Central admin (+ Super-Admin) |

## Integrare GLPI

| Zonă | Cum |
| --- | --- |
| Assets | Stoc plugin poate crea/lega Computer (etc.); câmp container pe formulare asset native |
| Tickets | Ticket / Item_Ticket → **În service**; rezolvat/închis → **Alocat** |
| Users | Destinatar alocare, confirmare, notices/email |
| Entities / Locations | Scop pe echipament & containere; manager/tech limitați de Location |
| Profiles | Patru profile + matrice drepturi; config pentru Central admin |
| Documents | Permise pe Echipament & Container plugin |
| Meniu | Sector top-level via `redefine_menus` |

## Hooks folosite

- `csrf_compliant`, `post_init`, `redefine_menus`, `add_css`, `add_javascript`
- `config_page` (praguri)
- `post_item_form`, `item_add` / `pre_item_update` / `item_update` pe tipuri asset
- `item_add` / `item_update` pe Ticket + Item_Ticket
- Firewall `STRATEGY_NO_CHECK` + cale stateless pentru `/qr.php`
