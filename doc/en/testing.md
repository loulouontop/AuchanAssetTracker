# Testing & QA (D-09 / D-10 / D-11 / D-12)

## D-09 — Test plan / cases

Trace IDs to functional areas. Run as the role noted. Fill **Pass/Fail** in D-10 when executed — do not invent results here.

| ID | Area | Steps (short) | Expected |
| --- | --- | --- | --- |
| FR-CONT-01 | Container CRUD | Create container at location | Saved, listed |
| FR-CONT-02 | Soft-delete | Delete container | Hidden from stock pickers; QR inactive |
| FR-QR-01 | QR URL | Open `/plugins/.../qr.php?t=…` logged out | Shelf view, no login |
| FR-QR-02 | Bad token | Wrong `t=` | 404 |
| FR-EQ-01 | Receipt | Add equipment + container | Status Available |
| FR-EQ-02 | Mandatory container | Save Available without container | Blocked / error |
| FR-EQ-03 | Bulk | Bulk accessories receipt | Multiple Available |
| FR-AL-01 | Allocate | Allocate Available → user | Awaiting validation; container cleared |
| FR-AL-02 | Confirm | End user Confirm | Allocated |
| FR-AL-03 | Reject | Did not receive + comment | Back Available; notice/mail |
| FR-AL-04 | No comment | Reject without comment | Blocked |
| FR-AL-05 | Notice/mail | Allocate to user with email | Recipient gets notice + mail |
| FR-TR-01 | Transfer send | Send Available to other location | In transit |
| FR-TR-02 | Validate | Dest + containers | Available at dest |
| FR-TR-03 | Reject transfer | Reject + comment | Back Available at source |
| FR-TR-04 | Block ops | Allocate while In transit | Blocked |
| FR-TR-05 | Edit in transit | Sender edits dest/notes/items | Saved while in transit |
| FR-SV-01 | Service in | Ticket on tracked gear | In service |
| FR-SV-02 | Service out | Solve/close ticket | Allocated |
| FR-FIN-01 | Write-off | Write-off + reason | Final status |
| FR-FIN-02 | Immutable | Change status in form | Cannot leave final except reintroduce |
| FR-FIN-03 | Reintroduce | Manager + container | Available |
| FR-CFG-01 | Thresholds | Set days in Configuration | Alerts use new values |
| FR-DASH-01 | Role dashboard | Login each role | Correct home widgets |
| FR-REP-01 | Reports | Run + CSV/Excel | File downloads; export logged |
| FR-REP-02 | Report filters | Inventory by location without location | Validation error |
| FR-RIGHT-01 | Location scope | Manager other location | No access / empty |
| FR-RIGHT-02 | End user menu | Login End user | Dashboard + Confirm only |
| FR-RIGHT-03 | Support tech | Login Support tech | No Transfers / Bulk / Config |
| FR-AUD-01 | Audit | Create/update/allocate | Row in audit log |

Always include: mandatory container, final-status immutability, threshold alerts (no email from threshold alone), transfer while in transit, QR inactive container.

## D-10 — Test execution report (template)

Fill with **real** QA runs only.

| Sprint | Date | Env (GLPI/PHP) | Cases run | Pass | Fail | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| S1 | | 11.0.2 / 8.x | Cont + receipt + roles | | | |
| S2 | | | Allocation + confirm | | | |
| S3 | | | Transfer + service + write-off | | | |
| S4 | | | QR public page | | | |
| S5 | | | Dashboard + reports + thresholds | | | |

Attach screenshots / export files under your QA share if the contract requires. Defects → D-11.

## D-11 — Bug-fix / defect log

Running log — add rows as found (do not invent defects).

| ID | Found | Severity | Summary | Status | Resolution date |
| --- | --- | --- | --- | --- | --- |
| BUG-001 | Dev/UAT | | | Open / Fixed / Won’t fix | |

Severity: Blocker / Major / Minor / Trivial. Keep warranty-period items in the same table.

## D-12 — Existing functionality / regression

After install/upgrade, confirm previous sprint features still work and core GLPI is untouched.

| Check | Pass? |
| --- | --- |
| GLPI login / Users / Profiles | |
| Assets (Computer etc.) create/search | |
| Tickets create/update | |
| Locations | |
| Plugin S1: containers + Available stock | |
| Plugin S2: allocate + confirm | |
| Plugin S3: transfer + service + write-off | |
| Plugin S4: QR scan anonymous | |
| Plugin S5: dashboards + reports + config | |

Uninstall/disable must **not** delete core Assets / Tickets / Users. Plugin uninstall is soft (tables kept).
