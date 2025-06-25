-- REMS Demo Data
-- Sample properties for testing map functionality

-- Demo properties with Romania coordinates
INSERT INTO properties (
    user_id, title, slug, description, property_type, transaction_type, 
    price, currency, address, city, postal_code, county, country,
    latitude, longitude, surface_total, surface_useful, rooms, bedrooms, bathrooms,
    floor, total_floors, construction_year, condition_type, heating_type,
    parking_spaces, utilities, amenities, status, featured, created_at, updated_at
) VALUES 
-- Bucharest Properties
(1, 'Apartament 3 camere Herastrau', 'apartament-3-camere-herastrau', 
 'Apartament modern cu 3 camere în zona Herastrau, cu vedere la parc. Complet mobilat și utilat, gata de mutat.', 
 'apartment', 'sale', 185000, 'EUR', 'Șoseaua Nordului 15', 'București', '014104', 'București', 'România',
 44.4795, 26.0894, 85, 75, 3, 2, 1, 4, 8, 2018, 'new', 'central', 1,
 '["electricity","water","gas","internet","cable"]', '["elevator","parking","balcony","air_conditioning"]', 
 'active', true, datetime('now'), datetime('now')),

(1, 'Penthouse de lux Primaverii', 'penthouse-lux-primaverii',
 'Penthouse spectaculos cu 4 camere și terasă de 50mp în zona Primaverii. Finisaje premium și vedere panoramică.',
 'penthouse', 'sale', 420000, 'EUR', 'Calea Primaverii 22', 'București', '013975', 'București', 'România',
 44.4739, 26.0894, 150, 120, 4, 3, 2, 12, 12, 2020, 'new', 'central', 2,
 '["electricity","water","gas","internet","cable"]', '["elevator","parking","terrace","air_conditioning","security"]',
 'active', true, datetime('now'), datetime('now')),

(1, 'Apartament 2 camere Floreasca', 'apartament-2-camere-floreasca',
 'Apartament confortabil cu 2 camere în zona Floreasca, aproape de metrou și centre comerciale.',
 'apartment', 'rent', 800, 'EUR', 'Strada Barbu Văcărescu 201', 'București', '020276', 'București', 'România',
 44.4856, 26.1026, 65, 55, 2, 1, 1, 7, 10, 2015, 'renovated', 'central', 1,
 '["electricity","water","gas","internet"]', '["elevator","parking","balcony"]',
 'active', false, datetime('now'), datetime('now')),

-- Cluj-Napoca Properties
(1, 'Casa individuala Grigorescu', 'casa-individuala-grigorescu',
 'Casă frumoasă cu 5 camere în cartierul Grigorescu, cu grădină și garaj. Zonă liniștită și verde.',
 'house', 'sale', 280000, 'EUR', 'Strada Memorandumului 45', 'Cluj-Napoca', '400114', 'Cluj', 'România',
 46.7712, 23.6236, 180, 150, 5, 4, 2, 0, 2, 2010, 'good', 'gas', 2,
 '["electricity","water","gas","internet"]', '["garden","garage","basement"]',
 'active', true, datetime('now'), datetime('now')),

(1, 'Apartament 1 camera Centru', 'apartament-1-camera-centru-cluj',
 'Studio modern în centrul Clujului, perfect pentru tineri sau investiție. Complet mobilat.',
 'studio', 'rent', 450, 'EUR', 'Strada Universitații 12', 'Cluj-Napoca', '400091', 'Cluj', 'România',
 46.7693, 23.5890, 35, 30, 1, 1, 1, 3, 5, 2019, 'new', 'central', 0,
 '["electricity","water","gas","internet","cable"]', '["elevator","air_conditioning"]',
 'active', false, datetime('now'), datetime('now')),

-- Constanța Properties
(1, 'Vila cu piscina Mamaia', 'vila-piscina-mamaia',
 'Vilă luxoasă cu piscină și vedere la mare în Mamaia. Perfectă pentru vacanțe sau închiriere sezonieră.',
 'villa', 'sale', 650000, 'EUR', 'Strada Remus Opreanu 8', 'Constanța', '900001', 'Constanța', 'România',
 44.1621, 28.6348, 300, 250, 6, 5, 3, 0, 3, 2017, 'new', 'electric', 3,
 '["electricity","water","gas","internet","cable"]', '["pool","garden","terrace","garage","security"]',
 'active', true, datetime('now'), datetime('now')),

