// Application state
let properties = [];
let filteredProperties = [];
let currentUserLocation = null;

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    loadProperties();
    initializeGeolocation();
});

// Show/hide sections
function showSection(section) {
    const mapSection = document.getElementById('map-section');
    const listSection = document.getElementById('list-section');
    
    if (section === 'map') {
        mapSection.style.display = 'block';
        listSection.style.display = 'none';
        // Refresh map if needed
        if (window.map) {
            setTimeout(() => window.map.invalidateSize(), 100);
        }
    } else if (section === 'list') {
        mapSection.style.display = 'none';
        listSection.style.display = 'block';
        displayPropertiesList(filteredProperties.length > 0 ? filteredProperties : properties);
    }
}

// Load properties from API
function loadProperties() {
    makeAjaxRequest('GET', 'http://localhost:8000/api/properties.php', null, function(response) {
        properties = response.data || [];
        filteredProperties = properties;
        displayPropertiesList(properties);
        if (window.updateMapMarkers) {
            window.updateMapMarkers(properties);
        }
    }, function(error) {
        console.error('Error loading properties:', error);
        showNotification('Error loading properties', 'error');
    });
}

// Search properties based on filters
function searchProperties() {
    const filters = {
        propertyType: document.getElementById('property-type').value,
        transactionType: document.getElementById('transaction-type').value,
        minPrice: parseFloat(document.getElementById('min-price').value) || 0,
        maxPrice: parseFloat(document.getElementById('max-price').value) || 999999999,
        minArea: parseFloat(document.getElementById('min-area').value) || 0
    };

    filteredProperties = properties.filter(property => {
        return (
            (!filters.propertyType || property.type === filters.propertyType) &&
            (!filters.transactionType || property.transaction_type === filters.transactionType) &&
            (property.price >= filters.minPrice && property.price <= filters.maxPrice) &&
            (property.area >= filters.minArea)
        );
    });

    displayPropertiesList(filteredProperties);
    if (window.updateMapMarkers) {
        window.updateMapMarkers(filteredProperties);
    }
    
    showNotification(`Found ${filteredProperties.length} properties`, 'success');
}

// Clear all filters
function clearFilters() {
    document.getElementById('property-type').value = '';
    document.getElementById('transaction-type').value = '';
    document.getElementById('min-price').value = '';
    document.getElementById('max-price').value = '';
    document.getElementById('min-area').value = '';
    
    filteredProperties = properties;
    displayPropertiesList(properties);
    if (window.updateMapMarkers) {
        window.updateMapMarkers(properties);
    }
    
    showNotification('Filters cleared', 'info');
}

// Find nearby properties using geolocation
function findNearbyProperties() {
    if (!currentUserLocation) {
        initializeGeolocation();
        return;
    }

    const radius = 5; // 5km radius
    const nearbyProperties = properties.filter(property => {
        const distance = calculateDistance(
            currentUserLocation.lat,
            currentUserLocation.lng,
            property.latitude,
            property.longitude
        );
        return distance <= radius;
    });

    filteredProperties = nearbyProperties;
    displayPropertiesList(nearbyProperties);
    if (window.updateMapMarkers) {
        window.updateMapMarkers(nearbyProperties);
    }
    
    showNotification(`Found ${nearbyProperties.length} properties within ${radius}km`, 'success');
}

// Initialize geolocation
function initializeGeolocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                currentUserLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                showNotification('Location detected', 'success');
            },
            function(error) {
                console.error('Geolocation error:', error);
                showNotification('Could not get your location', 'warning');
            }
        );
    } else {
        showNotification('Geolocation not supported', 'warning');
    }
}

// Calculate distance between two points (Haversine formula)
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radius of the Earth in kilometers
    const dLat = deg2rad(lat2 - lat1);
    const dLon = deg2rad(lon2 - lon1);
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    const d = R * c; // Distance in kilometers
    return d;
}

function deg2rad(deg) {
    return deg * (Math.PI/180);
}

