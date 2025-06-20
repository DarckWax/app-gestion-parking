CREATE DATABASE IF NOT EXISTS parking_management_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE parking_management_system;

-- 1. Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE
);

-- 2. Table des sessions utilisateurs
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- 3. Table des places de parking
CREATE TABLE IF NOT EXISTS parking_spots (
    spot_id INT AUTO_INCREMENT PRIMARY KEY,
    spot_number VARCHAR(10) UNIQUE NOT NULL,
    spot_type ENUM('standard', 'disabled', 'reserved', 'electric', 'compact') DEFAULT 'standard',
    status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
    floor_level INT DEFAULT 1,
    zone_section VARCHAR(10) DEFAULT 'A',
    length_cm INT DEFAULT 500,
    width_cm INT DEFAULT 250,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    hourly_rate DECIMAL(10,2) DEFAULT 2.50
);

-- 4. Table des règles de tarification
CREATE TABLE IF NOT EXISTS pricing_rules (
    pricing_id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    spot_type ENUM('standard', 'disabled', 'reserved', 'electric', 'compact') DEFAULT 'standard',
    time_period ENUM('weekday_day', 'weekday_night', 'weekend_day', 'weekend_night', 'holiday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    base_price DECIMAL(8,2) NOT NULL,
    hourly_rate DECIMAL(8,2) NOT NULL,
    daily_rate DECIMAL(8,2) DEFAULT NULL,
    max_duration_hours INT DEFAULT 24,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 5. Table des réservations
CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    spot_id INT NOT NULL,
    reservation_code VARCHAR(20) UNIQUE NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    actual_start_datetime DATETIME NULL,
    actual_end_datetime DATETIME NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'active', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded', 'failed') DEFAULT 'pending',
    vehicle_plate VARCHAR(20) DEFAULT NULL,
    vehicle_model VARCHAR(50) DEFAULT NULL,
    special_requests TEXT,
    cancellation_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 6. Table des paiements
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'paypal', 'bank_transfer', 'cash') NOT NULL,
    payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100) UNIQUE,
    gateway_response TEXT,
    currency VARCHAR(3) DEFAULT 'EUR',
    fee_amount DECIMAL(8,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2) NOT NULL,
    processed_at TIMESTAMP NULL,
    refunded_at TIMESTAMP NULL,
    refund_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 7. Table des notifications
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reservation_id INT NULL,
    type ENUM('booking_confirmation', 'reminder', 'cancellation', 'payment_success', 'payment_failed', 'availability_alert', 'system_maintenance', 'account_update') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'read') DEFAULT 'pending',
    delivery_method ENUM('email', 'sms', 'in_app', 'push') DEFAULT 'email',
    scheduled_at DATETIME DEFAULT NULL,
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. Table des logs système
CREATE TABLE IF NOT EXISTS system_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    log_level ENUM('info', 'warning', 'error', 'critical') NOT NULL,
    action VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_data JSON DEFAULT NULL,
    stack_trace TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ajout des contraintes de clés étrangères (après création des tables)
ALTER TABLE user_sessions 
ADD CONSTRAINT fk_sessions_user 
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE reservations 
ADD CONSTRAINT fk_reservations_user 
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE reservations 
ADD CONSTRAINT fk_reservations_spot 
FOREIGN KEY (spot_id) REFERENCES parking_spots(spot_id) ON DELETE RESTRICT;

ALTER TABLE payments 
ADD CONSTRAINT fk_payments_reservation 
FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE;

ALTER TABLE payments 
ADD CONSTRAINT fk_payments_user 
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE notifications 
ADD CONSTRAINT fk_notifications_user 
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE notifications 
ADD CONSTRAINT fk_notifications_reservation 
FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE;

ALTER TABLE system_logs 
ADD CONSTRAINT fk_logs_user 
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- Création des index pour les performances
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_spots_number ON parking_spots(spot_number);
CREATE INDEX idx_spots_status ON parking_spots(status);
CREATE INDEX idx_reservations_user ON reservations(user_id);
CREATE INDEX idx_reservations_spot ON reservations(spot_id);
CREATE INDEX idx_reservations_datetime ON reservations(start_datetime, end_datetime);
CREATE INDEX idx_payments_reservation ON payments(reservation_id);
CREATE INDEX idx_notifications_user ON notifications(user_id);

-- Insertion des données de test
-- Utilisateurs (admin et utilisateurs normaux)
INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified, phone_verified, created_at) VALUES
-- Compte administrateur principal
('Admin', 'System', 'admin@parkingsystem.com', '+33123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', TRUE, TRUE, NOW()),

-- Comptes utilisateurs de test
('Marie', 'Martin', 'marie.martin@email.com', '+33123456790', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', TRUE, FALSE, NOW()),
('Pierre', 'Dupont', 'pierre.dupont@email.com', '+33123456791', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', TRUE, FALSE, NOW()),
('Sophie', 'Bernard', 'sophie.bernard@email.com', '+33123456792', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', FALSE, FALSE, NOW()),
('Lucas', 'Moreau', 'lucas.moreau@email.com', '+33123456793', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', TRUE, TRUE, NOW()),

-- Compte admin secondaire
('Super', 'Admin', 'admin@parkfinder.com', '+33987654321', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', TRUE, TRUE, NOW());

-- Message de confirmation
SELECT 'Base de données créée avec succès!' as message;

/*
=================================================================
COMPTES DE TEST CRÉÉS
=================================================================

ADMINISTRATEURS:
- Email: admin@parkingsystem.com | Mot de passe: admin123
- Email: admin@parkfinder.com | Mot de passe: admin123

UTILISATEURS NORMAUX:
- Email: marie.martin@email.com | Mot de passe: admin123
- Email: pierre.dupont@email.com | Mot de passe: admin123
- Email: sophie.bernard@email.com | Mot de passe: admin123
- Email: lucas.moreau@email.com | Mot de passe: admin123

Note: Tous les mots de passe sont hashés avec PASSWORD_DEFAULT
Le mot de passe en clair pour tous les comptes est: admin123
=================================================================
*/

-- Insertion des places de parking de test avec tarifs
INSERT INTO parking_spots (spot_number, spot_type, zone_section, status, hourly_rate, description, is_active) VALUES
('A01', 'standard', 'A', 'available', 2.50, 'Place standard rez-de-chaussée', TRUE),
('A02', 'standard', 'A', 'available', 2.50, 'Place standard rez-de-chaussée', TRUE),
('A03', 'handicapped', 'A', 'available', 2.00, 'Place PMR avec accès facile', TRUE),
('A04', 'electric', 'A', 'available', 3.00, 'Borne de recharge véhicule électrique', TRUE),
('A05', 'standard', 'A', 'available', 2.50, 'Place standard rez-de-chaussée', TRUE),
('B01', 'standard', 'B', 'available', 2.75, 'Place standard premier étage', TRUE),
('B02', 'compact', 'B', 'available', 2.25, 'Place pour véhicule compact', TRUE),
('B03', 'large', 'B', 'available', 3.25, 'Place réservée membres VIP', TRUE),
('C01', 'standard', 'C', 'available', 2.50, 'Place près de l\'entrée', TRUE),
('C02', 'standard', 'C', 'reserved', 2.50, 'Place près de l\'entrée', TRUE);