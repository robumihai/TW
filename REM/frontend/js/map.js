// Map variables
let map;
let markers = [];
let layerGroups = {
    pollution: null,
    traffic: null,
    crime: null,
    transport: null
};

// Initialize map
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
    setupLayerControls();
});

function initializeMap() {
    // Default center (Bucharest, Romania)
    const defaultCenter = [44.4268, 26.1025];
    
    // Initialize map
    map = L.map('map').setView(defaultCenter, 11);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Initialize layer groups
    layerGroups.pollution = L.layerGroup();
    layerGroups.traffic = L.layerGroup();
    layerGroups.crime = L.layerGroup();
    layerGroups.transport = L.layerGroup();
    
    // Add some sample overlay data
    addSampleOverlayData();
    
    // Set up click event for map
    map.on('click', function(e) {
        console.log('Map clicked at:', e.latlng);
    });
}

function setupLayerControls() {
    // Pollution layer control
    document.getElementById('layer-pollution').addEventListener('change', function() {
        if (this.checked) {
            map.addLayer(layerGroups.pollution);
        } else {
            map.removeLayer(layerGroups.pollution);
        }
    });
    
    // Traffic layer control
    document.getElementById('layer-traffic').addEventListener('change', function() {
        if (this.checked) {
            map.addLayer(layerGroups.traffic);
        } else {
            map.removeLayer(layerGroups.traffic);
        }
    });
    
    // Crime layer control
    document.getElementById('layer-crime').addEventListener('change', function() {
        if (this.checked) {
            map.addLayer(layerGroups.crime);
        } else {
            map.removeLayer(layerGroups.crime);
        }
    });
    
    // Transport layer control
    document.getElementById('layer-transport').addEventListener('change', function() {
        if (this.checked) {
            map.addLayer(layerGroups.transport);
        } else {
            map.removeLayer(layerGroups.transport);
        }
    });
}

function addSampleOverlayData() {
    // Sample pollution data (red circles for high pollution areas)
    const pollutionData = [
        {lat: 44.4268, lng: 26.1025, level: 'high', description: 'Industrial area - High pollution'},
        {lat: 44.4000, lng: 26.0800, level: 'medium', description: 'City center - Medium pollution'},
        {lat: 44.4500, lng: 26.1200, level: 'low', description: 'Residential area - Low pollution'}
    ];
    
    pollutionData.forEach(function(point) {
        const color = point.level === 'high' ? 'red' : point.level === 'medium' ? 'orange' : 'yellow';
        const circle = L.circle([point.lat, point.lng], {
            color: color,
            fillColor: color,
            fillOpacity: 0.3,
            radius: 1000
        }).bindPopup(point.description);
        
        layerGroups.pollution.addLayer(circle);
    });
    
    // Sample traffic data (lines for busy roads)
    const trafficData = [
        {coords: [[44.4268, 26.1025], [44.4300, 26.1100]], level: 'heavy', description: 'Heavy traffic'},
        {coords: [[44.4200, 26.0900], [44.4250, 26.0950]], level: 'moderate', description: 'Moderate traffic'}
    ];
    
    trafficData.forEach(function(road) {
        const color = road.level === 'heavy' ? 'red' : 'orange';
        const polyline = L.polyline(road.coords, {
            color: color,
            weight: 6,
            opacity: 0.7
        }).bindPopup(road.description);
        
        layerGroups.traffic.addLayer(polyline);
    });
    
    // Sample crime data (markers for crime reports)
    const crimeData = [
        {lat: 44.4100, lng: 26.0900, type: 'theft', description: 'Theft reported'},
        {lat: 44.4300, lng: 26.1100, type: 'vandalism', description: 'Vandalism reported'}
    ];
    
    crimeData.forEach(function(crime) {
        const marker = L.marker([crime.lat, crime.lng], {
            icon: L.divIcon({
                className: 'crime-marker',
                html: '⚠️',
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            })
        }).bindPopup(crime.description);
        
        layerGroups.crime.addLayer(marker);
    });
    
    // Sample transport data (bus stops, metro stations)
    const transportData = [
        {lat: 44.4268, lng: 26.1025, type: 'metro', description: 'Metro Station'},
        {lat: 44.4200, lng: 26.0950, type: 'bus', description: 'Bus Stop'},
        {lat: 44.4350, lng: 26.1150, type: 'bus', description: 'Bus Stop'}
    ];
    
    transportData.forEach(function(transport) {
        const icon = transport.type === 'metro' ? '🚇' : '🚌';
        const marker = L.marker([transport.lat, transport.lng], {
            icon: L.divIcon({
                className: 'transport-marker',
                html: icon,
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            })
        }).bindPopup(transport.description);
        
        layerGroups.transport.addLayer(marker);
    });
}

