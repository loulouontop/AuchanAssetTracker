# D-13 — Debug & troubleshooting

## Logging

| Ce | Unde |
| --- | --- |
| Log fișier plugin | `{GLPI_LOG_DIR}/plugin_auchanassettracker.log` (`PluginAuchanassettrackerPluginlog`) |
| Niveluri | INFO / WARNING / ERROR (+ helper exception) |
| Log-uri core GLPI | Același `GLPI_LOG_DIR` (`php-errors.log` etc.) |
| Audit (business) | Tabel `glpi_plugin_auchanassettracker_auditlogs` + UI Audit |

Mai multă verbositate: activează debug GLPI (**Setup → General → System** / debug bar) în timp ce reproduci; urmărește `plugin_auchanassettracker.log` și log-ul PHP. Nu există switch „trace” separat — pe copie non-prod poți adăuga temporar `PluginAuchanassettrackerPluginlog::info()`.

## Checklist diagnostic

### Blocat: Așteaptă validare

1. Deschide echipamentul → status + alocare pending.
2. Destinatar Confirm / Did not receive (sau cale admin).
3. SQL: alocări pending (vezi mai jos).
4. Dacă e orphan: repară din UI dacă se poate; nu inventa status în DB fără backup.

### Blocat: În tranzit

1. **Transfers** → rând `in_transit`.
2. Dest validează cu containere, sau reject/cancel.
3. Verifică transfer items pe echipament.

### Blocat: În service

1. Vezi `service_tickets_id` pe echipament.
2. Deschide ticket — solve/close → revine Alocat.
3. Dacă ticket lipsește dar status e stuck: verifică starea ticket + audit.

### Asociere container greșită

1. Disponibil trebuie să aibă `plugin_auchanassettracker_containers_id` > 0 și aceeași Locație.
2. Alocat / awaiting / transit de obicei au container `0`.
3. Pagina QR listează doar echipamentul nedeles din acel container.

### QR inaccesibil

1. URL: `/plugins/{ExactFolder}/qr.php?t=…` — **fără** `/public/`.
2. Nume folder = `glpi_plugins.directory` (case-sensitive pe Linux).
3. `setup.php` înregistrează Firewall `STRATEGY_NO_CHECK` pentru `qr.php`.
4. Container activ, nedeleted, token corect.
5. Test în fereastră privată (delogat).

### `plugin_version_<folder> method must be defined` / Unable to load plugin information

GLPI denumește funcțiile de bootstrap după folderul exact din `plugins/` (`plugin_version_<dir>`, `plugin_init_<dir>`, …). Codul canonic folosește `auchanassettracker`. Diferențele de majuscule sunt OK; o **ortografie diferită** (ex. `auchanassettraacker`) bloca încărcarea până la alias-uri.

1. Preferabil: redenumește folderul în `auchanassettracker` sau `AuchanAssetTracker`, apoi **Setup → Plugins** → refresh / upgrade.
2. Sau desfășoară un build cu alias-uri de director (în `setup.php` actual).
3. Verifică că `glpi_plugins.directory` = folderul de pe disc după încărcare (self-heal îl rescrie).

## SQL read-only (doar inspectare)

Înlocuiește ID-urile. Nu UPDATE/DELETE în producție fără backup și ticket.

```sql
-- Prezentare echipamente
SELECT id, name, serial, status, locations_id,
       plugin_auchanassettracker_containers_id AS container_id,
       users_id, service_tickets_id, date_mod
FROM glpi_plugin_auchanassettracker_equipments
WHERE is_deleted = 0
ORDER BY date_mod DESC
LIMIT 50;

-- Un echipament
SELECT * FROM glpi_plugin_auchanassettracker_equipments WHERE id = ?;

-- Container + token
SELECT id, code, name, locations_id, qr_token, is_active, is_deleted
FROM glpi_plugin_auchanassettracker_containers
WHERE id = ? OR qr_token = ?;

-- Conținut container
SELECT id, name, serial, status
FROM glpi_plugin_auchanassettracker_equipments
WHERE plugin_auchanassettracker_containers_id = ?
  AND is_deleted = 0;

-- Istoric alocare
SELECT * FROM glpi_plugin_auchanassettracker_allocations
WHERE plugin_auchanassettracker_equipments_id = ?
ORDER BY id DESC;

-- Transferuri deschise
SELECT * FROM glpi_plugin_auchanassettracker_transfers
WHERE transfer_status = 'in_transit';

SELECT * FROM glpi_plugin_auchanassettracker_transferitems
WHERE plugin_auchanassettracker_transfers_id = ?;

-- Audit
SELECT id, users_id, action, itemtype, items_id, details, date_creation
FROM glpi_plugin_auchanassettracker_auditlogs
ORDER BY id DESC
LIMIT 100;

-- Praguri
SELECT name, value FROM glpi_plugin_auchanassettracker_configs;
```

## Rollback / recovery

1. **Înainte de upgrade:** backup DB + folder `plugins/{dir}`.
2. Versiune defectă: disable → restaurează folder vechi → enable/upgrade.
3. Uninstall = soft (tabele rămân). Drop tabele plugin = ultima opțiune (pierzi doar istoricul plugin) — nu atinge Assets/Tickets/Users.
4. Migrație greșită: restore backup DB; ideal doar tabele plugin dacă DBA poate izola.
5. Nu „repara” status final / tranzit cu SQL brut fără să înțelegi legăturile alocare/transfer/ticket.

## Checklist mediu / versiuni

| Item | Cum verifici |
| --- | --- |
| GLPI 11.0.2 | Setup → General / about |
| Plugin 0.5.0 | Setup → Plugins |
| PHP ≥ 8.1 | `php -v` sau info server |
| GD (QR PNG) | `php -m \| grep -i gd` |
| MySQL/MariaDB | Cerințele GLPI |
| Casing folder plugin | = `glpi_plugins.directory` |
| `url_base` (opțional) | Setup → General — dacă lipsește host-ul din request |
