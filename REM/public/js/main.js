// Initialize map
const map = L.map('map').setView([44.4268, 26.1025], 13); // Default to Bucharest
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Map layers
const layers = {
    pollution: L.layerGroup(),
    crime: L.layerGroup(),
    temperature: L.layerGroup(),
    parking: L.layerGroup(),
    shops: L.layerGroup(),
    crowd: L.layerGroup()
};

// Sample data for layers (in a real app, this would come from an API)
const sampleLayerData = {
    pollution: [
        { lat: 44.4268, lng: 26.1025, level: 'High', color: '#ff0000' },
        { lat: 44.4368, lng: 26.1125, level: 'Medium', color: '#ffaa00' },
        { lat: 44.4168, lng: 26.0925, level: 'Low', color: '#00ff00' }
    ],
    crime: [
        { lat: 44.4268, lng: 26.0925, count: 12, color: '#ff0000' },
        { lat: 44.4368, lng: 26.1225, count: 5, color: '#ffaa00' },
        { lat: 44.4168, lng: 26.1125, count: 2, color: '#00ff00' }
    ],
    temperature: [
        { lat: 44.4268, lng: 26.1025, temp: 25, color: '#ff0000' },
        { lat: 44.4368, lng: 26.1125, temp: 22, color: '#ffaa00' },
        { lat: 44.4168, lng: 26.0925, temp: 20, color: '#00ff00' }
    ],
    parking: [
        { lat: 44.4268, lng: 26.0925, name: 'Central Parking', spots: 100 },
        { lat: 44.4368, lng: 26.1225, name: 'Mall Parking', spots: 200 },
        { lat: 44.4168, lng: 26.1125, name: 'Street Parking', spots: 50 }
    ],
    shops: [
        { lat: 44.4268, lng: 26.1025, name: 'Central Mall', type: 'Shopping Center' },
        { lat: 44.4368, lng: 26.1125, name: 'Grocery Store', type: 'Supermarket' },
        { lat: 44.4168, lng: 26.0925, name: 'Local Market', type: 'Market' }
    ],
    crowd: [
        { lat: 44.4268, lng: 26.0925, level: 'High', color: '#ff0000' },
        { lat: 44.4368, lng: 26.1225, level: 'Medium', color: '#ffaa00' },
        { lat: 44.4168, lng: 26.1125, level: 'Low', color: '#00ff00' }
    ]
};

// Initialize layer data
function initializeLayers() {
    // Pollution layer
    sampleLayerData.pollution.forEach(item => {
        L.circle([item.lat, item.lng], {
            color: item.color,
            fillColor: item.color,
            fillOpacity: 0.5,
            radius: 300
        }).bindPopup(`Pollution Level: ${item.level}`).addTo(layers.pollution);
    });

    // Crime layer
    sampleLayerData.crime.forEach(item => {
        L.circle([item.lat, item.lng], {
            color: item.color,
            fillColor: item.color,
            fillOpacity: 0.5,
            radius: 300
        }).bindPopup(`Crime Reports: ${item.count}`).addTo(layers.crime);
    });

    // Temperature layer
    sampleLayerData.temperature.forEach(item => {
        L.circle([item.lat, item.lng], {
            color: item.color,
            fillColor: item.color,
            fillOpacity: 0.5,
            radius: 300
        }).bindPopup(`Average Temperature: ${item.temp}°C`).addTo(layers.temperature);
    });

    // Parking layer
    sampleLayerData.parking.forEach(item => {
        L.marker([item.lat, item.lng], {
            icon: L.divIcon({
                className: 'parking-icon',
                html: '<div style="background-color:#4285F4;color:white;padding:5px;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;">P</div>'
            })
        }).bindPopup(`Parking: ${item.name}<br>Available spots: ${item.spots}`).addTo(layers.parking);
    });

    // Shops layer
    sampleLayerData.shops.forEach(item => {
        L.marker([item.lat, item.lng], {
            icon: L.divIcon({
                className: 'shop-icon',
                html: '<div style="background-color:#DB4437;color:white;padding:5px;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;">S</div>'
            })
        }).bindPopup(`${item.name}<br>Type: ${item.type}`).addTo(layers.shops);
    });

    // Crowd layer
    sampleLayerData.crowd.forEach(item => {
        L.circle([item.lat, item.lng], {
            color: item.color,
            fillColor: item.color,
            fillOpacity: 0.5,
            radius: 300
        }).bindPopup(`Crowding Level: ${item.level}`).addTo(layers.crowd);
    });
}

