# User manuals (D-06 / D-07 / D-08)

Menu: **Auchan Asset Tracker**. Language follows GLPI (EN / RO).

---

## D-06 — Central admin / IT Manager

**Profile:** Central admin · full access all locations · Configuration allowed.

### Configuration (thresholds)

1. Menu → **Configuration**.
2. Set days: allocation confirm (default 5), transfer validate (7), service max (30).
3. Save. Overdue items show under **Active alerts** (no email from threshold alone).

### Dashboard

Opens role home: totals, active alerts, stock by location, last moves (clickable).

### Reports

**Reports** → pick report + filters → run → optional **CSV / Excel** export.

Typical reports: inventory by location, gear per user, movements by period, stock by type, issues (over thresholds), inventory by container.

### Stock & containers

- **Physical containers**: create per Location; print QR label; soft-delete if unused.
- **Equipment**: receive one item (container mandatory when Available) or **Bulk accessories**.
- Link / sync with GLPI assets when using asset forms with container field.

### Allocation & transfers

- **New allocation**: Available → user → Awaiting validation (container cleared).
- **Transfers**: send Available items → In transit → destination validates with containers (or reject with comment). Sender can edit/delete while in transit.

### Final status (write-off / lost / stolen)

1. Open equipment → Write-off tab.
2. Choose Written off / Lost / Stolen + reason (+ document).
3. Status locks. Only manager/admin **Reintroduce** with a container → back to Available.

### Profiles

**Administration → Profiles → Auchan Asset Tracker**: rights matrix. Assign users under Users; set Location for managers/techs.

---

## D-07 — Location manager / Support technician

**Location manager:** same ops as admin but **only your Location**.  
**Support technician:** mainly **new allocation** + service on assigned location (no stock receipt / bulk / transfers by default).

### Receive equipment (manager)

1. Create / pick container at your location.
2. **Equipment** → Add (or bulk) → fill serial/type → choose container → save → status **Available**.

### Containers & QR

1. Open container → check URL (left) + QR (right).
2. Print label; stick on shelf.
3. Phone scan opens public page (contents only).

### Allocate

1. **New allocation** (or from Available equipment).
2. Pick user at same location → send.
3. User must Confirm or Did not receive. Pending too long → alert.

### Transfer (manager)

1. **Transfers** → new → destination + Available items.
2. Items become **In transit**.
3. Destination validates each item into a container → Available there, or rejects with comment.

### Service

Open a GLPI **Ticket** on tracked gear → status **In service**. When ticket solved/closed → **Allocated** again.

### Write-off / lost / stolen

Same as admin, on equipment you can access. Reintroduce only if your rights allow (managers).

---

## D-08 — End user

**Profile:** End user · Helpdesk interface · short paths only.

### Dashboard

**Auchan Asset Tracker → Dashboard**: pending confirmations + **my gear**.

### Confirm receipt

1. Open **Confirm receipt** (or pending row on dashboard).
2. **Confirm receipt** → equipment becomes Allocated to you.  
   **Did not receive** → comment required → gear returns to stock; allocator gets notice/email.

### History

From dashboard / my gear: see what is allocated to you and past confirmation state. No stock or transfer screens.
