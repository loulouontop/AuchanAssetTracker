# QA Test Plan — Auchan Asset Tracker

**Product:** `auchanassettracker` (Auchan Asset Tracker)  
**Version under test:** 0.5.0 (Sprint 5)  
**Target:** GLPI 11.0.2 · PHP 8.1+ · locales `en_GB` + `ro_RO`  
**Scope:** Sprints 1–5 acceptance. No new feature work in this pack.  
**Sprint 6 (docs / zip / manuals):** starts only after P0 pass and P1 pass-or-waive.

Fill **Result** with `Pass` / `Fail` / `Blocked` / `N/A`. Use **Notes** for evidence (screenshot name, ticket id, waiver).

---

## How to use this pack

1. Complete **Prerequisites** once.
2. Run cases in **Priority order** (INST → ECH → ALO → TRF / SVC → WOF → QR → DASH / RPT / CFG / AUD → SEC / I18N / REG).
3. Log every Result. Failures → fix PRs before Sprint 6 manuals freeze.
4. Reuse this file in Sprint 6 as the client test checklist (IDs ECH, ALO, TRF…).

### Pass / fail rules

| Result | Meaning |
| --- | --- |
| **Pass** | Expected matches; no PHP fatal / blank page; audit present when the case says so |
| **Fail** | Wrong status, missing block, wrong location data, QR leak, forbidden action visible, export empty/corrupt |
| **Blocked** | Environment gap (no mail, no 2nd location, no plugin 1) — note and retest; not a product fail if documented |
| **N/A** | Case not applicable to this install |

### Ship gate

- **P0:** all Pass  
- **P1:** all Pass, or waived in writing in Notes  
- Open bugs listed and fixed **before** Sprint 6 documentation freeze  

### Priority legend

| Priority | Meaning |
| --- | --- |
| **P0** | Must pass to ship |
| **P1** | Should pass |
| **P2** | Nice to have |

---

## Prerequisites (before day 1)

| # | Need | Detail | Done |
| --- | --- | --- | --- |
| P-01 | GLPI 11.0.2 | Plugin installed or upgraded, enabled; log out and back in | ☐ |
| P-02 | Offline smoke | From plugin root: `php tools/structure_check.php` → **0 FAIL** | ☐ |
| P-03 | Locations | At least two: e.g. **BUC** and **CLU** | ☐ |
| P-04 | Users | See test accounts below; managers/techs have **Location** set on the user form | ☐ |
| P-05 | Mail | GLPI mail configured **or** accept in-app notices only (document which) | ☐ |
| P-06 | Optional REG | First plugin `auchanequipment` installed alongside this one | ☐ |
| P-07 | Languages | Plan to run critical paths once in **en_GB** and once in **ro_RO** | ☐ |

### Suggested test accounts

| Login (example) | Profile | Location | Role |
| --- | --- | --- | --- |
| `admin@…` | Central admin | — | `central_admin` |
| `mgr-buc@…` | Location manager | BUC | `location_manager` |
| `mgr-clu@…` | Location manager | CLU | `location_manager` |
| `tech-buc@…` | Support technician | BUC | `support_tech` |
| `user1@…` | End user | — | `user` (helpdesk) |
| `user2@…` | End user | — | `user` (helpdesk) |

Use real emails on allocate/reject paths if testing mail.

---

## Role matrix (menu smoke)

Run once after install. Expected: each role sees only what the matrix allows.

| Check | Central admin | Location mgr | Support tech | End user |
| --- | --- | --- | --- | --- |
| Dashboard | Full totals + all-location alerts | Own location | Own location (no transfers) | Pending + my gear only |
| Containers / Equipment / Bulk | Yes (all locations) | Yes (own location) | Read/update gear; **no** bulk/receipt | No |
| Allocation | Yes | Yes | Yes (new allocation) | No |
| Transfers | Yes | Yes | **No** | No |
| Reports + export | Yes | Yes | Yes | No |
| Configuration (3 thresholds) | Yes | No | No | No |
| Confirm receipt | Yes | Yes | Yes | **Yes** (main job) |

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| ROLE-01 | P0 | Each of the 4 | Log in as each account; open **Auchan Asset Tracker** menu | Matches matrix above; no fatal | | |

---

## Test ID map

| Prefix | Area |
| --- | --- |
| **INST** | Install / upgrade / profiles |
| **ECH** | Stock & containers |
| **ALO** | Allocation + confirm / reject |
| **TRF** | Transfers + container handoff |
| **SVC** | Service via ticket |
| **WOF** | Write-off / lost / stolen / reintroduce |
| **QR** | QR labels + public scan |
| **DASH** | Role dashboards |
| **RPT** | Reports + CSV / Excel |
| **CFG** | Threshold config + alerts |
| **AUD** | Audit log |
| **SEC** | Rights / location isolation |
| **I18N** | RO / EN |
| **REG** | Coexistence with plugin 1 |