// Update map markers with properties
function updateMapMarkers(properties) {
    // Clear existing markers
    markers.forEach(function(marker) {
        map.removeLayer(marker);
    });
    markers = [];
    
    if (!properties || properties.length === 0) {
        return;
    }
    
    // Add new markers
    properties.forEach(function(property) {
        if (property.latitude && property.longitude) {
            const marker = L.marker([property.latitude, property.longitude], {
                icon: L.divIcon({
                    className: 'property-marker',
                    html: `<div style="background: #667eea; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px;">€${formatPrice(property.price)}</div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            });
            
            // Create popup content
            const popupContent = `
                <div style="min-width: 200px;">
                    <h3>${escapeHtml(property.title)}</h3>
                    <p><strong>Price:</strong> €${formatNumber(property.price)}</p>
                    <p><strong>Area:</strong> ${property.area}m²</p>
                    <p><strong>Type:</strong> ${property.type}</p>
                    <p><strong>Transaction:</strong> ${property.transaction_type === 'sale' ? 'For Sale' : 'For Rent'}</p>
                    <p><strong>Address:</strong> ${escapeHtml(property.address)}</p>
                    <button onclick="showPropertyDetails(${property.id})" style="margin-top: 10px; padding: 5px 10px; background: #667eea; color: white; border: none; border-radius: 3px; cursor: pointer;">View Details</button>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            marker.addTo(map);
            markers.push(marker);
        }
    });
    
    // Fit map to show all markers if there are any
    if (markers.length > 0) {
        const group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds(), {padding: [20, 20]});
    }
}

// Format price for marker display
function formatPrice(price) {
    if (price >= 1000000) {
        return Math.round(price / 1000000) + 'M';
    } else if (price >= 1000) {
        return Math.round(price / 1000) + 'K';
    }
    return price.toString();
}

// Get properties in map bounds
function getPropertiesInBounds() {
    if (!map) return [];
    
    const bounds = map.getBounds();
    return properties.filter(function(property) {
        if (!property.latitude || !property.longitude) return false;
        return bounds.contains([property.latitude, property.longitude]);
    });
}

// Center map on user location
function centerMapOnUser() {
    if (currentUserLocation) {
        map.setView([currentUserLocation.lat, currentUserLocation.lng], 13);
        
        // Add user location marker
        const userMarker = L.marker([currentUserLocation.lat, currentUserLocation.lng], {
            icon: L.divIcon({
                className: 'user-marker',
                html: '📍',
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            })
        }).bindPopup('Your Location');
        
        userMarker.addTo(map);
    }
}

// Add click event to map for area selection
function enableAreaSelection() {
    let rectangle;
    
    map.on('mousedown', function(e) {
        if (rectangle) {
            map.removeLayer(rectangle);
        }
        
        const startLatLng = e.latlng;
        
        function onMouseMove(e) {
            if (rectangle) {
                map.removeLayer(rectangle);
            }
            
            rectangle = L.rectangle([startLatLng, e.latlng], {
                color: '#667eea',
                weight: 2,
                fillOpacity: 0.1
            }).addTo(map);
        }
        
        function onMouseUp() {
            map.off('mousemove', onMouseMove);
            map.off('mouseup', onMouseUp);
            
            if (rectangle) {
                const bounds = rectangle.getBounds();
                const propertiesInArea = properties.filter(function(property) {
                    if (!property.latitude || !property.longitude) return false;
                    return bounds.contains([property.latitude, property.longitude]);
                });
                
                showNotification(`Found ${propertiesInArea.length} properties in selected area`, 'info');
                
                // Update display with properties in selected area
                filteredProperties = propertiesInArea;
                displayPropertiesList(propertiesInArea);
                updateMapMarkers(propertiesInArea);
            }
        }
        
        map.on('mousemove', onMouseMove);
        map.on('mouseup', onMouseUp);
    });
}

// Expose functions to global scope
window.map = map;
window.updateMapMarkers = updateMapMarkers;
window.centerMapOnUser = centerMapOnUser;
window.enableAreaSelection = enableAreaSelection; 