initializeLayers();

// Layer checkbox handlers
document.getElementById('layer-pollution').addEventListener('change', function() {
    toggleLayer('pollution', this.checked);
});

document.getElementById('layer-crime').addEventListener('change', function() {
    toggleLayer('crime', this.checked);
});

document.getElementById('layer-temperature').addEventListener('change', function() {
    toggleLayer('temperature', this.checked);
});

document.getElementById('layer-parking').addEventListener('change', function() {
    toggleLayer('parking', this.checked);
});

document.getElementById('layer-shops').addEventListener('change', function() {
    toggleLayer('shops', this.checked);
});

document.getElementById('layer-crowd').addEventListener('change', function() {
    toggleLayer('crowd', this.checked);
});

function toggleLayer(layerName, isVisible) {
    if (isVisible) {
        map.addLayer(layers[layerName]);
    } else {
        map.removeLayer(layers[layerName]);
    }
}

// Modal handling
const addPropertyModal = document.getElementById('addPropertyModal');
const filterModal = document.getElementById('filterModal');
const addBtn = document.getElementById('addPropertyBtn');
const filterBtn = document.getElementById('filterBtn');
const closeBtns = document.getElementsByClassName('close');

addBtn.onclick = () => {
    addPropertyModal.style.display = "block";
    initFormMap();
};

filterBtn.onclick = () => {
    filterModal.style.display = "block";
};

for (let i = 0; i < closeBtns.length; i++) {
    closeBtns[i].onclick = function() {
        addPropertyModal.style.display = "none";
        filterModal.style.display = "none";
    };
}

window.onclick = (event) => {
    if (event.target == addPropertyModal) {
        addPropertyModal.style.display = "none";
    } else if (event.target == filterModal) {
        filterModal.style.display = "none";
    }
};

// Form map for selecting location
let formMap;
let formMarker;

