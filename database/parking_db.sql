-- Script MySQL Simplifié pour Parking Management System
-- Exécutez ce script si vous avez des problèmes de permissions

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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

-- Insertion des données par défaut
-- Utilisateur admin par défaut (mot de passe : admin123)
INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified) 
VALUES ('Admin', 'System', 'admin@parkingsystem.com', '+33123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', TRUE);

-- Places de parking d'exemple
INSERT INTO parking_spots (spot_number, spot_type, status, floor_level, zone_section, description) VALUES
('A001', 'standard', 'available', 1, 'A', 'Place standard rez-de-chaussée'),
('A002', 'standard', 'available', 1, 'A', 'Place standard rez-de-chaussée'),
('A003', 'disabled', 'available', 1, 'A', 'Place PMR avec accès facile'),
('A004', 'electric', 'available', 1, 'A', 'Borne de recharge véhicule électrique'),
('A005', 'standard', 'available', 1, 'A', 'Place standard rez-de-chaussée'),
('B001', 'standard', 'available', 2, 'B', 'Place standard premier étage'),
('B002', 'compact', 'available', 2, 'B', 'Place pour véhicule compact'),
('B003', 'reserved', 'available', 2, 'B', 'Place réservée membres VIP'),
('C001', 'standard', 'available', 1, 'C', 'Place près de l\'entrée'),
('C002', 'standard', 'available', 1, 'C', 'Place près de l\'entrée');

-- Règles de tarification par défaut
INSERT INTO pricing_rules (rule_name, spot_type, time_period, start_time, end_time, base_price, hourly_rate, daily_rate, max_duration_hours) VALUES
('Standard Jour Semaine', 'standard', 'weekday_day', '06:00:00', '18:00:00', 2.00, 3.00, 25.00, 12),
('Standard Nuit Semaine', 'standard', 'weekday_night', '18:00:00', '06:00:00', 1.50, 2.00, 15.00, 12),
('Standard Jour Weekend', 'standard', 'weekend_day', '06:00:00', '20:00:00', 2.50, 4.00, 30.00, 24),
('Standard Nuit Weekend', 'standard', 'weekend_night', '20:00:00', '06:00:00', 1.50, 2.00, 15.00, 12),
('PMR Jour Semaine', 'disabled', 'weekday_day', '06:00:00', '18:00:00', 1.00, 1.50, 12.50, 12),
('Électrique Jour Semaine', 'electric', 'weekday_day', '06:00:00', '18:00:00', 3.00, 4.50, 35.00, 8),
('Réservée Jour Semaine', 'reserved', 'weekday_day', '06:00:00', '18:00:00', 5.00, 6.00, 50.00, 24),
('Compact Jour Semaine', 'compact', 'weekday_day', '06:00:00', '18:00:00', 1.50, 2.50, 20.00, 12);

-- Message de confirmation
SELECT 'Base de données créée avec succès!' as message;