// Display properties in list view
function displayPropertiesList(propertiesToShow) {
    const listContainer = document.getElementById('properties-list');
    
    if (!propertiesToShow || propertiesToShow.length === 0) {
        listContainer.innerHTML = '<div class="loading">No properties found</div>';
        return;
    }

    listContainer.innerHTML = propertiesToShow.map(property => `
        <div class="property-card" onclick="showPropertyDetails(${property.id})">
            <div class="property-image">
                ${property.images ? `<img src="${property.images}" alt="${property.title}" style="width:100%;height:100%;object-fit:cover;">` : 'No Image'}
            </div>
            <div class="property-details">
                <div class="property-title">${escapeHtml(property.title)}</div>
                <div class="property-price">€${formatNumber(property.price)}</div>
                <div class="property-location">${escapeHtml(property.address)}</div>
                <div class="property-features">
                    <span class="feature-tag">${property.area}m²</span>
                    <span class="feature-tag">${property.type}</span>
                    <span class="feature-tag">${property.transaction_type === 'sale' ? 'For Sale' : 'For Rent'}</span>
                    ${property.rooms ? `<span class="feature-tag">${property.rooms} rooms</span>` : ''}
                </div>
            </div>
        </div>
    `).join('');
}

// Show property details modal
function showPropertyDetails(propertyId) {
    const property = properties.find(p => p.id === propertyId);
    if (!property) return;

    const modalBody = document.getElementById('modal-body');
    modalBody.innerHTML = `
        <h2>${escapeHtml(property.title)}</h2>
        <div class="property-price" style="font-size: 2rem; margin: 1rem 0;">€${formatNumber(property.price)}</div>
        <div style="margin: 1rem 0;"><strong>Address:</strong> ${escapeHtml(property.address)}</div>
        <div style="margin: 1rem 0;"><strong>Type:</strong> ${escapeHtml(property.type)}</div>
        <div style="margin: 1rem 0;"><strong>Transaction:</strong> ${property.transaction_type === 'sale' ? 'For Sale' : 'For Rent'}</div>
        <div style="margin: 1rem 0;"><strong>Area:</strong> ${property.area}m²</div>
        ${property.rooms ? `<div style="margin: 1rem 0;"><strong>Rooms:</strong> ${property.rooms}</div>` : ''}
        ${property.description ? `<div style="margin: 1rem 0;"><strong>Description:</strong><br>${escapeHtml(property.description)}</div>` : ''}
        ${property.facilities ? `<div style="margin: 1rem 0;"><strong>Facilities:</strong><br>${escapeHtml(property.facilities)}</div>` : ''}
        ${property.contact_info ? `<div style="margin: 1rem 0;"><strong>Contact:</strong><br>${escapeHtml(property.contact_info)}</div>` : ''}
        ${property.building_condition ? `<div style="margin: 1rem 0;"><strong>Condition:</strong> ${escapeHtml(property.building_condition)}</div>` : ''}
        ${property.risks ? `<div style="margin: 1rem 0;"><strong>Risks:</strong><br>${escapeHtml(property.risks)}</div>` : ''}
    `;
    
    document.getElementById('property-modal').style.display = 'block';
}

// Close modal
function closeModal() {
    document.getElementById('property-modal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('property-modal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Export data functionality
function exportData(format) {
    const url = `http://localhost:8000/api/export.php?format=${format}`;
    makeAjaxRequest('GET', url, null, function(response) {
        if (format === 'json') {
            downloadFile(JSON.stringify(response.data, null, 2), 'properties.json', 'application/json');
        } else if (format === 'csv') {
            downloadFile(response.data, 'properties.csv', 'text/csv');
        }
        showNotification(`Data exported as ${format.toUpperCase()}`, 'success');
    }, function(error) {
        showNotification('Export failed', 'error');
    });
}

// Download file
function downloadFile(content, filename, contentType) {
    const blob = new Blob([content], { type: contentType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Generic AJAX request function
function makeAjaxRequest(method, url, data, successCallback, errorCallback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200 || xhr.status === 201) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    successCallback(response);
                } catch (e) {
                    errorCallback('Invalid JSON response');
                }
            } else {
                errorCallback('HTTP Error: ' + xhr.status);
            }
        }
    };
    
    if (data) {
        xhr.send(JSON.stringify(data));
    } else {
        xhr.send();
    }
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

function formatNumber(num) {
    return new Intl.NumberFormat('ro-RO').format(num);
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 1001;
        font-weight: 500;
        max-width: 300px;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}

// Function to save property
async function saveProperty(propertyData) {
    try {
        const response = await fetch('http://localhost:8000/api/properties.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(propertyData),
            credentials: 'include'
        });

        const data = await response.json();
        
        if (response.ok || response.status === 201) {
            showNotification('Property saved successfully', 'success');
            document.getElementById('propertyForm').reset();
            await loadProperties();
        } else {
            throw new Error(data.error || 'Failed to save property');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
} 