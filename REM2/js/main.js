/**
 * REMS - Real Estate Management System
 * Main JavaScript file with Map Integration
 * 
 * Features:
 * - Navigation and UI handling
 * - Form validation and submission
 * - API communication
 * - OpenStreetMap integration with Leaflet
 * - Property display on map
 * - Interactive filtering and search
 * - Geolocation support
 * - Property listing and filtering
 * - Pagination and view management
 */

// ======================================
// Global Configuration
// ======================================

const CONFIG = {
    API_BASE_URL: '/api',
    DEFAULT_MAP_CENTER: [44.4268, 26.1025], // Bucharest, Romania
    DEFAULT_MAP_ZOOM: 7,
    MAX_ZOOM: 18,
    CLUSTER_RADIUS: 80,
    REQUEST_TIMEOUT: 10000,
    DEBOUNCE_DELAY: 300,
    
    // Romania bounds for map constraints
    ROMANIA_BOUNDS: [
        [43.5, 20.0], // Southwest
        [48.5, 30.0]  // Northeast
    ],
    
    // Map tile layers
    MAP_LAYERS: {
        openstreet: {
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            attribution: '© OpenStreetMap contributors',
            name: 'OpenStreetMap'
        },
        satellite: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            attribution: '© Esri, Maxar, GeoEye',
            name: 'Satelit'
        },
        terrain: {
            url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
            attribution: '© OpenTopoMap',
            name: 'Relief'
        }
    }
};

// ======================================
// Utility Functions
// ======================================

class Utils {
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    static formatPrice(price, currency = '€') {
        if (!price) return 'Preț la cerere';
        return new Intl.NumberFormat('ro-RO').format(price) + ' ' + currency;
    }

    static formatArea(area) {
        if (!area) return '';
        return new Intl.NumberFormat('ro-RO').format(area) + ' m²';
    }

    static capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    static getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth radius in km
        const dLat = this.deg2rad(lat2-lat1);
        const dLon = this.deg2rad(lon2-lon1); 
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) * 
            Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
        return R * c;
    }

    static deg2rad(deg) {
        return deg * (Math.PI/180);
    }

    static escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    static generateCSRFToken() {
        return Array.from(crypto.getRandomValues(new Uint8Array(32)))
            .map(b => b.toString(16).padStart(2, '0')).join('');
    }
}

// ======================================
// API Handler Class
// ======================================

class APIHandler {
    constructor() {
        this.csrfToken = null;
        this.requestQueue = new Map();
        this.init();
    }

    async init() {
        await this.fetchCSRFToken();
    }

    async fetchCSRFToken() {
        try {
            const response = await this.request('/auth/csrf');
            this.csrfToken = response.token;
        } catch (error) {
            console.warn('Could not fetch CSRF token:', error);
            this.csrfToken = Utils.generateCSRFToken();
        }
    }

    async request(endpoint, options = {}) {
        const url = `${CONFIG.API_BASE_URL}${endpoint}`;
        const requestId = `${options.method || 'GET'}_${url}_${Date.now()}`;
        
        // Cancel duplicate requests
        if (this.requestQueue.has(requestId)) {
            this.requestQueue.get(requestId).controller.abort();
        }

        const controller = new AbortController();
        this.requestQueue.set(requestId, { controller });

        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: controller.signal,
            timeout: CONFIG.REQUEST_TIMEOUT
        };

        if (this.csrfToken && ['POST', 'PUT', 'DELETE'].includes(options.method)) {
            defaultOptions.headers['X-CSRF-Token'] = this.csrfToken;
        }

        const finalOptions = { ...defaultOptions, ...options };
        
        if (finalOptions.body && typeof finalOptions.body === 'object') {
            finalOptions.body = JSON.stringify(finalOptions.body);
        }

        try {
            const response = await fetch(url, finalOptions);
            this.requestQueue.delete(requestId);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            this.requestQueue.delete(requestId);
            if (error.name === 'AbortError') {
                throw new Error('Request was cancelled');
            }
            throw error;
        }
    }

    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url);
    }

    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: data
        });
    }

    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: data
        });
    }

    async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }
}

// ======================================
// Loading Manager Class
// ======================================

class LoadingManager {
    constructor() {
        this.activeRequests = new Set();
        this.loadingElement = document.getElementById('loading-spinner');
    }

    show(requestId = 'global') {
        this.activeRequests.add(requestId);
        if (this.loadingElement) {
            this.loadingElement.style.display = 'flex';
        }
    }

    hide(requestId = 'global') {
        this.activeRequests.delete(requestId);
        if (this.activeRequests.size === 0 && this.loadingElement) {
            this.loadingElement.style.display = 'none';
        }
    }

    hideAll() {
        this.activeRequests.clear();
        if (this.loadingElement) {
            this.loadingElement.style.display = 'none';
        }
    }
}

// ======================================
// Message System Class
// ======================================

class MessageSystem {
    constructor() {
        this.container = document.getElementById('message-container');
        if (!this.container) {
            this.createContainer();
        }
    }

    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'message-container';
        this.container.className = 'message-container';
        this.container.setAttribute('aria-live', 'polite');
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', duration = 5000) {
        const messageElement = document.createElement('div');
        messageElement.className = `message message-${type}`;
        messageElement.innerHTML = `
            <div class="message-content">
                <span class="message-icon">${this.getIcon(type)}</span>
                <span class="message-text">${Utils.escapeHtml(message)}</span>
                <button class="message-close" aria-label="Închide mesajul">&times;</button>
            </div>
        `;

        const closeBtn = messageElement.querySelector('.message-close');
        closeBtn.addEventListener('click', () => this.remove(messageElement));

        this.container.appendChild(messageElement);

        // Trigger animation
        requestAnimationFrame(() => {
            messageElement.classList.add('message-show');
        });

        // Auto remove
        if (duration > 0) {
            setTimeout(() => this.remove(messageElement), duration);
        }

        return messageElement;
    }

    remove(messageElement) {
        if (messageElement && messageElement.parentNode) {
            messageElement.classList.add('message-hide');
            setTimeout(() => {
                if (messageElement.parentNode) {
                    messageElement.parentNode.removeChild(messageElement);
                }
            }, 300);
        }
    }

    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    error(message, duration) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration) {
        return this.show(message, 'info', duration);
    }

    getIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        return icons[type] || icons.info;
    }
}

// ======================================
// Map Manager Class
// ======================================

class MapManager {
    constructor() {
        this.map = null;
        this.markers = new Map();
        this.markerCluster = null;
        this.currentLayer = 'openstreet';
        this.layers = new Map();
        this.bounds = null;
        this.searchAreaMode = false;
        this.searchAreaRect = null;
        this.fullscreenMode = false;
        this.userLocation = null;
        this.propertiesData = [];
        this.currentFilters = {};
        
        this.mapContainer = document.getElementById('property-map');
        this.mapLoading = document.getElementById('map-loading');
        
        if (this.mapContainer) {
            this.init();
        }
    }

    async init() {
        try {
            this.showMapLoading();
            await this.initializeMap();
            this.setupControls();
            this.setupFilters();
            await this.loadProperties();
            this.hideMapLoading();
        } catch (error) {
            console.error('Map initialization failed:', error);
            this.showMapError('Eroare la încărcarea hărții');
        }
    }

    showMapLoading() {
        if (this.mapLoading) {
            this.mapLoading.classList.remove('hidden');
        }
    }

    hideMapLoading() {
        if (this.mapLoading) {
            this.mapLoading.classList.add('hidden');
        }
    }

    showMapError(message) {
        this.hideMapLoading();
        if (this.mapContainer) {
            this.mapContainer.innerHTML = `
                <div class="map-error">
                    <div class="map-error-content">
                        <h3>🗺️ Eroare Hartă</h3>
                        <p>${message}</p>
                        <button class="btn btn-primary" onclick="window.mapManager.init()">
                            Încearcă din nou
                        </button>
                    </div>
                </div>
            `;
        }
    }

