# D-05 — API / integrare

Nu există REST API public separat. Integrarea = hook-uri GLPI + câteva endpoint-uri interne + URL QR public.

## QR public (pagină container)

| Element | Detaliu |
| --- | --- |
| URL | `{GLPI_BASE}/plugins/{PluginDir}/qr.php?t={qr_token}&lang={en_GB\|ro_RO}` |
| Auth | Fără (Firewall `STRATEGY_NO_CHECK` + path fără sesiune în `setup.php`) |
| Fișier | `public/qr.php` (în URL **fără** `/public/`) |
| Afișează | Nume, cod, locație, listă echipamente (Nume, Serial, Tip, Adăugat) |
| Editare | Doar vizualizare — modificări doar în UI GLPI |
| Token greșit / inactiv / șters | 404 „Container not found” |

Token: `qr_token` unic pe container. Generat de `PluginAuchanassettrackerQrhelper::getScanUrl()`.  
Host = request curent (fallback: Setup → General → URL aplicație).

Etichetă: formular container → print HTML/PDF (același URL + imagine QR).

## AJAX intern (necesită login)

Bază: `/plugins/{PluginDir}/ajax/…`

| Endpoint | Scop |
| --- | --- |
| `containers.php?locations_id=&display=dropdown\|json` | Containere pe locație (scopuit) |
| `users.php` | Helper useri |
| `models.php` | Helper modele |

Sesiune GLPI normală. Profilurile pe locație văd doar locația lor.

## Controllere front (intrări UI)

În `front/`: `dashboard.php`, `equipment*.php`, `container*.php`, `allocation.form.php`, `confirm.php`, `transfer*.php`, `report.php`, `config.form.php`, `auditlog.php`, `stockbylocation.php`, `equipment.bulk.php`, …

## Evenimente GLPI consumate

| Eveniment | Efect |
| --- | --- |
| Ticket / Item_Ticket add | Echipament urmărit → **În service** |
| Ticket update (solved/closed) | **În service** → **Alocat** |
| Asset add / update | Sync container / stoc pe formular asset |
| Profile / init plugin | Seed drepturi + 4 roluri |

## Evenimente produse (nu NotificationTemplate nativ)

`Mailhelper` + **notices** in-app la: alocare pending, Nu am primit, respingere transfer.  
Pragurile de zile **nu** trimit email singure — alimentează doar Active alerts.

## Audit

Acțiunile importante → `glpi_plugin_auchanassettracker_auditlogs` (UI Audit / mutări recente pe dashboard).