---

## Execution order

```text
INST → ECH → ALO → TRF
                 ↘ SVC → WOF
         ECH → QR
         ALO → DASH / RPT / CFG / AUD → SEC / I18N / REG
```

---

## INST — Install & upgrade

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| INST-01 | P0 | Super-Admin / Central admin | Clean install: copy plugin folder → Setup → Plugins → Install → Enable. Open Administration → Profiles. Open plugin menu. | Four profiles exist (**Central admin**, **Location manager**, **Support technician**, **End user**). Menu **Auchan Asset Tracker** visible. **Configuration** available to Central admin. | | |
| INST-02 | P0 | Super-Admin | From a 0.4.x install: replace plugin files (keep folder name); reload or click Upgrade; log out/in. Check version, thresholds, one QR URL. | Version **0.5.0**; schema self-healed; thresholds kept; QR still works; Dashboard + Reports open | | Skip if no 0.4.x baseline — mark Blocked |
| INST-03 | P0 | Dev / QA | From plugin root run `php tools/structure_check.php` | All lines OK; **0 FAIL** | | Offline; no GLPI required |

---

## ECH — Containers & stock

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| ECH-01 | P0 | Location manager (BUC) | Auchan Asset Tracker → Containers → Add. Code `BUC-A1`, location BUC, active. Save. | Container created, active, listed for BUC | | |
| ECH-02 | P0 | Location manager (BUC) | Equipment → Add (or receipt form). Laptop (or PC) with serial; status **Available**; container `BUC-A1`; location BUC. Save. | Saved as **Available**; location BUC; linked to `BUC-A1` | | |
| ECH-03 | P0 | Location manager (BUC) | **NEG:** Try to save equipment as **Available** with empty / no container. | Save blocked; error about mandatory container; no Available row without container | | |
| ECH-04 | P1 | Location manager (BUC) | Bulk accessories receipt: several accessories into `BUC-A1` (or chosen container). | Several **Available** rows in that container | | |
| ECH-05 | P2 | Location manager (BUC) | Soft-delete a container that had stock (or empty). Check list + public QR for that token. | Container `is_deleted` / inactive; stock/QR hide contents; scan shows invalid / no data | | |
| ECH-06 | P0 | Location manager (BUC) | As BUC manager, open Containers / Equipment lists. Try to open a CLU container by id if known. | Only BUC (own location) data in lists; CLU not editable | | Overlaps SEC-01 |
| ECH-07 | P1 | Central admin | Receive equipment into any / central location with a valid container (same form as manager). | Receipt works for chosen location; status **Available** | | |

---

## ALO — Allocation & confirmation

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| ALO-01 | P0 | Location manager (BUC) | New allocation: pick End user `user1` + Available gear. Submit. | Equipment → **Awaiting validation**; container cleared; allocation history row **pending** | | |
| ALO-02 | P1 | Location manager (BUC) | On allocation form, select a user who already has gear (after ALO-04 or seed). | UI shows user’s current gear before allocate | | |
| ALO-03 | P0 | Location manager (BUC) | **NEG:** Try to allocate again the same item still awaiting confirmation. | Blocked; item not re-allocated | | |
| ALO-04 | P0 | End user (`user1`) | Helpdesk → Confirm receipt → **Confirm** on pending item. | Status **Allocated** + confirmation timestamp; audit `allocation_confirm` | | |
| ALO-05 | P0 | End user + allocator | Allocate a second Available item to `user2`. As `user2`: **Did not receive** with mandatory comment. | Status back **Available** (manager must pick container again); allocator gets **in-app notice** (+ email if mail OK) | | Document mail vs notice-only |
| ALO-06 | P1 | Central admin | Set allocation confirm days to **1** (CFG). Create a pending allocation older than 1 day (or adjust date in DB for test). Open Dashboard / Active alerts / Issues report. | Pending overdue appears in alerts / issues. **No** email from the threshold alone | | |
| ALO-07 | P0 | Location manager (BUC) | **NEG:** With an item **In transit** (after TRF-01) or **In service** (after SVC-01), try Allocate. | Allocate blocked for that item | | Run after TRF/SVC or seed statuses |

---

