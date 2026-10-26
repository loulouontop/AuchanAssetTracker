# Testare & QA (D-09 / D-10 / D-11 / D-12)

## D-09 — Plan de test / cazuri

ID-uri pe zone funcționale. Rulează ca Central admin / Location manager / Support tech / End user după caz.

| ID | Zonă | Pași (scurt) | Așteptat |
| --- | --- | --- | --- |
| FR-CONT-01 | Container CRUD | Creează container pe locație | Salvat, listat |
| FR-CONT-02 | Soft-delete | Șterge container | Ascuns din pickere; QR inactiv |
| FR-QR-01 | URL QR | Deschide `/plugins/.../qr.php?t=…` delogat | Raft, fără login |
| FR-QR-02 | Token greșit | `t=` invalid | 404 |
| FR-EQ-01 | Recepție | Adaugă echipament + container | Status Disponibil |
| FR-EQ-02 | Container obligatoriu | Salvează Disponibil fără container | Blocat / eroare |
| FR-EQ-03 | Bulk | Bulk accessories | Mai multe Disponibil |
| FR-AL-01 | Alocare | Alocă Disponibil → user | Așteaptă validare; container golit |
| FR-AL-02 | Confirm | End user Confirm | Alocat |
| FR-AL-03 | Reject | Did not receive + comentariu | Înapoi Disponibil; notice/mail |
| FR-AL-04 | Fără comentariu | Reject fără comentariu | Blocat |
| FR-TR-01 | Transfer send | Trimite Disponibil | În tranzit |
| FR-TR-02 | Validate | Dest + containere | Disponibil la dest |
| FR-TR-03 | Reject transfer | Reject + comentariu | Disponibil la sursă |
| FR-TR-04 | Block ops | Alocare cât e În tranzit | Blocat |
| FR-SV-01 | Service in | Ticket pe echipament | În service |
| FR-SV-02 | Service out | Solve/close ticket | Alocat |
| FR-FIN-01 | Write-off | Write-off + motiv | Status final |
| FR-FIN-02 | Imutabil | Schimbă status în form | Nu ieși din final fără reintroducere |
| FR-FIN-03 | Reintroduce | Manager + container | Disponibil |
| FR-CFG-01 | Praguri | Setează zile în Configuration | Alertele folosesc valorile noi |
| FR-DASH-01 | Dashboard rol | Login pe fiecare rol | Widget-uri corecte |
| FR-REP-01 | Rapoarte | Rulează + CSV/Excel | Download; export în audit |
| FR-RIGHT-01 | Scope locație | Manager pe altă locație | Fără acces / gol |
| FR-RIGHT-02 | Meniu End user | Login End user | Doar Dashboard + Confirm |
| FR-AUD-01 | Audit | Create/update/allocate | Rând în audit |

Edge cases obligatorii: container obligatoriu, imutabilitate status final, alerte prag, transfer în tranzit, QR container inactiv.

## D-10 — Raport execuție teste (șablon)

Completează la fiecare livrare sprint:

| Sprint | Dată | Env (GLPI/PHP) | Cazuri | Pass | Fail | Note |
| --- | --- | --- | --- | --- | --- | --- |
| S1 | — | 11.0.2 / 8.x | Cont + recepție + roluri | — | — | |
| S2 | — | | Alocare + confirm | — | — | |
| S3 | — | | Transfer + service + write-off | — | — | |
| S4 | — | | QR public | — | — | |
| S5 | — | | Dashboard + rapoarte + praguri | — | — | |

Atașează screenshot-uri / exporturi pe share-ul QA dacă cere contractul. Defecte → D-11.

## D-11 — Bug-fix / defect log

Jurnal rulant:

| ID | Găsit | Severitate | Rezumat | Status | Dată rezolvare |
| --- | --- | --- | --- | --- | --- |
| BUG-001 | Dev/UAT | — | — | Open / Fixed / Won’t fix | — |

Severitate: Blocker / Major / Minor / Trivial.  
Include și perioada de garanție în același tabel.

## D-12 — Funcționalitate existentă / regresie

După install/upgrade: verifică că **sprinturile anterioare încă merg** și core GLPI e neatins.

| Verificare | Pass? |
| --- | --- |
| Login GLPI / Users / Profiles | |
| Assets (Computer etc.) create/search | |
| Tickets create/update | |
| Locations | |
| Plugin S1: containere + stoc Disponibil | |
| Plugin S2: alocare + confirm | |
| Plugin S3: transfer + service + write-off | |
| Plugin S4: scan QR anonim | |
| Plugin S5: dashboard + rapoarte + config | |

Disable/uninstall **nu** șterge Assets / Tickets / Users. Uninstall plugin = soft (tabele păstrate).
