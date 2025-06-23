// Admin Panel JavaScript

// Navigation
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    const navLinks = document.querySelectorAll('.admin-sidebar a');
    const sections = document.querySelectorAll('.admin-section');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href').substring(1);
            
            // Update active link
            navLinks.forEach(link => link.classList.remove('active'));
            this.classList.add('active');
            
            // Show target section
            sections.forEach(section => {
                section.classList.remove('active');
                if (section.id === targetId) {
                    section.classList.add('active');
                }
            });
        });
    });
    
    // Load properties
    loadProperties();
    
    // Load statistics
    loadStatistics();
    
    // Setup event listeners
    document.getElementById('refreshProperties').addEventListener('click', loadProperties);
    document.getElementById('adminExportJson').addEventListener('click', function() {
        window.open('/api/export/json', '_blank');
    });
    document.getElementById('adminExportCsv').addEventListener('click', function() {
        window.open('/api/export/csv', '_blank');
    });
    document.getElementById('importForm').addEventListener('submit', importProperties);
});

// Utility: Escape HTML for XSS prevention
function escapeHtml(str) {
    return String(str).replace(/[&<>'"`=\/]/g, function (s) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','\'':'&#39;','"':'&quot;','`':'&#96;','=':'&#61;','/':'&#47;'}[s]);
    });
}

// Load properties for admin table
async function loadProperties() {
    try {
        const response = await authenticatedFetch('/api/properties');
        if (!response) return;
        const properties = await response.json();
        const tbody = document.getElementById('propertiesTableBody');
        tbody.innerHTML = '';
        properties.forEach(property => {
            const tr = document.createElement('tr');
            // Use textContent for user data
            const idTd = document.createElement('td');
            idTd.textContent = property.id;
            const titleTd = document.createElement('td');
            titleTd.textContent = property.title;
            const priceTd = document.createElement('td');
            priceTd.textContent = `$${Number(property.price).toLocaleString()}`;
            const typeTd = document.createElement('td');
            typeTd.textContent = property.type === 'sale' ? 'For Sale' : 'For Rent';
            const propertyTypeTd = document.createElement('td');
            propertyTypeTd.textContent = property.property_type;
            const areaTd = document.createElement('td');
            areaTd.textContent = `${property.area} m²`;
            const actionsTd = document.createElement('td');
            const delBtn = document.createElement('button');
            delBtn.className = 'admin-btn delete-btn';
            delBtn.dataset.id = property.id;
            delBtn.textContent = 'Delete';
            actionsTd.appendChild(delBtn);
            tr.appendChild(idTd);
            tr.appendChild(titleTd);
            tr.appendChild(priceTd);
            tr.appendChild(typeTd);
            tr.appendChild(propertyTypeTd);
            tr.appendChild(areaTd);
            tr.appendChild(actionsTd);
            tbody.appendChild(tr);
        });
        // Add event listeners to delete buttons
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                deleteProperty(id);
            });
        });
    } catch (error) {
        console.error('Error loading properties:', error);
    }
}

// Delete property
async function deleteProperty(id) {
    if (confirm('Are you sure you want to delete this property?')) {
        try {
            const response = await authenticatedFetch(`/api/properties/${id}`, {
                method: 'DELETE'
            });
            
            if (!response) return;
            
            if (response.ok) {
                alert('Property deleted successfully');
                loadProperties();
                loadStatistics();
            } else {
                const data = await response.json();
                alert('Error: ' + (data.error || 'Failed to delete property'));
            }
        } catch (error) {
            console.error('Error deleting property:', error);
            alert('An error occurred while deleting the property');
        }
    }
}

