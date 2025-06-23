// Initialize map
const map = L.map('map').setView([45.9432, 24.9668], 7); // Center on Romania
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
    crowd: L.layerGroup(),
    cost: L.layerGroup()
};

// Romania GeoJSON boundary (simplified for performance)
const romaniaBoundary = {
    "type": "Feature",
    "properties": {},
    "geometry": {
        "type": "Polygon",
        "coordinates": [[
            [20.2201, 46.1328],
            [21.0421, 46.2851],
            [22.1506, 47.9647],
            [22.7106, 47.8915],
            [23.1422, 48.0963],
            [24.5300, 47.9819],
            [24.9252, 47.7147],
            [25.2075, 47.8910],
            [26.0692, 47.9999],
            [26.6191, 48.2207],
            [26.8573, 47.9867],
            [27.2339, 47.6635],
            [27.5509, 47.2488],
            [28.1285, 46.9703],
            [28.1605, 46.6373],
            [28.0586, 45.5962],
            [28.2330, 45.4881],
            [28.6799, 45.2396],
            [29.1497, 45.4649],
            [29.6039, 45.2118],
            [29.6266, 44.8187],
            [28.8776, 44.9139],
            [28.5584, 43.7075],
            [27.1892, 44.1759],
            [26.1173, 43.9888],
            [25.2769, 43.7125],
            [24.1006, 43.6884],
            [23.3323, 43.8974],
            [22.9377, 43.8238],
            [22.6571, 44.2349],
            [22.4745, 44.4784],
            [22.1450, 44.4784],
            [21.5628, 44.7685],
            [21.4832, 45.1821],
            [20.8741, 45.4156],
            [20.2621, 45.8515],
            [20.2201, 46.1328]
        ]]
    }
};

// Add Romania boundary to the map
L.geoJSON(romaniaBoundary, {
    style: {
        fillColor: 'none',
        weight: 2,
        color: '#666',
        opacity: 0.5
    }
}).addTo(map);

// Create a function to check if a point is inside Romania's bounds (simple box check)
function isPointInRomaniaBounds(point) {
    const lat = point[0];
    const lng = point[1];
    return lat >= 43.5 && lat <= 48.3 && lng >= 20.2 && lng <= 29.7;
}

// Romania bounds
const romaniaBounds = {
    minLat: 43.5,
    maxLat: 48.3,
    minLng: 20.2,
    maxLng: 29.7
};2

// Maximum interpolation distance in km
const maxDistance = 150;

// Create grid points for layer data
function createGrid(bounds, size) {
    const points = [];
    const latStep = (bounds.maxLat - bounds.minLat) / size;
    const lngStep = (bounds.maxLng - bounds.minLng) / size;
    
    for (let lat = bounds.minLat; lat <= bounds.maxLat; lat += latStep) {
        for (let lng = bounds.minLng; lng <= bounds.maxLng; lng += lngStep) {
            points.push([lat, lng]);
        }
    }
    return points;
}

// Interpolate values between data points
function interpolateValue(point, dataPoints, maxDistance) {
    let totalWeight = 0;
    let weightedSum = 0;
    
    dataPoints.forEach(dp => {
        const distance = getDistance(point[0], point[1], dp.lat, dp.lng);
        if (distance <= maxDistance) {
            const weight = 1 / Math.pow(distance + 1, 2); // Add 1 to avoid division by zero
            totalWeight += weight;
            weightedSum += weight * dp.value;
        }
    });
    
    return totalWeight > 0 ? weightedSum / totalWeight : null;
}

// Calculate distance between two points in kilometers
function getDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Create grid points
const gridPoints = createGrid(romaniaBounds, 60); // 60x60 grid for smoother coverage