    async initializeMap() {
        // Initialize map
        this.map = L.map(this.mapContainer, {
            center: CONFIG.DEFAULT_MAP_CENTER,
            zoom: CONFIG.DEFAULT_MAP_ZOOM,
            maxZoom: CONFIG.MAX_ZOOM,
            zoomControl: true,
            attributionControl: true
        });

        // Set bounds to Romania
        const bounds = L.latLngBounds(CONFIG.ROMANIA_BOUNDS);
        this.map.setMaxBounds(bounds);

        // Initialize layers
        this.initializeLayers();
        
        // Add default layer
        this.addLayer(this.currentLayer);

        // Initialize marker clustering
        this.initializeMarkerClustering();

        // Setup map events
        this.setupMapEvents();
    }

    initializeLayers() {
        Object.entries(CONFIG.MAP_LAYERS).forEach(([key, config]) => {
            const layer = L.tileLayer(config.url, {
                attribution: config.attribution,
                maxZoom: CONFIG.MAX_ZOOM
            });
            this.layers.set(key, layer);
        });
    }

    addLayer(layerKey) {
        if (this.layers.has(layerKey)) {
            // Remove current layer
            this.layers.forEach(layer => {
                if (this.map.hasLayer(layer)) {
                    this.map.removeLayer(layer);
                }
            });
            
            // Add new layer
            this.layers.get(layerKey).addTo(this.map);
            this.currentLayer = layerKey;
        }
    }

    initializeMarkerClustering() {
        // Using a simple marker cluster implementation since we don't have Leaflet.markercluster
        this.markerCluster = L.layerGroup().addTo(this.map);
    }

    setupMapEvents() {
        this.map.on('zoomend', () => {
            this.updateMarkersVisibility();
        });

        this.map.on('moveend', () => {
            this.updateMarkersVisibility();
        });

        this.map.on('click', (e) => {
            if (this.searchAreaMode) {
                this.handleSearchAreaClick(e);
            }
        });
    }

    setupControls() {
        // Fullscreen control
        const fullscreenBtn = document.getElementById('map-fullscreen');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
        }

        // Layer switcher
        const layersBtn = document.getElementById('map-layers');
        if (layersBtn) {
            layersBtn.addEventListener('click', () => this.toggleLayerSwitcher());
        }

        // Search area control
        const searchAreaBtn = document.getElementById('map-search-area');
        if (searchAreaBtn) {
            searchAreaBtn.addEventListener('click', () => this.toggleSearchAreaMode());
        }

        // My location control
        const locationBtn = document.getElementById('my-location');
        if (locationBtn) {
            locationBtn.addEventListener('click', () => this.getUserLocation());
        }

