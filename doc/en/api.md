# D-05 — API / integration reference

No separate public REST API. Integration is GLPI hooks + a few internal endpoints + the public QR URL.

## Public QR (container page)

| Item | Detail |
| --- | --- |
| URL | `{GLPI_BASE}/plugins/{PluginDir}/qr.php?t={qr_token}&lang={en_GB\|ro_RO}` |
| Auth | None (Firewall `STRATEGY_NO_CHECK` + stateless path in `setup.php`) |
| File | `public/qr.php` (served **without** `/public/` in the URL) |
| Shows | Container name, code, location, list of equipment inside (Name, Serial, Type, Added on) |
| Edit | View only — changes only in GLPI UI |
| Bad token / inactive / deleted | 404 “Container not found” |

Token: unique `qr_token` on each container. Built by `PluginAuchanassettrackerQrhelper::getScanUrl()`.  
Host comes from the current request (fallback: Setup → General → URL of the application).

Printable label: container form → print HTML/PDF shelf label (same URL + QR image).

## Internal AJAX (login required)

Base: `/plugins/{PluginDir}/ajax/…`

| Endpoint | Purpose |
| --- | --- |
| `containers.php?locations_id=&display=dropdown\|json` | Containers for a location (scoped) |
| `users.php` | User picker helper |
| `models.php` | Model picker helper |

CSRF / session: normal GLPI logged-in session. Location-scoped profiles only see their location.

## Front controllers (UI entry points)

Under `front/` — main screens: `dashboard.php`, `equipment*.php`, `container*.php`, `allocation.form.php`, `confirm.php`, `transfer*.php`, `report.php`, `config.form.php`, `auditlog.php`, `stockbylocation.php`, `equipment.bulk.php`, …

## GLPI events consumed

| Event | Effect |
| --- | --- |
| Ticket / Item_Ticket add | Tracked gear → **In service** |
| Ticket update (solved/closed) | **In service** → **Allocated** |
| Asset add / update | Sync container / stock link when using native asset form |
| Profile / plugin init | Seed rights + four roles |

## Events produced (not native NotificationTemplate)

Plugin `Mailhelper` + in-app **notices** on: allocate pending, Did not receive, transfer reject.  
Threshold days alone do **not** send email — they only feed Active alerts on dashboards.

## Audit

Every important action writes to `glpi_plugin_auchanassettracker_auditlogs` (see UI **Audit log** / dashboard recent moves).
