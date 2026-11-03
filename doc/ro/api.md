# D-05 — Referință API / integrare

Nu există REST API public separat. Integrarea = hooks GLPI + câteva endpoint-uri AJAX interne + URL-ul public QR.

## QR public (pagina container)

| Element | Detaliu |
| --- | --- |
| URL | `{GLPI_BASE}/plugins/{PluginDir}/qr.php?t={qr_token}&lang={en_GB\|ro_RO}` |
| Auth | Niciuna — Firewall `STRATEGY_NO_CHECK` + cale stateless în `setup.php` |
| Fișier | `public/qr.php` (în URL **fără** segmentul `/public/`) |
| Afișează | Nume container, cod, locație; tabel echipamente (Nume, Serial, Tip, Adăugat la) |
| Editare | Doar vizualizare — modificările doar în UI GLPI |
| Token greșit / inactiv / șters | 404 „Container not found” |

Token: `qr_token` unic pe container (`PluginAuchanassettrackerQrhelper::getScanUrl()`).  
Host: request curent (fallback: Setup → General → URL of the application).

Etichetă tipăribilă: formular container → print HTML/PDF (același URL + imagine QR). Necesită PHP GD pentru PNG.

## AJAX intern (login necesar)

Bază: `/plugins/{PluginDir}/ajax/…` — sesiune GLPI + CSRF. Profilele pe locație văd doar locația lor.

| Endpoint | Scop |
| --- | --- |
| `containers.php?locations_id=&display=dropdown\|json` | Containere pentru o locație |
| `users.php` | Helper alegere user |
| `models.php` | Helper alegere model |

## Controllere front (puncte de intrare UI)

| Cale sub `front/` | Scop |
| --- | --- |
| `dashboard.php` | Home pe rol |
| `equipment.php` / `equipment.form.php` | Listă / creare-editare echipament |
| `equipment.bulk.php` | Recepție accesorii bulk |
| `container.php` / `container.form.php` / `container.qr.php` | Containere + QR/etichetă |
| `allocation.form.php` | Alocare nouă |
| `confirm.php` | Confirmare / Nu am primit |
| `transfer.php` / `transfer.form.php` | Transferuri |
| `report.php` | Rapoarte + export CSV/Excel |
| `config.form.php` | Praguri alerte |
| `auditlog.php` | UI jurnal audit |
| `stockbylocation.php` | Stoc pe locație |
| `equipmenttype*.php` / `manufacturer*.php` | Catalog |
| `profile.form.php` | Helper drepturi profil |

## Evenimente GLPI consumate

| Eveniment | Efect |
| --- | --- |
| Ticket / Item_Ticket add | Echipament urmărit → **În service** (`service_tickets_id`, `service_since`) |
| Ticket update (solved/closed) | **În service** → **Alocat** |
| Asset add / update | Sync container / legătură stoc pe formular asset native |
| Plugin init / Profile | Seed drepturi + patru roluri; self-heal schema |

## Evenimente produse (nu NotificationTemplate nativ)

`Mailhelper` + **notices** in-app (`glpi_plugin_auchanassettracker_notices`) la:

- Alocare → confirmare pending (destinatar)
- Nu am primit (alocator)
- Respingere transfer

Nu sunt înregistrate la Setup → Notifications. Mail HTML stil Auchan; prietenos RO.

**Alerte active** (dashboard) folosesc doar pragurile de zile din config — **nu** trimit email singure.

## Audit

Acțiunile importante scriu în `glpi_plugin_auchanassettracker_auditlogs` (UI Audit / mutări recente pe dashboard admin). Exporturile CSV/Excel din rapoarte sunt și ele jurnalizate.