// Sample data for layers
const sampleLayerData = {
    temperature: [
        { lat: 44.4268, lng: 26.1025, value: 25 }, // București
        { lat: 45.7489, lng: 21.2087, value: 23 }, // Timișoara
        { lat: 46.7712, lng: 23.6236, value: 21 }, // Cluj-Napoca
        { lat: 47.1585, lng: 27.6014, value: 22 }, // Iași
        { lat: 45.6427, lng: 25.5887, value: 20 }, // Brașov
        { lat: 44.1733, lng: 28.6383, value: 24 }, // Constanța
        { lat: 44.3190, lng: 23.7965, value: 22 }, // Craiova
        { lat: 45.4353, lng: 28.0480, value: 23 }, // Galați
        { lat: 47.0465, lng: 21.9189, value: 20 }, // Oradea
        { lat: 45.7983, lng: 24.1469, value: 19 }  // Sibiu
    ],
    cost: [
        { lat: 44.4268, lng: 26.1025, value: 1150 }, // București
        { lat: 46.7712, lng: 23.6236, value: 1045 }, // Cluj-Napoca
        { lat: 45.7489, lng: 21.2087, value: 965 },  // Timișoara
        { lat: 47.1585, lng: 27.6014, value: 865 },  // Iași
        { lat: 44.1733, lng: 28.6383, value: 925 },  // Constanța
        { lat: 45.6427, lng: 25.5887, value: 905 }   // Brașov
    ],
    pollution: [
        { lat: 44.4268, lng: 26.1025, value: 90 },  // București
        { lat: 45.7489, lng: 21.2087, value: 60 },  // Timișoara
        { lat: 46.7712, lng: 23.6236, value: 55 },  // Cluj-Napoca
        { lat: 47.1585, lng: 27.6014, value: 70 },  // Iași
        { lat: 45.6427, lng: 25.5887, value: 40 },  // Brașov
        { lat: 44.1733, lng: 28.6383, value: 65 }   // Constanța
    ],
    crime: [
        { lat: 44.4268, lng: 26.1025, value: 85 },  // București
        { lat: 45.7489, lng: 21.2087, value: 45 },  // Timișoara
        { lat: 46.7712, lng: 23.6236, value: 50 },  // Cluj-Napoca
        { lat: 47.1585, lng: 27.6014, value: 55 },  // Iași
        { lat: 45.6427, lng: 25.5887, value: 35 },  // Brașov
        { lat: 44.1733, lng: 28.6383, value: 60 },  // Constanța
        { lat: 45.4384, lng: 28.0500, value: 40 },  // Galați
        { lat: 44.3178, lng: 23.7945, value: 45 },  // Craiova
        { lat: 47.6567, lng: 26.2557, value: 30 },  // Suceava
        { lat: 46.5455, lng: 24.5627, value: 35 }   // Târgu Mureș
    ],
    parking: [
        { lat: 44.4268, lng: 26.1025, value: 2500 }, // București
        { lat: 45.7489, lng: 21.2087, value: 1200 }, // Timișoara
        { lat: 46.7712, lng: 23.6236, value: 1500 }, // Cluj-Napoca
        { lat: 47.1585, lng: 27.6014, value: 1000 }, // Iași
        { lat: 45.6427, lng: 25.5887, value: 800 },  // Brașov
        { lat: 44.1733, lng: 28.6383, value: 1100 }, // Constanța
        { lat: 45.4384, lng: 28.0500, value: 600 },  // Galați
        { lat: 44.3178, lng: 23.7945, value: 700 },  // Craiova
        { lat: 47.6567, lng: 26.2557, value: 400 },  // Suceava
        { lat: 46.5455, lng: 24.5627, value: 500 }   // Târgu Mureș
    ],
    shops: [
        { lat: 44.4268, lng: 26.1025, value: 95 },  // București
        { lat: 45.7489, lng: 21.2087, value: 75 },  // Timișoara
        { lat: 46.7712, lng: 23.6236, value: 80 },  // Cluj-Napoca
        { lat: 47.1585, lng: 27.6014, value: 70 },  // Iași
        { lat: 45.6427, lng: 25.5887, value: 65 },  // Brașov
        { lat: 44.1733, lng: 28.6383, value: 75 },  // Constanța
        { lat: 45.4384, lng: 28.0500, value: 55 },  // Galați
        { lat: 44.3178, lng: 23.7945, value: 60 },  // Craiova
        { lat: 47.6567, lng: 26.2557, value: 45 },  // Suceava
        { lat: 46.5455, lng: 24.5627, value: 50 }   // Târgu Mureș
    ],
    crowd: [
        { lat: 44.4268, lng: 26.1025, value: 90 },  // București
        { lat: 45.7489, lng: 21.2087, value: 70 },  // Timișoara
        { lat: 46.7712, lng: 23.6236, value: 75 },  // Cluj-Napoca
        { lat: 47.1585, lng: 27.6014, value: 65 },  // Iași
        { lat: 45.6427, lng: 25.5887, value: 60 },  // Brașov
        { lat: 44.1733, lng: 28.6383, value: 80 },  // Constanța
        { lat: 45.4384, lng: 28.0500, value: 50 },  // Galați
        { lat: 44.3178, lng: 23.7945, value: 55 },  // Craiova
        { lat: 47.6567, lng: 26.2557, value: 40 },  // Suceava
        { lat: 46.5455, lng: 24.5627, value: 45 }   // Târgu Mureș
    ]
};

// API configuration
const API_CONFIG = {
    openweathermap: {
        key: 'YOUR_API_KEY', // This should be moved to environment variables
        baseUrl: 'https://api.openweathermap.org/data/2.5'
    },
    numbeo: {
        key: 'YOUR_API_KEY',
        baseUrl: 'https://www.numbeo.com/api/v1'
    },
    waqi: {
        key: 'YOUR_API_KEY',
        baseUrl: 'https://api.waqi.info/v2'
    }
};

// Major Romanian cities for data collection
const MAJOR_CITIES = [
    { name: 'București', lat: 44.4268, lng: 26.1025 },
    { name: 'Cluj-Napoca', lat: 46.7712, lng: 23.6236 },
    { name: 'Timișoara', lat: 45.7489, lng: 21.2087 },
    { name: 'Iași', lat: 47.1585, lng: 27.6014 },
    { name: 'Constanța', lat: 44.1733, lng: 28.6383 },
    { name: 'Brașov', lat: 45.6427, lng: 25.5887 },
    { name: 'Craiova', lat: 44.3190, lng: 23.7965 },
    { name: 'Galați', lat: 45.4353, lng: 28.0480 },
    { name: 'Oradea', lat: 47.0465, lng: 21.9189 },
    { name: 'Sibiu', lat: 45.7983, lng: 24.1469 }
];

