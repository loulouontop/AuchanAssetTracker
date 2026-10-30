# D-03 — Tehnic / arhitectură

Nume pe disc: folder în `plugins/` (ex. `AuchanAssetTracker`).  
Versiune: `0.5.2`. GLPI min: `11.0.0` (țintă `11.0.2`). PHP: `8.1+`.

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
| `locales/` | EN + RO |
| `css/`, `js/` | UI |

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

- Stoc (**Disponibil**) cere **container fizic**.
- **În tranzit** / **În service** / **Așteaptă validare**: fără alocare / transfer.
- Status final (**Casat / Pierdut / Furat**): imutabil până la **reintroducere** în stoc de către manager.

## Model de date (ERD text)

**Container** `glpi_plugin_auchanassettracker_containers`  
→ Locație, `code` unic, `qr_token` unic, soft-delete.

**Echipament** `glpi_plugin_auchanassettracker_equipments`  
→ link opțional asset GLPI (`itemtype` + `items_id`), tip, producător, Locație, Container (în stoc), User (alocat), `status`, câmpuri final_* / service_*.

**Istoric alocare** `glpi_plugin_auchanassettracker_allocations`  
→ Echipament, destinatar, alocator, container anterior, `allocation_status` (pending / confirmed / rejected / returned).

**Audit** `glpi_plugin_auchanassettracker_auditlogs`  
→ cine / acțiune / tip / id / detalii / dată.

Plus: transferuri + items, notices, configs (praguri), tipuri, producători, cache stoc pe locație, mapare profil↔rol.

```
Locație 1──* Container 1──* Echipament (când Disponibil)
User 1──* Alocare *──1 Echipament
Transfer 1──* TransferItem *──1 Echipament
Ticket (GLPI) ──service──► Equipment.status
Echipament / Container / … ──► Audit
```

## Integrare GLPI

| Zonă | Cum |
| --- | --- |
| Assets | Stoc plugin poate crea/lega Computer etc.; câmp container pe formulare asset |
| Tickets | Hook Ticket / Item_Ticket → **În service**; rezolvat/închis → **Alocat** |
| Users | Destinatar alocare, confirmare, notices/email |
| Entities / Locations | Scope pe echipament & containere; manager/tech limitați la Location user |
| Profiles | 4 profile + matrice drepturi pe Profile; config pentru Central admin |
| Documents | Permise pe Equipment & Container plugin |
| Meniu | **Auchan Asset Tracker** (`redefine_menus`) |

## Hook-uri folosite

- `csrf_compliant`, `post_init`, `redefine_menus`, `add_css`, `add_javascript`
- `config_page` (praguri)
- `post_item_form`, `item_add` / `pre_item_update` / `item_update` pe tipuri asset
- `item_add` / `item_update` pe Ticket + Item_Ticket
- Firewall `STRATEGY_NO_CHECK` + path fără sesiune pentru `/qr.php`
