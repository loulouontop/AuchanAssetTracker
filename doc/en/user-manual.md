# User manuals (D-06 / D-07 / D-08)

Menu: **Auchan Asset Tracker**. Language follows GLPI (EN / RO).

| Menu item | Central admin | Location manager | Support tech | End user |
| --- | --- | --- | --- | --- |
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Equipment / Containers | ✓ | ✓ | ✓ (limited) | — |
| Bulk accessories | ✓ | ✓ | — | — |
| New allocation | ✓ | ✓ | ✓ | — |
| Transfers | ✓ | ✓ | — | — |
| Confirm receipt | ✓ | ✓ | ✓ | ✓ |
| Reports | ✓ | ✓ | ✓ | — |
| Configuration | ✓ | — | — | — |

---

## D-06 — Central admin / IT Manager

**Profile:** Central admin · all locations · Configuration allowed.

### Configuration (thresholds)

1. **Auchan Asset Tracker → Configuration**.
2. Set days (min 1):
   - Allocation confirmation — default **5**
   - Transfer validation — default **7**
   - Service max period — default **30**
3. Save. Overdue items appear under **Active alerts** on the dashboard / allocation screens.  
   Thresholds do **not** send email by themselves (see Alerts vs emails below).

### Dashboard

Totals, active alerts (clickable), stock by location, last moves (clickable).

### Reports

**Reports** → choose type + filters → run → optional **CSV / Excel**.

| Report | Required filters |
| --- | --- |
| Current inventory by location | Location |
| Equipment per user | User |
| Movements over a period | Date from + date to |
| Stock by type | — |
| Equipment with issues | — (uses thresholds) |
| Inventory by container | Location + container |

Scoped roles auto-limit location; they cannot report other locations’ users.

### Stock & containers

1. **Physical containers** → Add → Location + code/name → save.
2. Open container → URL left, QR right → print shelf label → stick on shelf.
3. **Equipment** → Add (container mandatory when Available) or **Bulk accessories**.
4. Soft-delete unused containers (QR becomes inactive).

Link / sync with GLPI assets when using native asset forms that show the container field.

### Allocation & transfers

1. **New allocation**: pick Available equipment + user → status **Awaiting validation** (container cleared).
2. Recipient uses Confirm receipt (or Did not receive + comment).
3. **Transfers**: source → destination + Available items → **In transit**.  
   Sender can edit destination/notes/items or delete while in transit.  
   Destination validates each item into a container (→ Available there) or rejects with comment.

### Final status (write-off / lost / stolen)

1. Open equipment → Write-off tab.
2. Choose Written off / Lost / Stolen + reason (+ document).
3. Status locks. Only manager/admin **Reintroduce** with a container → Available.

### Profiles

**Administration → Profiles → Auchan Asset Tracker**: rights matrix.  
Assign users under **Administration → Users**; set **Location** for managers/techs.

### Alerts vs emails

- **Configuration** days → Active alerts lists only.
- **Emails + in-app notices** → allocate pending, Did not receive, transfer reject (`Mailhelper`).

---

## D-07 — Location manager / Support technician

**Location manager:** same ops as admin but **only your Location** (no Configuration).  
**Support technician:** **New allocation** + service on assigned location; no stock receipt / bulk / transfers by default.

### Receive equipment (manager)

1. Create / pick a container at your location.
2. **Equipment** → Add (or Bulk) → serial/type → container → save → **Available**.

### Containers & QR

1. Open container → URL + QR.
2. Print label; stick on shelf.
3. Phone scan = public contents page (no login, view only).

### Allocate

1. **New allocation** (or from Available equipment).
2. Pick a user (same location scope) → send.
3. User must Confirm or Did not receive. Too late → Active alert.

### Transfer (manager)

1. **Transfers** → new → destination + Available items.
2. Items → **In transit**.
3. Destination validates into containers, or rejects with comment.

### Service

Open a GLPI **Ticket** on tracked gear → **In service**. Ticket solved/closed → **Allocated** again.

### Write-off / lost / stolen

Same flow as admin on equipment you can access. Reintroduce if your rights allow (managers).

---

## D-08 — End user

**Profile:** End user · Helpdesk interface · short paths only.

### Dashboard

**Auchan Asset Tracker → Dashboard**: pending confirmations + **my gear**.

### Confirm receipt

1. Open **Confirm receipt** (or a pending row on the dashboard).
2. **Confirm receipt** → equipment becomes **Allocated** to you.  
   **Did not receive** → comment required → gear returns to Available; allocator gets notice + email.

### History

From dashboard / my gear: what is allocated to you and confirmation state. No stock, transfer, or report screens.
