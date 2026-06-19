-- Roommate Expense Management System SQL Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.2+


-- 1. Table: members
CREATE TABLE IF NOT EXISTS `members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: expenses
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `category` ENUM('rent', 'electricity', 'water', 'wifi', 'food', 'maintenance', 'other') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `paid_by` INT NOT NULL,
  `date` DATE NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `split_type` ENUM('equal', 'custom') NOT NULL DEFAULT 'equal',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`paid_by`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: expense_splits
CREATE TABLE IF NOT EXISTS `expense_splits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_id` INT NOT NULL,
  `member_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table: deposits
CREATE TABLE IF NOT EXISTS `deposits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `date` DATE NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table: payments (Settlements)
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `from_member_id` INT NOT NULL,
  `to_member_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `date` DATE NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`from_member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed Sample Data (Default password for all: password123)
-- --------------------------------------------------------

INSERT INTO `members` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `status`, `avatar`, `created_at`) VALUES
(1, 'Alice Johnson', 'alice@example.com', '123-456-7890', '$2y$12$m6gBwisec8OPCW079c6OxeScdcEuPJSrNfk79mLwvxO2G2IbHziEe', 'admin', 'active', NULL, NOW() - INTERVAL 30 DAY),
(2, 'Bob Smith', 'bob@example.com', '234-567-8901', '$2y$12$m6gBwisec8OPCW079c6OxeScdcEuPJSrNfk79mLwvxO2G2IbHziEe', 'member', 'active', NULL, NOW() - INTERVAL 30 DAY),
(3, 'Charlie Brown', 'charlie@example.com', '345-678-9012', '$2y$12$m6gBwisec8OPCW079c6OxeScdcEuPJSrNfk79mLwvxO2G2IbHziEe', 'member', 'active', NULL, NOW() - INTERVAL 30 DAY),
(4, 'Diana Prince', 'diana@example.com', '456-789-0123', '$2y$12$m6gBwisec8OPCW079c6OxeScdcEuPJSrNfk79mLwvxO2G2IbHziEe', 'member', 'active', NULL, NOW() - INTERVAL 30 DAY);

-- Seed Security Deposits
INSERT INTO `deposits` (`id`, `member_id`, `amount`, `date`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 200.00, CURDATE() - INTERVAL 29 DAY, 'Initial security deposit', 1, NOW() - INTERVAL 29 DAY),
(2, 2, 200.00, CURDATE() - INTERVAL 29 DAY, 'Initial security deposit', 1, NOW() - INTERVAL 29 DAY),
(3, 3, 200.00, CURDATE() - INTERVAL 29 DAY, 'Initial security deposit', 1, NOW() - INTERVAL 29 DAY),
(4, 4, 200.00, CURDATE() - INTERVAL 29 DAY, 'Initial security deposit', 1, NOW() - INTERVAL 29 DAY);

-- Seed Expenses
-- Expense 1: Rent ($1200) paid by Alice, split equally
INSERT INTO `expenses` (`id`, `title`, `category`, `amount`, `paid_by`, `date`, `notes`, `attachment`, `split_type`, `created_by`, `created_at`) VALUES
(1, 'June Rent', 'rent', 1200.00, 1, CURDATE() - INTERVAL 15 DAY, 'Monthly apartment rent fee', NULL, 'equal', 1, NOW() - INTERVAL 15 DAY);

INSERT INTO `expense_splits` (`expense_id`, `member_id`, `amount`) VALUES
(1, 1, 300.00),
(1, 2, 300.00),
(1, 3, 300.00),
(1, 4, 300.00);

-- Expense 2: WiFi ($60) paid by Bob, split equally
INSERT INTO `expenses` (`id`, `title`, `category`, `amount`, `paid_by`, `date`, `notes`, `attachment`, `split_type`, `created_by`, `created_at`) VALUES
(2, 'WiFi Internet', 'wifi', 60.00, 2, CURDATE() - INTERVAL 10 DAY, 'High speed internet package', NULL, 'equal', 2, NOW() - INTERVAL 10 DAY);

INSERT INTO `expense_splits` (`expense_id`, `member_id`, `amount`) VALUES
(2, 1, 15.00),
(2, 2, 15.00),
(2, 3, 15.00),
(2, 4, 15.00);

-- Expense 3: Electricity ($120) paid by Charlie, split custom: Charlie 30, Alice 40, Bob 30, Diana 20
INSERT INTO `expenses` (`id`, `title`, `category`, `amount`, `paid_by`, `date`, `notes`, `attachment`, `split_type`, `created_by`, `created_at`) VALUES
(3, 'Electricity Bill', 'electricity', 120.00, 3, CURDATE() - INTERVAL 5 DAY, 'AC usage was higher in Alice and Bob\'s rooms', NULL, 'custom', 3, NOW() - INTERVAL 5 DAY);

INSERT INTO `expense_splits` (`expense_id`, `member_id`, `amount`) VALUES
(3, 1, 40.00),
(3, 2, 30.00),
(3, 3, 30.00),
(3, 4, 20.00);

-- Expense 4: Snacks/Food ($80) paid by Diana, split equally
INSERT INTO `expenses` (`id`, `title`, `category`, `amount`, `paid_by`, `date`, `notes`, `attachment`, `split_type`, `created_by`, `created_at`) VALUES
(4, 'Shared Groceries', 'food', 80.00, 4, CURDATE() - INTERVAL 2 DAY, 'Snacks, paper towels, and condiments', NULL, 'equal', 4, NOW() - INTERVAL 2 DAY);

INSERT INTO `expense_splits` (`expense_id`, `member_id`, `amount`) VALUES
(4, 1, 20.00),
(4, 2, 20.00),
(4, 3, 20.00),
(4, 4, 20.00);

-- Seed Settlements / Payments
-- Bob paid $100 to Alice (approved)
INSERT INTO `payments` (`id`, `from_member_id`, `to_member_id`, `amount`, `date`, `notes`, `status`, `created_at`) VALUES
(1, 2, 1, 100.00, CURDATE() - INTERVAL 3 DAY, 'Partial settlement of rent', 'approved', NOW() - INTERVAL 3 DAY);

-- Charlie paid $50 to Alice (pending approval)
INSERT INTO `payments` (`id`, `from_member_id`, `to_member_id`, `amount`, `date`, `notes`, `status`, `created_at`) VALUES
(2, 3, 1, 50.00, CURDATE() - INTERVAL 1 DAY, 'Settlement payment', 'pending', NOW() - INTERVAL 1 DAY);
