// Initialize map
const map = L.map('map').setView([44.4268, 26.1025], 13); // Default to Bucharest
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Modal handling
const modal = document.getElementById('addPropertyModal');
const addBtn = document.getElementById('addPropertyBtn');
const closeBtn = document.getElementsByClassName('close')[0];

addBtn.onclick = () => modal.style.display = "block";
closeBtn.onclick = () => modal.style.display = "none";
window.onclick = (event) => {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// Property markers
let markers = [];

// Load properties
async function loadProperties() {
    try {
        const response = await fetch('/api/properties');
        const properties = await response.json();
        
        // Clear existing markers
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];
        
        // Clear property list
        const propertyList = document.getElementById('properties');
        propertyList.innerHTML = '';
        
        properties.forEach(property => {
            // Add marker to map
            const marker = L.marker([property.latitude, property.longitude])
                .bindPopup(`
                    <strong>${property.title}</strong><br>
                    ${property.description}<br>
                    Price: ${property.price}<br>
                    Type: ${property.type}<br>
                    Contact: ${property.contact_info}
                `);
            markers.push(marker);
            marker.addTo(map);
            
            // Add to property list
            const propertyCard = document.createElement('div');
            propertyCard.className = 'property-card';
            propertyCard.innerHTML = `
                <h3>${property.title}</h3>
                <p>${property.description}</p>
                <p>Price: ${property.price}</p>
                <p>Type: ${property.type}</p>
                <p>Contact: ${property.contact_info}</p>
            `;
            propertyList.appendChild(propertyCard);
        });
    } catch (error) {
        console.error('Error loading properties:', error);
    }
}

// Handle form submission
document.getElementById('propertyForm').onsubmit = async (e) => {
    e.preventDefault();
    
    // Get current map center for coordinates
    const center = map.getCenter();
    
    const property = {
        title: document.getElementById('title').value,
        description: document.getElementById('description').value,
        price: document.getElementById('price').value,
        type: document.getElementById('type').value,
        latitude: center.lat,
        longitude: center.lng,
        contact_info: document.getElementById('contact').value
    };
    
    try {
        const response = await fetch('/api/properties', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(property)
        });
        
        if (response.ok) {
            modal.style.display = "none";
            document.getElementById('propertyForm').reset();
            loadProperties();
        }
    } catch (error) {
        console.error('Error adding property:', error);
    }
};

// Initial load
loadProperties(); 