## TRF — Transfers

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| TRF-01 | P0 | Location manager (BUC) | Transfers → New: destination CLU, select Available item(s), send. | Items **In transit**; transfer status **in_transit** | | |
| TRF-02 | P0 | Location manager (BUC) | **NEG:** Try allocate or start another transfer on those In-transit items. | Blocked | | |
| TRF-03 | P0 | Location manager (CLU) | Open incoming transfer → Validate. If no CLU container, create one first. Assign container(s). Confirm. | Items **Available** at CLU in chosen container(s); transfer **completed** | | Forced container step |
| TRF-04 | P1 | Location manager (CLU) + initiator | New transfer BUC→CLU. As CLU: **Reject** with comment. | Items **Available** again at source BUC; initiator notified (notice + email if mail OK) | | |
| TRF-05 | P1 | Location manager (BUC) | Start transfer; while in transit: edit destination / notes / equipment and/or **Cancel**. | Edit saved while in transit; cancel returns items **Available** at source | | |
| TRF-06 | P1 | Central admin | List all transfers. Set transfer validate days low; leave one in transit past threshold. | Admin sees all locations’ transfers; overdue appears in Active alerts | | |
| TRF-07 | P0 | Support technician (BUC) | Log in as tech; inspect menu and direct transfer URLs if known. | **No** Transfers menu / create rights; cannot complete transfer workflow | | |

---

## SVC — Service (tickets)

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| SVC-01 | P0 | Support tech / Manager | On **Allocated** (or Available) tracked gear: open/link a GLPI Ticket (Item_Ticket or matching serial per hook). | Equipment → **In service**; `service_tickets_id` / `service_since` set | | |
| SVC-02 | P0 | Location manager | **NEG:** Try allocate or transfer while **In service**. | Blocked | | |
| SVC-03 | P0 | Support tech / Manager | Solve or close the ticket. | Equipment back **Allocated** to same user (not via stock shelf) | | |
| SVC-04 | P1 | Central admin | Set service max days low; leave item In service past threshold. Check Dashboard / Issues report. | Overdue service appears in alerts / issues | | |

---

## WOF — Write-off / lost / stolen

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| WOF-01 | P1 | Location manager (BUC) | On non-final, non-transit, non-awaiting item: Write-off / Lost / Stolen tab → choose status, **reason** required, optional document → Save. | Final status set; reason stored; audit `equipment_final_*` | | |
| WOF-02 | P1 | Central admin (+ Location manager contrast) | As Location manager: confirm reintroduce UI absent (unless Super-Admin/config). As **Central admin**: Reintroduce into stock with a valid container at that location. | Only Central admin (or Super-Admin with config UPDATE) can reintroduce → **Available** with container; audit `equipment_reintroduce` | | Matches `canChangeFinalStatus()` |
| WOF-03 | P1 | Location manager | Try allocate / transfer a written-off / lost / stolen item. | Excluded from normal stock / allocate / transfer | | |

---

## QR — Labels & public scan

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| QR-01 | P0 | Location manager | Open container form. | Unique `qr_token`; URL on left + QR PNG on right | | |
| QR-02 | P1 | Location manager | Open printable label (`container.qr.php` / Print label). Print or Print preview. | Print-ready HTML label; QR readable | | |
| QR-03 | P0 | Anonymous | Log out. Open `/plugins/<dir>/qr.php?t=<token>` (and optional `&lang=`). | Shelf view: name, code, location; contents table (Name, Serial, Type, Added on); **view only** — no edit | | |
| QR-04 | P0 | Anonymous | Soft-delete or deactivate container; or use wrong token. | Invalid / inactive message; **no** gear data | | |
| QR-05 | P0 | Anonymous | Scan `BUC-A1` token while CLU has other stock. | Only that container’s gear listed | | Location isolation |

---

## DASH — Role dashboards

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| DASH-01 | P0 | Central admin | Open Dashboard. Click totals / alerts / stock-by-location / last 15 moves where clickable. | Totals by status; written-off last 30d; alerts; stock by location; last 15 audit moves; links open correct lists | | |
| DASH-02 | P0 | Location manager / Support tech | Open Dashboard as each. | Container stock; to resolve today; in movement; alerts scoped to location. Tech has no transfer actions | | |
| DASH-03 | P0 | End user | Open Dashboard (helpdesk). | Pending confirms + my gear only | | |

---

