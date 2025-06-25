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

document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Initialize core systems
        window.apiHandler = new APIHandler();
        window.loadingManager = new LoadingManager();
        window.messageSystem = new MessageSystem();
        
        // Initialize UI components
        window.navigation = new Navigation();
        window.formHandler = new FormHandler();
        window.authModal = new AuthModal();
        window.statsCounter = new StatsCounter();
        
        // Initialize map (Stage 3 feature)
        window.mapManager = new MapManager();
        
        // Hide initial loading
        loadingManager.hideAll();
        
        console.log('🏠 REMS application initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize application:', error);
        messageSystem?.error('Eroare la inițializarea aplicației');
    }
}); 