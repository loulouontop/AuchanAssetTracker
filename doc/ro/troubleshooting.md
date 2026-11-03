# D-13 — Debug & troubleshooting

## Logging

| Ce | Unde |
| --- | --- |
| Log fișier plugin | `{GLPI_LOG_DIR}/plugin_auchanassettracker.log` (`PluginAuchanassettrackerPluginlog`) |
| Niveluri | INFO / WARNING / ERROR (+ helper excepții) |
| Loguri core GLPI | Același `GLPI_LOG_DIR` (`php-errors.log` etc.) |
| Audit (business) | Tabel `glpi_plugin_auchanassettracker_auditlogs` + UI Audit |

Creștere verbosity: activați modul debug GLPI (**Setup → General → System** / debug bar) în timp ce reproduceți; urmăriți `plugin_auchanassettracker.log` și logul PHP. Nu există switch separat „trace” — adăugați temporar `PluginAuchanassettrackerPluginlog::info()` doar pe o copie non-prod dacă e nevoie.

## Checklist diagnostic

### Echipament blocat: Așteaptă validare

1. Deschideți echipamentul → status `awaiting_validation`; găsiți alocarea legată cu `allocation_status = pending`.
2. Cereți destinatarului Confirmare / Nu am primit (comentariu obligatoriu la respingere).
3. SQL: alocări pending pentru acel echipament (query-uri mai jos).
4. Preferabil remediere din UI; nu inventați status în DB fără backup.

### Blocat: În tranzit

1. **Transferuri** → găsiți rând cu `transfer_status = in_transit`.
2. Destinația validează cu containere, sau expeditor/dest respinge (comentariu) / expeditorul șterge când e permis.
3. Verificați că `transferitems` încă pointează la echipament.

### Blocat: În service

1. Citiți `service_tickets_id` / `service_since` pe echipament.
2. Deschideți ticketul — rezolvați/închideți pentru revenire la Alocat.
3. Dacă ticketul lipsește dar statusul e blocat: verificați starea ticketului; folosiți jurnalul audit pentru ultimul eveniment service.

### Asociere container greșită

1. Disponibil trebuie să aibă `plugin_auchanassettracker_containers_id` > 0 și aceeași Location ca containerul.
2. Alocat / awaiting / transit au de obicei container `0`.
3. Pagina QR listează doar echipament non-șters aflat în acel container (`is_active`, nu deleted).

### Pagina QR inaccesibilă

1. URL-ul trebuie să fie `/plugins/{ExactFolder}/qr.php?t=…` — **fără** `/public/` în cale.
2. Numele folderului trebuie să coincidă cu `glpi_plugins.directory` (case-sensitive pe Linux).
3. Confirmați că `setup.php` înregistrează Firewall `STRATEGY_NO_CHECK` pentru `qr.php`.
4. Container activ, nu șters, token potrivit.
5. Testați în fereastră privată (delogat).
6. Dacă lipsește imaginea QR pe formular: verificați PHP GD (`php -m | grep -i gd`).

### Nu vine email la alocare / respingere

1. Destinatarul / alocatorul trebuie să aibă email valid pe userul GLPI.
2. Transportul mail GLPI trebuie să funcționeze (Setup → Notifications / mailer).
3. Pluginul folosește `Mailhelper` (nu șabloanele Setup → Notification templates).
4. Alertele pe prag nu trimit mail singure.

## SQL read-only (doar inspecție)

Înlocuiți ID-urile. Nu faceți UPDATE/DELETE în producție fără backup și tichet de schimbare.

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

-- Mapare profil → rol
SELECT * FROM glpi_plugin_auchanassettracker_profiles;
```

## Rollback / recovery

1. **Înainte de upgrade:** backup DB + folder `plugins/{dir}`.
2. Versiune nouă defectă: disable plugin → restaurați folderul anterior → enable/upgrade.
3. Uninstall = soft (tabele păstrate). Drop pe tabelele plugin e ultima soluție și pierde doar istoricul plugin — nu Assets/Tickets/Users core.
4. Migrare greșită: restaurați backup DB; preferabil doar tabelele plugin dacă DBA le poate izola.
5. Nu „reparați” statusuri finale sau tranzit prin SQL brut fără să înțelegeți legăturile alocare/transfer/ticket.

## Checklist mediu / versiune

| Element | Cum verificați |
| --- | --- |
| GLPI 11.0.2 | Setup → General / about |
| Plugin 0.5.1 | Setup → Plugins |
| PHP ≥ 8.1 | `php -v` sau info server |
| GD (QR PNG) | `php -m \| grep -i gd` |
| MySQL/MariaDB | Ca cerințele GLPI |
| Casing folder plugin | Coincide cu `glpi_plugins.directory` |
| `url_base` (opțional) | Setup → General — folosit dacă lipsește hostul din request |