-- Brașov Properties  
(1, 'Apartament 3 camere Tampa', 'apartament-3-camere-tampa-brasov',
 'Apartament spațios cu 3 camere și vedere la Tampa. Zona foarte căutată, aproape de centrul istoric.',
 'apartment', 'sale', 145000, 'EUR', 'Strada Postăvarului 15', 'Brașov', '500001', 'Brașov', 'România',
 45.6427, 25.5887, 78, 68, 3, 2, 1, 2, 4, 2016, 'good', 'gas', 1,
 '["electricity","water","gas","internet"]', '["parking","balcony","mountain_view"]',
 'active', false, datetime('now'), datetime('now')),

-- Timișoara Properties
(1, 'Casa renovata Fabric', 'casa-renovata-fabric-timisoara',
 'Casă istorică complet renovată în cartierul Fabric. Arhitectură autentică cu facilități moderne.',
 'house', 'sale', 195000, 'EUR', 'Strada Clemenceau 24', 'Timișoara', '300011', 'Timiș', 'România',
 45.7597, 21.2137, 120, 100, 4, 3, 2, 0, 2, 1920, 'renovated', 'gas', 1,
 '["electricity","water","gas","internet"]', '["garden","basement","historical"]',
 'active', false, datetime('now'), datetime('now')),

-- Iași Properties
(1, 'Apartament 2 camere Copou', 'apartament-2-camere-copou-iasi',
 'Apartament cu 2 camere în zona Copou, aproape de universități. Ideal pentru studenți sau tineri.',
 'apartment', 'rent', 350, 'EUR', 'Bulevardul Carol I 15', 'Iași', '700505', 'Iași', 'România', 
 47.1615, 27.5837, 55, 48, 2, 1, 1, 5, 8, 2014, 'good', 'central', 0,
 '["electricity","water","gas","internet"]', '["elevator","balcony"]',
 'active', false, datetime('now'), datetime('now')),

-- Sibiu Properties
(1, 'Casa saseasca Centru Istoric', 'casa-saseasca-centru-istoric-sibiu',
 'Casă săsească autentică în centrul istoric al Sibiului. Monument istoric cu potențial turistic.',
 'house', 'sale', 320000, 'EUR', 'Strada Cetății 18', 'Sibiu', '550160', 'Sibiu', 'România',
 45.7983, 24.1256, 200, 180, 6, 4, 2, 0, 3, 1650, 'renovated', 'gas', 0,
 '["electricity","water","gas","internet"]', '["historical","basement","courtyard"]',
 'active', true, datetime('now'), datetime('now')),

-- Oradea Properties  
(1, 'Apartament 3 camere Rogerius', 'apartament-3-camere-rogerius-oradea',
 'Apartament modern cu 3 camere în ansamblul Rogerius. Finisaje de calitate și zonă verde.',
 'apartment', 'sale', 89000, 'EUR', 'Strada Rogerius 45', 'Oradea', '410203', 'Bihor', 'România',
 47.0379, 21.9294, 72, 62, 3, 2, 1, 1, 4, 2021, 'new', 'central', 1,
 '["electricity","water","gas","internet","cable"]', '["elevator","parking","playground"]',
 'active', false, datetime('now'), datetime('now')),

-- Craiova Properties
(1, 'Vila cu gradina Craiovita', 'vila-gradina-craiovita-craiova',
 'Vilă spațioasă cu grădină mare în zona Craiovița. Perfectă pentru familii cu copii.',
 'villa', 'sale', 175000, 'EUR', 'Strada Craiovița 78', 'Craiova', '200177', 'Dolj', 'România',
 44.3302, 23.7949, 160, 140, 5, 4, 2, 0, 2, 2008, 'good', 'gas', 2,
 '["electricity","water","gas","internet"]', '["garden","garage","basement"]',
 'active', false, datetime('now'), datetime('now')),

