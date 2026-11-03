# D-04 — Installation & deployment

## Prerequisites

| Check | Value |
| --- | --- |
| GLPI | **11.0.2** (min `11.0.0`, max `11.9.99`) |
| PHP | **8.1+** |
| PHP GD | Recommended (QR PNG on container form / labels) |
| MySQL / MariaDB | Same as your GLPI 11 install |
| Access | Super-Admin (or rights for Setup → Plugins) |

Verify: **Setup → General** (GLPI version) and server PHP version (`php -v` or hosting panel).

## D-02 — Build installable package

From the repo root:

```bash
./tools/build_package.sh /tmp/auchanassettracker.tar.gz
```

The archive contains one folder (`auchanassettracker/`) ready to extract under `glpi/plugins/`.  
You may rename the folder on disk to `AuchanAssetTracker` — keep that name **stable** afterwards (Linux is case-sensitive; it must match `glpi_plugins.directory`).

Do **not** ship `.git`, `*.zip`, or `*.tar.gz` inside the package for production.

## Fresh install

1. Extract / copy into `glpi/plugins/AuchanAssetTracker` (include `public/`).
2. **Setup → Plugins** → **Install** → **Enable**.
3. On enable, the plugin creates schema (`install/install.sql`) and seeds four profiles if missing.
4. Log out / log in (menu and rights refresh).
5. **Administration → Profiles** — assign users:
   - **Central admin** — all locations
   - **Location manager** / **Support technician** — set **Location** on the user form
   - **End user** — Helpdesk interface; Confirm + Dashboard only
6. Create **Locations** → **Physical containers** → then receive equipment.
7. Open a container: check public URL (left) + QR (right), print label, open the URL logged out to verify shelf view.
8. Optional: **Setup → General → URL of the application** (fallback host for QR links).

## Upgrade (from a previous release)

1. Backup DB + the current plugin folder.
2. Replace plugin files (same folder name).
3. Reload GLPI, or click **Upgrade** on Setup → Plugins if shown.  
   The plugin also **self-heals** on load: `upgrade` + `ensure_schema`, then updates `glpi_plugins.version`.
4. Log out / log in; clear browser / GLPI cache if CSS/menu look old.
5. Smoke-check: Dashboard, Reports, Configuration thresholds, one allocate + confirm, one QR scan (logged out).

## Rollback

1. **Setup → Plugins** → **Disable** (uninstall is soft: **plugin tables stay**).
2. Restore previous plugin folder from backup (same directory name).
3. Re-enable / Upgrade as needed.
4. Do **not** drop core GLPI tables (Assets, Tickets, Users). Plugin uninstall does not wipe those.
5. Bad schema / data: restore a DB backup taken **before** the upgrade (plugin tables only if DBA can isolate them). Prefer a full GLPI backup before any upgrade.

## After install checklist

- [ ] Plugin enabled
- [ ] Four profiles present; users assigned; Location set on managers/techs
- [ ] Menu **Auchan Asset Tracker** visible for each role (End user: Dashboard + Confirm)
- [ ] Container QR opens without login
- [ ] EN / RO: set user language and spot-check labels
- [ ] Central admin: Configuration shows three day thresholds (defaults 5 / 7 / 30)
