// Direct database import script
const fs = require('fs');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();

// Read the sample properties file
const samplePropertiesPath = path.join(__dirname, 'sample-properties.json');
const properties = JSON.parse(fs.readFileSync(samplePropertiesPath, 'utf8'));

// Connect to database
const db = new sqlite3.Database('./database/rem.db', (err) => {
  if (err) {
    console.error('Error opening database:', err);
    process.exit(1);
  }
  console.log('Connected to SQLite database');
});

// Create tables if they don't exist
db.serialize(() => {
  db.run(`CREATE TABLE IF NOT EXISTS properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    price REAL NOT NULL,
    type TEXT NOT NULL,
    property_type TEXT NOT NULL,
    area REAL NOT NULL,
    building_condition TEXT,
    facilities TEXT,
    risks TEXT,
    latitude REAL NOT NULL,
    longitude REAL NOT NULL,
    contact_info TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )`);
});

// Insert properties
async function importProperties() {
  console.log(`Importing ${properties.length} properties...`);
  
  let successCount = 0;
  let errorCount = 0;
  
  // Use a promise to ensure we wait for all inserts to complete
  const insertPromises = properties.map(property => {
    return new Promise((resolve, reject) => {
      const sql = `INSERT INTO properties (
        title, description, price, type, property_type, area,
        building_condition, facilities, risks, latitude, longitude, contact_info
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;
      
      db.run(sql, [
        property.title, property.description, property.price, property.type, 
        property.property_type, property.area, property.building_condition, 
        property.facilities, property.risks, property.latitude, 
        property.longitude, property.contact_info
      ], function(err) {
        if (err) {
          console.error(`Error inserting property "${property.title}":`, err.message);
          errorCount++;
          resolve(); // Still resolve so Promise.all completes
        } else {
          successCount++;
          resolve();
        }
      });
    });
  });
  
  try {
    await Promise.all(insertPromises);
    console.log('Import completed successfully!');
    console.log(`${successCount} properties imported.`);
    console.log(`${errorCount} errors.`);
  } catch (error) {
    console.error('Error during import:', error);
  } finally {
    // Close the database connection
    db.close();
  }
}

// Run the import
importProperties(); 