-- Commercial Properties
(1, 'Spatiu comercial Calea Victoriei', 'spatiu-comercial-calea-victoriei',
 'Spațiu comercial premium pe Calea Victoriei. Poziție excelentă pentru business de lux.',
 'commercial', 'rent', 3500, 'EUR', 'Calea Victoriei 104', 'București', '010071', 'București', 'România',
 44.4378, 26.0969, 120, 120, 0, 0, 2, 0, 4, 2005, 'renovated', 'central', 0,
 '["electricity","water","gas","internet"]', '["central_location","display_windows"]',
 'active', true, datetime('now'), datetime('now')),

(1, 'Birou open space Floreasca', 'birou-open-space-floreasca',
 'Birou modern tip open space în clădire nouă. Facilități complete pentru companii.',
 'office', 'rent', 1200, 'EUR', 'Strada Barbu Văcărescu 164', 'București', '020276', 'București', 'România',
 44.4856, 26.1026, 80, 80, 0, 0, 1, 8, 15, 2020, 'new', 'central', 2,
 '["electricity","water","gas","internet","cable"]', '["elevator","parking","air_conditioning","security"]',
 'active', false, datetime('now'), datetime('now')),

-- Land Properties
(1, 'Teren intravilan Baneasa', 'teren-intravilan-baneasa',
 'Teren intravilan cu deschidere la drum, perfect pentru construcție casă. Zona în dezvoltare.',
 'land', 'sale', 85000, 'EUR', 'Șoseaua Bucureștii Noi 156', 'București', '013682', 'București', 'România',
 44.5294, 26.0711, 600, 600, 0, 0, 0, 0, 0, 0, 'undeveloped', 'none', 0,
 '["electricity","water"]', '["development_potential","road_access"]',
 'active', false, datetime('now'), datetime('now'));

-- Insert sample property images
INSERT INTO property_images (property_id, filename, alt_text, is_primary, sort_order, created_at) VALUES
(1, 'apartment_herastrau_1.jpg', 'Living apartament Herastrau', 1, 1, datetime('now')),
(1, 'apartment_herastrau_2.jpg', 'Bucătărie apartament Herastrau', 0, 2, datetime('now')),
(2, 'penthouse_primaverii_1.jpg', 'Living penthouse Primaverii', 1, 1, datetime('now')),
(3, 'apartment_floreasca_1.jpg', 'Living apartament Floreasca', 1, 1, datetime('now')),
(4, 'house_grigorescu_1.jpg', 'Exterior casă Grigorescu', 1, 1, datetime('now')),
(5, 'studio_cluj_1.jpg', 'Interior studio Cluj', 1, 1, datetime('now')),
(6, 'villa_mamaia_1.jpg', 'Exterior vilă Mamaia', 1, 1, datetime('now')),
(7, 'apartment_brasov_1.jpg', 'Living apartament Brașov', 1, 1, datetime('now')),
(8, 'house_timisoara_1.jpg', 'Exterior casă Timișoara', 1, 1, datetime('now')),
(9, 'apartment_iasi_1.jpg', 'Living apartament Iași', 1, 1, datetime('now')),
(10, 'house_sibiu_1.jpg', 'Exterior casă Sibiu', 1, 1, datetime('now')),
(11, 'apartment_oradea_1.jpg', 'Living apartament Oradea', 1, 1, datetime('now')),
(12, 'villa_craiova_1.jpg', 'Exterior vilă Craiova', 1, 1, datetime('now')),
(13, 'commercial_bucuresti_1.jpg', 'Interior spațiu comercial', 1, 1, datetime('now')),
(14, 'office_floreasca_1.jpg', 'Birou open space', 1, 1, datetime('now')),
(15, 'land_baneasa_1.jpg', 'Teren Băneasa', 1, 1, datetime('now'));

-- Insert sample user for properties
INSERT INTO users (name, email, password_hash, role, phone, email_verified_at, created_at, updated_at) VALUES
('Agent Demo', 'agent@rems.ro', '$argon2id$v=19$m=65536,t=4,p=3$demo_hash', 'agent', '+40123456789', datetime('now'), datetime('now'), datetime('now'))
ON CONFLICT(email) DO NOTHING; 