        // Create layer switcher
        this.createLayerSwitcher();
    }

    setupFilters() {
        const applyBtn = document.getElementById('apply-map-filters');
        const clearBtn = document.getElementById('clear-map-filters');

        if (applyBtn) {
            applyBtn.addEventListener('click', () => this.applyFilters());
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearFilters());
        }

        // Setup filter change listeners
        const filterInputs = document.querySelectorAll('#map-transaction-type, #map-property-type, #map-min-price, #map-max-price');
        filterInputs.forEach(input => {
            input.addEventListener('change', Utils.debounce(() => this.applyFilters(), CONFIG.DEBOUNCE_DELAY));
        });
    }

    createLayerSwitcher() {
        const layerSwitcher = document.createElement('div');
        layerSwitcher.className = 'layer-switcher hidden';
        layerSwitcher.id = 'layer-switcher';

        const layerOptions = Object.entries(CONFIG.MAP_LAYERS).map(([key, config]) => `
            <div class="layer-option">
                <input type="radio" id="layer-${key}" name="map-layer" value="${key}" ${key === this.currentLayer ? 'checked' : ''}>
                <label for="layer-${key}">${config.name}</label>
            </div>
        `).join('');

        layerSwitcher.innerHTML = layerOptions;

        // Add event listeners
        const radioButtons = layerSwitcher.querySelectorAll('input[type="radio"]');
        radioButtons.forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.addLayer(e.target.value);
                this.hideLayerSwitcher();
            });
        });

        this.mapContainer.parentNode.appendChild(layerSwitcher);
    }

    toggleLayerSwitcher() {
        const switcher = document.getElementById('layer-switcher');
        if (switcher) {
            switcher.classList.toggle('hidden');
        }
    }

    hideLayerSwitcher() {
        const switcher = document.getElementById('layer-switcher');
        if (switcher) {
            switcher.classList.add('hidden');
        }
    }

    toggleFullscreen() {
        const mapContainer = this.mapContainer.parentNode;
        
        if (!this.fullscreenMode) {
            mapContainer.classList.add('map-fullscreen');
            this.fullscreenMode = true;
        } else {
            mapContainer.classList.remove('map-fullscreen');
            this.fullscreenMode = false;
        }

        // Trigger map resize
        setTimeout(() => {
            this.map.invalidateSize();
        }, 100);
    }

    toggleSearchAreaMode() {
        this.searchAreaMode = !this.searchAreaMode;
        const btn = document.getElementById('map-search-area');
        
        if (this.searchAreaMode) {
            this.map.getContainer().classList.add('map-search-area-active');
            btn.style.background = '#2563eb';
            btn.style.color = 'white';
            this.showSearchAreaInfo();
        } else {
            this.map.getContainer().classList.remove('map-search-area-active');
            btn.style.background = '';
            btn.style.color = '';
            this.hideSearchAreaInfo();
            if (this.searchAreaRect) {
                this.map.removeLayer(this.searchAreaRect);
                this.searchAreaRect = null;
            }
        }
    }

    showSearchAreaInfo() {
        const info = document.createElement('div');
        info.className = 'search-area-info';
        info.id = 'search-area-info';
        info.textContent = 'Click și trage pentru a selecta o zonă de căutare';
        this.mapContainer.appendChild(info);
    }

    hideSearchAreaInfo() {
        const info = document.getElementById('search-area-info');
        if (info) {
            info.remove();
        }
    }

    handleSearchAreaClick(e) {
        if (!this.searchAreaMode) return;

        if (!this.searchAreaRect) {
            // Start area selection
            this.searchAreaStartPoint = e.latlng;
            this.searchAreaRect = L.rectangle([e.latlng, e.latlng], {
                className: 'search-area-overlay'
            }).addTo(this.map);

            // Listen for mouse move
            this.map.on('mousemove', this.handleSearchAreaMouseMove.bind(this));
            this.map.on('click', this.handleSearchAreaEnd.bind(this));
        }
    }

    handleSearchAreaMouseMove(e) {
        if (this.searchAreaRect && this.searchAreaStartPoint) {
            const bounds = L.latLngBounds(this.searchAreaStartPoint, e.latlng);
            this.searchAreaRect.setBounds(bounds);
        }
    }

    handleSearchAreaEnd(e) {
        if (this.searchAreaRect) {
            this.map.off('mousemove', this.handleSearchAreaMouseMove);
            this.map.off('click', this.handleSearchAreaEnd);
            
            const bounds = this.searchAreaRect.getBounds();
            this.searchInArea(bounds);
            this.toggleSearchAreaMode();
        }
    }

    async searchInArea(bounds) {
        const ne = bounds.getNorthEast();
        const sw = bounds.getSouthWest();
        
        const filters = {
            ...this.getCurrentFilters(),
            north: ne.lat,
            south: sw.lat,
            east: ne.lng,
            west: sw.lng
        };

        await this.loadProperties(filters);
        messageSystem.info(`Căutare în zona selectată: ${this.markers.size} proprietăți găsite`);
    }

    async getUserLocation() {
        if (!navigator.geolocation) {
            messageSystem.error('Geolocalizarea nu este suportată de browser');
            return;
        }

        const btn = document.getElementById('my-location');
        btn.style.opacity = '0.6';

        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000
                });
            });

            const { latitude, longitude } = position.coords;
            this.userLocation = [latitude, longitude];

            // Check if location is within Romania bounds
            const bounds = L.latLngBounds(CONFIG.ROMANIA_BOUNDS);
            if (bounds.contains([latitude, longitude])) {
                this.map.setView([latitude, longitude], 12);
                
                // Add user location marker
                if (this.userLocationMarker) {
                    this.map.removeLayer(this.userLocationMarker);
                }
                
                this.userLocationMarker = L.marker([latitude, longitude], {
                    icon: L.divIcon({
                        className: 'user-location-marker',
                        html: '📍',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    })
                }).addTo(this.map);

                messageSystem.success('Locația ta a fost găsită!');
            } else {
                messageSystem.warning('Locația ta nu se află în România');
            }
        } catch (error) {
            console.error('Geolocation error:', error);
            messageSystem.error('Nu s-a putut determina locația ta');
        } finally {
            btn.style.opacity = '';
        }
    }

    getCurrentFilters() {
        return {
            transaction_type: document.getElementById('map-transaction-type')?.value || '',
            property_type: document.getElementById('map-property-type')?.value || '',
            min_price: document.getElementById('map-min-price')?.value || '',
            max_price: document.getElementById('map-max-price')?.value || ''
        };
    }

    async applyFilters() {
        this.currentFilters = this.getCurrentFilters();
        await this.loadProperties(this.currentFilters);
    }

    clearFilters() {
        document.getElementById('map-transaction-type').value = '';
        document.getElementById('map-property-type').value = '';
        document.getElementById('map-min-price').value = '';
        document.getElementById('map-max-price').value = '';
        
        this.currentFilters = {};
        this.loadProperties();
    }

    async loadProperties(filters = {}) {
        try {
            this.showMapLoading();
            
            const response = await apiHandler.get('/properties', {
                ...filters,
                limit: 1000, // Load more properties for map
                status: 'active'
            });

            this.propertiesData = response.data || [];
            this.updateMapMarkers();
            
        } catch (error) {
            console.error('Failed to load properties:', error);
            messageSystem.error('Eroare la încărcarea proprietăților');
        } finally {
            this.hideMapLoading();
        }
    }

    updateMapMarkers() {
        // Clear existing markers
        this.clearMarkers();

        // Add new markers
        this.propertiesData.forEach(property => {
            if (property.latitude && property.longitude) {
                this.addPropertyMarker(property);
            }
        });

        // Fit map to markers if we have any
        if (this.markers.size > 0) {
            const group = new L.featureGroup([...this.markers.values()]);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    addPropertyMarker(property) {
        const lat = parseFloat(property.latitude);
        const lng = parseFloat(property.longitude);
        
        if (isNaN(lat) || isNaN(lng)) return;

        // Create custom marker
        const markerIcon = L.divIcon({
            className: `custom-marker ${property.transaction_type} ${property.featured ? 'featured' : ''}`,
            html: this.getMarkerContent(property),
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -15]
        });

        const marker = L.marker([lat, lng], { icon: markerIcon });
        
        // Create popup content
        const popupContent = this.createPopupContent(property);
        marker.bindPopup(popupContent, {
            maxWidth: 350,
            minWidth: 280,
            closeButton: true,
            className: 'property-popup'
        });

        // Add to map and store reference
        marker.addTo(this.markerCluster);
        this.markers.set(property.id, marker);
    }

    getMarkerContent(property) {
        const icon = property.featured ? '★' : '●';
        return `<span style="color: inherit;">${icon}</span>`;
    }

    createPopupContent(property) {
        const imageUrl = property.primary_image ? 
            `/assets/images/properties/${property.primary_image}` : 
            `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 200'%3E%3Crect width='300' height='200' fill='%23f3f4f6'/%3E%3Ctext x='150' y='100' text-anchor='middle' fill='%236b7280' font-family='Arial' font-size='14'%3EFără imagine%3C/text%3E%3C/svg%3E`;

        return `
            <div class="property-popup">
                <div class="popup-header" style="background-image: url('${imageUrl}')">
                    <div class="property-type-badge">${Utils.capitalizeFirst(property.property_type)}</div>
                    <div class="transaction-indicator ${property.transaction_type}"></div>
                    <div class="popup-price">${Utils.formatPrice(property.price)}</div>
                </div>
                <div class="popup-body">
                    <h4 class="popup-title">${Utils.escapeHtml(property.title)}</h4>
                    <p class="popup-address">📍 ${Utils.escapeHtml(property.address)}, ${Utils.escapeHtml(property.city)}</p>
                    
                    <div class="popup-details">
                        <div class="popup-detail">
                            <span class="popup-detail-value">${property.rooms || '-'}</span>
                            <span class="popup-detail-label">Camere</span>
                        </div>
                        <div class="popup-detail">
                            <span class="popup-detail-value">${Utils.formatArea(property.surface_useful)}</span>
                            <span class="popup-detail-label">Suprafață</span>
                        </div>
                        <div class="popup-detail">
                            <span class="popup-detail-value">${property.floor || '-'}</span>
                            <span class="popup-detail-label">Etaj</span>
                        </div>
                    </div>
                    
                    <div class="popup-actions">
                        <button class="popup-btn popup-btn-primary" onclick="window.viewProperty(${property.id})">
                            Vezi Detalii
                        </button>
                        <button class="popup-btn popup-btn-secondary" onclick="window.contactProperty(${property.id})">
                            Contact
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    clearMarkers() {
        this.markers.forEach(marker => {
            this.markerCluster.removeLayer(marker);
        });
        this.markers.clear();
    }

    updateMarkersVisibility() {
        // This would handle marker clustering based on zoom level
        // For now, we'll keep it simple
    }
}

// ======================================
// Navigation Class
// ======================================

class Navigation {
    constructor() {
        this.navToggle = document.querySelector('.nav-toggle');
        this.navMenu = document.querySelector('.nav-menu');
        this.navLinks = document.querySelectorAll('.nav-link');
        
        this.init();
    }

    init() {
        this.setupMobileMenu();
        this.setupSmoothScrolling();
        this.setupActiveLink();
        this.setupThemeToggle();
    }

    setupMobileMenu() {
        if (this.navToggle && this.navMenu) {
            this.navToggle.addEventListener('click', () => {
                this.navMenu.classList.toggle('active');
                this.navToggle.classList.toggle('active');
                
                const isExpanded = this.navToggle.getAttribute('aria-expanded') === 'true';
                this.navToggle.setAttribute('aria-expanded', !isExpanded);
            });

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!this.navToggle.contains(e.target) && !this.navMenu.contains(e.target)) {
                    this.navMenu.classList.remove('active');
                    this.navToggle.classList.remove('active');
                    this.navToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }

    setupSmoothScrolling() {
        this.navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href.startsWith('#')) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        
                        // Close mobile menu
                        this.navMenu.classList.remove('active');
                        this.navToggle.classList.remove('active');
                        this.navToggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });
    }

    setupActiveLink() {
        const observerOptions = {
            threshold: 0.3,
            rootMargin: '-80px 0px -80px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    this.navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${id}`) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        }, observerOptions);

        // Observe sections
        document.querySelectorAll('section[id]').forEach(section => {
            observer.observe(section);
        });
    }

    setupThemeToggle() {
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-theme');
                const isDark = document.body.classList.contains('dark-theme');
                themeToggle.textContent = isDark ? '☀️' : '🌙';
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            });

            // Load saved theme
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
                themeToggle.textContent = '☀️';
            }
        }
    }
}

// ======================================
// Form Handler Class
// ======================================

class FormHandler {
    constructor() {
        this.forms = {
            search: document.getElementById('hero-search-form'),
            newsletter: document.getElementById('newsletter-form'),
            login: document.getElementById('login-form'),
            register: document.getElementById('register-form')
        };
        
        this.init();
    }

    init() {
        Object.values(this.forms).forEach(form => {
            if (form) {
                form.addEventListener('submit', this.handleSubmit.bind(this));
            }
        });
    }

