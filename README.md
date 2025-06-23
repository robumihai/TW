# Real Estate Management (REM)

A web application for managing real estate properties with an interactive map interface.

## Features

- Interactive map using OpenStreetMap
- Add and view properties
- Property details including price, type, and contact information
- Responsive design for all devices

## Prerequisites

- Node.js (v14 or higher)
- npm (Node Package Manager)

## Installation

1. Clone the repository
2. Navigate to the project directory
3. Install dependencies:
```bash
npm install
```

## Running the Application

1. Start the server:
```bash
npm start
```

2. Open your browser and navigate to `http://localhost:3000`

## Project Structure

```
REM/
├── public/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── index.html
├── src/
│   └── app.js
├── database/
│   └── rem.db
├── package.json
└── README.md
```

## API Endpoints

- GET `/api/properties` - Get all properties
- POST `/api/properties` - Add a new property

## Technologies Used

- Backend: Node.js with Express
- Frontend: Vanilla HTML5, CSS3, JavaScript
- Database: SQLite
- Maps: OpenStreetMap with Leaflet.js 