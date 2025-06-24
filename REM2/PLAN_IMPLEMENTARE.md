# Plan de Implementare - Aplicație Web Tranzacții Imobiliare

## Obiectiv
Dezvoltarea unei aplicații web pentru gestionarea eficientă a tranzacțiilor imobiliare folosind PHP + XAMPP, fără framework-uri.

## Tehnologii Folosite
- **Backend**: PHP 8+ (vanilla, fără framework)
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **Baza de date**: SQLite (pentru simplicitate) + MySQL pentru producție
- **Server**: XAMPP (Apache + PHP + MySQL)
- **Cartografie**: OpenStreetMap + Leaflet.js
- **APIs**: Geolocation API, servicii externe pentru straturi suplimentare

## Structura Etapelor

### ETAPA 1: Setup Inițial + Frontend Base (Commit #1)
**Durată estimată: 2-3 zile**

**Frontend:**
- Structura HTML5 de bază (index.html)
- CSS responsive framework propriu
- Layout principal cu header, navigation, footer
- Pagina de home cu preview hartă
- Design system (culori, tipografie, spacing)

**Files created:**
```
├── index.html
├── css/
│   ├── style.css
│   ├── responsive.css
│   └── components.css
├── js/
│   └── main.js
├── assets/
│   └── images/
└── README.md
```

### ETAPA 2: Backend Core + Database (Commit #2)
**Durată estimată: 3-4 zile**

**Backend:**
- Structura de directoare PHP
- Configurare conexiune SQLite
- Modelul de date pentru properties
- API endpoints de bază (CRUD properties)
- Sistem de rutare simplu
- Configurare CORS pentru Ajax

**Files created:**
```
├── api/
│   ├── index.php
│   ├── routes/
│   │   ├── properties.php
│   │   └── auth.php
│   ├── models/
│   │   ├── Property.php
│   │   └── Database.php
│   ├── config/
│   │   └── database.php
│   └── utils/
│       ├── Response.php
│       └── Security.php
├── database/
│   ├── schema.sql
│   └── real_estate.db
└── .htaccess
```

### ETAPA 3: Frontend Hartă + Integrare OpenStreetMap (Commit #3)
**Durată estimată: 4-5 zile**

**Frontend:**
- Integrare Leaflet.js pentru OpenStreetMap
- Componenta de hartă interactivă
- Afișare markere pentru properties
- Popup-uri cu informații de bază
- Controale pentru zoom și navigare
- Responsive design pentru hartă

**Files updated/created:**
```
├── js/
│   ├── map.js
│   ├── components/
│   │   ├── Map.js
│   │   └── PropertyMarker.js
│   └── libs/
│       └── leaflet/ (CDN)
├── css/
│   └── map.css
└── pages/
    └── map.html
```

### ETAPA 4: Backend API Properties + CRUD (Commit #4)
**Durată estimată: 3-4 zile**

**Backend:**
- Implementare completă CRUD pentru properties
- Validare și sanitizare date
- Upload imagini pentru properties
- Geolocalizare coordonate
- Căutare și filtrare properties
- Rate limiting pentru API

**Files updated/created:**
```
├── api/
│   ├── routes/
│   │   ├── properties.php (enhanced)
│   │   └── upload.php
│   ├── models/
│   │   ├── Property.php (enhanced)
│   │   └── Image.php
│   └── middleware/
│       ├── Auth.php
│       └── RateLimit.php
├── uploads/
│   └── properties/
└── database/
    └── schema.sql (updated)
```

### ETAPA 5: Frontend Listare și Filtrare Properties (Commit #5)
**Durată estimată: 3-4 zile**

**Frontend:**
- Pagină de listare properties
- Sistem de filtrare (preț, suprafață, tip, etc.)
- Căutare text
- Paginare rezultate
- Cards responsive pentru properties
- Conectare cu API-ul backend

**Files created/updated:**
```
├── pages/
│   ├── properties.html
│   └── property-detail.html
├── js/
│   ├── properties.js
│   ├── filters.js
│   └── components/
│       ├── PropertyCard.js
│       ├── FilterPanel.js
│       └── Pagination.js
└── css/
    ├── properties.css
    └── filters.css
```

### ETAPA 6: Backend Sistem de Autentificare (Commit #6)
**Durată estimată: 2-3 zile**

**Backend:**
- Sistem de înregistrare/login
- Hashare parole (password_hash)
- Sesiuni PHP securizate
- Roluri utilizatori (admin, user, agent)
- JWT pentru API authentication
- Protecție împotriva brute force

**Files created/updated:**
```
├── api/
│   ├── routes/
│   │   ├── auth.php (enhanced)
│   │   └── users.php
│   ├── models/
│   │   └── User.php
│   └── middleware/
│       └── JWT.php
├── database/
│   └── schema.sql (updated)
└── config/
    └── jwt.php
```

### ETAPA 7: Frontend Autentificare + Dashboard (Commit #7)
**Durată estimată: 3-4 zile**

