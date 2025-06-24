# REMS - Real Estate Management System

## 📋 Descriere Proiect

REMS (Real Estate Management System) este o aplicație web modernă pentru gestionarea eficientă a tranzacțiilor imobiliare. Sistemul permite managementul unor imobile spre vânzare și/sau închiriere, oferind o interfață intuitivă și funcționalități avansate de căutare și visualizare pe hartă.

### 🎯 Caracteristici Principale

- **🗺️ Hartă Interactivă**: Folosește OpenStreetMap pentru localizarea facilă a proprietăților
- **📊 Straturi de Date**: Vizualizare informații despre poluare, criminalitate, transport public
- **🔍 Căutare Avansată**: Filtrare după preț, suprafață, tip proprietate, facilități
- **📱 Design Responsiv**: Optimizat pentru desktop, tabletă și mobile
- **🔒 Securitate**: Protecție împotriva SQL injection și XSS
- **📤 Export/Import**: Suport pentru CSV și JSON
- **👥 Administrare**: Panel complet de administrare

## 🛠️ Tehnologii Folosite

### Frontend
- **HTML5** - Markup semantic și accesibil
- **CSS3** - Design modern cu CSS custom properties
- **JavaScript Vanilla** - Fără framework-uri, cod optimizat
- **Leaflet.js** - Pentru integrarea OpenStreetMap

### Backend
- **PHP 8+** - Vanilla PHP, fără framework-uri
- **SQLite** - Baza de date principală (pentru dezvoltare)
- **MySQL** - Opțional pentru producție

### Server
- **XAMPP** - Apache + PHP + MySQL pentru dezvoltare locală

## 🚀 Instalare și Configurare

### Cerințe de Sistem

- PHP 8.0 sau superior
- XAMPP sau LAMP/WAMP
- Browser modern (Chrome, Firefox, Safari, Edge)
- Minim 2GB RAM
- 500MB spațiu disponibil

### Pași de Instalare

1. **Clonează repository-ul**
   ```bash
   git clone <repository-url>
   cd REM2
   ```

2. **Configurează XAMPP**
   - Pornește Apache și MySQL din XAMPP Control Panel
   - Copiază proiectul în directorul `htdocs` al XAMPP

3. **Configurează baza de date**
   ```bash
   # Navighează la http://localhost/phpmyadmin
   # Creează o bază de date nouă numită 'rems'
   # Importă schema din database/schema.sql
   ```

4. **Configurează aplicația**
   ```bash
   # Copiază fișierul de configurare
   cp api/config/database.example.php api/config/database.php
   # Editează setările de conexiune la baza de date
   ```

5. **Accesează aplicația**
   ```
   http://localhost/REM2/
   ```

## 📁 Structura Proiectului

```
REM2/
├── index.html                 # Pagina principală
├── css/                       # Stiluri CSS
│   ├── style.css             # Stiluri principale
│   ├── responsive.css        # Media queries
│   └── components.css        # Componente reutilizabile
├── js/                       # JavaScript
│   ├── main.js              # Script principal
│   ├── map.js               # Funcționalități hartă
│   └── components/          # Componente modulare
├── pages/                    # Pagini aplicație
│   ├── properties.html      # Listare proprietăți
│   ├── map.html            # Hartă interactivă
│   ├── login.html          # Autentificare
│   └── dashboard.html      # Dashboard utilizator
├── api/                     # Backend PHP
│   ├── index.php           # Router principal
│   ├── routes/             # Endpoint-uri API
│   ├── models/             # Modele de date
│   ├── config/             # Configurări
│   └── utils/              # Utilitare
├── admin/                   # Panel administrare
├── assets/                  # Resurse statice
├── database/               # Schema și migrări
├── docs/                   # Documentație
└── tests/                  # Teste automatizate
```

## 🎨 Design System

### Culori Principale
- **Primary**: #2563eb (Blue)
- **Secondary**: #0f172a (Dark Blue)
- **Accent**: #06b6d4 (Cyan)
- **Success**: #10b981 (Green)
- **Warning**: #f59e0b (Orange)
- **Error**: #ef4444 (Red)

### Tipografie
- **Headings**: Georgia, serif
- **Body**: Segoe UI, sans-serif
- **Responsive**: 16px base, scalable

### Spacing System
- Base unit: 0.25rem (4px)
- Scale: 4px, 8px, 12px, 16px, 20px, 24px, 32px, 40px, 48px, 64px

## 🔧 Dezvoltare

### Cerințe pentru Dezvoltatori

1. **Respectă standardele de cod**
   - HTML5 semantic valid
   - CSS3 valid (W3C)
   - JavaScript ES6+
   - PHP 8+ cu type hints

2. **Securitate**
   - Prepared statements pentru SQL
   - Sanitizare input-uri
   - Validare server-side
   - CSRF protection
   - XSS prevention

3. **Performanță**
   - Imagini optimizate
   - CSS/JS minificat în producție
   - Cache headers corecte
   - Lazy loading

### Scripts Disponibile

```bash
# Validare HTML
npm run validate:html

# Validare CSS
npm run validate:css

# Lint JavaScript
npm run lint:js

# Teste backend
php tests/run_tests.php

# Build pentru producție
npm run build
```

## 📊 Plan de Implementare

Proiectul este dezvoltat în 12 etape principale:

1. **Etapa 1**: Setup inițial + Frontend base ✅
2. **Etapa 2**: Backend core + Database
3. **Etapa 3**: Frontend hartă + OpenStreetMap
4. **Etapa 4**: Backend API Properties + CRUD
5. **Etapa 5**: Frontend listare și filtrare
6. **Etapa 6**: Backend autentificare
7. **Etapa 7**: Frontend auth + dashboard
8. **Etapa 8**: Backend straturi + APIs externe
9. **Etapa 9**: Frontend straturi + geolocation
10. **Etapa 10**: Backend securitate + import/export
11. **Etapa 11**: Frontend admin panel
12. **Etapa 12**: Testing + optimizare + deploy

## 📝 Licență

Acest proiect este dezvoltat sub licență MIT. Toate dependențele folosite sunt open source.

## 👥 Contribuții

Contribuțiile sunt binevenite! Te rugăm să:

1. Fork-uiești repository-ul
2. Creezi o branch pentru feature (`git checkout -b feature/AmazingFeature`)
3. Commit-uiești schimbările (`git commit -m 'Add some AmazingFeature'`)
4. Push-uiești branch-ul (`git push origin feature/AmazingFeature`)
5. Deschizi un Pull Request

## 🐛 Raportare Bug-uri

Pentru raportarea bug-urilor, te rugăm să deschizi un issue cu:
- Descrierea detaliată a problemei
- Pașii pentru reproducere
- Screenshots (dacă este cazul)
- Informații despre browser/OS

## 📞 Contact

Pentru întrebări sau suport, contactează-ne la:
- Email: support@rems.ro
- GitHub Issues: [Issues](https://github.com/username/REM2/issues)

---

**Dezvoltat cu ❤️ folosind tehnologii web moderne și open source.** 