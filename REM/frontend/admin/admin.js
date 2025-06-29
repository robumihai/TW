// Admin panel functionality
let adminProperties = [];
let currentEditingProperty = null;

// Initialize admin panel
document.addEventListener('DOMContentLoaded', function() {
    loadAdminProperties();
    updateDashboardStats();
});

// Load properties for admin panel
function loadAdminProperties() {
    makeAjaxRequest('GET', 'http://localhost:8000/api/properties.php', null, function(response) {
        adminProperties = response.data || [];
        displayPropertiesTable(adminProperties);
        updateDashboardStats();
    }, function(error) {
        console.error('Error loading properties:', error);
    });
}

// Update dashboard statistics
function updateDashboardStats() {
    const totalProperties = adminProperties.length;
    const saleProperties = adminProperties.filter(p => p.transaction_type === 'sale').length;
    const rentProperties = adminProperties.filter(p => p.transaction_type === 'rent').length;
    const totalValue = adminProperties
        .filter(p => p.transaction_type === 'sale')
        .reduce((sum, p) => sum + p.price, 0);

    document.getElementById('total-properties').textContent = totalProperties;
    document.getElementById('sale-properties').textContent = saleProperties;
    document.getElementById('rent-properties').textContent = rentProperties;
    document.getElementById('total-value').textContent = '€' + formatNumber(totalValue);
}