    async handleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            switch (form.id) {
                case 'hero-search-form':
                    await this.handleSearchSubmit(data);
                    break;
                case 'newsletter-form':
                    await this.handleNewsletterSubmit(data);
                    break;
                case 'login-form':
                    await this.handleLoginSubmit(data);
                    break;
                case 'register-form':
                    await this.handleRegisterSubmit(data);
                    break;
            }
        } catch (error) {
            console.error('Form submission error:', error);
            messageSystem.error('Eroare la procesarea formularului');
        }
    }

    async handleSearchSubmit(data) {
        // Apply search filters to map
        if (window.mapManager) {
            const filterElements = {
                'map-transaction-type': data.transaction_type,
                'map-property-type': data.property_type,
                'map-min-price': data.min_price,
                'map-max-price': data.max_price
            };

            Object.entries(filterElements).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element && value) {
                    element.value = value;
                }
            });

            await window.mapManager.applyFilters();
            
            // Scroll to map
            document.getElementById('map')?.scrollIntoView({ behavior: 'smooth' });
            messageSystem.success('Căutarea a fost aplicată pe hartă');
        }
    }

    async handleNewsletterSubmit(data) {
        // Simulate newsletter subscription
        messageSystem.success('Te-ai abonat cu succes la newsletter!');
    }

    async handleLoginSubmit(data) {
        try {
            const response = await apiHandler.post('/auth/login', data);
            messageSystem.success('Conectare reușită!');
            authModal.hide();
            // Handle successful login
        } catch (error) {
            messageSystem.error('Eroare la conectare');
        }
    }

    async handleRegisterSubmit(data) {
        if (data.password !== data.confirm_password) {
            messageSystem.error('Parolele nu coincid');
            return;
        }

        try {
            const response = await apiHandler.post('/auth/register', data);
            messageSystem.success('Înregistrare reușită!');
            authModal.hide();
        } catch (error) {
            messageSystem.error('Eroare la înregistrare');
        }
    }
}

// ======================================
// Authentication Modal Class
// ======================================

class AuthModal {
    constructor() {
        this.modal = document.getElementById('auth-modal');
        this.loginForm = document.getElementById('login-form');
        this.registerForm = document.getElementById('register-form');
        this.modalTitle = document.getElementById('auth-modal-title');
        
        this.init();
    }

    init() {
        if (!this.modal) return;

        // Modal controls
        const closeBtn = this.modal.querySelector('.modal-close');
        const loginBtn = document.getElementById('login-btn');
        const registerBtn = document.getElementById('register-btn');
        const switchToRegister = document.getElementById('switch-to-register');
        const switchToLogin = document.getElementById('switch-to-login');

        closeBtn?.addEventListener('click', () => this.hide());
        loginBtn?.addEventListener('click', () => this.showLogin());
        registerBtn?.addEventListener('click', () => this.showRegister());
        switchToRegister?.addEventListener('click', () => this.switchToRegister());
        switchToLogin?.addEventListener('click', () => this.switchToLogin());

        // Close on background click
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.hide();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.modal.classList.contains('hidden')) {
                this.hide();
            }
        });
    }

    show() {
        this.modal.classList.remove('hidden');
        this.modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    hide() {
        this.modal.classList.add('hidden');
        this.modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    showLogin() {
        this.modalTitle.textContent = 'Conectare';
        this.loginForm.style.display = 'block';
        this.registerForm.style.display = 'none';
        document.getElementById('switch-to-register').style.display = 'block';
        document.getElementById('switch-to-login').style.display = 'none';
        this.show();
    }

    showRegister() {
        this.modalTitle.textContent = 'Înregistrare';
        this.loginForm.style.display = 'none';
        this.registerForm.style.display = 'block';
        document.getElementById('switch-to-register').style.display = 'none';
        document.getElementById('switch-to-login').style.display = 'block';
        this.show();
    }

    switchToRegister() {
        this.showRegister();
    }

    switchToLogin() {
        this.showLogin();
    }
}

// ======================================
// Statistics Counter Class
// ======================================

class StatsCounter {
    constructor() {
        this.statsNumbers = document.querySelectorAll('.stat-number[data-target]');
        this.hasAnimated = false;
        this.init();
    }

    init() {
        if (this.statsNumbers.length === 0) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.hasAnimated) {
                    this.animateCounters();
                    this.hasAnimated = true;
                }
            });
        }, { threshold: 0.5 });

        this.statsNumbers.forEach(stat => observer.observe(stat));
    }

    animateCounters() {
        this.statsNumbers.forEach(stat => {
            const target = parseInt(stat.getAttribute('data-target'));
            const duration = 2000;
            const start = Date.now();
            const startValue = 0;

            const updateCounter = () => {
                const elapsed = Date.now() - start;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const current = Math.floor(startValue + (target - startValue) * easeOutQuart);
                
                stat.textContent = current.toLocaleString('ro-RO');
                
                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                }
            };

            updateCounter();
        });
    }
}

// ======================================
// Property Listing Manager
// ======================================

class PropertyListing {
    constructor() {
        this.apiHandler = null;
        this.loadingManager = null;
        this.messageSystem = null;
        this.currentPage = 1;
        this.itemsPerPage = 12;
        this.currentFilters = {};
        this.currentSort = 'newest';
        this.currentView = 'grid';
        this.totalResults = 0;
        this.isLoading = false;
        this.searchTimeout = null;
        
        // Elements
        this.elements = {
            form: null,
            grid: null,
            pagination: null,
            resultsCount: null,
            resultsTitle: null,
            sortSelect: null,
            viewButtons: null,
            quickFilters: null,
            backToTop: null,
            loadingContainer: null,
            errorContainer: null,
            emptyContainer: null,
            advancedFilters: null,
            advancedToggle: null,
            showAdvanced: null,
            totalProperties: null
        };
        
        this.cities = [];
    }

    async init() {
        // Get references to shared instances
        this.apiHandler = window.App?.apiHandler || new APIHandler();
        this.loadingManager = window.App?.loadingManager || new LoadingManager();
        this.messageSystem = window.App?.messageSystem || new MessageSystem();
        
        this.initializeElements();
        this.setupEventListeners();
        await this.loadCities();
        await this.loadInitialData();
        this.setupURLHandling();
        this.setupBackToTop();
        
        console.log('PropertyListing initialized');
    }

    initializeElements() {
        this.elements.form = document.getElementById('property-search-form');
        this.elements.grid = document.getElementById('properties-grid');
        this.elements.pagination = document.querySelector('.pagination');
        this.elements.resultsCount = document.getElementById('results-count');
        this.elements.resultsTitle = document.querySelector('.results-title');
        this.elements.sortSelect = document.getElementById('sort-select');
        this.elements.viewButtons = document.querySelectorAll('.view-btn');
        this.elements.quickFilters = document.querySelectorAll('.quick-filter-btn');
        this.elements.backToTop = document.getElementById('back-to-top');
        this.elements.loadingContainer = document.getElementById('loading-container');
        this.elements.errorContainer = document.getElementById('error-container');
        this.elements.emptyContainer = document.getElementById('empty-container');
        this.elements.advancedFilters = document.getElementById('advanced-filters');
        this.elements.advancedToggle = document.getElementById('toggle-advanced');
        this.elements.showAdvanced = document.getElementById('show-advanced');
        this.elements.totalProperties = document.getElementById('total-properties');
    }

    setupEventListeners() {
        // Search form
        if (this.elements.form) {
            this.elements.form.addEventListener('submit', (e) => this.handleSearch(e));
            
            // Real-time search for text input
            const searchInput = this.elements.form.querySelector('[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', Utils.debounce((e) => {
                    this.handleInstantSearch();
                }, CONFIG.DEBOUNCE_DELAY));
            }
            
            // Filter changes
            const filterInputs = this.elements.form.querySelectorAll('select, input[type="number"]');
            filterInputs.forEach(input => {
                input.addEventListener('change', () => this.handleFilterChange());
            });
        }

        // Sort selection
        if (this.elements.sortSelect) {
            this.elements.sortSelect.addEventListener('change', (e) => {
                this.currentSort = e.target.value;
                this.currentPage = 1;
                this.loadProperties();
            });
        }

        // View toggle
        this.elements.viewButtons.forEach(btn => {
            btn.addEventListener('click', (e) => this.handleViewToggle(e));
        });

        // Quick filters
        this.elements.quickFilters.forEach(btn => {
            btn.addEventListener('click', (e) => this.handleQuickFilter(e));
        });

        // Advanced filters toggle
        if (this.elements.advancedToggle) {
            this.elements.advancedToggle.addEventListener('click', () => this.toggleAdvancedFilters());
        }
        
        if (this.elements.showAdvanced) {
            this.elements.showAdvanced.addEventListener('click', () => this.showAdvancedFilters());
        }

        // Clear filters
        const clearFiltersBtn = document.getElementById('clear-filters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => this.clearFilters());
        }
        
