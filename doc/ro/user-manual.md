# Manuale utilizator (D-06 / D-07 / D-08)

Meniu: **Auchan Asset Tracker**. Limba = limba GLPI (EN / RO).

---

## D-06 — Central admin / IT Manager

**Profil:** Central admin · acces pe toate locațiile · Configuration permisă.

### Configuration (praguri)

1. Meniu → **Configuration**.
2. Zile: confirmare alocare (default 5), validare transfer (7), max service (30).
3. Salvează. Depășirile apar la **Active alerts** (fără email doar din prag).

### Dashboard

Ecran pe rol: totaluri, alerte, stoc pe locație, ultimele mutări (clickabile).

### Rapoarte

**Reports** → tip + filtre → rulează → export **CSV / Excel**.

Exemple: inventar pe locație, echipament pe user, mutări pe perioadă, stoc pe tip, probleme (peste prag), inventar pe container.

### Stoc & containere

- **Physical containers**: pe Locație; tipărește QR; soft-delete dacă nu e folosit.
- **Equipment**: recepție un item (container obligatoriu la Disponibil) sau **Bulk accessories**.
- Link / sync cu asset GLPI când folosești formularul asset cu container.

### Alocare & transferuri

- **New allocation**: Disponibil → user → Așteaptă validare (container golit).
- **Transfers**: trimite Disponibil → În tranzit → destinația validează cu containere (sau respinge cu comentariu). Expeditorul poate edita/șterge cât e în tranzit.

### Status final (casare / pierdut / furat)

1. Deschide echipamentul → tab Write-off.
2. Alege Casat / Pierdut / Furat + motiv (+ document).
3. Status blocat. Doar manager/admin **Reintroduce** cu container → Disponibil.

### Profiluri

**Administration → Profiles → Auchan Asset Tracker**: matrice drepturi. Useri → asignează profil; Location pentru manager/tech.

---

## D-07 — Location manager / Support technician

**Location manager:** aceleași operații ca admin, dar **doar Locația ta**.  
**Support technician:** în principal **alocare nouă** + service pe locația asignată (fără recepție stoc / bulk / transferuri by default).

### Recepție echipament (manager)

1. Creează / alege container pe locație.
2. **Equipment** → Add (sau bulk) → serial/tip → container → salvează → **Disponibil**.

### Containere & QR

1. Deschide container → URL (stânga) + QR (dreapta).
2. Tipărește eticheta; lipește pe raft.
3. Scan telefon = pagină publică (doar conținut).

### Alocare

1. **New allocation** (sau din echipament Disponibil).
2. Alege user pe aceeași locație → trimite.
3. User Confirmă sau Nu am primit. Pending prea mult → alertă.

### Transfer (manager)

1. **Transfers** → nou → destinație + iteme Disponibile.
2. Itemele → **În tranzit**.
3. Destinația pune fiecare într-un container → Disponibil acolo, sau respinge cu comentariu.

### Service

Ticket GLPI pe echipament urmărit → **În service**. Ticket rezolvat/închis → din nou **Alocat**.

### Casare / pierdut / furat

Ca la admin, pe echipamentul accesibil. Reintroducere dacă ai drepturi (manager).

---

## D-08 — End user

**Profil:** End user · interfață Helpdesk · doar căi scurte.

### Dashboard

**Auchan Asset Tracker → Dashboard**: confirmări pending + **echipamentul meu**.

### Confirmare primire

1. **Confirm receipt** (sau rând pending pe dashboard).
2. **Confirm receipt** → Alocat pe tine.  
   **Did not receive** → comentariu obligatoriu → revine în stoc; alocatorul primește notice/email.

### Istoric

Din dashboard / my gear: ce ai alocat și starea confirmării. Fără ecrane de stoc sau transfer.