// Display properties in table
function displayPropertiesTable(properties) {
    const tableBody = document.getElementById('properties-table-body');
    
    if (!properties || properties.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" class="empty-state"><h3>No properties found</h3><p>Add some properties to get started</p></td></tr>';
        return;
    }

    tableBody.innerHTML = properties.map(property => `
        <tr class="fade-in">
            <td>${property.id}</td>
            <td>
                <strong>${escapeHtml(property.title)}</strong>
                ${property.description ? `<br><small style="color: #666;">${escapeHtml(property.description.substring(0, 50))}${property.description.length > 50 ? '...' : ''}</small>` : ''}
            </td>
            <td><span class="type-badge">${property.type}</span></td>
            <td><span class="status-badge status-${property.transaction_type}">${property.transaction_type === 'sale' ? 'For Sale' : 'For Rent'}</span></td>
            <td class="price-cell">€${formatNumber(property.price)}</td>
            <td>${property.area}m²</td>
            <td>
                ${escapeHtml(property.address)}
                ${property.latitude && property.longitude ? `<br><small style="color: #666;">Lat: ${property.latitude}, Lng: ${property.longitude}</small>` : ''}
            </td>
            <td>
                <div class="table-actions">
                    <button class="edit-btn" onclick="editProperty(${property.id})">Edit</button>
                    <button class="delete-btn" onclick="deleteProperty(${property.id})">Delete</button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Search properties in admin table
function searchProperties() {
    const searchTerm = document.getElementById('admin-search').value.toLowerCase();
    
    if (!searchTerm) {
        displayPropertiesTable(adminProperties);
        return;
    }

    const filteredProperties = adminProperties.filter(property => 
        property.title.toLowerCase().includes(searchTerm) ||
        property.address.toLowerCase().includes(searchTerm) ||
        property.type.toLowerCase().includes(searchTerm) ||
        property.transaction_type.toLowerCase().includes(searchTerm) ||
        (property.description && property.description.toLowerCase().includes(searchTerm))
    );

    displayPropertiesTable(filteredProperties);
}

// Show add property form
function showAddPropertyForm() {
    currentEditingProperty = null;
    document.getElementById('form-title').textContent = 'Add Property';
    document.getElementById('property-form').reset();
    document.getElementById('property-id').value = '';
    document.getElementById('property-form-modal').style.display = 'block';
}

// Edit property
function editProperty(propertyId) {
    const property = adminProperties.find(p => p.id === propertyId);
    if (!property) return;

    currentEditingProperty = property;
    document.getElementById('form-title').textContent = 'Edit Property';
    
    // Populate form with property data
    document.getElementById('property-id').value = property.id;
    document.getElementById('property-title').value = property.title || '';
    document.getElementById('property-type').value = property.type || '';
    document.getElementById('property-transaction').value = property.transaction_type || '';
    document.getElementById('property-price').value = property.price || '';
    document.getElementById('property-area').value = property.area || '';
    document.getElementById('property-rooms').value = property.rooms || '';
    document.getElementById('property-address').value = property.address || '';
    document.getElementById('property-latitude').value = property.latitude || '';
    document.getElementById('property-longitude').value = property.longitude || '';
    document.getElementById('property-condition').value = property.building_condition || '';
    document.getElementById('property-description').value = property.description || '';
    document.getElementById('property-facilities').value = property.facilities || '';
    document.getElementById('property-risks').value = property.risks || '';
    document.getElementById('property-contact').value = property.contact_info || '';
    document.getElementById('property-images').value = property.images || '';
    
    document.getElementById('property-form-modal').style.display = 'block';
}

// Submit property form
function submitProperty(event) {
    event.preventDefault();
    
    const formData = {
        title: document.getElementById('property-title').value,
        type: document.getElementById('property-type').value,
        transaction_type: document.getElementById('property-transaction').value,
        price: parseFloat(document.getElementById('property-price').value),
        area: parseFloat(document.getElementById('property-area').value),
        rooms: document.getElementById('property-rooms').value ? parseInt(document.getElementById('property-rooms').value) : null,
        address: document.getElementById('property-address').value,
        latitude: document.getElementById('property-latitude').value ? parseFloat(document.getElementById('property-latitude').value) : null,
        longitude: document.getElementById('property-longitude').value ? parseFloat(document.getElementById('property-longitude').value) : null,
        building_condition: document.getElementById('property-condition').value || null,
        description: document.getElementById('property-description').value || null,
        facilities: document.getElementById('property-facilities').value || null,
        risks: document.getElementById('property-risks').value || null,
        contact_info: document.getElementById('property-contact').value || null,
        images: document.getElementById('property-images').value || null
    };

    const isEditing = currentEditingProperty !== null;
    const method = isEditing ? 'PUT' : 'POST';
    
    if (isEditing) {
        formData.id = currentEditingProperty.id;
    }

    makeAjaxRequest(method, 'http://localhost:8000/api/properties.php', formData, function(response) {
        showAdminNotification(isEditing ? 'Property updated successfully' : 'Property added successfully', 'success');
        closePropertyForm();
        loadAdminProperties(); // Reload the properties
    }, function(error) {
        showAdminNotification('Error saving property: ' + error, 'error');
    });
}

// Delete property
function deleteProperty(propertyId) {
    if (!confirm('Are you sure you want to delete this property? This action cannot be undone.')) {
        return;
    }

    makeAjaxRequest('DELETE', 'http://localhost:8000/api/properties.php', { id: propertyId }, function(response) {
        showAdminNotification('Property deleted successfully', 'success');
        loadAdminProperties(); // Reload the properties
    }, function(error) {
        showAdminNotification('Error deleting property: ' + error, 'error');
    });
}

// Close property form
function closePropertyForm() {
    document.getElementById('property-form-modal').style.display = 'none';
    currentEditingProperty = null;
}

// Show import form
function showImportForm() {
    document.getElementById('import-form').reset();
    document.getElementById('import-progress').style.display = 'none';
    document.getElementById('import-modal').style.display = 'block';
}

// Submit import
function submitImport(event) {
    event.preventDefault();
    
    const fileInput = document.getElementById('import-file');
    const file = fileInput.files[0];
    
    if (!file) {
        showAdminNotification('Please select a file to import', 'error');
        return;
    }

    // Show progress
    document.getElementById('import-progress').style.display = 'block';
    document.getElementById('import-status').textContent = 'Uploading file...';
    
    const formData = new FormData();
    formData.append('file', file);

    // Use XMLHttpRequest for file upload with progress
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'http://localhost:8000/api/import.php', true);
    
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            document.querySelector('.progress-fill').style.width = percentComplete + '%';
        }
    };
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showImportResults(response);
                    loadAdminProperties(); // Reload properties
                } else {
                    showAdminNotification('Import failed: ' + response.error, 'error');
                }
            } catch (e) {
                showAdminNotification('Invalid response from server', 'error');
            }
        } else {
            showAdminNotification('Import failed with HTTP error: ' + xhr.status, 'error');
        }
        
        // Reset progress
        setTimeout(() => {
            document.getElementById('import-progress').style.display = 'none';
            document.querySelector('.progress-fill').style.width = '0%';
        }, 2000);
    };
    
    xhr.onerror = function() {
        showAdminNotification('Import failed due to network error', 'error');
        document.getElementById('import-progress').style.display = 'none';
    };
    
    xhr.send(formData);
}

// Show import results
function showImportResults(response) {
    let message = `Import completed!\n`;
    message += `Processed: ${response.total_processed} records\n`;
    message += `Imported: ${response.imported} properties\n`;
    
    if (response.errors && response.errors.length > 0) {
        message += `Errors: ${response.errors.length}\n\n`;
        message += 'Error details:\n' + response.errors.slice(0, 5).join('\n');
        if (response.errors.length > 5) {
            message += `\n... and ${response.errors.length - 5} more errors`;
        }
    }
    
    alert(message);
    document.getElementById('import-status').textContent = `Imported ${response.imported} properties successfully`;
}

// Close import form
function closeImportForm() {
    document.getElementById('import-modal').style.display = 'none';
}

// Export data (reuse from main app)
function exportData(format) {
    window.open(`http://localhost:8000/api/export.php?format=${format}`, '_blank');
    showAdminNotification(`Exporting data as ${format.toUpperCase()}...`, 'info');
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // In a real application, this would clear session/tokens
        showAdminNotification('Logged out successfully', 'info');
        // Redirect to main page or login page
        setTimeout(() => {
            window.location.href = '../index.html';
        }, 1000);
    }
}

// Admin notification system
function showAdminNotification(message, type = 'info') {
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
        max-width: 350px;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remove notification after 4 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 4000);
}

// Close modals when clicking outside
window.onclick = function(event) {
    const propertyModal = document.getElementById('property-form-modal');
    const importModal = document.getElementById('import-modal');
    
    if (event.target === propertyModal) {
        propertyModal.style.display = 'none';
    }
    if (event.target === importModal) {
        importModal.style.display = 'none';
    }
}

// Utility function to format numbers (already defined in main app.js but included for completeness)
if (typeof formatNumber === 'undefined') {
    function formatNumber(num) {
        return new Intl.NumberFormat('ro-RO').format(num);
    }
}

// Utility function to escape HTML (already defined in main app.js but included for completeness)
if (typeof escapeHtml === 'undefined') {
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
}

// AJAX function (reuse from main app if not available)
if (typeof makeAjaxRequest === 'undefined') {
    function makeAjaxRequest(method, url, data, successCallback, errorCallback) {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
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
} 