// Fetch real temperature data from OpenWeatherMap
async function fetchTemperatureData() {
    const temperatureData = [];
    
    try {
        // Fetch data for all major cities in parallel
        const promises = MAJOR_CITIES.map(city => 
            fetch(`${API_CONFIG.openweathermap.baseUrl}/weather?lat=${city.lat}&lon=${city.lng}&units=metric&appid=${API_CONFIG.openweathermap.key}`)
            .then(response => response.json())
        );

        const results = await Promise.all(promises);
        
        results.forEach((result, index) => {
            if (result.main && result.main.temp) {
                temperatureData.push({
                    lat: MAJOR_CITIES[index].lat,
                    lng: MAJOR_CITIES[index].lng,
                    value: result.main.temp,
                    city: MAJOR_CITIES[index].name
                });
            }
        });

        return temperatureData;
    } catch (error) {
        console.error('Error fetching temperature data:', error);
        // Fallback to sample data if API fails
        return sampleLayerData.temperature;
    }
}

// Fetch real air quality data from WAQI
async function fetchAirQualityData() {
    const pollutionData = [];
    
    try {
        const promises = MAJOR_CITIES.map(city =>
            fetch(`${API_CONFIG.waqi.baseUrl}/feed/geo:${city.lat};${city.lng}/?token=${API_CONFIG.waqi.key}`)
            .then(response => response.json())
        );

        const results = await Promise.all(promises);
        
        results.forEach((result, index) => {
            if (result.data && result.data.aqi) {
                pollutionData.push({
                    lat: MAJOR_CITIES[index].lat,
                    lng: MAJOR_CITIES[index].lng,
                    value: result.data.aqi,
                    city: MAJOR_CITIES[index].name
                });
            }
        });

        return pollutionData;
    } catch (error) {
        console.error('Error fetching air quality data:', error);
        return sampleLayerData.pollution;
    }
}

// Fetch real cost of living data from Numbeo
async function fetchCostData() {
    const costData = [];
    
    try {
        const promises = MAJOR_CITIES.map(city =>
            fetch(`${API_CONFIG.numbeo.baseUrl}/city_prices?api_key=${API_CONFIG.numbeo.key}&query=${encodeURIComponent(city.name)}`)
            .then(response => response.json())
        );

        const results = await Promise.all(promises);
        
        results.forEach((result, index) => {
            if (result.prices) {
                // Calculate average monthly cost based on various factors
                const rentIndex = result.prices.find(p => p.item_name === "Apartment (1 bedroom) in City Centre, Rent Per Month");
                const utilityIndex = result.prices.find(p => p.item_name === "Basic utilities for 85m2 Apartment including Electricity, Heating or Cooling, Water and Garbage");
                
                const monthlyCost = (rentIndex?.average_price || 0) + (utilityIndex?.average_price || 0);
                
                costData.push({
                    lat: MAJOR_CITIES[index].lat,
                    lng: MAJOR_CITIES[index].lng,
                    value: monthlyCost,
                    city: MAJOR_CITIES[index].name
                });
            }
        });

        return costData;
    } catch (error) {
        console.error('Error fetching cost data:', error);
        return sampleLayerData.cost;
    }
}

// Helper function to get color from value
function getColor(value, scale) {
    const colors = {
        temperature: [
            [0, '#0000ff'],   // Cold
            [15, '#00ff00'],  // Mild
            [30, '#ff0000']   // Hot
        ],
        cost: [
            [800, '#00ff00'],  // Low cost
            [1000, '#ffaa00'], // Medium cost
            [1200, '#ff0000']  // High cost
        ],
        pollution: [
            [0, '#00ff00'],    // Low pollution
            [50, '#ffaa00'],   // Medium pollution
            [100, '#ff0000']   // High pollution
        ],
        crime: [
            [30, '#00ff00'],    // Low crime
            [60, '#ffaa00'],    // Medium crime
            [90, '#ff0000']     // High crime
        ],
        parking: [
        ]
    };
    
    const colorScale = colors[scale];
    for (let i = 0; i < colorScale.length - 1; i++) {
        if (value <= colorScale[i][0]) return colorScale[i][1];
        if (value < colorScale[i + 1][0]) {
            const ratio = (value - colorScale[i][0]) / (colorScale[i + 1][0] - colorScale[i][0]);
            return interpolateColor(colorScale[i][1], colorScale[i + 1][1], ratio);
        }
    }
    return colorScale[colorScale.length - 1][1];
}

// Helper function to interpolate between two colors
function interpolateColor(color1, color2, ratio) {
    const r1 = parseInt(color1.substring(1, 3), 16);
    const g1 = parseInt(color1.substring(3, 5), 16);
    const b1 = parseInt(color1.substring(5, 7), 16);
    const r2 = parseInt(color2.substring(1, 3), 16);
    const g2 = parseInt(color2.substring(3, 5), 16);
    const b2 = parseInt(color2.substring(5, 7), 16);
    
    const r = Math.round(r1 + (r2 - r1) * ratio);
    const g = Math.round(g1 + (g2 - g1) * ratio);
    const b = Math.round(b1 + (b2 - b1) * ratio);
    
    return '#' + [r, g, b].map(x => {
        const hex = x.toString(16);
        return hex.length === 1 ? '0' + hex : hex;
    }).join('');
}

// Helper: return true dacă un punct e în raza dată față de centru
function isPointInRadius(point, center, radiusKm) {
    const [lat, lng] = point;
    const d = getDistance(lat, lng, center.lat, center.lng);
    return d <= radiusKm;
}

