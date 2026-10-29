# D-04 — Instalare & deployment

## Cerințe

| Verificare | Valoare |
| --- | --- |
| GLPI | **11.0.2** (min `11.0.0`, max `11.9.99`) |
| PHP | **8.1+** (GD recomandat pentru QR PNG) |
| MySQL / MariaDB | Ca la instalarea ta GLPI 11 |
| Acces | Super-Admin GLPI (sau drepturi Setup → Plugins) |

Verifică: **Setup → General** (versiune GLPI) și info PHP pe server.

## D-02 — Pachet instalabil

Din rădăcina repo:

```bash
./tools/build_package.sh /tmp/auchanassettracker-0.5.1.tar.gz
```

Sau zip pe folderul plugin (păstrează numele real, include `public/`).  
Nu pune `.git` în pachetul de producție.

## Instalare nouă

1. Copiază / extrage în `glpi/plugins/AuchanAssetTracker` (sau alt nume — păstrează-l stabil).
2. **Setup → Plugins** → **Install** → **Enable**.
3. Logout / login.
4. **Administration → Profiles** — apar cele 4 profiluri: Central admin, Location manager, Support technician, End user. Asociază useri; setează **Location** la manager/tech.
5. Creează Locații → Containere → apoi recepție echipament.
6. Deschide un container: URL + QR, tipărește eticheta, scanează URL public (fără login).

## Upgrade (ex. 0.4.x / 0.5.0 → 0.5.1)

1. Înlocuiește fișierele (același nume de folder).
2. Reîncarcă GLPI sau **Upgrade** în Setup → Plugins. Pluginul face și **self-heal** pe schema/versiune.
3. Logout / login; golește cache browser / GLPI dacă UI e vechi.
4. Smoke: Dashboard, Rapoarte, praguri Config, o alocare + confirmare, un scan QR.

## Rollback

1. Disable în **Setup → Plugins** (uninstall e soft: **tabelele plugin rămân**).
2. Restaurează folderul vechi din backup (același nume).
3. Re-enable / Upgrade.
4. **Nu** șterge tabelele core GLPI (Assets, Tickets, Users).
5. Migrație greșită: restore backup DB făcut **înainte** de upgrade (ideal doar tabele plugin). Backup complet înainte de orice upgrade.

## Checklist după instalare

- [ ] Plugin activ, versiune `0.5.1`
- [ ] Profiluri asignate + Location pe userii scopuiți
- [ ] Meniu **Auchan Asset Tracker** vizibil
- [ ] QR container se deschide fără login
- [ ] EN / RO: limba user + etichete OK
