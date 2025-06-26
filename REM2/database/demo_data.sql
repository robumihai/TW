-- =============================================================================
-- REMS - Demo Data
-- Sample data for testing the Real Estate Management System
-- =============================================================================

-- Clear existing data (except configuration)
DELETE FROM property_views;
DELETE FROM property_images;
DELETE FROM favorites;
DELETE FROM search_history;

DELETE FROM properties;
DELETE FROM users WHERE id != 1; -- Keep admin user

-- Insert demo users (password for all is "password123")
INSERT INTO users (id, username, email, password_hash, first_name, last_name, phone, role, status, email_verified, last_login, created_at, updated_at) VALUES
(2, 'maria.popescu', 'maria@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria', 'Popescu', '+40721234567', 'agent', 'active', 1, datetime('now', '-1 day'), datetime('now', '-30 days'), datetime('now', '-1 day')),
(3, 'ion.marinescu', 'ion@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ion', 'Marinescu', '+40722345678', 'agent', 'active', 1, datetime('now', '-2 days'), datetime('now', '-25 days'), datetime('now', '-2 days')),
(4, 'ana.constantinescu', 'ana@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana', 'Constantinescu', '+40723456789', 'agent', 'active', 1, datetime('now', '-3 days'), datetime('now', '-20 days'), datetime('now', '-3 days')),
(5, 'mihai.stoica', 'mihai@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mihai', 'Stoica', '+40724567890', 'user', 'active', 1, datetime('now', '-1 day'), datetime('now', '-15 days'), datetime('now', '-1 day')),
(6, 'elena.vasilescu', 'elena@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Elena', 'Vasilescu', '+40725678901', 'user', 'active', 1, datetime('now', '-2 days'), datetime('now', '-10 days'), datetime('now', '-2 days')),
(7, 'admin', 'admin@rems.ro', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', '+40741234567', 'admin', 'active', 1, datetime('now'), datetime('now', '-60 days'), datetime('now'));

-- Insert demo properties
INSERT INTO properties (
    id, user_id, title, slug, description, property_type, transaction_type, price, currency,
    address, city, county, postal_code, latitude, longitude,
    surface_total, surface_useful, rooms, bedrooms, bathrooms, floor, total_floors,
    construction_year, condition_type, heating_type, parking_spaces,
    utilities, amenities, featured, status, view_count,
    created_at, updated_at
) VALUES

-- Bucuresti Properties
(1, 2, 'Apartament modern 3 camere Floreasca', 'apartament-modern-3-camere-floreasca', 
 'Apartament modern și spațios în zona premium Floreasca. Mobilat și utilat complet, parcare subterană, vedere la Parcul Floreasca. Ideal pentru profesioniști.', 
 'apartment', 'sale', 185000, 'EUR',
 'Strada Floreasca nr. 15', 'București', 'București', '014453', 44.4732, 26.1062,
 85, 78, 3, 2, 1, 4, 8, 2019, 'new', 'central', 1,
 '["gas", "electricity", "water", "sewage", "internet", "cable_tv"]',
 '["parking", "elevator", "balcony", "central_heating", "air_conditioning", "furnished"]',
 1, 'active', 245,
 datetime('now', '-25 days'), datetime('now', '-1 day')),

(2, 3, 'Vila superba Pipera - 5 camere', 'vila-superba-pipera-5-camere',
 'Vila individuală în complexul exclusivist din Pipera. Grădină mare, piscină, garaj pentru 2 mașini. Zona foarte liniștită și sigură cu pază 24/7.',
 'villa', 'sale', 420000, 'EUR',
 'Strada Iancu Nicolae nr. 88', 'București', 'Ilfov', '077190', 44.5142, 26.1396,
 320, 280, 5, 4, 3, 1, 2, 2018, 'very_good', 'central', 2,
 '["gas", "electricity", "water", "sewage", "internet", "cable_tv", "alarm"]',
 '["pool", "garden", "garage", "alarm_system", "air_conditioning", "fully_furnished"]',
 1, 'active', 189,
 datetime('now', '-22 days'), datetime('now', '-2 days')),

(3, 2, 'Garsonieră Universitate - investiție', 'garsoniera-universitate-investitie',
 'Garsonieră complet renovată în zona Universității. Perfectă pentru închiriere studenților sau tineri profesioniști. Randament de închiriere excelent.',
 'studio', 'sale', 65000, 'EUR',
 'Strada Academiei nr. 22', 'București', 'București', '010014', 44.4368, 26.0969,
 28, 25, 1, 0, 1, 2, 4, 1985, 'renovated', 'central', 0,
 '["electricity", "water", "sewage", "internet"]',
 '["elevator", "central_heating", "fully_renovated"]',
 0, 'active', 156,
 datetime('now', '-20 days'), datetime('now', '-3 days')),

(4, 4, 'Apartament 4 camere Herastrau - lux', 'apartament-4-camere-herastrau-lux',
 'Apartament de lux cu vedere la lacul Herăstrău. Finisaje premium, mobilat și utilat complet. Parcare subterană și boxa. Zona exclusivistă.',
 'apartment', 'sale', 350000, 'EUR',
 'Strada Kiseleff nr. 45', 'București', 'București', '011347', 44.4758, 26.0894,
 125, 110, 4, 3, 2, 8, 10, 2020, 'new', 'central', 1,
 '["gas", "electricity", "water", "sewage", "internet", "cable_tv"]',
 '["parking", "elevator", "balcony", "central_heating", "air_conditioning", "luxury_furniture"]',
 1, 'active', 378,
 datetime('now', '-18 days'), datetime('now', '-1 day')),

-- Cluj-Napoca Properties
(5, 2, 'Apartament nou Gheorgheni - 2 camere', 'apartament-nou-gheorgheni-2-camere',
 'Apartament nou în ansamblul rezidențial din Gheorgheni. Parcare inclusă, zonă verde, aproape de Iulius Mall și centru. Ideal pentru cuplu.',
 'apartment', 'sale', 95000, 'EUR',
 'Strada Observatorului nr. 12', 'Cluj-Napoca', 'Cluj', '400000', 46.7712, 23.6236,
 58, 52, 2, 1, 1, 3, 4, 2021, 'new', 'central', 1,
 '["gas", "electricity", "water", "sewage", "internet", "cable_tv"]',
 '["parking", "elevator", "balcony", "central_heating", "green_spaces"]',
 0, 'active', 134,
 datetime('now', '-15 days'), datetime('now', '-4 days')),

(6, 3, 'Casă individuală Florești', 'casa-individuala-floresti',
 'Casă nouă individuală în Florești. 4 camere, grădină mare, garaj, zonă foarte liniștită. La 15 minute de centrul Clujului.',
 'house', 'sale', 145000, 'EUR',
 'Strada Florilor nr. 23', 'Florești', 'Cluj', '407280', 46.7853, 23.5089,
 180, 150, 4, 3, 2, 1, 2, 2022, 'new', 'central', 1,
 '["gas", "electricity", "water", "sewage", "internet"]',
 '["garden", "garage", "central_heating", "concrete_fence"]',
 0, 'active', 89,
 datetime('now', '-12 days'), datetime('now', '-2 days')),

-- Constanta Properties
(7, 4, 'Apartament cu vedere la mare - Mamaia', 'apartament-cu-vedere-la-mare-mamaia',
 'Apartament spectaculos cu vedere frontală la mare în Mamaia. 2 camere, mobilat, balcon mare. Perfect pentru vacanță sau închiriere sezonieră.',
 'apartment', 'sale', 125000, 'EUR',
 'Bulevardul Mamaia nr. 345', 'Constanța', 'Constanța', '900001', 44.2619, 28.6336,
 65, 58, 2, 1, 1, 7, 8, 2015, 'good', 'central', 0,
 '["electricity", "water", "sewage", "internet", "cable_tv"]',
 '["elevator", "balcony", "central_heating", "furnished", "sea_view"]',
 1, 'active', 267,
 datetime('now', '-10 days'), datetime('now', '-1 day')),

(8, 2, 'Vila moderna Mamaia Nord', 'vila-moderna-mamaia-nord',
 'Vila de lux cu piscină în Mamaia Nord. 6 camere, grădină amenajată, garaj dublu. La 200m de plajă. Perfectă pentru famiglia mare.',
 'villa', 'sale', 285000, 'EUR',
 'Strada Neptun nr. 67', 'Năvodari', 'Constanța', '905700', 44.3242, 28.6019,
 250, 220, 6, 4, 3, 1, 2, 2019, 'very_good', 'central', 2,
 '["gas", "electricity", "water", "sewage", "internet", "cable_tv"]',
 '["pool", "garden", "garage", "alarm_system", "air_conditioning"]',
 1, 'active', 198,
 datetime('now', '-8 days'), datetime('now', '-1 day')),

-- Iasi Properties
(9, 3, 'Apartament 3 camere Tatarasi', 'apartament-3-camere-tatarasi',
 'Apartament decomandat în zona Tătărași. Etaj intermediar, balcon închis, parcare. Aproape de școli și transport în comun.',
 'apartment', 'sale', 78000, 'EUR',
 'Strada Tătărași nr. 15', 'Iași', 'Iași', '700259', 47.1615, 27.5889,
 70, 62, 3, 2, 1, 5, 10, 2005, 'good', 'central', 0,
 '["gas", "electricity", "water", "sewage", "internet"]',
 '["elevator", "balcony", "central_heating", "outdoor_parking"]',
 0, 'active', 112,
 datetime('now', '-6 days'), datetime('now', '-3 days')),

-- Timisoara Properties
(10, 4, 'Penthouse exclusivist centru vechi', 'penthouse-exclusivist-centru-vechi',
 'Penthouse de lux în centrul istoric Timișoara. Terasă mare cu vedere panoramică, mobilat designer, 3 camere. Unic în oraș!',
 'penthouse', 'sale', 220000, 'EUR',
 'Piața Victoriei nr. 8', 'Timișoara', 'Timiș', '300006', 45.7494, 21.2272,
 95, 85, 3, 2, 2, 8, 8, 2017, 'very_good', 'central', 0,
 '["gas", "electricity", "water", "sewage", "internet", "cable_tv"]',
 '["terrace", "elevator", "central_heating", "luxury_furniture", "panoramic_view"]',
 1, 'active', 289,
 datetime('now', '-5 days'), datetime('now', '-1 day')),

-- Rental Properties
(11, 2, 'Apartament 2 camere închiriere Calea Victoriei', 'apartament-2-camere-inchiriere-calea-victoriei',
 'Apartament elegant pentru închiriere în centrul Bucureștiului. Mobilat complet, toate utilitățile incluse. Ideal pentru expați.',
 'apartment', 'rent', 800, 'EUR',
 'Calea Victoriei nr. 125', 'București', 'București', '010071', 44.4395, 26.0969,
 55, 48, 2, 1, 1, 3, 6, 2010, 'very_good', 'central', 0,
 '["gas", "electricity", "water", "sewage", "internet", "cable_tv"]',
 '["elevator", "balcony", "central_heating", "fully_furnished", "all_utilities_included"]',
 0, 'active', 78,
 datetime('now', '-4 days'), datetime('now', '-1 day')),

(12, 3, 'Casa închiriere Baneasa - grădină', 'casa-inchiriere-baneasa-gradina',
 'Casă frumoasă pentru închiriere în Băneasa. Grădină mare, garaj, perfect pentru familie. 4 camere, mobilată parțial.',
 'house', 'rent', 1200, 'EUR',
 'Strada Băneasa nr. 45', 'București', 'București', '013681', 44.5089, 26.0789,
 160, 140, 4, 3, 2, 1, 2, 2015, 'good', 'central', 1,
 '["gas", "electricity", "water", "sewage", "internet"]',
 '["garden", "garage", "central_heating", "partially_furnished"]',
 0, 'active', 67,
 datetime('now', '-3 days'), datetime('now', '-1 day')),

-- More properties to reach good number for pagination testing
(13, 2, 'Studio modern Universitate', 'studio-modern-universitate',
 'Studio modern, perfect pentru student sau tânăr profesionist. Zonă centrală, mobilat complet, preț excelent.',
 'studio', 'rent', 350, 'EUR',
 'Strada Universității nr. 7', 'București', 'București', '030167', 44.4343, 26.1013,
 25, 22, 1, 0, 1, 4, 5, 2018, 'very_good', 'central', 0,
 '["electricity", "water", "sewage", "internet", "cable_tv"]',
 '["elevator", "central_heating", "fully_furnished"]',
 0, 'active', 45,
 datetime('now', '-2 days'), datetime('now', '-1 day')),

(14, 3, 'Apartament 3 camere Drumul Taberei', 'apartament-3-camere-drumul-taberei',
 'Apartament spațios în Drumul Taberei, etaj 2, balcon mare, parcare în curte. Zona liniștită cu multe spații verzi.',
 'apartment', 'sale', 92000, 'EUR',
 'Strada Brașov nr. 33', 'București', 'București', '061344', 44.4128, 26.0436,
 75, 68, 3, 2, 1, 2, 4, 1995, 'renovated', 'central', 0,
 '["gas", "electricity", "water", "sewage", "internet"]',
 '["balcony", "central_heating", "courtyard_parking", "renovated"]',
 0, 'active', 87,
 datetime('now', '-1 day'), datetime('now')),

(15, 4, 'Birou închiriere zona Nordului', 'birou-inchiriere-zona-nordului',
 'Spațiu de birou modern pentru închiriere. 3 camere, 2 băi, parcare, ideal pentru firmă mică sau cabinet.',
 'office', 'rent', 900, 'EUR',
 'Calea Dorobanți nr. 15', 'București', 'București', '010573', 44.4589, 26.1019,
 85, 80, 3, 0, 2, 2, 5, 2016, 'very_good', 'central', 2,
 '["electricity", "water", "sewage", "internet", "cable_tv"]',
 '["elevator", "central_heating", "parking", "air_conditioning"]',
 0, 'active', 34,
 datetime('now'), datetime('now'));

-- Insert sample property images
INSERT INTO property_images (property_id, filename, alt_text, is_primary, sort_order, created_at) VALUES
(1, 'apartment_floreasca_1.jpg', 'Living apartament Floreasca', 1, 1, datetime('now', '-25 days')),
(1, 'apartment_floreasca_2.jpg', 'Bucătărie apartament Floreasca', 0, 2, datetime('now', '-25 days')),
(2, 'vila_pipera_1.jpg', 'Exterior vila Pipera', 1, 1, datetime('now', '-22 days')),
(2, 'vila_pipera_2.jpg', 'Piscină vila Pipera', 0, 2, datetime('now', '-22 days')),
(3, 'garsoniera_univ_1.jpg', 'Interior garsonieră Universitate', 1, 1, datetime('now', '-20 days')),
(4, 'apartment_herastrau_1.jpg', 'Living apartament Herăstrău', 1, 1, datetime('now', '-18 days')),
(5, 'apartment_cluj_1.jpg', 'Living apartament Cluj', 1, 1, datetime('now', '-15 days')),
(6, 'casa_floresti_1.jpg', 'Exterior casă Florești', 1, 1, datetime('now', '-12 days')),
(7, 'apartment_mamaia_1.jpg', 'Vedere mare apartament Mamaia', 1, 1, datetime('now', '-10 days')),
(8, 'vila_mamaia_1.jpg', 'Vila cu piscină Mamaia Nord', 1, 1, datetime('now', '-8 days')),
(9, 'apartment_iasi_1.jpg', 'Living apartament Iași', 1, 1, datetime('now', '-6 days')),
(10, 'penthouse_tm_1.jpg', 'Terasă penthouse Timișoara', 1, 1, datetime('now', '-5 days')),
(11, 'apartment_rent_buc_1.jpg', 'Apartament închiriere București', 1, 1, datetime('now', '-4 days')),
(12, 'casa_rent_baneasa_1.jpg', 'Casă închiriere Băneasa', 1, 1, datetime('now', '-3 days')),
(13, 'studio_univ_1.jpg', 'Studio Universitate', 1, 1, datetime('now', '-2 days')),
(14, 'apartment_drumul_1.jpg', 'Apartament Drumul Taberei', 1, 1, datetime('now', '-1 day')),
(15, 'birou_nord_1.jpg', 'Birou zona Nordului', 1, 1, datetime('now'));

-- Insert sample favorites
INSERT INTO favorites (user_id, property_id, created_at) VALUES
(5, 1, datetime('now', '-20 days')),
(5, 4, datetime('now', '-15 days')),
(5, 7, datetime('now', '-10 days')),
(6, 2, datetime('now', '-18 days')),
(6, 8, datetime('now', '-8 days')),
(6, 10, datetime('now', '-5 days'));

-- Insert sample search history
INSERT INTO search_history (user_id, query_text, filters, results_count, created_at) VALUES
(5, 'apartament bucuresti', '{"property_type":"apartment","city":"București"}', 8, datetime('now', '-10 days')),
(5, 'casa cu gradina', '{"property_type":"house","amenities":["garden"]}', 3, datetime('now', '-8 days')),
(6, 'vedere la mare', '{"search":"mare","city":"Constanța"}', 2, datetime('now', '-7 days')),
(6, 'apartament 2 camere', '{"property_type":"apartment","rooms":"2"}', 5, datetime('now', '-5 days'));

-- Insert sample property views
INSERT INTO property_views (property_id, ip_address, user_agent, viewed_at) VALUES
(1, '192.168.1.100', 'Mozilla/5.0...', datetime('now', '-1 day')),
(1, '192.168.1.101', 'Mozilla/5.0...', datetime('now', '-6 hours')),
(4, '192.168.1.100', 'Mozilla/5.0...', datetime('now', '-3 hours')),
(7, '192.168.1.102', 'Mozilla/5.0...', datetime('now', '-2 hours')),
(10, '192.168.1.100', 'Mozilla/5.0...', datetime('now', '-1 hour'));



 