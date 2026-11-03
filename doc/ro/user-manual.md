# Manuale utilizator (D-06 / D-07 / D-08)

Meniu: **Auchan Asset Tracker**. Limba urmează GLPI (EN / RO).

| Element meniu | Central admin | Location manager | Support tech | End user |
| --- | --- | --- | --- | --- |
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Echipament / Containere | ✓ | ✓ | ✓ (limitat) | — |
| Accesorii bulk | ✓ | ✓ | — | — |
| Alocare nouă | ✓ | ✓ | ✓ | — |
| Transferuri | ✓ | ✓ | — | — |
| Confirmare primire | ✓ | ✓ | ✓ | ✓ |
| Rapoarte | ✓ | ✓ | ✓ | — |
| Configurare | ✓ | — | — | — |

---

## D-06 — Central admin / IT Manager

**Profil:** Central admin · toate locațiile · Configurare permisă.

### Configurare (praguri)

1. **Auchan Asset Tracker → Configurare**.
2. Setați zilele (min 1):
   - Confirmare alocare — implicit **5**
   - Validare transfer — implicit **7**
   - Perioadă max service — implicit **30**
3. Salvați. Elementele depășite apar la **Alerte active** pe dashboard / ecrane alocare.  
   Pragurile **nu** trimit email singure (vezi Alerte vs emailuri mai jos).

### Dashboard

Totaluri, alerte active (clickabile), stoc pe locație, ultimele mutări (clickabile).

### Rapoarte

**Rapoarte** → tip + filtre → rulare → opțional **CSV / Excel**.

| Raport | Filtre obligatorii |
| --- | --- |
| Inventar curent pe locație | Locație |
| Echipament pe user | User |
| Mișcări pe perioadă | Dată de la + până la |
| Stoc pe tip | — |
| Echipament cu probleme | — (folosește pragurile) |
| Inventar pe container | Locație + container |

Rolurile cu scop pe locație sunt auto-limitate; nu pot raporta useri din alte locații.

### Stoc & containere

1. **Containere fizice** → Adaugă → Locație + cod/nume → salvează.
2. Deschide container → URL stânga, QR dreapta → tipărește eticheta → lipește pe raft.
3. **Echipament** → Adaugă (container obligatoriu când Disponibil) sau **Accesorii bulk**.
4. Soft-delete containere nefolosite (QR devine inactiv).

Legătură / sync cu asset-uri GLPI când formularul native arată câmpul container.

### Alocare & transferuri

1. **Alocare nouă**: echipament Disponibil + user → status **Așteaptă validare** (container golit).
2. Destinatarul folosește Confirmare primire (sau Nu am primit + comentariu).
3. **Transferuri**: sursă → destinație + elemente Disponibile → **În tranzit**.  
   Expeditorul poate edita destinație/note/elemente sau șterge cât timp e în tranzit.  
   Destinația validează fiecare element într-un container (→ Disponibil acolo) sau respinge cu comentariu.

### Status final (casare / pierdere / furt)

1. Deschide echipament → tab Write-off / Casare.
2. Alege Casat / Pierdut / Furat + motiv (+ document).
3. Statusul se blochează. Doar manager/admin **Reintroduce** cu un container → Disponibil.

### Profile

**Administrare → Profile → Auchan Asset Tracker**: matrice drepturi.  
Atribuiți useri la **Administrare → Utilizatori**; setați **Location** pentru manageri/tech.

### Alerte vs emailuri

- Zilele din **Configurare** → doar liste Alerte active.
- **Emailuri + notices in-app** → alocare pending, Nu am primit, respingere transfer (`Mailhelper`).

---

## D-07 — Location manager / Support technician

**Location manager:** aceleași ops ca admin, dar **doar Locația ta** (fără Configurare).  
**Support technician:** **Alocare nouă** + service pe locația atribuită; fără recepție stoc / bulk / transferuri în mod implicit.

### Recepție echipament (manager)

1. Creează / alege un container la locația ta.
2. **Echipament** → Adaugă (sau Bulk) → serial/tip → container → salvează → **Disponibil**.

### Containere & QR

1. Deschide container → URL + QR.
2. Tipărește eticheta; lipește pe raft.
3. Scan telefon = pagina publică de conținut (fără login, doar vizualizare).

### Alocare

1. **Alocare nouă** (sau din echipament Disponibil).
2. Alege un user (același scop de locație) → trimite.
3. Userul trebuie să Confirme sau Nu am primit. Prea târziu → Alertă activă.

### Transfer (manager)

1. **Transferuri** → nou → destinație + elemente Disponibile.
2. Elemente → **În tranzit**.
3. Destinația validează în containere sau respinge cu comentariu.

### Service

Deschide un **Ticket** GLPI pe echipament urmărit → **În service**. Ticket rezolvat/închis → din nou **Alocat**.

### Casare / pierdere / furt

Același flux ca la admin pe echipamentul la care ai acces. Reintroducere dacă drepturile permit (manageri).

---

## D-08 — End user

**Profil:** End user · interfață Helpdesk · doar căi scurte.

### Dashboard

**Auchan Asset Tracker → Dashboard**: confirmări pending + **echipamentul meu**.

### Confirmare primire

1. Deschide **Confirmare primire** (sau un rând pending pe dashboard).
2. **Confirmare primire** → echipamentul devine **Alocat** ție.  
   **Nu am primit** → comentariu obligatoriu → echipamentul revine Disponibil; alocatorul primește notice + email.

### Istoric

Din dashboard / echipamentul meu: ce e alocat ție și starea confirmării. Fără ecrane de stoc, transfer sau rapoarte.
