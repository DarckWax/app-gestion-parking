-- Script pour créer un compte administrateur par défaut

USE parking_management_system;

-- Supprimer les comptes admin existants
DELETE FROM users WHERE role = 'admin';

-- Créer le compte administrateur principal
INSERT INTO users (
    first_name, 
    last_name, 
    email, 
    phone, 
    password_hash, 
    role, 
    status, 
    email_verified, 
    phone_verified, 
    created_at
) VALUES 
(
    'Admin',
    'System', 
    'admin@parkingsystem.com',
    '+33123456789',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'admin',
    'active',
    TRUE,
    TRUE,
    NOW()
),
(
    'Super',
    'Admin', 
    'admin@parkfinder.com',
    '+33987654321',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'admin',
    'active',
    TRUE,
    TRUE,
    NOW()
);

-- Vérifier que les comptes ont été créés
SELECT user_id, first_name, last_name, email, role, status FROM users WHERE role = 'admin';