        const clearSearchBtn = document.getElementById('clear-search');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => this.clearFilters());
        }

        // Retry button
        const retryBtn = document.getElementById('retry-button');
        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.loadProperties());
        }

        // Back to top
        if (this.elements.backToTop) {
            this.elements.backToTop.addEventListener('click', () => this.scrollToTop());
        }

        // Pagination delegation
        document.addEventListener('click', (e) => {
            if (e.target.matches('.pagination-item:not(.active):not(:disabled)')) {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                if (page && page !== this.currentPage) {
                    this.goToPage(page);
                }
            }
        });

        // Property card delegation
        document.addEventListener('click', (e) => {
            if (e.target.matches('.property-card, .property-card *:not(.property-card-favorite)')) {
                const card = e.target.closest('.property-card');
                if (card && !e.target.closest('.property-card-favorite')) {
                    this.handlePropertyClick(card);
                }
            }
            
            if (e.target.matches('.property-card-favorite')) {
                e.stopPropagation();
                this.handleFavoriteClick(e.target);
            }
        });
    }

    async loadCities() {
        try {
            const response = await fetch('/api_properties.php?endpoint=cities');
            const data = await response.json();
            this.cities = data.data || [];
            this.populateCitiesDropdown();
        } catch (error) {
            console.warn('Could not load cities:', error);
            this.cities = ['București', 'Cluj-Napoca', 'Constanța', 'Iași', 'Timișoara', 'Craiova', 'Brașov', 'Galați', 'Ploiești', 'Oradea'];
            this.populateCitiesDropdown();
        }
    }

    populateCitiesDropdown() {
        const citySelect = document.getElementById('city');
        if (!citySelect || !this.cities.length) return;

        // Clear existing options except the first one
        const firstOption = citySelect.querySelector('option[value=""]');
        citySelect.innerHTML = '';
        if (firstOption) {
            citySelect.appendChild(firstOption);
        }

        this.cities.forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            citySelect.appendChild(option);
        });
    }

    async loadInitialData() {
        this.parseURLParams();
        await this.loadProperties();
        await this.loadStatistics();
    }

    parseURLParams() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Parse filters from URL
        const filters = {};
        for (const [key, value] of urlParams.entries()) {
            if (value) {
                filters[key] = value;
            }
        }
        
        // Parse pagination
        this.currentPage = parseInt(urlParams.get('page')) || 1;
        this.currentSort = urlParams.get('sort') || 'newest';
        this.currentView = urlParams.get('view') || 'grid';
        
        // Set form values
        this.setFormValues(filters);
        this.currentFilters = filters;
        
        // Update UI
        if (this.elements.sortSelect) {
            this.elements.sortSelect.value = this.currentSort;
        }
        
        this.updateViewButtons();
    }

    setFormValues(filters) {
        if (!this.elements.form) return;

        Object.keys(filters).forEach(key => {
            const element = this.elements.form.querySelector(`[name="${key}"]`);
            if (element) {
                element.value = filters[key];
            }
        });
    }

    updateURL() {
        const params = new URLSearchParams();
        
        // Add filters
        Object.keys(this.currentFilters).forEach(key => {
            if (this.currentFilters[key]) {
                params.set(key, this.currentFilters[key]);
            }
        });
        
        // Add pagination and sort
        if (this.currentPage > 1) {
            params.set('page', this.currentPage.toString());
        }
        
        if (this.currentSort !== 'newest') {
            params.set('sort', this.currentSort);
        }
        
        if (this.currentView !== 'grid') {
            params.set('view', this.currentView);
        }
        
        // Update URL without reload
        const newURL = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.replaceState({}, '', newURL);
    }

    setupURLHandling() {
        window.addEventListener('popstate', () => {
            this.parseURLParams();
            this.loadProperties();
        });
    }

    async handleSearch(e) {
        e.preventDefault();
        this.currentPage = 1;
        this.collectFilters();
        await this.loadProperties();
    }

    async handleInstantSearch() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(async () => {
            this.currentPage = 1;
            this.collectFilters();
            await this.loadProperties();
        }, CONFIG.DEBOUNCE_DELAY);
    }

    async handleFilterChange() {
        this.currentPage = 1;
        this.collectFilters();
        await this.loadProperties();
    }

    collectFilters() {
        if (!this.elements.form) return;

        const formData = new FormData(this.elements.form);
        const filters = {};

        for (const [key, value] of formData.entries()) {
            if (value && value.trim()) {
                filters[key] = value.trim();
            }
        }

        this.currentFilters = filters;
    }

    handleViewToggle(e) {
        const button = e.target.closest('.view-btn');
        if (!button) return;

        this.currentView = button.dataset.view;
        this.updateViewButtons();
        this.updateGridView();
        this.updateURL();
    }

    updateViewButtons() {
        this.elements.viewButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === this.currentView);
        });
    }

    updateGridView() {
        if (!this.elements.grid) return;

        this.elements.grid.className = `properties-grid view-${this.currentView}`;
    }

    handleQuickFilter(e) {
        const button = e.target.closest('.quick-filter-btn');
        if (!button) return;

        const filter = button.dataset.filter;
        if (!filter) return;

        const [key, value] = filter.split('=');
        
        // Toggle quick filter
        if (this.currentFilters[key] === value) {
            delete this.currentFilters[key];
            button.classList.remove('active');
        } else {
            this.currentFilters[key] = value;
            button.classList.add('active');
            
            // Remove active class from other buttons of same type
            this.elements.quickFilters.forEach(btn => {
                if (btn !== button && btn.dataset.filter.startsWith(key + '=')) {
                    btn.classList.remove('active');
                }
            });
        }

        // Update form
        this.setFormValues(this.currentFilters);
        
        this.currentPage = 1;
        this.loadProperties();
    }

    clearFilters() {
        this.currentFilters = {};
        this.currentPage = 1;
        
        // Clear form
        if (this.elements.form) {
            this.elements.form.reset();
        }
        
        // Clear quick filters
        this.elements.quickFilters.forEach(btn => {
            btn.classList.remove('active');
        });
        
        this.loadProperties();
    }

    toggleAdvancedFilters() {
        if (!this.elements.advancedFilters || !this.elements.advancedToggle) return;
        
        const isHidden = this.elements.advancedFilters.classList.contains('hidden');
        
        if (isHidden) {
            this.showAdvancedFilters();
        } else {
            this.hideAdvancedFilters();
        }
    }

    showAdvancedFilters() {
        if (!this.elements.advancedFilters) return;
        
        this.elements.advancedFilters.classList.remove('hidden');
        
        if (this.elements.advancedToggle) {
            this.elements.advancedToggle.style.display = 'block';
            const toggleText = this.elements.advancedToggle.querySelector('.toggle-text');
            const toggleIcon = this.elements.advancedToggle.querySelector('.toggle-icon');
            if (toggleText) toggleText.textContent = 'Ascunde filtrele avansate';
            if (toggleIcon) toggleIcon.textContent = '▲';
        }
        
        if (this.elements.showAdvanced) {
            this.elements.showAdvanced.parentElement.classList.add('hidden');
        }
    }

    hideAdvancedFilters() {
        if (!this.elements.advancedFilters) return;
        
        this.elements.advancedFilters.classList.add('hidden');
        
        if (this.elements.showAdvanced) {
            this.elements.showAdvanced.parentElement.classList.remove('hidden');
        }
    }

    async goToPage(page) {
        if (page === this.currentPage || this.isLoading) return;
        
        this.currentPage = page;
        await this.loadProperties();
        
        // Scroll to results
        const resultsSection = document.querySelector('.results-section');
        if (resultsSection) {
            resultsSection.scrollIntoView({ behavior: 'smooth' });
        }
    }

    async loadProperties() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            const params = {
                ...this.currentFilters,
                page: this.currentPage,
                limit: this.itemsPerPage,
                sort: this.currentSort,
                endpoint: 'search'
            };
            
            // Use simplified API endpoint
            const url = '/api_properties.php';
            const queryString = new URLSearchParams(params).toString();
            const response = await fetch(`${url}?${queryString}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.message || 'Failed to load properties');
            }
            
            this.totalResults = data.pagination?.total || 0;
            this.displayProperties(data.data || []);
            this.updatePagination(data.pagination);
            this.updateResultsInfo();
            this.updateURL();
            this.hideError();
            this.hideEmpty();
            
        } catch (error) {
            console.error('Error loading properties:', error);
            this.showError(error.message);
            this.displayProperties([]);
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }

    displayProperties(properties) {
        if (!this.elements.grid) return;
        
        if (!properties.length) {
            this.showEmpty();
            this.elements.grid.innerHTML = '';
            return;
        }
        
        this.hideEmpty();
        
        const html = properties.map(property => this.createPropertyCard(property)).join('');
        this.elements.grid.innerHTML = html;
        
        // Update quick filters state
        this.updateQuickFiltersState();
        
        // Animate cards
        this.animateCards();
    }

    createPropertyCard(property) {
        const price = Utils.formatPrice(property.price, property.currency || '€');
        const pricePerSqm = property.price && property.surface_useful ? 
            ` (${Utils.formatPrice(Math.round(property.price / property.surface_useful))}/${property.surface_useful > 0 ? 'm²' : 'mp'})` : '';
        
        const imageUrl = property.primary_image ? 
            `/assets/images/properties/${property.primary_image}` : 
            '/assets/images/placeholder-property.jpg';
        
        const typeLabels = {
            'apartament': 'Apartament',
            'casa': 'Casă',
            'teren': 'Teren',
            'comercial': 'Comercial',
            'birou': 'Birou',
            'garsoniera': 'Garsonieră'
        };
        
        const transactionLabels = {
            'vanzare': 'Vânzare',
            'inchiriere': 'Închiriere'
        };
        
        const typeLabel = typeLabels[property.property_type] || property.property_type;
        const transactionLabel = transactionLabels[property.transaction_type] || property.transaction_type;
        
        return `
            <div class="property-card" data-property-id="${property.id}">
                <div class="property-card-image">
                    <img src="${imageUrl}" alt="${Utils.escapeHtml(property.title)}" loading="lazy">
                    <div class="property-card-badges">
                        <span class="badge badge-primary">${transactionLabel}</span>
                        ${property.featured ? '<span class="badge badge-warning">Recomandat</span>' : ''}
                    </div>
                    <button class="property-card-favorite" data-property-id="${property.id}" aria-label="Adaugă la favorite">
                        ♡
                    </button>
                </div>
                
                <div class="property-card-content">
                    <div class="property-card-header">
                        <div class="property-card-price">
                            ${price}
                            ${property.transaction_type === 'inchiriere' ? '<span class="property-card-price-period">/lună</span>' : ''}
                        </div>
                        <div class="property-card-type">${typeLabel}</div>
                    </div>
                    
                    <h3 class="property-card-title">${Utils.escapeHtml(property.title)}</h3>
                    <p class="property-card-location">${Utils.escapeHtml(property.address || '')}, ${Utils.escapeHtml(property.city || '')}</p>
                    
                    <div class="property-card-details">
                        ${property.surface_useful ? `
                            <div class="property-card-detail">
                                <span class="property-card-detail-value">${property.surface_useful}</span>
                                <span class="property-card-detail-label">mp</span>
                            </div>
                        ` : ''}
                        ${property.rooms ? `
                            <div class="property-card-detail">
                                <span class="property-card-detail-value">${property.rooms}</span>
                                <span class="property-card-detail-label">camere</span>
                            </div>
                        ` : ''}
                        ${property.bathrooms ? `
                            <div class="property-card-detail">
                                <span class="property-card-detail-value">${property.bathrooms}</span>
                                <span class="property-card-detail-label">băi</span>
                            </div>
                        ` : ''}
                        ${property.floor ? `
                            <div class="property-card-detail">
                                <span class="property-card-detail-value">${property.floor}</span>
                                <span class="property-card-detail-label">etaj</span>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="property-card-footer">
                        <span class="property-card-agent">${Utils.escapeHtml(property.agent_name || 'Agent')}</span>
                        <span class="property-card-date">${this.formatDate(property.created_at)}</span>
                    </div>
                </div>
            </div>
        `;
    }

    formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 1) return 'Ieri';
        if (diffDays < 7) return `${diffDays} zile`;
        if (diffDays < 30) return `${Math.floor(diffDays / 7)} săptămâni`;
        
        return new Intl.DateTimeFormat('ro-RO', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(date);
    }

    updatePagination(pagination) {
        if (!this.elements.pagination || !pagination) return;
        
        const { current_page, total_pages, has_prev, has_next } = pagination;
        
        if (total_pages <= 1) {
            this.elements.pagination.parentElement.style.display = 'none';
            return;
        }
        
        this.elements.pagination.parentElement.style.display = 'flex';
        
        let html = '';
        
        // Previous button
        html += `
            <button class="pagination-item pagination-prev" 
                    data-page="${current_page - 1}" 
                    ${!has_prev ? 'disabled' : ''}>
                ←
            </button>
        `;
        
        // Page numbers
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(total_pages, current_page + 2);
        
        if (startPage > 1) {
            html += `<button class="pagination-item" data-page="1">1</button>`;
            if (startPage > 2) {
                html += `<span class="pagination-item" disabled>...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button class="pagination-item ${i === current_page ? 'active' : ''}" 
                        data-page="${i}" 
                        ${i === current_page ? 'disabled' : ''}>
                    ${i}
                </button>
            `;
        }
        
        if (endPage < total_pages) {
            if (endPage < total_pages - 1) {
                html += `<span class="pagination-item" disabled>...</span>`;
            }
            html += `<button class="pagination-item" data-page="${total_pages}">${total_pages}</button>`;
        }
        
        // Next button
        html += `
            <button class="pagination-item pagination-next" 
                    data-page="${current_page + 1}" 
                    ${!has_next ? 'disabled' : ''}>
                →
            </button>
        `;
        
        this.elements.pagination.innerHTML = html;
    }

    updateResultsInfo() {
        if (this.elements.resultsCount) {
            this.elements.resultsCount.textContent = this.totalResults.toLocaleString('ro-RO');
        }
        
        if (this.elements.resultsTitle) {
            const text = this.totalResults === 1 ? 'proprietate găsită' : 'proprietăți găsite';
            this.elements.resultsTitle.innerHTML = `<span id="results-count">${this.totalResults.toLocaleString('ro-RO')}</span> ${text}`;
        }
    }

    updateQuickFiltersState() {
        this.elements.quickFilters.forEach(btn => {
            const filter = btn.dataset.filter;
            if (!filter) return;
            
            const [key, value] = filter.split('=');
            btn.classList.toggle('active', this.currentFilters[key] === value);
        });
    }

    animateCards() {
        if (!this.elements.grid) return;
        
        const cards = this.elements.grid.querySelectorAll('.property-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 50);
        });
    }

    async loadStatistics() {
        try {
            const response = await fetch('/api_properties.php?endpoint=statistics');
            const data = await response.json();
            if (data && this.elements.totalProperties) {
                this.elements.totalProperties.textContent = (data.total_properties || 0).toLocaleString('ro-RO');
            }
        } catch (error) {
            console.warn('Could not load statistics:', error);
        }
    }

    handlePropertyClick(card) {
        const propertyId = card.dataset.propertyId;
        if (!propertyId) return;
        
        // For now, show a modal. Later this will navigate to property detail page
        this.showPropertyModal(propertyId);
    }

    async showPropertyModal(propertyId) {
        try {
            const response = await this.apiHandler.get(`/properties/${propertyId}`);
            if (response.success) {
                this.displayPropertyModal(response.data);
            }
        } catch (error) {
            this.messageSystem.error('Nu am putut încărca detaliile proprietății');
        }
    }

    displayPropertyModal(property) {
        const modal = document.getElementById('property-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body');
        
        if (!modal || !modalTitle || !modalBody) return;
        
        modalTitle.textContent = property.title;
        modalBody.innerHTML = this.createPropertyModalContent(property);
        
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        
        // Setup modal close
        const closeModal = () => {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        };
        
        modal.querySelector('.modal-close').onclick = closeModal;
        modal.querySelector('.modal-overlay').onclick = closeModal;
        
        // Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    }

    createPropertyModalContent(property) {
        const price = Utils.formatPrice(property.price, property.currency || '€');
        const imageUrl = property.primary_image ? 
            `/assets/images/properties/${property.primary_image}` : 
            '/assets/images/placeholder-property.jpg';
        
        return `
            <div class="property-modal-content">
                <div class="property-modal-image">
                    <img src="${imageUrl}" alt="${Utils.escapeHtml(property.title)}">
                </div>
                <div class="property-modal-info">
                    <div class="property-price">${price}</div>
                    <div class="property-location">${property.address}, ${property.city}</div>
                    <div class="property-description">${Utils.escapeHtml(property.description || '')}</div>
                    <div class="property-details-grid">
                        ${property.surface_useful ? `<div><strong>Suprafață utilă:</strong> ${property.surface_useful} mp</div>` : ''}
                        ${property.rooms ? `<div><strong>Camere:</strong> ${property.rooms}</div>` : ''}
                        ${property.bedrooms ? `<div><strong>Dormitoare:</strong> ${property.bedrooms}</div>` : ''}
                        ${property.bathrooms ? `<div><strong>Băi:</strong> ${property.bathrooms}</div>` : ''}
                        ${property.floor ? `<div><strong>Etaj:</strong> ${property.floor}</div>` : ''}
                        ${property.construction_year ? `<div><strong>An construcție:</strong> ${property.construction_year}</div>` : ''}
                    </div>
                    <div class="property-contact">
                        <strong>Contact:</strong> ${property.agent_name || 'Agent'}<br>
                        ${property.agent_email || ''} ${property.agent_phone || ''}
                    </div>
                </div>
            </div>
        `;
    }

    handleFavoriteClick(button) {
        const propertyId = button.dataset.propertyId;
        if (!propertyId) return;
        
        // For now, just toggle visual state. Auth will be implemented in Stage 6
        button.classList.toggle('active');
        const isActive = button.classList.contains('active');
        button.innerHTML = isActive ? '♥' : '♡';
        
        this.messageSystem.info(
            isActive ? 'Proprietate adăugată la favorite' : 'Proprietate eliminată din favorite',
            3000
        );
    }

    setupBackToTop() {
        if (!this.elements.backToTop) return;
        
        const toggleBackToTop = Utils.throttle(() => {
            const scrolled = window.pageYOffset > 300;
            this.elements.backToTop.classList.toggle('visible', scrolled);
        }, 100);
        
        window.addEventListener('scroll', toggleBackToTop);
    }

    scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    showLoading() {
        if (this.elements.loadingContainer) {
            this.elements.loadingContainer.style.display = 'block';
        }
        if (this.elements.grid) {
            this.elements.grid.style.display = 'none';
        }
    }

    hideLoading() {
        if (this.elements.loadingContainer) {
            this.elements.loadingContainer.style.display = 'none';
        }
        if (this.elements.grid) {
            this.elements.grid.style.display = 'grid';
        }
    }

    showError(message = 'A apărut o eroare') {
        if (this.elements.errorContainer) {
            this.elements.errorContainer.style.display = 'block';
            const errorMessage = this.elements.errorContainer.querySelector('#error-message');
            if (errorMessage) {
                errorMessage.textContent = message;
            }
        }
        if (this.elements.grid) {
            this.elements.grid.style.display = 'none';
        }
    }

    hideError() {
        if (this.elements.errorContainer) {
            this.elements.errorContainer.style.display = 'none';
        }
    }

    showEmpty() {
        if (this.elements.emptyContainer) {
            this.elements.emptyContainer.style.display = 'block';
        }
        if (this.elements.grid) {
            this.elements.grid.style.display = 'none';
        }
    }

    hideEmpty() {
        if (this.elements.emptyContainer) {
            this.elements.emptyContainer.style.display = 'none';
        }
    }
}

