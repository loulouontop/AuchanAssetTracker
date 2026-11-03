# Testare & QA (D-09 / D-10 / D-11 / D-12)

## D-09 — Plan de test / cazuri

ID-uri trasabile pe zone funcționale. Rulați pe rolul notat. Completați Pass/Fail în D-10 la execuție — **nu inventați** rezultate aici.

| ID | Zonă | Pași (scurt) | Așteptat |
| --- | --- | --- | --- |
| FR-CONT-01 | Container CRUD | Creează container la locație | Salvat, listat |
| FR-CONT-02 | Soft-delete | Șterge container | Ascuns din pickere stoc; QR inactiv |
| FR-QR-01 | URL QR | Deschide `/plugins/.../qr.php?t=…` delogat | Vedere raft, fără login |
| FR-QR-02 | Token greșit | `t=` greșit | 404 |
| FR-EQ-01 | Recepție | Adaugă echipament + container | Status Disponibil |
| FR-EQ-02 | Container obligatoriu | Salvează Disponibil fără container | Blocat / eroare |
| FR-EQ-03 | Bulk | Recepție accesorii bulk | Mai multe Disponibile |
| FR-AL-01 | Alocare | Alocă Disponibil → user | Așteaptă validare; container golit |
| FR-AL-02 | Confirmare | End user Confirmă | Alocat |
| FR-AL-03 | Respingere | Nu am primit + comentariu | Înapoi Disponibil; notice/mail |
| FR-AL-04 | Fără comentariu | Respinge fără comentariu | Blocat |
| FR-AL-05 | Notice/mail | Alocă user cu email | Destinatarul primește notice + mail |
| FR-TR-01 | Trimitere transfer | Trimite Disponibil la altă locație | În tranzit |
| FR-TR-02 | Validare | Dest + containere | Disponibil la dest |
| FR-TR-03 | Respingere transfer | Respinge + comentariu | Înapoi Disponibil la sursă |
| FR-TR-04 | Ops blocate | Alocare în timp ce În tranzit | Blocat |
| FR-TR-05 | Edit în tranzit | Expeditor editează dest/note/elemente | Salvat cât e în tranzit |
| FR-SV-01 | Service in | Ticket pe echipament urmărit | În service |
| FR-SV-02 | Service out | Rezolvă/închide ticket | Alocat |
| FR-FIN-01 | Casare | Casare + motiv | Status final |
| FR-FIN-02 | Imutabil | Schimbă status în formular | Nu iese din final decât prin reintroducere |
| FR-FIN-03 | Reintroducere | Manager + container | Disponibil |
| FR-CFG-01 | Praguri | Setează zile în Configurare | Alertele folosesc valorile noi |
| FR-DASH-01 | Dashboard pe rol | Login fiecare rol | Widget-uri corecte |
| FR-REP-01 | Rapoarte | Rulează + CSV/Excel | Fișier descărcat; export jurnalizat |
| FR-REP-02 | Filtre raport | Inventar pe locație fără locație | Eroare validare |
| FR-RIGHT-01 | Scop locație | Manager altă locație | Fără acces / gol |
| FR-RIGHT-02 | Meniu End user | Login End user | Doar Dashboard + Confirmare |
| FR-RIGHT-03 | Support tech | Login Support tech | Fără Transferuri / Bulk / Config |
| FR-AUD-01 | Audit | Creare/update/alocare | Rând în jurnal audit |

Includeți mereu: container obligatoriu, imutabilitate status final, alerte prag (fără email doar din prag), transfer în tranzit, QR container inactiv.

## D-10 — Raport execuție teste (șablon)

Completați doar cu rulări QA **reale**.

| Sprint | Dată | Env (GLPI/PHP) | Cazuri rulate | Pass | Fail | Note |
| --- | --- | --- | --- | --- | --- | --- |
| S1 | | 11.0.2 / 8.x | Cont + recepție + roluri | | | |
| S2 | | | Alocare + confirmare | | | |
| S3 | | | Transfer + service + casare | | | |
| S4 | | | Pagină QR publică | | | |
| S5 | | | Dashboard + rapoarte + praguri | | | |

Atașați screenshot-uri / exporturi în share-ul QA dacă cere contractul. Defecte → D-11.

## D-11 — Bug-fix / jurnal defecte

Jurnal rulant — adăugați rânduri pe măsură ce apar (nu inventați defecte).

| ID | Găsit | Severitate | Rezumat | Status | Dată rezolvare |
| --- | --- | --- | --- | --- | --- |
| BUG-001 | Dev/UAT | | | Open / Fixed / Won’t fix | |

Severitate: Blocker / Major / Minor / Trivial. Păstrați și itemele din perioada de garanție în același tabel.

## D-12 — Funcționalitate existentă / regresie

După install/upgrade, confirmați că feature-urile din sprinturile anterioare funcționează și că GLPI core nu e afectat.

| Verificare | Pass? |
| --- | --- |
| Login GLPI / Users / Profiles | |
| Assets (Computer etc.) create/search | |
| Tickets create/update | |
| Locations | |
| Plugin S1: containere + stoc Disponibil | |
| Plugin S2: alocare + confirmare | |
| Plugin S3: transfer + service + casare | |
| Plugin S4: scan QR anonim | |
| Plugin S5: dashboard + rapoarte + config | |

Uninstall/disable **nu** trebuie să șteargă Assets / Tickets / Users core. Uninstall plugin = soft (tabele păstrate).