// Import properties (supports JSON and CSV)
async function importProperties(e) {
    e.preventDefault();
    const fileInput = document.getElementById('importFile');
    const file = fileInput.files[0];
    if (!file) {
        alert('Please select a file');
        return;
    }
    const isJson = file.type === 'application/json' || file.name.endsWith('.json');
    const isCsv = file.type === 'text/csv' || file.name.endsWith('.csv');
    if (!isJson && !isCsv) {
        alert('Please select a JSON or CSV file');
        return;
    }
    try {
        const reader = new FileReader();
        reader.onload = async function(e) {
            try {
                let properties;
                if (isJson) {
                    properties = JSON.parse(e.target.result);
                } else if (isCsv) {
                    properties = csvToJson(e.target.result);
                }
                if (!Array.isArray(properties)) {
                    throw new Error('Invalid data format: expected an array of properties');
                }
                const response = await authenticatedFetch('/api/import/json', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(properties)
                });
                if (!response) return;
                const result = await response.json();
                const importResult = document.getElementById('importResult');
                if (result.error) {
                    importResult.innerHTML = `<div class="error"></div>`;
                    importResult.querySelector('.error').textContent = result.error;
                } else {
                    importResult.innerHTML = `<div class="success"></div>`;
                    importResult.querySelector('.success').textContent = `Import completed: ${result.success} properties imported successfully, ${result.errors} errors`;
                    loadProperties();
                    loadStatistics();
                }
            } catch (error) {
                console.error('Error parsing file:', error);
                const importResult = document.getElementById('importResult');
                importResult.innerHTML = `<div class="error"></div>`;
                importResult.querySelector('.error').textContent = 'Invalid file format';
            }
        };
        reader.readAsText(file);
    } catch (error) {
        console.error('Error reading file:', error);
        const importResult = document.getElementById('importResult');
        importResult.innerHTML = `<div class="error"></div>`;
        importResult.querySelector('.error').textContent = 'Error reading file';
    }
}

// CSV to JSON utility (simple, secure, no external libs)
function csvToJson(csv) {
    const lines = csv.split(/\r?\n/).filter(Boolean);
    if (lines.length < 2) return [];
    const headers = lines[0].split(',').map(h => h.trim());
    return lines.slice(1).map(line => {
        const values = line.split(',').map(v => v.trim());
        const obj = {};
        headers.forEach((h, i) => { obj[h] = values[i] || ''; });
        return obj;
    });
}

// Load statistics
async function loadStatistics() {
    try {
        const response = await authenticatedFetch('/api/properties');
        if (!response) return;
        
        const properties = await response.json();
        
        // Calculate statistics
        const totalProperties = properties.length;
        const forSaleCount = properties.filter(p => p.type === 'sale').length;
        const forRentCount = properties.filter(p => p.type === 'rent').length;
        
        // Calculate average price
        let totalPrice = 0;
        properties.forEach(property => {
            totalPrice += property.price;
        });
        const averagePrice = totalProperties > 0 ? totalPrice / totalProperties : 0;
        
        // Update DOM
        document.getElementById('totalProperties').textContent = totalProperties;
        document.getElementById('forSaleCount').textContent = forSaleCount;
        document.getElementById('forRentCount').textContent = forRentCount;
        document.getElementById('averagePrice').textContent = '$' + averagePrice.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Create property type distribution chart
        createPropertyTypeChart(properties);
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

// Create property type chart
function createPropertyTypeChart(properties) {
    const chartContainer = document.getElementById('propertyTypeChart');
    chartContainer.innerHTML = '';
    
    // Count property types
    const propertyTypes = {};
    properties.forEach(property => {
        if (!propertyTypes[property.property_type]) {
            propertyTypes[property.property_type] = 0;
        }
        propertyTypes[property.property_type]++;
    });
    
    // Create chart data
    const data = Object.entries(propertyTypes).map(([type, count]) => ({
        type: type.charAt(0).toUpperCase() + type.slice(1),
        count
    }));
    
    // Sort data by count (descending)
    data.sort((a, b) => b.count - a.count);
    
    // Create chart
    const colors = ['#4CAF50', '#2196F3', '#FFC107', '#9C27B0', '#F44336', '#607D8B'];
    
    // Simple bar chart
    const chart = document.createElement('div');
    chart.className = 'bar-chart';
    
    const maxCount = Math.max(...data.map(d => d.count));
    
    data.forEach((item, i) => {
        const barContainer = document.createElement('div');
        barContainer.className = 'bar-container';
        
        const label = document.createElement('div');
        label.className = 'bar-label';
        label.textContent = item.type;
        
        const barWrapper = document.createElement('div');
        barWrapper.className = 'bar-wrapper';
        
        const bar = document.createElement('div');
        bar.className = 'bar';
        bar.style.width = `${(item.count / maxCount) * 100}%`;
        bar.style.backgroundColor = colors[i % colors.length];
        
        const value = document.createElement('div');
        value.className = 'bar-value';
        value.textContent = item.count;
        
        barWrapper.appendChild(bar);
        barWrapper.appendChild(value);
        
        barContainer.appendChild(label);
        barContainer.appendChild(barWrapper);
        
        chart.appendChild(barContainer);
    });
    
    chartContainer.appendChild(chart);
}
