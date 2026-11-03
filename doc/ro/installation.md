# D-04 — Instalare & deployment

## Cerințe

| Verificare | Valoare |
| --- | --- |
| GLPI | **11.0.2** (min `11.0.0`, max `11.9.99`) |
| PHP | **8.1+** |
| PHP GD | Recomandat (QR PNG pe formular / etichete) |
| MySQL / MariaDB | Ca la instalarea GLPI 11 |
| Acces | Super-Admin (sau drepturi Setup → Plugins) |

Verificați: **Setup → General** (versiune GLPI) și versiunea PHP pe server (`php -v` sau panoul de hosting).

## D-02 — Construire pachet instalabil

Din rădăcina repo-ului:

```bash
./tools/build_package.sh /tmp/auchanassettracker-0.5.1.tar.gz
```

Arhiva conține un folder (`auchanassettracker/`) de extras în `glpi/plugins/`.  
Puteți redenumi folderul pe disc în `AuchanAssetTracker` — păstrați numele **stabil** după aceea (Linux e case-sensitive; trebuie să coincidă cu `glpi_plugins.directory`).

Nu includeți `.git`, `*.zip` sau `*.tar.gz` în pachetul de producție.

## Instalare nouă

1. Extrageți / copiați în `glpi/plugins/AuchanAssetTracker` (includeți `public/`).
2. **Setup → Plugins** → **Install** → **Enable**.
3. La activare, pluginul creează schema (`install/install.sql`) și cele patru profile dacă lipsesc.
4. Logout / login (meniu și drepturi se reîncarcă).
5. **Administrare → Profile** — atribuiți useri:
   - **Central admin** — toate locațiile
   - **Location manager** / **Support technician** — setați **Location** pe formularul userului
   - **End user** — interfață Helpdesk; doar Confirmare + Dashboard
6. Creați **Locații** → **Containere fizice** → apoi recepție echipament.
7. Deschideți un container: URL public (stânga) + QR (dreapta), tipăriți eticheta, deschideți URL-ul delogat pentru verificarea raftului.
8. Opțional: **Setup → General → URL of the application** (host fallback pentru linkuri QR).

## Upgrade (ex. 0.4.x / 0.5.0 → 0.5.1)

1. Backup DB + folderul curent al pluginului.
2. Înlocuiți fișierele (același nume de folder).
3. Reîncărcați GLPI sau apăsați **Upgrade** pe Setup → Plugins dacă apare.  
   Pluginul face și **self-heal** la load: `upgrade` + `ensure_schema`, apoi actualizează `glpi_plugins.version` la **0.5.1**.
4. Logout / login; goliți cache browser / GLPI dacă CSS/meniul par vechi.
5. Smoke-check: Dashboard, Rapoarte, praguri Configurare, o alocare + confirmare, un scan QR (delogat).

## Rollback

1. **Setup → Plugins** → **Disable** (uninstall e soft: **tabelele plugin rămân**).
2. Restaurați folderul anterior din backup (același nume de director).
3. Re-enable / Upgrade după nevoie.
4. Nu ștergeți tabelele core GLPI (Assets, Tickets, Users). Uninstall-ul pluginului nu le șterge.
5. Schema / date greșite: restaurați backup DB din **înainte** de upgrade (doar tabele plugin dacă DBA le poate izola). Preferabil backup full GLPI înainte de orice upgrade.

## Checklist după instalare

- [ ] Plugin activ, versiune `0.5.1`
- [ ] Patru profile prezente; useri atribuiți; Location pe manageri/tech
- [ ] Meniu **Auchan Asset Tracker** vizibil pe fiecare rol (End user: Dashboard + Confirmare)
- [ ] QR container se deschide fără login
- [ ] EN / RO: setați limba userului și verificați etichetele
- [ ] Central admin: Configurare arată cele 3 praguri (implicite 5 / 7 / 30)
