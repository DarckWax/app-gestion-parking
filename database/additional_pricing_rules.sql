-- Ajout des règles de tarification manquantes pour tous les types et périodes

USE parking_management_system;

-- Supprimer les anciennes règles incomplètes si nécessaire
-- DELETE FROM pricing_rules;

-- Règles complètes pour tous les types de places et toutes les périodes
INSERT INTO pricing_rules (rule_name, spot_type, time_period, start_time, end_time, base_price, hourly_rate, daily_rate, max_duration_hours) VALUES

-- Places STANDARD
('Standard Jour Semaine', 'standard', 'weekday_day', '06:00:00', '18:00:00', 2.00, 3.00, 25.00, 12),
('Standard Nuit Semaine', 'standard', 'weekday_night', '18:00:00', '06:00:00', 1.50, 2.00, 15.00, 12),
('Standard Jour Weekend', 'standard', 'weekend_day', '06:00:00', '20:00:00', 2.50, 4.00, 30.00, 24),
('Standard Nuit Weekend', 'standard', 'weekend_night', '20:00:00', '06:00:00', 1.50, 2.00, 15.00, 12),

-- Places PMR (disabled)
('PMR Jour Semaine', 'disabled', 'weekday_day', '06:00:00', '18:00:00', 1.00, 1.50, 12.50, 12),
('PMR Nuit Semaine', 'disabled', 'weekday_night', '18:00:00', '06:00:00', 0.50, 1.00, 8.00, 12),
('PMR Jour Weekend', 'disabled', 'weekend_day', '06:00:00', '20:00:00', 1.50, 2.00, 15.00, 24),
('PMR Nuit Weekend', 'disabled', 'weekend_night', '20:00:00', '06:00:00', 0.50, 1.00, 8.00, 12),

-- Places ÉLECTRIQUES
('Électrique Jour Semaine', 'electric', 'weekday_day', '06:00:00', '18:00:00', 3.00, 4.50, 35.00, 8),
('Électrique Nuit Semaine', 'electric', 'weekday_night', '18:00:00', '06:00:00', 2.50, 3.50, 25.00, 12),
('Électrique Jour Weekend', 'electric', 'weekend_day', '06:00:00', '20:00:00', 3.50, 5.00, 40.00, 8),
('Électrique Nuit Weekend', 'electric', 'weekend_night', '20:00:00', '06:00:00', 2.50, 3.50, 25.00, 12),

-- Places RÉSERVÉES (VIP)
('Réservée Jour Semaine', 'reserved', 'weekday_day', '06:00:00', '18:00:00', 5.00, 6.00, 50.00, 24),
('Réservée Nuit Semaine', 'reserved', 'weekday_night', '18:00:00', '06:00:00', 4.00, 5.00, 40.00, 24),
('Réservée Jour Weekend', 'reserved', 'weekend_day', '06:00:00', '20:00:00', 6.00, 7.00, 60.00, 24),
('Réservée Nuit Weekend', 'reserved', 'weekend_night', '20:00:00', '06:00:00', 4.00, 5.00, 40.00, 24),

-- Places COMPACTES
('Compact Jour Semaine', 'compact', 'weekday_day', '06:00:00', '18:00:00', 1.50, 2.50, 20.00, 12),
('Compact Nuit Semaine', 'compact', 'weekday_night', '18:00:00', '06:00:00', 1.00, 1.50, 12.00, 12),
('Compact Jour Weekend', 'compact', 'weekend_day', '06:00:00', '20:00:00', 2.00, 3.00, 25.00, 24),
('Compact Nuit Weekend', 'compact', 'weekend_night', '20:00:00', '06:00:00', 1.00, 1.50, 12.00, 12)

ON DUPLICATE KEY UPDATE
base_price = VALUES(base_price),
hourly_rate = VALUES(hourly_rate),
daily_rate = VALUES(daily_rate);

SELECT 'Règles de tarification complètes ajoutées!' as message;
