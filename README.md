# 🎬 FilmTracker

Een webapplicatie om films en series bij te houden, beoordelen en statistieken te bekijken.

---

## Projectstructuur

```
filmtracker/
├── fpdf/               ← FPDF library (zelf downloaden via fpdf.org)
├── db.php              ← PostgreSQL connectie (gedeeld)
├── style.css           ← Globale stijlen (gedeeld)
├── index.php           ← Landingspagina (gedeeld)
├── logout.php          ← Uitloggen (gedeeld)
├── schema.sql          ← Database structuur (documentatie)
│
├── ── PERSOON A ──
├── search.php          ← Films zoeken via TMDB + toevoegen aan watchlist
├── watchlist.php       ← Watchlist beheren (status wijzigen, verwijderen)
└── film-detail.php     ← Filmdetails + review schrijven
│
└── ── PERSOON B ──
├── register.php        ← Registratie
├── login.php           ← Inloggen
├── stats.php           ← Statistieken + grafieken + REST API endpoint
└── export.php          ← Watchlist exporteren als PDF
```

---

## Installatie

### Vereisten
- XAMPP (Apache + PHP 8+)
- PostgreSQL
- FPDF library: [fpdf.org](http://www.fpdf.org/)

### Stappen

1. Clone de repository in `C:\xampp\htdocs\filmtracker\`
2. Download FPDF en zet de `fpdf/` map in de projectmap
3. Maak een PostgreSQL database aan: `filmtracker`
4. Voer `schema.sql` uit in pgAdmin om de tabellen aan te maken
5. Pas het wachtwoord aan in `db.php`
6. Start Apache in XAMPP
7. Ga naar `http://localhost/filmtracker/`

---

## Taakverdeling & Vereisten

### Persoon A — Films

| Vereiste | Bestand | Hoe |
|---|---|---|
| PHP server-side | search.php, watchlist.php, film-detail.php | TMDB API, sessies, queries |
| SQL INSERT + SELECT + UPDATE + DELETE | search.php, watchlist.php, film-detail.php | Watchlist beheren |
| JavaScript client-side | search.php, watchlist.php, film-detail.php | Debounce, ster-rating, escaping |
| jQuery + Ajax | search.php, watchlist.php, film-detail.php | Live zoeken, status wijzigen |
| REST API extern apparaat | search.php (`action=remote_add`) | Smartphone voegt film toe via POST |

### Persoon B — Gebruikers & Statistieken

| Vereiste | Bestand | Hoe |
|---|---|---|
| PHP server-side | register.php, login.php, stats.php, export.php | Validatie, sessies, queries |
| SQL INSERT + SELECT | register.php, login.php, stats.php, export.php | Users + statistieken |
| JavaScript client-side | register.php, stats.php | Wachtwoord sterkte, Chart.js |
| jQuery + Ajax | stats.php | Grafiekdata live ophalen |
| REST API extern apparaat | stats.php (`?api=1`) | Smartphone vraagt statistieken op via GET |
| Geavanceerde PHP (PDF) | export.php | FPDF watchlist exporteren |

---

## REST API Endpoints

### Persoon A — Film toevoegen via extern apparaat
```
POST http://localhost/filmtracker/search.php
Body: action=remote_add&user_id=1&tmdb_id=550&title=Fight+Club&poster=/abc.jpg
```

### Persoon B — Statistieken opvragen via extern apparaat
```
GET http://localhost/filmtracker/stats.php?api=1&user_id=1
Response: { "totaal": 12, "bekeken": 8, "bezig": 2, "plan": 2, "gem_rating": 4.2 }
```

---

## Database Schema

```sql
users     → id, username, email, password, created_at
watchlist → id, user_id, tmdb_id, title, poster, status, rating, review, added_at
```

---

## Gebruikte Technologieën

- **PHP** — server-side logica
- **PostgreSQL** — database
- **jQuery 3.7** — DOM manipulatie + Ajax
- **Chart.js** — statistieken grafieken
- **FPDF** — PDF generatie
- **TMDB API** — filmdata