## RPT — Reports & export

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| RPT-01 | P1 | Central admin / Manager | Report **Current inventory by location** + required `locations_id`. | Rows match stock for that location | | |
| RPT-02 | P1 | Central admin / Manager | Report **Equipment per user** + required `users_id`. | Rows match that user’s gear | | |
| RPT-03 | P1 | Central admin / Manager | Report **Movements over a period** + `date_from` / `date_to`. | Rows limited to period | | |
| RPT-04 | P1 | Central admin / Manager | Report **Stock by type** (no required filter). | Aggregates by type | | |
| RPT-05 | P1 | Central admin / Manager | Report **Equipment with issues** (over thresholds). | Overdue alloc / transfer / service rows | | |
| RPT-06 | P1 | Central admin / Manager | Report **Inventory by container** + location + container. | Only that container’s contents | | |
| RPT-07 | P1 | Central admin / Manager | On any populated report: **Export CSV** and **Export Excel**. Open files; compare row counts to on-screen. Check audit. | Files download; open in spreadsheet app; counts match; audit `report_export`. Excel is SpreadsheetML `.xls` (not `.xlsx`) | | P2 polish: Office/LibreOffice open cleanly |

---

## CFG — Threshold configuration

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| CFG-01 | P1 | Central admin | Configuration: set allocation / transfer / service days (e.g. 1 / 1 / 1). Save. Exercise overdue paths (ALO-06, TRF-06, SVC-04). As manager: confirm no Config menu. | Thresholds saved; Active alerts / issues respect new values; managers cannot open Config | | Thresholds do **not** send email |

---

## AUD — Audit log

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| AUD-01 | P1 | Central admin | After prior cases, open Audit log (and/or dashboard recent moves). Spot-check actions. | Entries for: container create, equipment receipt, allocation initiate, confirm (and/or reject), transfer validate (and/or cancel/reject), write-off, report export — with user + timestamp | | |

---

## SEC — Rights & isolation

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| SEC-01 | P0 | Location manager (BUC) | Lists + try mutate CLU container/equipment (UI and, if feasible, form URL with CLU id). | Cannot see or change CLU stock; denied / empty / error | | |
| SEC-02 | P0 | End user | From helpdesk, try open equipment list, config, reports, transfer URLs directly. | Redirected or access denied; only Dashboard + Confirm usable | | |

---

## I18N — Locales

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| I18N-01 | P2 | Central admin + End user | Set GLPI language `ro_RO`. Open menu, Dashboard, Confirm, one Report. | Romanian strings on main paths; no raw English msgid keys | | |
| I18N-02 | P2 | Central admin + End user | Set language `en_GB`. Repeat critical path (allocate → confirm). | English UI coherent | | |

---

## REG — Coexistence with plugin 1

| ID | Pri | Role | Steps | Expected | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| REG-01 | P2 | Super-Admin | Enable `auchanequipment` and `auchanassettracker` together. Open both menus; smoke one stock action in each. | Both enabled; no fatal / menu clash; basic operations still work | | Blocked if plugin 1 not available |

---

## Sign-off

| Gate | Status | Date | Tester | Notes |
| --- | --- | --- | --- | --- |
| P0 all Pass | ☐ | | | |
| P1 Pass or waived | ☐ | | | |
| Open bugs closed or deferred in writing | ☐ | | | |
| Ready for Sprint 6 documentation | ☐ | | | |

### Sprint 6 (after this pack — out of scope here)

- Installable zip (`tools/build_package.sh`)
- Architecture + ERD + install guide + QR URL note
- Three user manuals (Admin / Manager / End user)
- This checklist as client test pack
- Regression note vs plugin 1
- Debug / SQL peek / rollback notes (D-01 → D-13 light set)

---

## Case count summary

| Prefix | Cases | Typical priority |
| --- | --- | --- |
| ROLE | 1 | P0 |
| INST | 3 | P0 |
| ECH | 7 | P0–P2 |
| ALO | 7 | P0–P1 |
| TRF | 7 | P0–P1 |
| SVC | 4 | P0–P1 |
| WOF | 3 | P1 |
| QR | 5 | P0–P1 |
| DASH | 3 | P0 |
| RPT | 7 | P1 |
| CFG | 1 | P1 |
| AUD | 1 | P1 |
| SEC | 2 | P0 |
| I18N | 2 | P2 |
| REG | 1 | P2 |
| **Total** | **54** | |

**P0 must-pass IDs:** ROLE-01, INST-01, INST-02*, INST-03, ECH-01, ECH-02, ECH-03, ECH-06, ALO-01, ALO-03, ALO-04, ALO-05, ALO-07, TRF-01, TRF-02, TRF-03, TRF-07, SVC-01, SVC-02, SVC-03, QR-01, QR-03, QR-04, QR-05, DASH-01, DASH-02, DASH-03, SEC-01, SEC-02  

\*INST-02 may be Blocked if no 0.4.x baseline.