// ======================================
// Global Functions
// ======================================

window.viewProperty = function(propertyId) {
    messageSystem.info(`Redirectionare către proprietatea #${propertyId}`);
    // TODO: Implement property details page
};

window.contactProperty = function(propertyId) {
    messageSystem.info(`Deschidere formular contact pentru proprietatea #${propertyId}`);
    // TODO: Implement contact functionality
};

// ======================================
// Main Application Initialization
// ======================================

// Global App object
window.App = {
    apiHandler: null,
    loadingManager: null,
    messageSystem: null,
    navigation: null,
    formHandler: null,
    authModal: null,
    statsCounter: null,
    mapManager: null,
    propertyListing: null,
    
    // Initialize the application
    async init() {
        try {
            console.log('🏠 Initializing REMS application...');
            
            // Initialize core systems
            this.apiHandler = new APIHandler();
            this.loadingManager = new LoadingManager();
            this.messageSystem = new MessageSystem();
            
            await this.apiHandler.init();
            
            // Initialize UI components
            this.navigation = new Navigation();
            this.formHandler = new FormHandler();
            this.authModal = new AuthModal();
            this.statsCounter = new StatsCounter();
            this.authManager = new AuthManager();
            
            // Initialize map (Stage 3 feature)
            this.mapManager = new MapManager();
            
            // Initialize property listing (Stage 5)
            this.propertyListing = new PropertyListing();
            
            // Initialize all components
            await this.navigation.init();
            await this.formHandler.init();
            await this.authModal.init();
            await this.statsCounter.init();
            
            // Initialize map if on a page with map
            if (document.getElementById('map-container')) {
                await this.mapManager.init();
            }
            
            // Initialize property listing if on properties page
            if (document.getElementById('properties-grid')) {
                await this.propertyListing.init();
            }
            
            // Expose instances globally for backward compatibility
            window.apiHandler = this.apiHandler;
            window.loadingManager = this.loadingManager;
            window.messageSystem = this.messageSystem;
            window.navigation = this.navigation;
            window.formHandler = this.formHandler;
            window.authModal = this.authModal;
            window.statsCounter = this.statsCounter;
            window.authManager = this.authManager;
            window.mapManager = this.mapManager;
            window.propertyListing = this.propertyListing;
            
            // Hide initial loading
            this.loadingManager.hideAll();
            
            console.log('🏠 REMS application initialized successfully');
            
        } catch (error) {
            console.error('Failed to initialize application:', error);
            this.messageSystem?.error('Eroare la inițializarea aplicației');
        }
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    await window.App.init();
});