// Pinuri reale pentru shops și parkings (exemplu pentru câteva județe)
const PARKINGS_BY_COUNTY = {
    'București': [
        { lat: 44.439663, lng: 26.096306, name: 'Parking Universitate' },
        { lat: 44.43225, lng: 26.10626, name: 'Parking Intercontinental' },
        { lat: 44.426767, lng: 26.102538, name: 'Parking Piața Unirii' },
        { lat: 44.435, lng: 26.048, name: 'Parking Plaza România' },
        { lat: 44.447, lng: 26.097, name: 'Parking Piața Romană' },
        { lat: 44.420, lng: 26.110, name: 'Parking Tineretului' },
        { lat: 44.426, lng: 26.143, name: 'Parking Mega Mall' },
        { lat: 44.414, lng: 26.102, name: 'Parking Sun Plaza' },
        { lat: 44.462, lng: 26.085, name: 'Parking Colosseum Mall' },
        { lat: 44.437, lng: 26.124, name: 'Parking Arena Națională' }
    ],
    'Cluj': [
        { lat: 46.7712, lng: 23.6236, name: 'Parking Piața Unirii' },
        { lat: 46.770, lng: 23.589, name: 'Parking Iulius Mall' },
        { lat: 46.765, lng: 23.577, name: 'Parking Vivo!' },
        { lat: 46.773, lng: 23.620, name: 'Parking Central' },
        { lat: 46.771, lng: 23.608, name: 'Parking Polus' },
        { lat: 46.769, lng: 23.594, name: 'Parking Expo' },
        { lat: 46.774, lng: 23.625, name: 'Parking Avram Iancu' },
        { lat: 46.772, lng: 23.630, name: 'Parking Opera' },
        { lat: 46.768, lng: 23.622, name: 'Parking Memo' },
        { lat: 46.775, lng: 23.610, name: 'Parking Zorilor' }
    ],
    'Iași': [
        { lat: 47.1585, lng: 27.6014, name: 'Parking Palas Mall' },
        { lat: 47.162, lng: 27.588, name: 'Parking Iulius Mall' },
        { lat: 47.167, lng: 27.601, name: 'Parking Copou' },
        { lat: 47.154, lng: 27.590, name: 'Parking Nicolina' },
        { lat: 47.158, lng: 27.620, name: 'Parking Tătărași' },
        { lat: 47.160, lng: 27.605, name: 'Parking Podu Roș' },
        { lat: 47.155, lng: 27.610, name: 'Parking Gara' },
        { lat: 47.165, lng: 27.595, name: 'Parking Păcurari' },
        { lat: 47.150, lng: 27.600, name: 'Parking CUG' },
        { lat: 47.170, lng: 27.610, name: 'Parking Bucium' }
    ],
    'Timiș': [
        { lat: 45.7489, lng: 21.2087, name: 'Parking Piața Victoriei' },
        { lat: 45.753, lng: 21.225, name: 'Parking Iulius Town' },
        { lat: 45.750, lng: 21.230, name: 'Parking Shopping City' },
        { lat: 45.755, lng: 21.220, name: 'Parking Bega' },
        { lat: 45.748, lng: 21.210, name: 'Parking Unirii' },
        { lat: 45.752, lng: 21.215, name: 'Parking Maria' },
        { lat: 45.747, lng: 21.205, name: 'Parking Circumvalațiunii' },
        { lat: 45.749, lng: 21.218, name: 'Parking Bastion' },
        { lat: 45.751, lng: 21.212, name: 'Parking Catedrala' },
        { lat: 45.754, lng: 21.208, name: 'Parking Traian' }
    ],
    'Constanța': [
        { lat: 44.1733, lng: 28.6383, name: 'Parking City Park Mall' },
        { lat: 44.180, lng: 28.634, name: 'Parking Vivo!' },
        { lat: 44.170, lng: 28.650, name: 'Parking Tomis Mall' },
        { lat: 44.175, lng: 28.630, name: 'Parking Port' },
        { lat: 44.172, lng: 28.640, name: 'Parking Ovidiu' },
        { lat: 44.168, lng: 28.645, name: 'Parking Delfinariu' },
        { lat: 44.178, lng: 28.638, name: 'Parking Gara' },
        { lat: 44.176, lng: 28.642, name: 'Parking Faleză' },
        { lat: 44.174, lng: 28.636, name: 'Parking Cazino' },
        { lat: 44.171, lng: 28.648, name: 'Parking Mamaia' }
    ],
    'Brașov': [
        { lat: 45.6427, lng: 25.5887, name: 'Parking Modarom' },
        { lat: 45.646, lng: 25.601, name: 'Parking AFI Mall' },
        { lat: 45.640, lng: 25.590, name: 'Parking Gara' },
        { lat: 45.650, lng: 25.595, name: 'Parking Coresi' },
        { lat: 45.644, lng: 25.585, name: 'Parking Tampa' },
        { lat: 45.648, lng: 25.593, name: 'Parking Livada Poștei' },
        { lat: 45.641, lng: 25.600, name: 'Parking Bartolomeu' },
        { lat: 45.643, lng: 25.589, name: 'Parking Tractorul' },
        { lat: 45.645, lng: 25.587, name: 'Parking Astra' },
        { lat: 45.647, lng: 25.591, name: 'Parking Poiana' }
    ]
};

