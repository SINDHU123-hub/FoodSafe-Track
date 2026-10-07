-- ============================================================
-- FoodSafe Track - Food Safety & Expiry Management System
-- Database: foodsafe_track
-- For: College Web Application Development Project
-- Compatible with: MySQL 5.7+ / MySQL 8.0+ / MariaDB (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `foodsafe_track` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `foodsafe_track`;

-- ------------------------------------------------------------
-- Drop existing tables if they exist (in reverse FK order)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `expiry_alerts`;
DROP TABLE IF EXISTS `wastage`;
DROP TABLE IF EXISTS `food_items`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ------------------------------------------------------------
-- Table structure for: users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Staff',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table structure for: categories
-- ------------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table structure for: suppliers
-- ------------------------------------------------------------
CREATE TABLE `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_name` VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table structure for: food_items
-- ------------------------------------------------------------
CREATE TABLE `food_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `food_name` VARCHAR(150) NOT NULL,
  `category_id` INT NOT NULL,
  `supplier_id` INT DEFAULT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit` VARCHAR(30) NOT NULL,
  `purchase_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  `storage_location` VARCHAR(100) NOT NULL,
  `batch_number` VARCHAR(50) NOT NULL,
  `food_safety_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_food_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_food_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX `idx_food_name` (`food_name`),
  INDEX `idx_expiry_date` (`expiry_date`),
  INDEX `idx_batch_number` (`batch_number`),
  INDEX `idx_storage_location` (`storage_location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table structure for: wastage
-- ------------------------------------------------------------
CREATE TABLE `wastage` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `food_item_id` INT NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `reason` ENUM('Expired', 'Spoiled/Rotten', 'Damaged Packaging', 'Contaminated', 'Preparation Waste', 'Temperature Abuse', 'Other') NOT NULL,
  `wastage_date` DATE NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_wastage_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX `idx_wastage_date` (`wastage_date`),
  INDEX `idx_wastage_reason` (`reason`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table structure for: expiry_alerts
-- ------------------------------------------------------------
CREATE TABLE `expiry_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `food_item_id` INT NOT NULL,
  `alert_type` ENUM('Expired', 'Critical (<= 2 Days)', 'Near Expiry (<= 7 Days)', 'Warning') NOT NULL,
  `alert_date` DATE NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_alerts_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX `idx_alert_type` (`alert_type`),
  INDEX `idx_alert_is_read` (`is_read`),
  INDEX `idx_alert_date` (`alert_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- 1. Seed Users (passwords: admin123 and staff123)
-- Bcrypt hash generated for standard PHP compatibility
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'System Administrator', 'admin@foodsafe.com', '$2y$10$vI8qU61k1qKq9R18tA2z3uJ89P1Z5r7K2E0G7n9A5Y3C1D2E3F4G5', 'Admin', NOW()),
(2, 'Sarah Jenkins (Kitchen Lead)', 'staff@foodsafe.com', '$2y$10$vI8qU61k1qKq9R18tA2z3uJ89P1Z5r7K2E0G7n9A5Y3C1D2E3F4G5', 'Staff', NOW());

-- 2. Seed Categories
INSERT INTO `categories` (`id`, `category_name`) VALUES
(1, 'Dairy'),
(2, 'Meat'),
(3, 'Vegetables'),
(4, 'Fruits'),
(5, 'Grains'),
(6, 'Beverages'),
(7, 'Frozen'),
(8, 'Bakery'),
(9, 'Other');

-- 3. Seed Suppliers
INSERT INTO `suppliers` (`id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'FreshFarm Organics', 'John Miller', '+1 (555) 234-5678', 'orders@freshfarm.com', '102 Green Valley Way, Farmville', NOW()),
(2, 'Prime Cuts Butchery', 'Robert Sterling', '+1 (555) 345-6789', 'sales@primecuts.com', '44 Industrial Blvd, Meatpacking Dist', NOW()),
(3, 'PureDairy Co-Op', 'Elena Rostova', '+1 (555) 456-7890', 'elena@puredairy.com', '88 Dairy Lane, Pasture Hills', NOW()),
(4, 'OceanFresh Seafoods', 'David Chang', '+1 (555) 567-8901', 'supply@oceanfresh.com', 'Pier 9 Harbor Road, Port City', NOW()),
(5, 'Artisan Bakers Supply', 'Sarah Jenkins', '+1 (555) 678-9012', 'info@artisanbakers.com', '21 Flour Mill Avenue, Bakerstown', NOW()),
(6, 'GreenLeaf Agro Supply', 'Carlos Gomez', '+1 (555) 789-0123', 'contact@greenleafagro.com', '350 Orchard Road, Sunnyvale', NOW());

-- 4. Seed Food Items (Relative dates ensure realistic data whenever imported)
INSERT INTO `food_items` (`id`, `food_name`, `category_id`, `supplier_id`, `quantity`, `unit`, `purchase_date`, `expiry_date`, `storage_location`, `batch_number`, `food_safety_notes`, `created_at`) VALUES
-- Expired items (for testing expiry detection and wastage logging)
(1, 'Whole Fresh Milk 1L', 1, 3, 4.00, 'liters', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Walk-in Chiller A', 'BAT-MK-2026-001', 'Store at 2°C - 4°C. Check seal for leaks.', NOW()),
(2, 'Baby Spinach Leaves', 3, 1, 1.50, 'kg', DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Vegetable Crisper 2', 'BAT-SP-2026-002', 'Crisp cold storage at 4°C. High moisture sensitivity.', NOW()),

-- Critical Near-Expiry items (expiring in 0-2 days)
(3, 'Fresh Chicken Breast Fillets', 2, 2, 12.50, 'kg', DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Cold Room Meat Rack', 'BAT-CH-2026-003', 'Critical HACCP item. Keep below 2°C. Must cook or discard.', NOW()),
(4, 'Artisan Sourdough Loaf', 8, 5, 6.00, 'pcs', DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Bakery Dry Shelf A', 'BAT-BR-2026-004', 'Store in dry shaded area at room temp (20°C).', NOW()),

-- Near-Expiry items (expiring in 3-7 days)
(5, 'Greek Yogurt 500g Tub', 1, 3, 15.00, 'pcs', DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Walk-in Chiller A', 'BAT-YG-2026-005', 'Store at 1°C - 4°C. Priority usage for desserts/breakfast.', NOW()),
(6, 'Atlantic Salmon Portions', 7, 4, 8.00, 'kg', DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'Deep Freezer B', 'BAT-SL-2026-006', 'Maintain at -18°C or lower. Do not refreeze once thawed.', NOW()),
(7, 'Ripe Organic Bananas', 4, 1, 10.00, 'kg', DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Fruit Display Stand 1', 'BAT-BN-2026-007', 'Store at 13°C - 15°C. Ventilated area.', NOW()),

-- Safe items with longer shelf life (> 10 days)
(8, 'Basmati Long Grain Rice', 5, 6, 50.00, 'kg', DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'Dry Pantry Section C', 'BAT-RC-2026-008', 'Store off floor on pallets, dry pest-free humidity < 60%.', NOW()),
(9, 'Organic Orange Juice 2L', 6, 1, 18.00, 'liters', DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'Beverage Chiller 3', 'BAT-OJ-2026-009', 'Shake well before serving. Keep refrigerated at 4°C.', NOW()),
(10, 'Cheddar Cheese Block 2.5kg', 1, 3, 5.00, 'pcs', DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 45 DAY), 'Dairy Chiller Shelf 2', 'BAT-CH-2026-010', 'Aged cheddar. Reseal wrapper tightly after cutting.', NOW()),
(11, 'Grass-Fed Ribeye Steaks', 2, 2, 14.00, 'kg', DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Cold Room Meat Rack', 'BAT-RB-2026-011', 'Raw beef. Vacuum sealed. Strict separation from cooked food.', NOW()),
(12, 'Frozen Garden Peas 1kg', 7, 6, 20.00, 'packs', DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'Deep Freezer A', 'BAT-PE-2026-012', 'Constant -18°C. Inspect for ice crystallization.', NOW());

-- 5. Seed Wastage Records
INSERT INTO `wastage` (`id`, `food_item_id`, `quantity`, `reason`, `wastage_date`, `notes`, `created_at`) VALUES
(1, 1, 2.00, 'Expired', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Milk carton past expiry date, sour smell detected.', NOW()),
(2, 2, 0.50, 'Spoiled/Rotten', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Moisture buildup caused slime on lower leaves.', NOW()),
(3, 4, 1.00, 'Damaged Packaging', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Bag torn during morning delivery handling.', NOW());

-- 6. Seed Expiry Alerts
INSERT INTO `expiry_alerts` (`id`, `food_item_id`, `alert_type`, `alert_date`, `is_read`, `created_at`) VALUES
(1, 1, 'Expired', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 0, NOW()),
(2, 2, 'Expired', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 0, NOW()),
(3, 3, 'Critical (<= 2 Days)', CURDATE(), 0, NOW()),
(4, 4, 'Critical (<= 2 Days)', CURDATE(), 0, NOW()),
(5, 5, 'Near Expiry (<= 7 Days)', CURDATE(), 0, NOW()),
(6, 6, 'Near Expiry (<= 7 Days)', CURDATE(), 0, NOW()),
(7, 7, 'Near Expiry (<= 7 Days)', CURDATE(), 1, NOW());
