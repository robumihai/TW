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

// Layer toggle event listeners
document.querySelectorAll('.layer-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const layerName = this.id.split('-')[1];
        if (this.checked) {
            map.addLayer(layers[layerName]);
        } else {
            map.removeLayer(layers[layerName]);
        }
    });
});

// Initialize all map layers
initializeLayers();

// Property markers group
const markers = L.layerGroup().addTo(map);

// Load properties from API
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
    // Clear existing markers and list
    markers.clearLayers();
    document.getElementById('properties').innerHTML = '';
    
    // Add property markers to map and list
    properties.forEach(property => {
        // Add marker to map
        const marker = L.marker([property.latitude, property.longitude])
            .bindPopup(`
                <h3>${property.title}</h3>
                <p><strong>Price:</strong> $${property.price.toLocaleString()}</p>
                <p><strong>Type:</strong> ${property.type === 'sale' ? 'For Sale' : 'For Rent'}</p>
                <p><strong>Area:</strong> ${property.area} m²</p>
                <a href="#" class="view-property" data-id="${property.id}">View Details</a>
            `);
        
        markers.addLayer(marker);
        
        // Add property to list
        const propertyEl = document.createElement('div');
        propertyEl.className = 'property-card';
        propertyEl.dataset.id = property.id;
        
        // Create property card HTML
        propertyEl.innerHTML = `
            <h3>${property.title}</h3>
            <p><strong>Price:</strong> $${property.price.toLocaleString()}</p>
            <p><strong>Type:</strong> ${property.type === 'sale' ? 'For Sale' : 'For Rent'}</p>
            <p><strong>Property Type:</strong> ${property.property_type}</p>
            <p><strong>Area:</strong> ${property.area} m²</p>
            <div class="property-actions">
                <button class="view-property" data-id="${property.id}">View Details</button>
                ${canEditProperty(property.owner_id) ? 
                  `<button class="delete-property" data-id="${property.id}">Delete</button>` : ''}
            </div>
        `;
        
        document.getElementById('properties').appendChild(propertyEl);
    });
    
    // Add event listeners to view and delete buttons
    document.querySelectorAll('.view-property').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            // In a real app, this would show a modal with property details
            alert(`View property ${id}`);
        });
    });
    
    document.querySelectorAll('.delete-property').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            deleteProperty(id);
        });
    });
}

// Delete property
async function deleteProperty(id) {
    if (!isLoggedIn()) {
        alert('You must be logged in to delete properties');
        window.location.href = '/login';
        return;
    }
    
    if (confirm('Are you sure you want to delete this property?')) {
        try {
            const response = await authenticatedFetch(`/api/properties/${id}`, {
                method: 'DELETE'
            });
            
            if (response && response.ok) {
                alert('Property deleted successfully');
                loadProperties(); // Reload the properties
            } else if (response) {
                const data = await response.json();
                alert('Error: ' + (data.error || 'Failed to delete property'));
            }
        } catch (error) {
            console.error('Error deleting property:', error);
            alert('An error occurred while deleting the property');
        }
    }
}

// Handle add property form
const propertyForm = document.getElementById('propertyForm');
const addPropertyModal = document.getElementById('addPropertyModal');
const modalMap = L.map('form-map').setView([44.4268, 26.1025], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(modalMap);

// Show modal when add property button is clicked
document.getElementById('addPropertyBtn').addEventListener('click', function() {
    if (!isLoggedIn()) {
        alert('You must be logged in to add properties');
        window.location.href = '/login';
        return;
    }
    
    addPropertyModal.style.display = 'block';
    setTimeout(() => {
        modalMap.invalidateSize();
    }, 100);
});

// Close modal when X is clicked
document.querySelector('.close').addEventListener('click', function() {
    addPropertyModal.style.display = 'none';
});

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === addPropertyModal) {
        addPropertyModal.style.display = 'none';
    }
});

// Allow setting location by clicking on map
let marker;
modalMap.on('click', function(e) {
    document.getElementById('latitude').value = e.latlng.lat;
    document.getElementById('longitude').value = e.latlng.lng;
    
    if (marker) {
        modalMap.removeLayer(marker);
    }
    
    marker = L.marker(e.latlng).addTo(modalMap);
});

// Submit form
propertyForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!isLoggedIn()) {
        alert('You must be logged in to add properties');
        window.location.href = '/login';
        return;
    }
    
    const formData = {
        title: document.getElementById('title').value,
        description: document.getElementById('description').value,
        price: parseFloat(document.getElementById('price').value),
        type: document.getElementById('type').value,
        property_type: document.getElementById('property_type').value,
        area: parseFloat(document.getElementById('area').value),
        building_condition: document.getElementById('building_condition').value,
        facilities: document.getElementById('facilities').value,
        risks: document.getElementById('risks').value,
        latitude: parseFloat(document.getElementById('latitude').value),
        longitude: parseFloat(document.getElementById('longitude').value),
        contact_info: document.getElementById('contact').value
    };
    
    try {
        const response = await authenticatedFetch('/api/properties', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        if (response && response.ok) {
            alert('Property added successfully');
            propertyForm.reset();
            addPropertyModal.style.display = 'none';
            loadProperties(); // Reload properties
        } else if (response) {
            const data = await response.json();
            alert('Error: ' + (data.error || 'Failed to add property'));
        }
    } catch (error) {
        console.error('Error adding property:', error);
        alert('An error occurred while adding the property');
    }
});

// Filter properties
const filterForm = document.getElementById('filterForm');
const filterModal = document.getElementById('filterModal');

// Show filter modal when filter button is clicked
document.getElementById('filterBtn').addEventListener('click', function() {
    filterModal.style.display = 'block';
});

// Close filter modal
document.querySelector('.filter-close').addEventListener('click', function() {
    filterModal.style.display = 'none';
});

// Close filter modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === filterModal) {
        filterModal.style.display = 'none';
    }
});

// Submit filter form
filterForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const params = new URLSearchParams();
    
    const type = document.getElementById('filter-type').value;
    if (type) params.append('type', type);
    
    const propertyType = document.getElementById('filter-property-type').value;
    if (propertyType) params.append('property_type', propertyType);
    
    const minPrice = document.getElementById('filter-min-price').value;
    if (minPrice) params.append('min_price', minPrice);
    
    const maxPrice = document.getElementById('filter-max-price').value;
    if (maxPrice) params.append('max_price', maxPrice);
    
    const minArea = document.getElementById('filter-min-area').value;
    if (minArea) params.append('min_area', minArea);
    
    const maxArea = document.getElementById('filter-max-area').value;
    if (maxArea) params.append('max_area', maxArea);
    
    const facilities = document.getElementById('filter-facilities').value;
    if (facilities) params.append('facilities', facilities);
    
    try {
        const response = await fetch(`/api/properties/filter?${params.toString()}`);
        const properties = await response.json();
        
        displayProperties(properties);
        filterModal.style.display = 'none';
    } catch (error) {
        console.error('Error filtering properties:', error);
    }
});

// Reset filters
document.getElementById('resetFilters').addEventListener('click', function() {
    filterForm.reset();
    loadProperties();
    filterModal.style.display = 'none';
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

// Update UI based on authentication
document.addEventListener('DOMContentLoaded', function() {
    // Update UI elements based on authentication
    updateAuthUI();
    
    // Initial load
    loadProperties();
});