// ========================================================================
// Authentication Management System
// ========================================================================

class AuthManager {
    constructor() {
        this.apiBaseUrl = '/api_auth.php';
        this.currentUser = null;
        this.isAuthenticated = false;
        this.csrfToken = null;
        
        // Initialize authentication state
        this.init();
    }
    
    /**
     * Initialize authentication manager
     */
    async init() {
        try {
            // Check if user is already authenticated
            await this.checkAuthStatus();
            
            // Setup click outside handler for user menu
            this.setupClickOutsideHandler();
        } catch (error) {
            console.warn('Auth check failed:', error);
        }
    }
    
    /**
     * Setup click outside handler for user menu
     */
    setupClickOutsideHandler() {
        document.addEventListener('click', (e) => {
            const userDropdown = document.querySelector('.user-dropdown');
            const userMenu = document.getElementById('user-menu');
            const userToggle = document.querySelector('.user-toggle');
            
            if (userDropdown && userMenu && !userDropdown.contains(e.target)) {
                userMenu.classList.remove('show');
                if (userToggle) {
                    userToggle.classList.remove('active');
                }
            }
        });
    }
    
    /**
     * Check current authentication status
     */
    async checkAuthStatus() {
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=me`, {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success && data.data) {
                this.currentUser = data.data;
                this.isAuthenticated = true;
                this.updateUI();
            } else {
                this.currentUser = null;
                this.isAuthenticated = false;
            }
            
        } catch (error) {
            console.error('Auth status check failed:', error);
            this.currentUser = null;
            this.isAuthenticated = false;
        }
    }
    
    /**
     * Login user
     */
    async login(credentials) {
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(credentials)
            });
            
            const data = await response.json();
            
            if (data.success && data.data.user) {
                this.currentUser = data.data.user;
                this.isAuthenticated = true;
                this.csrfToken = data.data.csrf_token;
                this.updateUI();
                
                // Redirect to dashboard or intended page
                this.redirectAfterLogin();
                
                return { success: true, data: data.data };
            } else {
                return { success: false, message: data.message };
            }
            
        } catch (error) {
            console.error('Login failed:', error);
            return { success: false, message: 'Eroare de conexiune' };
        }
    }
    
    /**
     * Register new user
     */
    async register(userData) {
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(userData)
            });
            
            const data = await response.json();
            
            return {
                success: data.success,
                message: data.message,
                data: data.data || null
            };
            
        } catch (error) {
            console.error('Registration failed:', error);
            return { success: false, message: 'Eroare de conexiune' };
        }
    }
    
    /**
     * Logout user
     */
    async logout() {
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=logout`, {
                method: 'POST',
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentUser = null;
                this.isAuthenticated = false;
                this.csrfToken = null;
                this.updateUI();
                
                // Redirect to login page
                window.location.href = 'login.html';
                
                return { success: true };
            }
            
        } catch (error) {
            console.error('Logout failed:', error);
        }
        