const SHOPS_BY_COUNTY = {
    'București': [
        { lat: 44.435, lng: 26.102, name: 'Mega Image' },
        { lat: 44.430, lng: 26.110, name: 'Carrefour' },
        { lat: 44.440, lng: 26.120, name: 'Lidl' },
        { lat: 44.445, lng: 26.100, name: 'Kaufland' },
        { lat: 44.425, lng: 26.130, name: 'Auchan' },
        { lat: 44.428, lng: 26.105, name: 'Profi' },
        { lat: 44.432, lng: 26.115, name: 'Penny' },
        { lat: 44.438, lng: 26.125, name: 'Cora' },
        { lat: 44.442, lng: 26.108, name: 'Shop&Go' },
        { lat: 44.420, lng: 26.140, name: 'Obor Market' }
    ],
    'Cluj': [
        { lat: 46.771, lng: 23.623, name: 'Sigma Center' },
        { lat: 46.770, lng: 23.589, name: 'Iulius Mall' },
        { lat: 46.765, lng: 23.577, name: 'Vivo!' },
        { lat: 46.773, lng: 23.620, name: 'Central' },
        { lat: 46.771, lng: 23.608, name: 'Polus' },
        { lat: 46.769, lng: 23.594, name: 'Expo' },
        { lat: 46.774, lng: 23.625, name: 'Avram Iancu' },
        { lat: 46.772, lng: 23.630, name: 'Opera' },
        { lat: 46.768, lng: 23.622, name: 'Memo' },
        { lat: 46.765, lng: 23.610, name: 'Piața Mărăști' }
    ],
    'Iași': [
        { lat: 47.158, lng: 27.601, name: 'Palas Mall' },
        { lat: 47.162, lng: 27.588, name: 'Iulius Mall' },
        { lat: 47.167, lng: 27.601, name: 'Copou Market' },
        { lat: 47.154, lng: 27.590, name: 'Nicolina Profi' },
        { lat: 47.158, lng: 27.620, name: 'Tătărași Shop' },
        { lat: 47.160, lng: 27.605, name: 'Podu Roș Market' },
        { lat: 47.155, lng: 27.610, name: 'Gara Shop' },
        { lat: 47.165, lng: 27.595, name: 'Păcurari Market' },
        { lat: 47.150, lng: 27.600, name: 'CUG Profi' },
        { lat: 47.170, lng: 27.615, name: 'Bucium Shop' }
    ],
    'Timiș': [
        { lat: 45.748, lng: 21.208, name: 'Iulius Town' },
        { lat: 45.753, lng: 21.225, name: 'Shopping City' },
        { lat: 45.750, lng: 21.230, name: 'Bega Market' },
        { lat: 45.755, lng: 21.220, name: 'Unirii Shop' },
        { lat: 45.748, lng: 21.210, name: 'Maria Profi' },
        { lat: 45.752, lng: 21.215, name: 'Circumvalațiunii Shop' },
        { lat: 45.747, lng: 21.205, name: 'Bastion Market' },
        { lat: 45.749, lng: 21.218, name: 'Catedrala Shop' },
        { lat: 45.751, lng: 21.212, name: 'Traian Shop' },
        { lat: 45.754, lng: 21.202, name: 'Giroc Market' }
    ],
    'Constanța': [
        { lat: 44.173, lng: 28.638, name: 'City Park Mall' },
        { lat: 44.180, lng: 28.634, name: 'Vivo!' },
        { lat: 44.170, lng: 28.650, name: 'Tomis Mall' },
        { lat: 44.175, lng: 28.630, name: 'Port Shop' },
        { lat: 44.172, lng: 28.640, name: 'Ovidiu Market' },
        { lat: 44.168, lng: 28.645, name: 'Delfinariu Shop' },
        { lat: 44.178, lng: 28.638, name: 'Gara Shop' },
        { lat: 44.176, lng: 28.642, name: 'Faleză Shop' },
        { lat: 44.174, lng: 28.636, name: 'Cazino Shop' },
        { lat: 44.171, lng: 28.648, name: 'Mamaia Shop' }
    ],
    'Brașov': [
        { lat: 45.642, lng: 25.588, name: 'Modarom Shop' },
        { lat: 45.646, lng: 25.601, name: 'AFI Mall' },
        { lat: 45.640, lng: 25.590, name: 'Gara Shop' },
        { lat: 45.650, lng: 25.595, name: 'Coresi Shop' },
        { lat: 45.644, lng: 25.585, name: 'Tampa Shop' },
        { lat: 45.648, lng: 25.593, name: 'Livada Poștei Shop' },
        { lat: 45.641, lng: 25.600, name: 'Bartolomeu Shop' },
        { lat: 45.643, lng: 25.589, name: 'Tractorul Shop' },
        { lat: 45.645, lng: 25.587, name: 'Astra Shop' },
        { lat: 45.649, lng: 25.581, name: 'Poiana Shop' }
    ]
};