function initFormMap() {
    if (formMap) {
        formMap.remove();
    }
    
    formMap = L.map('form-map').setView([44.4268, 26.1025], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(formMap);
    
    formMap.on('click', function(e) {
        setFormLocation(e.latlng.lat, e.latlng.lng);
    });
    
    // Set initial marker if coordinates are already set
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    if (lat && lng) {
        setFormLocation(lat, lng);
    }
}

function setFormLocation(lat, lng) {
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    
    if (formMarker) {
        formMap.removeLayer(formMarker);
    }
    
    formMarker = L.marker([lat, lng]).addTo(formMap);
}

// Property markers
let markers = [];

// Load properties
async function loadProperties() {
    try {
        const response = await fetch('/api/properties');
        const properties = await response.json();
        
        displayProperties(properties);
    } catch (error) {
        console.error('Error loading properties:', error);
    }
}

// Display properties on map and in list
function displayProperties(properties) {
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
                Property Type: ${property.property_type || 'N/A'}<br>
                Area: ${property.area || 'N/A'} m²<br>
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
            <p><strong>Price:</strong> ${property.price}</p>
            <p><strong>Type:</strong> ${property.type}</p>
            <p><strong>Property Type:</strong> ${property.property_type || 'N/A'}</p>
            <p><strong>Area:</strong> ${property.area || 'N/A'} m²</p>
            ${property.building_condition ? `<p><strong>Building Condition:</strong> ${property.building_condition}</p>` : ''}
            ${property.facilities ? `<p><strong>Facilities:</strong> ${property.facilities}</p>` : ''}
            ${property.risks ? `<p><strong>Risks:</strong> ${property.risks}</p>` : ''}
            <p><strong>Contact:</strong> ${property.contact_info}</p>
            <div class="property-actions">
                <button class="delete-property" data-id="${property.id}">Delete</button>
            </div>
        `;
        propertyList.appendChild(propertyCard);
    });
    
    // Add event listeners to delete buttons
    document.querySelectorAll('.delete-property').forEach(button => {
        button.addEventListener('click', async function() {
            const id = this.getAttribute('data-id');
            await deleteProperty(id);
        });
    });
}

// Delete property
async function deleteProperty(id) {
    try {
        const response = await fetch(`/api/properties/${id}`, {
            method: 'DELETE'
        });
        
        if (response.ok) {
            loadProperties();
        }
    } catch (error) {
        console.error('Error deleting property:', error);
    }
}

// Handle form submission
document.getElementById('propertyForm').onsubmit = async (e) => {
    e.preventDefault();
    
    const property = {
        title: document.getElementById('title').value,
        description: document.getElementById('description').value,
        price: document.getElementById('price').value,
        type: document.getElementById('type').value,
        property_type: document.getElementById('property_type').value,
        area: document.getElementById('area').value,
        building_condition: document.getElementById('building_condition').value,
        facilities: document.getElementById('facilities').value,
        risks: document.getElementById('risks').value,
        latitude: document.getElementById('latitude').value,
        longitude: document.getElementById('longitude').value,
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
            addPropertyModal.style.display = "none";
            document.getElementById('propertyForm').reset();
            loadProperties();
        }
    } catch (error) {
        console.error('Error adding property:', error);
    }
};

// Handle filter form submission
document.getElementById('filterForm').onsubmit = async (e) => {
    e.preventDefault();
    
    const type = document.getElementById('filter-type').value;
    const property_type = document.getElementById('filter-property-type').value;
    const min_price = document.getElementById('filter-min-price').value;
    const max_price = document.getElementById('filter-max-price').value;
    const min_area = document.getElementById('filter-min-area').value;
    const max_area = document.getElementById('filter-max-area').value;
    const facilities = document.getElementById('filter-facilities').value;
    
    try {
        let url = '/api/properties/filter?';
        const params = [];
        
        if (type) params.push(`type=${type}`);
        if (property_type) params.push(`property_type=${property_type}`);
        if (min_price) params.push(`min_price=${min_price}`);
        if (max_price) params.push(`max_price=${max_price}`);
        if (min_area) params.push(`min_area=${min_area}`);
        if (max_area) params.push(`max_area=${max_area}`);
        if (facilities) params.push(`facilities=${facilities}`);
        
        url += params.join('&');
        
        const response = await fetch(url);
        const properties = await response.json();
        
        filterModal.style.display = "none";
        displayProperties(properties);
    } catch (error) {
        console.error('Error filtering properties:', error);
    }
};

// Reset filters
document.getElementById('resetFilters').addEventListener('click', function() {
    loadProperties();
    filterModal.style.display = "none";
});

// Export buttons
document.getElementById('exportJsonBtn').addEventListener('click', function() {
    window.open('/api/export/json', '_blank');
});

document.getElementById('exportCsvBtn').addEventListener('click', function() {
    window.open('/api/export/csv', '_blank');
});

// Find nearby properties using Geolocation API
document.getElementById('findNearbyBtn').addEventListener('click', function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(async function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            try {
                const response = await fetch(`/api/properties/nearby?latitude=${lat}&longitude=${lng}`);
                const properties = await response.json();
                
                // Center map on user's location
                map.setView([lat, lng], 14);
                
                // Add a marker for user's location
                L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'user-location',
                        html: '<div style="background-color:#4285F4;color:white;padding:5px;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;">You</div>'
                    })
                }).addTo(map);
                
                displayProperties(properties);
            } catch (error) {
                console.error('Error finding nearby properties:', error);
            }
        }, function(error) {
            console.error('Geolocation error:', error);
            alert('Could not get your location. Please allow location access.');
        });
    } else {
        alert('Geolocation is not supported by your browser');
    }
});

// Initial load
loadProperties(); 