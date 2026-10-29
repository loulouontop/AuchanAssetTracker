# Plan prezentare plugin complet — Auchan Asset Tracker

**Audiență:** stakeholderi IT Auchan (proiect, operațiuni, UAT)  
**Produs:** AuchanAssetTracker **0.5.0** (Sprinturi 1–5 + documentație Faza 6)  
**Platformă:** GLPI **11.0.2** · PHP **8.1+** · limbi **EN + RO**  
**Durată sugerată:** ~30–40 min + demonstrație live + Q&A  
**Deck:** [Google Slides — Full Delivery](https://docs.google.com/presentation/d/1vUwWxFFCJe8wSs7RqSvsD4l4P7xWqBYn6IHnwDesn2U/edit) (construit după acest plan; generarea Canva AI nu este activă pe echipa conectată)  

---

## Scop

Prezentarea pluginului **finalizat** de gestiune stoc IT și trasabilitate: ce s-a construit, cum folosesc rolurile, integrarea cu GLPI și ce rămâne pentru UAT / go-live (pregătire inventar, instalare UAT/prod, freeze decembrie).

## Arc narativ

Hook (stoc fragmentat) → Soluție (plugin în GLPI) → Ciclu de viață & roluri → Funcționalități pe sprint → Operațiuni (alerte, QR, rapoarte) → Livrabile & pregătire → Întrebări / pași următori.

## Timing

| Bloc | Minute | Slide-uri |
| --- | --- | --- |
| Context & scop | 4 | 1–3 |
| Ciclu de viață & roluri | 5 | 4–5 |
| Parcurgere funcționalități | 12 | 6–11 |
| Dashboard-uri, rapoarte, alerte | 5 | 12–13 |
| Docs, deploy, pregătire | 5 | 14–15 |
| Q&A / next steps | 5+ | 16 |

Demo live (opțional, +15–20 min): container + QR → receptie → alocare → confirmare → transfer → ticket/service → dashboard/raport.

---

## Plan slide-uri

### Slide 1 — Titlu

**Titlu:** Auchan Asset Tracker — Livrare completă  
**Bullets:**

- Plugin GLPI pentru stoc IT, containere, alocare, transferuri, service, casare
- Versiune 0.5.0 · Sprinturi 1–5 complete · Documentație Faza 6 gata
- Prezentator: Lokmane Benaziza

### Slide 2 — Agendă

1. De ce acest plugin  
2. Ciclu de viață echipament  
3. Roluri & acces  
4. Funcționalități pe sprint  
5. Dashboard-uri, rapoarte, alerte  
6. Documentație & pregătire go-live  
7. Întrebări  

### Slide 3 — Problemă & rezultat

- Înainte: stoc și mișcări greu de urmărit pe locații  
- După: stoc legat de container, confirmare utilizator, transferuri, service pe ticket, audit  
- Rezultat: meniu unificat **Auchan Asset Tracker** în GLPI (EN/RO)

### Slide 4 — Ciclu de viață

```
Available → Awaiting validation → Allocated
                ↘ reject → Available
Allocated → In transit → Available (dest) / reject
Allocated → In service (ticket) → Allocated
Orice cale permisă → Written off / Lost / Stolen → Reintroduce → Available
```

Reguli: Available necesită container; In transit / In service / Awaiting validation blochează alocare & transfer; statusurile finale sunt imutabile până la reintroducere de manager.

### Slide 5 — Patru roluri

| Profil | Domeniu | Ecrane principale |
| --- | --- | --- |
| Central admin | Toate locațiile | Dashboard, stoc, transferuri, rapoarte, Configuration |
| Location manager | Locația proprie | Aceleași operațiuni, limitate |
| Support technician | Locația proprie | Alocare nouă + service; fără receptie stoc / transferuri (implicit) |
| End user | Propriu | Dashboard + Confirm receipt (Helpdesk) |

### Slide 6 — Sprint 1: Fundament stoc

Containere fizice, receptie (simplă + bulk), container obligatoriu în stoc, audit, EN + RO.

### Slide 7 — Sprint 2: Alocare & confirmare

Alocare → Awaiting validation; Confirm / Did not receive; alerte confirmare; notificări email/in-app; meniu top-level.

### Slide 8 — Sprint 3: Transferuri, service, casare

Transfer In transit → validare cu container; ticket → In service; Write-off / Lost / Stolen + reintroducere; praguri alerte.

### Slide 9 — Sprint 4: Etichete QR

`qr_token`, preview + PDF/HTML, pagină publică `qr.php` (fără login), scan view-only.

### Slide 10 — Sprint 5: Dashboard & rapoarte

Dashboard pe rol; rapoarte + CSV/Excel; Configuration cu 3 praguri (zile).

### Slide 11 — Alerte vs email

Praguri → liste Active alerts; email pe evenimente (Mailhelper), nu NotificationTarget GLPI nativ.

### Slide 12 — Script demo

Container/QR → receptie → alocare/confirmare → transfer → ticket/service → dashboard/raport.

### Slide 13 — Livrabile (Faza 6)

D-01…D-13 în `doc/en/` și `doc/ro/` (sursă, pachet, arhitectură, instalare, API, manuale, teste, troubleshooting).

### Slide 14 — Deploy & go-live

Pregătire inventar Auchan; UAT (Alexandru) / prod (infra); freeze ~10 dec — opțiuni: 02–03.12 sau prima săptămână din ianuarie.

### Slide 15 — Riscuri & cereri

Calitate inventar; fereastră UAT; nume folder + URL QR public; confirmare fereastră go-live.

### Slide 16 — Mulțumiri / Q&A

Linkuri: repo, `doc/`, deck Canva, URL UAT.

---

## Checklist mediu demo

- [ ] GLPI 11.0.2 UAT, plugin **0.5.0**  
- [ ] Cele 4 profile + locații/containere exemplu  
- [ ] Utilizatori: admin, manager, tech, end user (email)  
- [ ] Telefon/browser pentru QR  
- [ ] Ticket pentru demo service  

## După întâlnire

1. Trimite link Canva + export PDF  
2. Trimite `doc/` sau `.tar.gz`  
3. Notează deciziile go-live / UAT  
4. Defecte deschise cu șablonul D-11 din `testing.md`