// Modific initializeLayers pentru a afișa pinuri doar în zona selectată
function initializeLayers(center = null, radiusKm = null) {
    // Clear existing layers
    Object.values(layers).forEach(layer => layer.clearLayers());

    // Helper function pentru heatmap/gradient
    function createHeatmapLayer(dataPoints, valueKey, colorScale, unit) {
        const points = [];
        gridPoints.forEach(point => {
            // Dacă avem centru și rază, desenăm doar în acea zonă
            if (
                isPointInRomaniaBounds(point) &&
                (!center || !radiusKm || isPointInRadius(point, center, radiusKm))
            ) {
                const value = interpolateValue(point, dataPoints, maxDistance);
                if (value !== null) {
                    points.push({
                        lat: point[0],
                        lng: point[1],
                        value: value
                    });
                }
            }
        });
        // Create overlapping circles for smooth effect
        points.forEach(point => {
            const color = getColor(point.value, colorScale);
            for (let radius = 2000; radius <= 10000; radius += 2000) {
                L.circle([point.lat, point.lng], {
                    radius: radius,
                    color: color,
                    fillColor: color,
                    fillOpacity: 0.1,
                    weight: 0,
                    className: 'blur-circle'
                }).bindPopup(`${valueKey}: ${point.value.toFixed(1)}${unit}`).addTo(layers[colorScale]);
            }
        });
    }

    // Temperatură
    createHeatmapLayer(sampleLayerData.temperature, 'Temperature', 'temperature', '°C');
    // Cost of living
    createHeatmapLayer(sampleLayerData.cost, 'Cost of Living', 'cost', ' RON/lună');
    // Poluare
    createHeatmapLayer(sampleLayerData.pollution, 'Air Quality Index', 'pollution', '');
    // Crime
    gridPoints.forEach(point => {
        if (
            isPointInRomaniaBounds(point) &&
            (!center || !radiusKm || isPointInRadius(point, center, radiusKm))
        ) {
            const value = Math.floor(Math.random() * (90 - 30 + 1)) + 30;
            const color = getColor(value, 'crime');
            for (let radius = 2000; radius <= 10000; radius += 2000) {
                L.circle([point[0], point[1]], {
                    radius: radius,
                    color: color,
                    fillColor: color,
                    fillOpacity: 0.1,
                    weight: 0,
                    className: 'blur-circle'
                }).bindPopup(`Crime Reports: ${value}/lună`).addTo(layers.crime);
            }
        }
    });
    // Crowding
    gridPoints.forEach(point => {
        if (
            isPointInRomaniaBounds(point) &&
            (!center || !radiusKm || isPointInRadius(point, center, radiusKm))
        ) {
            const value = Math.floor(Math.random() * (90 - 40 + 1)) + 40;
            const color = value > 75 ? '#ff0000' : value > 50 ? '#ffaa00' : '#00ff00';
            for (let radius = 2000; radius <= 10000; radius += 2000) {
                L.circle([point[0], point[1]], {
                    radius: radius,
                    color: color,
                    fillColor: color,
                    fillOpacity: 0.1,
                    weight: 0,
                    className: 'blur-circle'
                }).bindPopup(`Crowding Level: ${value}%`).addTo(layers.crowd);
            }
        }
    });
    // Parking
    const parkingIcon = L.divIcon({
        className: 'custom-marker parking-marker',
        html: '<div class="marker-content">P</div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
    });
    // Shops
    const shopIcon = L.divIcon({
        className: 'custom-marker shop-marker',
        html: '<div class="marker-content">S</div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
    });

    // Helper pentru pinuri într-o zonă
    function pinsInArea(pins, center, radiusKm) {
        if (!center || !radiusKm) return pins;
        return pins.filter(pin => getDistance(pin.lat, pin.lng, center.lat, center.lng) <= radiusKm);
    }

    // Parking
    if (center && radiusKm && selectedCounty && PARKINGS_BY_COUNTY[selectedCounty]) {
        pinsInArea(PARKINGS_BY_COUNTY[selectedCounty], center, radiusKm).forEach(loc => {
            L.marker([loc.lat, loc.lng], { icon: parkingIcon })
                .bindPopup(`Parking: ${loc.name}`)
                .addTo(layers.parking);
        });
    } else {
        // Toate județele: afișează toate pinurile din toate județele
        Object.keys(PARKINGS_BY_COUNTY).forEach(county => {
            PARKINGS_BY_COUNTY[county].forEach(loc => {
                L.marker([loc.lat, loc.lng], { icon: parkingIcon })
                    .bindPopup(`Parking: ${loc.name}`)
                    .addTo(layers.parking);
            });
        });
    }
    // Shops
    if (center && radiusKm && selectedCounty && SHOPS_BY_COUNTY[selectedCounty]) {
        pinsInArea(SHOPS_BY_COUNTY[selectedCounty], center, radiusKm).forEach(loc => {
            L.marker([loc.lat, loc.lng], { icon: shopIcon })
                .bindPopup(`Shop: ${loc.name}`)
                .addTo(layers.shops);
        });
    } else {
        Object.keys(SHOPS_BY_COUNTY).forEach(county => {
            SHOPS_BY_COUNTY[county].forEach(loc => {
                L.marker([loc.lat, loc.lng], { icon: shopIcon })
                    .bindPopup(`Shop: ${loc.name}`)
                    .addTo(layers.shops);
            });
        });
    }
}

// Variabile globale pentru județ selectat
let selectedCounty = null;
let selectedCountyCenter = null;
let selectedCountyRadius = null;

// Dropdown județ: actualizează zona selectată și redesenează layerele
const countyDropdown = document.getElementById('filter-county');
countyDropdown.addEventListener('change', function() {
    const county = this.value;
    if (county && COUNTY_CENTERS[county]) {
        selectedCounty = county;
        selectedCountyCenter = COUNTY_CENTERS[county];
        selectedCountyRadius = 20;
    } else {
        selectedCounty = null;
        selectedCountyCenter = null;
        selectedCountyRadius = null;
    }
    initializeLayers(selectedCountyCenter, selectedCountyRadius);
});