        // Force logout even if API call fails
        this.currentUser = null;
        this.isAuthenticated = false;
        this.csrfToken = null;
        this.updateUI();
        window.location.href = 'login.html';
    }
    
    /**
     * Update UI based on authentication state
     */
    updateUI() {
        const userNav = document.querySelector('.user-nav');
        const loginBtn = document.querySelector('.login-btn');
        const registerBtn = document.querySelector('.register-btn');
        
        if (this.isAuthenticated && this.currentUser) {
            // Show user navigation
            if (userNav) {
                userNav.innerHTML = `
                    <div class="user-dropdown">
                        <button class="user-toggle" onclick="window.authManager.toggleUserMenu()">
                            <span class="user-name">${this.currentUser.first_name} ${this.currentUser.last_name}</span>
                            <span class="user-role">(${this.getUserRoleLabel()})</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="user-menu" id="user-menu">
                            <a href="dashboard.html" class="user-menu-item">
                                📊 Dashboard
                            </a>
                            <a href="profile.html" class="user-menu-item">
                                👤 Profil
                            </a>
                            <a href="favorites.html" class="user-menu-item">
                                ❤️ Favorite
                            </a>
                            ${this.currentUser.role === 'admin' ? '<a href="admin.html" class="user-menu-item">⚙️ Administrare</a>' : ''}
                            ${this.canManageProperties() ? '<a href="manage-properties.html" class="user-menu-item">🏠 Proprietățile mele</a>' : ''}
                            <hr class="user-menu-divider">
                            <button onclick="window.authManager.logout()" class="user-menu-item logout-btn">
                                🚪 Deconectare
                            </button>
                        </div>
                    </div>
                `;
                userNav.style.display = 'block';
            }
            
            // Hide login/register buttons
            if (loginBtn) loginBtn.style.display = 'none';
            if (registerBtn) registerBtn.style.display = 'none';
            
        } else {
            // Show login/register buttons
            if (loginBtn) loginBtn.style.display = 'inline-block';
            if (registerBtn) registerBtn.style.display = 'inline-block';
            
            // Hide user navigation
            if (userNav) {
                userNav.style.display = 'none';
            }
        }
        
        console.log('Auth state updated:', this.isAuthenticated ? 'Authenticated' : 'Not authenticated');
    }
    
    /**
     * Get user role label in Romanian
     */
    getUserRoleLabel() {
        const roleLabels = {
            'admin': 'Administrator',
            'agent': 'Agent',
            'user': 'Client'
        };
        
        return roleLabels[this.currentUser?.role] || 'Utilizator';
    }
    
    /**
     * Check if user can manage properties
     */
    canManageProperties() {
        return this.isAuthenticated && 
               this.currentUser && 
               ['admin', 'agent'].includes(this.currentUser.role);
    }
    
    /**
     * Toggle user menu
     */
    toggleUserMenu() {
        const userMenu = document.getElementById('user-menu');
        const userToggle = document.querySelector('.user-toggle');
        
        if (userMenu) {
            userMenu.classList.toggle('show');
            if (userToggle) {
                userToggle.classList.toggle('active');
            }
        }
    }
    
    /**
     * Redirect after successful login
     */
    redirectAfterLogin() {
        // Check if there's a stored redirect URL
        const redirectUrl = sessionStorage.getItem('redirect_after_login');
        
        if (redirectUrl) {
            sessionStorage.removeItem('redirect_after_login');
            window.location.href = redirectUrl;
        } else {
            // Default redirect based on user role
            if (this.currentUser.role === 'admin') {
                window.location.href = 'admin.html';
            } else if (this.currentUser.role === 'agent') {
                window.location.href = 'dashboard.html';
            } else {
                window.location.href = 'index.html';
            }
        }
    }
    
    /**
     * Initialize login page
     */
    initLoginPage() {
        const loginForm = document.getElementById('login-form');
        const loginBtn = document.getElementById('login-btn');
        
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // Clear previous messages
                this.hideMessages();
                
                // Show loading state
                loginBtn.classList.add('loading');
                
                // Get form data
                const formData = new FormData(loginForm);
                const credentials = {
                    login: formData.get('login'),
                    password: formData.get('password'),
                    remember_me: formData.get('remember_me') === 'on'
                };
                
                // Validate required fields
                if (!credentials.login || !credentials.password) {
                    this.showError('Vă rugăm să completați toate câmpurile obligatorii.');
                    loginBtn.classList.remove('loading');
                    return;
                }
                
                // Attempt login
                const result = await this.login(credentials);
                
                if (result.success) {
                    this.showSuccess('Autentificare reușită! Vă redirecționăm...');
                    // Redirect is handled in login method
                } else {
                    this.showError(result.message || 'Eroare la autentificare');
                    loginBtn.classList.remove('loading');
                }
            });
        }
    }
    
    /**
     * Initialize register page
     */
    initRegisterPage() {
        const registerForm = document.getElementById('register-form');
        const registerBtn = document.getElementById('register-btn');
        
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // Clear previous messages
                this.hideMessages();
                
                // Show loading state
                registerBtn.classList.add('loading');
                
                // Get form data
                const formData = new FormData(registerForm);
                const userData = {
                    username: formData.get('username'),
                    email: formData.get('email'),
                    password: formData.get('password'),
                    first_name: formData.get('first_name'),
                    last_name: formData.get('last_name'),
                    phone: formData.get('phone'),
                    terms: formData.get('terms') === 'on'
                };
                
                // Validate required fields
                const requiredFields = ['username', 'email', 'password', 'first_name', 'last_name'];
                const missingFields = requiredFields.filter(field => !userData[field]);
                
                if (missingFields.length > 0) {
                    this.showError('Vă rugăm să completați toate câmpurile obligatorii.');
                    registerBtn.classList.remove('loading');
                    return;
                }
                
                // Validate password confirmation
                const passwordConfirm = formData.get('password_confirm');
                if (userData.password !== passwordConfirm) {
                    this.showError('Parolele nu se potrivesc.');
                    registerBtn.classList.remove('loading');
                    return;
                }
                
                // Validate terms acceptance
                if (!userData.terms) {
                    this.showError('Trebuie să acceptați termenii și condițiile.');
                    registerBtn.classList.remove('loading');
                    return;
                }
                
                // Attempt registration
                const result = await this.register(userData);
                
                if (result.success) {
                    this.showSuccess('Cont creat cu succes! Vă puteți autentifica acum.');
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 2000);
                } else {
                    this.showError(result.message || 'Eroare la înregistrare');
                    registerBtn.classList.remove('loading');
                }
            });
        }
    }
    
    /**
     * Show error message
     */
    showError(message) {
        const errorDiv = document.getElementById('auth-error');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }
    }
    
    /**
     * Show success message
     */
    showSuccess(message) {
        const successDiv = document.getElementById('auth-success');
        if (successDiv) {
            successDiv.textContent = message;
            successDiv.style.display = 'block';
        }
    }
    
    /**
     * Hide all messages
     */
    hideMessages() {
        const errorDiv = document.getElementById('auth-error');
        const successDiv = document.getElementById('auth-success');
        
        if (errorDiv) errorDiv.style.display = 'none';
        if (successDiv) successDiv.style.display = 'none';
    }
}

// Make AuthManager globally available
window.AuthManager = AuthManager; 