**Frontend:**
- Formulare login/register
- Dashboard utilizator
- Gestionare profil
- Listare properties proprii
- Interfață pentru adăugare property nou
- State management pentru autentificare

**Files created:**
```
├── pages/
│   ├── login.html
│   ├── register.html
│   ├── dashboard.html
│   └── add-property.html
├── js/
│   ├── auth.js
│   ├── dashboard.js
│   └── components/
│       ├── LoginForm.js
│       ├── PropertyForm.js
│       └── UserProfile.js
└── css/
    ├── auth.css
    └── dashboard.css
```

### ETAPA 8: Backend Straturi Suplimentare + APIs Externe (Commit #8)
**Durată estimată: 4-5 zile**

**Backend:**
- Integrare APIs pentru straturi (poluare, criminalitate, etc.)
- Caching rezultate externe
- Agregare date pentru zonă
- API pentru statistici zonă
- Optimizare performanță

**Files created:**
```
├── api/
│   ├── routes/
│   │   ├── layers.php
│   │   └── stats.php
│   ├── services/
│   │   ├── PollutionAPI.php
│   │   ├── CrimeAPI.php
│   │   └── WeatherAPI.php
│   ├── models/
│   │   └── LayerData.php
│   └── cache/
│       └── CacheManager.php
└── config/
    └── external_apis.php
```

### ETAPA 9: Frontend Straturi Interactive + Geolocation (Commit #9)
**Durată estimată: 4-5 zile**

**Frontend:**
- Controale pentru straturi pe hartă
- Vizualizare date straturi (heatmaps, overlays)
- Implementare Geolocation API
- Căutare properties în proximitate
- Comparare zone
- Tooltips și legende pentru straturi

**Files created/updated:**
```
├── js/
│   ├── layers.js
│   ├── geolocation.js
│   ├── components/
│   │   ├── LayerControl.js
│   │   ├── Heatmap.js
│   │   └── ProximitySearch.js
│   └── map.js (enhanced)
├── css/
│   ├── layers.css
│   └── map.css (updated)
└── pages/
    └── compare.html
```

### ETAPA 10: Backend Securitate + Import/Export (Commit #10)
**Durată estimată: 3-4 zile**

**Backend:**
- Protecție SQL Injection (prepared statements)
- Protecție XSS (htmlspecialchars, CSP headers)
- Export CSV și JSON
- Import masiv de date
- Backup și restore
- Validare avansată input

**Files created:**
```
├── api/
│   ├── routes/
│   │   ├── export.php
│   │   └── import.php
│   ├── utils/
│   │   ├── CSVHandler.php
│   │   ├── JSONHandler.php
│   │   └── Security.php (enhanced)
│   └── middleware/
│       └── CSRF.php
├── exports/
├── imports/
└── config/
    └── security.php
```

### ETAPA 11: Frontend Admin Panel + Export/Import UI (Commit #11)
**Durată estimată: 3-4 zile**

**Frontend:**
- Panel de administrare complet
- Gestionare utilizatori
- Statistici și rapoarte
- Interfață export/import
- Setări aplicație
- Dashboard administrativ

**Files created:**
```
├── admin/
│   ├── index.html
│   ├── users.html
│   ├── properties.html
│   ├── settings.html
│   └── reports.html
├── js/
│   ├── admin.js
│   └── components/
│       ├── UserManagement.js
│       ├── PropertyStats.js
│       └── ExportImport.js
└── css/
    └── admin.css
```

### ETAPA 12: Testing + Optimizare + Deploy (Commit #12)
**Durată estimată: 2-3 zile**

**Final:**
- Testing funcționalități
- Optimizare performanță
- Validare HTML5/CSS3
- Configurare pentru deploy
- Documentație completă
- README final

**Files created:**
```
├── tests/
│   ├── api_tests.php
│   └── frontend_tests.html
├── docs/
│   ├── API_DOCUMENTATION.md
│   ├── DEPLOYMENT.md
│   └── USER_GUIDE.md
├── config/
│   ├── production.php
│   └── development.php
└── deploy/
    └── setup.sql
```

## Criterii de Finalizare pentru Fiecare Etapă

### Validare Tehnică:
- [ ] Cod validat HTML5 (W3C Validator)
- [ ] CSS validat (W3C CSS Validator)
- [ ] Teste funcționale trecute
- [ ] Responsive design testat pe multiple device-uri
- [ ] Cross-browser compatibility
- [ ] Performance optimizat (< 3s load time)

### Securitate:
- [ ] SQL Injection protection implementată
- [ ] XSS protection activă
- [ ] CSRF tokens implementate
- [ ] Rate limiting functional
- [ ] Input validation completă

### Funcționalitate:
- [ ] Toate CRUD operations funcționale
- [ ] Hartă interactivă cu markere
- [ ] Filtrare și căutare funcțională
- [ ] Autentificare securizată
- [ ] Export/Import CSV și JSON
- [ ] Geolocation API functional

## Timeline Estimat Total: 35-45 zile

Acest plan asigură dezvoltarea treptată cu push-uri regulate și testarea incrementală a funcționalităților. 