// Layer toggle: redesenează layerele cu zona selectată
// (asigură-te că layerele vizibile sunt reafișate corect)
document.querySelectorAll('.layer-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        // La orice toggle, redesenează layerele cu zona selectată
        initializeLayers(selectedCountyCenter, selectedCountyRadius);
        // Afișează/ascunde layerele în funcție de checkbox
        Object.keys(layers).forEach(layerName => {
            if (document.getElementById('layer-' + layerName)?.checked) {
            map.addLayer(layers[layerName]);
        } else {
            map.removeLayer(layers[layerName]);
        }
        });
    });
});

// La pornire, layerele se afișează pe toată țara
initializeLayers();

// Update the CSS styles
const style = document.createElement('style');
style.textContent = `
    .blur-circle {
        filter: blur(5px);
        -webkit-filter: blur(5px);
    }
    
    .layer-controls {
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        background-color: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }
`;
document.head.appendChild(style);

// Initialize all map layers
initializeLayers();

// Layer toggle event listeners
document.querySelectorAll('.layer-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const layerName = this.id.replace('layer-', '');
        if (this.checked) {
            map.addLayer(layers[layerName]);
        } else {
            map.removeLayer(layers[layerName]);
        }
    });
});

// Property markers group
const markers = L.layerGroup().addTo(map);

// Variabilă globală pentru proprietăți
let allProperties = [];

// Modifică loadProperties să salveze proprietățile
async function loadProperties() {
    try {
        const response = await fetch('/api/properties');
        const properties = await response.json();
        allProperties = properties;
        sortAndDisplayProperties();
    } catch (error) {
        console.error('Error loading properties:', error);
    }
}

// Funcție de sortare și afișare
function sortAndDisplayProperties() {
    const sortValue = document.getElementById('sortProperties').value;
    let sorted = [...allProperties];
    switch (sortValue) {
        case 'price-asc':
            sorted.sort((a, b) => a.price - b.price);
            break;
        case 'price-desc':
            sorted.sort((a, b) => b.price - a.price);
            break;
        case 'area-asc':
            sorted.sort((a, b) => a.area - b.area);
            break;
        case 'area-desc':
            sorted.sort((a, b) => b.area - a.area);
            break;
        case 'newest':
        default:
            sorted.sort((a, b) => b.id - a.id); // presupunem că id mai mare = mai nou
            break;
    }
    displayProperties(sorted);
}

// Event listener pentru sortare
const sortDropdown = document.getElementById('sortProperties');
sortDropdown.addEventListener('change', sortAndDisplayProperties);

// Display properties on map and in list
function displayProperties(properties) {
    // Clear existing markers and list
    markers.clearLayers();
    const propertiesContainer = document.getElementById('properties');
    propertiesContainer.innerHTML = '';
    // Add property markers to map and list
    properties.forEach(property => {
        // Add marker to map
        const marker = L.marker([property.latitude, property.longitude])
            .bindPopup(`
                <h3>${escapeHtml(property.title)}</h3>
                <p><strong>Price:</strong> $${Number(property.price).toLocaleString()}</p>
                <p><strong>Type:</strong> ${property.type === 'sale' ? 'For Sale' : 'For Rent'}</p>
                <p><strong>Area:</strong> ${property.area} m²</p>
                <a href="#" class="view-property" data-id="${property.id}">View Details</a>
            `);
        markers.addLayer(marker);
        // Add property to list
        const propertyEl = document.createElement('div');
        propertyEl.className = 'property-card';
        propertyEl.dataset.id = property.id;
        // Create property card HTML (use textContent for user data)
        const title = document.createElement('h3');
        title.textContent = property.title;
        const price = document.createElement('p');
        price.innerHTML = `<strong>Price:</strong> $${Number(property.price).toLocaleString()}`;
        const type = document.createElement('p');
        type.innerHTML = `<strong>Type:</strong> ${property.type === 'sale' ? 'For Sale' : 'For Rent'}`;
        const propertyType = document.createElement('p');
        propertyType.innerHTML = `<strong>Property Type:</strong> ${escapeHtml(property.property_type)}`;
        const area = document.createElement('p');
        area.innerHTML = `<strong>Area:</strong> ${property.area} m²`;
        const actions = document.createElement('div');
        actions.className = 'property-actions';
        const viewBtn = document.createElement('button');
        viewBtn.className = 'view-property';
        viewBtn.dataset.id = property.id;
        viewBtn.textContent = 'View Details';
        actions.appendChild(viewBtn);
        if (canEditProperty(property.owner_id)) {
            const delBtn = document.createElement('button');
            delBtn.className = 'delete-property';
            delBtn.dataset.id = property.id;
            delBtn.textContent = 'Delete';
            actions.appendChild(delBtn);
        }
        propertyEl.appendChild(title);
        propertyEl.appendChild(price);
        propertyEl.appendChild(type);
        propertyEl.appendChild(propertyType);
        propertyEl.appendChild(area);
        propertyEl.appendChild(actions);
        propertiesContainer.appendChild(propertyEl);
    });
    // Add event listeners to view and delete buttons
    document.querySelectorAll('.view-property').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            // Find the property by id
            const property = allProperties.find(p => String(p.id) === String(id));
            if (property) {
                // Fill modal with property details
                const modal = document.getElementById('propertyDetailsModal');
                const body = document.getElementById('propertyDetailsBody');
                body.innerHTML = `
                  <div class="details-list">
                    <h3>${escapeHtml(property.title)}</h3>
                    <p><strong>Description:</strong> ${escapeHtml(property.description || '')}</p>
                    <p><strong>Price:</strong> $${Number(property.price).toLocaleString()}</p>
                    <p><strong>Type:</strong> ${property.type === 'sale' ? 'For Sale' : 'For Rent'}</p>
                    <p><strong>Property Type:</strong> ${escapeHtml(property.property_type || '')}</p>
                    <p><strong>Area:</strong> ${property.area} m²</p>
                    <p><strong>Building Condition:</strong> ${escapeHtml(property.building_condition || '')}</p>
                    <p><strong>Facilities:</strong> ${escapeHtml(property.facilities || '')}</p>
                    <p><strong>Risks:</strong> ${escapeHtml(property.risks || '')}</p>
                    <p><strong>Contact Info:</strong> ${escapeHtml(property.contact_info || '')}</p>
                    <p><strong>Latitude:</strong> ${property.latitude}</p>
                    <p><strong>Longitude:</strong> ${property.longitude}</p>
                  </div>
                `;
                modal.style.display = 'flex';
            }
        });
    });
    document.querySelectorAll('.delete-property').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            deleteProperty(id);
        });
    });
}

