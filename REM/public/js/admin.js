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
    
    // Event listeners
    document.getElementById('refreshProperties').addEventListener('click', loadProperties);
    document.getElementById('adminExportJson').addEventListener('click', exportJson);
    document.getElementById('adminExportCsv').addEventListener('click', exportCsv);
    document.getElementById('importForm').addEventListener('submit', importData);
});

// Load properties for admin table
async function loadProperties() {
    try {
        const response = await fetch('/api/properties');
        const properties = await response.json();
        
        const tableBody = document.getElementById('propertiesTableBody');
        tableBody.innerHTML = '';
        
        properties.forEach(property => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${property.id}</td>
                <td>${property.title}</td>
                <td>${property.price}</td>
                <td>${property.type}</td>
                <td>${property.property_type || 'N/A'}</td>
                <td>${property.area || 'N/A'} m²</td>
                <td>
                    <button class="admin-btn delete-property" data-id="${property.id}">Delete</button>
                </td>
            `;
            tableBody.appendChild(row);
        });
        
        // Add event listeners to delete buttons
        document.querySelectorAll('.delete-property').forEach(button => {
            button.addEventListener('click', async function() {
                const id = this.getAttribute('data-id');
                if (confirm('Are you sure you want to delete this property?')) {
                    await deleteProperty(id);
                }
            });
        });
    } catch (error) {
        console.error('Error loading properties:', error);
    }
}

// Delete property
async function deleteProperty(id) {
    try {
        const response = await fetch(`/api/properties/${id}`, {
            method: 'DELETE'
        });
        
        if (response.ok) {
            loadProperties();
            loadStatistics();
        }
    } catch (error) {
        console.error('Error deleting property:', error);
    }
}

// Load statistics
async function loadStatistics() {
    try {
        const response = await fetch('/api/properties');
        const properties = await response.json();
        
        // Update statistics
        document.getElementById('totalProperties').textContent = properties.length;
        
        const forSaleCount = properties.filter(p => p.type === 'sale').length;
        const forRentCount = properties.filter(p => p.type === 'rent').length;
        
        document.getElementById('forSaleCount').textContent = forSaleCount;
        document.getElementById('forRentCount').textContent = forRentCount;
        
        // Calculate average price
        if (properties.length > 0) {
            const totalPrice = properties.reduce((sum, property) => sum + parseFloat(property.price), 0);
            const averagePrice = (totalPrice / properties.length).toFixed(2);
            document.getElementById('averagePrice').textContent = averagePrice;
        }
        
        // Create simple chart for property types
        const propertyTypes = {};
        properties.forEach(property => {
            const type = property.property_type || 'Other';
            propertyTypes[type] = (propertyTypes[type] || 0) + 1;
        });
        
        const chartContainer = document.getElementById('propertyTypeChart');
        chartContainer.innerHTML = '';
        
        Object.entries(propertyTypes).forEach(([type, count]) => {
            const percentage = (count / properties.length) * 100;
            
            const bar = document.createElement('div');
            bar.className = 'chart-bar';
            bar.style.cssText = `
                height: 30px;
                background-color: #4CAF50;
                margin-bottom: 5px;
                width: ${percentage}%;
                display: flex;
                align-items: center;
                padding-left: 10px;
                color: white;
                position: relative;
            `;
            bar.innerHTML = `
                <span>${type}: ${count}</span>
            `;
            
            chartContainer.appendChild(bar);
        });
        
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

// Export JSON
function exportJson() {
    window.open('/api/export/json', '_blank');
}

// Export CSV
function exportCsv() {
    window.open('/api/export/csv', '_blank');
}

// Import data
async function importData(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('importFile');
    const resultDiv = document.getElementById('importResult');
    
    if (!fileInput.files.length) {
        resultDiv.innerHTML = '<div class="error">Please select a file to import</div>';
        return;
    }
    
    const file = fileInput.files[0];
    
    try {
        const fileContent = await readFile(file);
        const jsonData = JSON.parse(fileContent);
        
        const response = await fetch('/api/import/json', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(jsonData)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            resultDiv.innerHTML = `
                <div class="success">
                    Import completed successfully.<br>
                    ${result.success} properties imported.<br>
                    ${result.errors} errors.
                </div>
            `;
            
            // Refresh data
            loadProperties();
            loadStatistics();
        } else {
            resultDiv.innerHTML = `<div class="error">Import failed: ${result.error}</div>`;
        }
    } catch (error) {
        resultDiv.innerHTML = `<div class="error">Error: ${error.message}</div>`;
    }
}

// Read file content
function readFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            resolve(e.target.result);
        };
        
        reader.onerror = function(e) {
            reject(new Error('Error reading file'));
        };
        
        reader.readAsText(file);
    });
} 