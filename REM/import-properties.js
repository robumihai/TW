// Import properties script
const fs = require('fs');
const path = require('path');
const fetch = require('node-fetch');

// Read the sample properties file
const samplePropertiesPath = path.join(__dirname, 'sample-properties.json');
const properties = JSON.parse(fs.readFileSync(samplePropertiesPath, 'utf8'));

// Function to import properties
async function importProperties() {
  try {
    console.log(`Importing ${properties.length} properties...`);
    
    const response = await fetch('http://localhost:3000/api/import/json', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(properties)
    });
    
    const result = await response.json();
    
    if (response.ok) {
      console.log('Import completed successfully!');
      console.log(`${result.success} properties imported.`);
      console.log(`${result.errors} errors.`);
    } else {
      console.error('Import failed:', result.error);
    }
  } catch (error) {
    console.error('Error importing properties:', error.message);
  }
}

// Run the import
importProperties(); 