// Utility to escape HTML for XSS prevention
function escapeHtml(str) {
    return String(str).replace(/[&<>'"`=\/]/g, function (s) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','\'':'&#39;','"':'&quot;','`':'&#96;','=':'&#61;','/':'&#47;'}[s]);
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

// Array cu centrele de reședință pentru fiecare județ și București
const COUNTY_CENTERS = {
    'București': { lat: 44.4268, lng: 26.1025 },
    'Alba': { lat: 46.0677, lng: 23.5802 },
    'Arad': { lat: 46.1866, lng: 21.3123 },
    'Argeș': { lat: 44.8565, lng: 24.8692 },
    'Bacău': { lat: 46.5672, lng: 26.9138 },
    'Bihor': { lat: 47.0722, lng: 21.9211 },
    'Bistrița-Năsăud': { lat: 47.1357, lng: 24.4987 },
    'Botoșani': { lat: 47.7486, lng: 26.6694 },
    'Brăila': { lat: 45.2692, lng: 27.9575 },
    'Brașov': { lat: 45.6526, lng: 25.6012 },
    'Buzău': { lat: 45.1508, lng: 26.8231 },
    'Caraș-Severin': { lat: 45.2977, lng: 21.8865 },
    'Călărași': { lat: 44.2017, lng: 27.3257 },
    'Cluj': { lat: 46.7712, lng: 23.6236 },
    'Constanța': { lat: 44.1733, lng: 28.6383 },
    'Covasna': { lat: 45.8609, lng: 25.7877 },
    'Dâmbovița': { lat: 44.9256, lng: 25.4560 },
    'Dolj': { lat: 44.3302, lng: 23.7949 },
    'Galați': { lat: 45.4353, lng: 28.0518 },
    'Giurgiu': { lat: 43.9037, lng: 25.9699 },
    'Gorj': { lat: 45.0456, lng: 23.2745 },
    'Harghita': { lat: 46.3598, lng: 25.8016 },
    'Hunedoara': { lat: 45.7500, lng: 22.9000 },
    'Ialomița': { lat: 44.5646, lng: 27.3667 },
    'Iași': { lat: 47.1585, lng: 27.6014 },
    'Ilfov': { lat: 44.5355, lng: 26.1584 },
    'Maramureș': { lat: 47.6573, lng: 23.5681 },
    'Mehedinți': { lat: 44.6369, lng: 22.6597 },
    'Mureș': { lat: 46.5425, lng: 24.5575 },
    'Neamț': { lat: 46.9276, lng: 26.3700 },
    'Olt': { lat: 44.4307, lng: 24.3717 },
    'Prahova': { lat: 44.9362, lng: 25.4597 },
    'Sălaj': { lat: 47.1790, lng: 23.0574 },
    'Satu Mare': { lat: 47.7928, lng: 22.8857 },
    'Sibiu': { lat: 45.7983, lng: 24.1469 },
    'Suceava': { lat: 47.6514, lng: 26.2556 },
    'Teleorman': { lat: 43.9766, lng: 25.3286 },
    'Timiș': { lat: 45.7489, lng: 21.2087 },
    'Tulcea': { lat: 45.1841, lng: 28.8056 },
    'Vâlcea': { lat: 45.1081, lng: 24.3755 },
    'Vaslui': { lat: 46.6407, lng: 27.7276 },
    'Vrancea': { lat: 45.6966, lng: 27.1868 }
};

// Modific filtrarea la submit pe filterForm
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
    
    // Filtru județ
    const county = document.getElementById('filter-county').value;
    if (county && COUNTY_CENTERS[county]) {
        params.append('county', county);
        params.append('center_lat', COUNTY_CENTERS[county].lat);
        params.append('center_lng', COUNTY_CENTERS[county].lng);
        params.append('radius_km', 20);
        // Mută harta pe centrul județului
        map.setView([COUNTY_CENTERS[county].lat, COUNTY_CENTERS[county].lng], 11);
    }
    
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
    selectedCounty = null;
    selectedCountyCenter = null;
    selectedCountyRadius = null;
    // redesenează layerele pe toată țara
    initializeLayers();
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

// Property Details Modal close logic
const propertyDetailsModal = document.getElementById('propertyDetailsModal');
document.querySelector('.details-close').addEventListener('click', function() {
    propertyDetailsModal.style.display = 'none';
});
window.addEventListener('click', function(e) {
    if (e.target === propertyDetailsModal) {
        propertyDetailsModal.style.display = 'none';
    }
});
