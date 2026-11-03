# D-05 — API / integration reference

No separate public REST API. Integration is GLPI hooks + a few internal AJAX endpoints + the public QR URL.

## Public QR (container page)

| Item | Detail |
| --- | --- |
| URL | `{GLPI_BASE}/plugins/{PluginDir}/qr.php?t={qr_token}&lang={en_GB\|ro_RO}` |
| Auth | None — Firewall `STRATEGY_NO_CHECK` + stateless path in `setup.php` |
| File | `public/qr.php` (URL has **no** `/public/` segment) |
| Shows | Container name, code, location; equipment table (Name, Serial, Type, Added on) |
| Edit | View only — changes only in GLPI UI |
| Bad token / inactive / deleted | 404 “Container not found” |

Token: unique `qr_token` per container (`PluginAuchanassettrackerQrhelper::getScanUrl()`).  
Host: current request (fallback: Setup → General → URL of the application).

Printable label: container form → print HTML/PDF shelf label (same URL + QR image). Requires PHP GD for PNG.

## Internal AJAX (login required)

Base: `/plugins/{PluginDir}/ajax/…` — normal GLPI session + CSRF. Location-scoped profiles only see their location.

| Endpoint | Purpose |
| --- | --- |
| `containers.php?locations_id=&display=dropdown\|json` | Containers for a location |
| `users.php` | User picker helper |
| `models.php` | Model picker helper |

## Front controllers (UI entry points)

| Path under `front/` | Purpose |
| --- | --- |
| `dashboard.php` | Role home |
| `equipment.php` / `equipment.form.php` | List / create-edit equipment |
| `equipment.bulk.php` | Bulk accessories receipt |
| `container.php` / `container.form.php` / `container.qr.php` | Containers + QR/label |
| `allocation.form.php` | New allocation |
| `confirm.php` | Confirm / Did not receive |
| `transfer.php` / `transfer.form.php` | Transfers |
| `report.php` | Reports + CSV/Excel export |
| `config.form.php` | Alert thresholds |
| `auditlog.php` | Audit log UI |
| `stockbylocation.php` | Stock-by-location view |
| `equipmenttype*.php` / `manufacturer*.php` | Catalog |
| `profile.form.php` | Profile rights helper |

## GLPI events consumed

| Event | Effect |
| --- | --- |
| Ticket / Item_Ticket add | Tracked gear → **In service** (`service_tickets_id`, `service_since`) |
| Ticket update (solved/closed) | **In service** → **Allocated** |
| Asset add / update | Sync container / stock link on native asset form |
| Plugin init / Profile | Seed rights + four roles; schema self-heal |

## Events produced (not native NotificationTemplate)

Plugin `Mailhelper` + in-app **notices** (`glpi_plugin_auchanassettracker_notices`) on:

- Allocate → pending confirmation (recipient)
- Did not receive (allocator)
- Transfer reject

Not registered under Setup → Notifications. HTML Auchan-style mail; RO-friendly.

**Active alerts** (dashboards) use config day thresholds only — they do **not** send email by themselves.

## Audit

Important actions append to `glpi_plugin_auchanassettracker_auditlogs` (UI Audit log / recent moves on admin dashboard). Report CSV/Excel exports are also logged.
