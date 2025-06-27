# Real Estate Management System (REM)

## Description
A web application for managing real estate transactions, allowing users to view, search, and manage properties for sale and rent. The system integrates with OpenStreetMap for location services and provides various data layers for informed decision-making.

## Features
- Property management (sale/rent)
- Interactive map integration with OpenStreetMap
- Advanced filtering and search capabilities
- Responsive design for all devices
- Admin panel for property management
- Import/export functionality (CSV, JSON)
- Security measures against SQL injection and XSS
- Geolocation API integration
- Additional data layers (pollution, traffic, crime, etc.)

## Technologies Used
- **Backend**: PHP (no frameworks)
- **Frontend**: HTML5, CSS3, JavaScript (no frameworks)
- **Database**: SQLite
- **Maps**: OpenStreetMap with Leaflet.js
- **Server**: XAMPP

## Requirements
- XAMPP (Apache, PHP, MySQL/MariaDB)
- Modern web browser with JavaScript enabled
- Internet connection for map services

## Installation
1. Clone the repository to your XAMPP htdocs folder
2. Start Apache server in XAMPP
3. Navigate to `http://localhost/REM`
4. The database will be created automatically on first run

## Project Structure
```
REM/
├── index.html              # Main application page
├── admin/                  # Admin panel
├── api/                    # Web services
├── css/                    # Stylesheets
├── js/                     # JavaScript files
├── data/                   # Database and data files
├── assets/                 # Images and media
└── README.md               # This file
```

## API Endpoints
- `GET /api/properties.php` - Get all properties
- `POST /api/properties.php` - Add new property
- `PUT /api/properties.php` - Update property
- `DELETE /api/properties.php` - Delete property
- `GET /api/export.php` - Export data
- `POST /api/import.php` - Import data

## License
This project is licensed under the MIT License - see the LICENSE file for details.

## Contributing
1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request 