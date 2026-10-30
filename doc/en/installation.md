# D-04 — Installation & deployment

## Prerequisites

| Check | Value |
| --- | --- |
| GLPI | **11.0.2** (min `11.0.0`, max `11.9.99`) |
| PHP | **8.1+** (GD recommended for QR PNG) |
| MySQL / MariaDB | Same as your GLPI 11 install |
| Access | GLPI Super-Admin (or rights to Setup → Plugins) |

Verify in GLPI: **Setup → General** (version) and PHP info / server package for PHP version.

## D-02 — Build installable package

From the repo root:

```bash
./tools/build_package.sh /tmp/auchanassettracker-0.5.2.tar.gz
```

Or zip the plugin folder (keep the real folder name, include `public/`).  
Do **not** put `.git` inside the package for production.

## Fresh install

1. Copy / extract into `glpi/plugins/AuchanAssetTracker` (or your chosen folder name — keep it stable).
2. **Setup → Plugins** → **Install** → **Enable**.
3. Log out / log in.
4. **Administration → Profiles** — four profiles should exist: Central admin, Location manager, Support technician, End user. Assign users; set **Location** on managers/techs.
5. Create Locations → Physical containers → then receive equipment.
6. Open a container: check URL + QR, print label, scan public URL (no login).

## Upgrade (e.g. from 0.4.x / 0.5.x → 0.5.2)

1. Replace plugin files (same folder name).
2. Reload GLPI or click **Upgrade** on Setup → Plugins if shown. Plugin also **self-heals** schema/version on load.
3. Log out / log in; clear browser / GLPI cache if UI looks old.
4. Smoke-check: Dashboard, Reports, Configuration thresholds, one allocate + confirm, one QR scan.

## Rollback

1. Disable plugin under **Setup → Plugins** (uninstall is soft: **plugin tables stay**).
2. Restore previous plugin folder from backup (same directory name).
3. Re-enable / Upgrade as needed.
4. Do **not** drop core GLPI tables (Assets, Tickets, Users). Plugin uninstall does not wipe those.
5. If you must undo a bad schema change: restore DB backup taken **before** the upgrade (plugin tables only if possible). Prefer full GLPI backup before any upgrade.

## After install checklist

- [ ] Plugin enabled, version `0.5.2`
- [ ] Profiles assigned + Location on scoped users
- [ ] Menu **Auchan Asset Tracker** visible
- [ ] Container QR opens without login
- [ ] EN / RO: set user language and spot-check labels
