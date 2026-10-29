# D-13 — Debug & troubleshooting

## Logging

| What | Where |
| --- | --- |
| Plugin file log | `{GLPI_LOG_DIR}/plugin_auchanassettracker.log` (via `PluginAuchanassettrackerPluginlog`) |
| Levels | INFO / WARNING / ERROR (and exception helper) |
| GLPI core logs | Same `GLPI_LOG_DIR` (`php-errors.log`, etc.) |
| Audit (business) | Table `glpi_plugin_auchanassettracker_auditlogs` + UI Audit log |

Increase verbosity: enable GLPI debug mode (**Setup → General → System** / debug bar) while reproducing; watch `plugin_auchanassettracker.log` and PHP error log. There is no separate “trace” switch — add temporary `PluginAuchanassettrackerPluginlog::info()` only on a non-prod copy if needed.

## Diagnostic checklist

### Equipment stuck: Awaiting validation

1. Open equipment → check status + linked allocation (pending).
2. Ask recipient to Confirm / Did not receive (or admin reject path).
3. SQL: pending allocations for that equipment (queries below).
4. If orphaned: fix via UI if possible; do not invent status in DB without backup.

### Stuck: In transit

1. Open **Transfers** → find `in_transit` row.
2. Destination validates with containers, or sender/dest rejects/cancels.
3. Check transfer items still point at the equipment.

### Stuck: In service

1. Find `service_tickets_id` on equipment.
2. Open ticket — solve/close to return to Allocated.
3. If ticket gone but status stuck: verify ticket state; use audit log for last service event.

### Container association wrong

1. Available must have `plugin_auchanassettracker_containers_id` > 0 and same Location.
2. Allocated / awaiting / transit usually have container `0`.
3. QR page lists only non-deleted gear currently in that container.

### QR page not accessible

1. URL must be `/plugins/{ExactFolder}/qr.php?t=…` — **no** `/public/` in path.
2. Folder name must match `glpi_plugins.directory` (case-sensitive on Linux).
3. Confirm `setup.php` registers Firewall `STRATEGY_NO_CHECK` for `qr.php`.
4. Container active, not deleted, token matches.
5. Test in private window (logged out).

## Read-only SQL (inspect only)

Replace IDs as needed. Do not UPDATE/DELETE in production without a backup and change ticket.

```sql
-- Equipment overview
SELECT id, name, serial, status, locations_id,
       plugin_auchanassettracker_containers_id AS container_id,
       users_id, service_tickets_id, date_mod
FROM glpi_plugin_auchanassettracker_equipments
WHERE is_deleted = 0
ORDER BY date_mod DESC
LIMIT 50;

-- One equipment
SELECT * FROM glpi_plugin_auchanassettracker_equipments WHERE id = ?;

-- Container + token
SELECT id, code, name, locations_id, qr_token, is_active, is_deleted
FROM glpi_plugin_auchanassettracker_containers
WHERE id = ? OR qr_token = ?;

-- Contents of a container
SELECT id, name, serial, status
FROM glpi_plugin_auchanassettracker_equipments
WHERE plugin_auchanassettracker_containers_id = ?
  AND is_deleted = 0;

-- Allocation history
SELECT * FROM glpi_plugin_auchanassettracker_allocations
WHERE plugin_auchanassettracker_equipments_id = ?
ORDER BY id DESC;

-- Open transfers
SELECT * FROM glpi_plugin_auchanassettracker_transfers
WHERE transfer_status = 'in_transit';

SELECT * FROM glpi_plugin_auchanassettracker_transferitems
WHERE plugin_auchanassettracker_transfers_id = ?;

-- Audit trail
SELECT id, users_id, action, itemtype, items_id, details, date_creation
FROM glpi_plugin_auchanassettracker_auditlogs
ORDER BY id DESC
LIMIT 100;

-- Thresholds
SELECT name, value FROM glpi_plugin_auchanassettracker_configs;
```

## Rollback / recovery

1. **Before upgrade:** backup DB + `plugins/{dir}` folder.
2. Faulty new version: disable plugin → restore previous folder → enable/upgrade.
3. Uninstall = soft (tables kept). Dropping plugin tables is a last resort and loses plugin history only — still does not remove core Assets/Tickets/Users.
4. Bad migration: restore DB backup; prefer restoring only plugin tables if your DBA can isolate them.
5. Never “fix” final statuses or transit by raw SQL unless you understand allocations/transfers/tickets links.

## Environment / version checklist

| Item | How to verify |
| --- | --- |
| GLPI 11.0.2 | Setup → General / about |
| Plugin 0.5.1 | Setup → Plugins |
| PHP ≥ 8.1 | `php -v` or server info |
| GD (QR PNG) | `php -m \| grep -i gd` |
| MySQL/MariaDB | Same as GLPI requirements |
| Plugin folder casing | Matches `glpi_plugins.directory` |
| `url_base` (optional) | Setup → General — used if request host missing |
