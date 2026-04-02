-- File: sql/mediqueue.sql
-- Complete MediQueue Database Schema

CREATE DATABASE IF NOT EXISTS `mediqueue_db`;
USE `mediqueue_db`;

-- Users Table
CREATE TABLE `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` VARCHAR(50) UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'staff', 'patient') DEFAULT 'patient',
    `department_id` INT NULL,
    `profile_image` VARCHAR(255) NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `email_verified` BOOLEAN DEFAULT FALSE,
    `phone_verified` BOOLEAN DEFAULT FALSE,
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (`role`),
    INDEX idx_email (`email`),
    INDEX idx_department (`department_id`)
);

-- Departments Table
CREATE TABLE `departments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `prefix` VARCHAR(5) NOT NULL,
    `color` VARCHAR(7) DEFAULT '#4F46E5',
    `avg_service_time` INT DEFAULT 15,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prefix (`prefix`)
);

-- Queue Entries Table
CREATE TABLE `queue_entries` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `queue_number` VARCHAR(20) UNIQUE NOT NULL,
    `patient_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `staff_id` INT NULL,
    `priority` ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
    `status` ENUM('waiting', 'called', 'serving', 'completed', 'cancelled', 'no_show') DEFAULT 'waiting',
    `position` INT NULL,
    `estimated_wait_time` INT NULL,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `called_at` TIMESTAMP NULL,
    `serving_started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `cancelled_at` TIMESTAMP NULL,
    `notification_sent` BOOLEAN DEFAULT FALSE,
    `notes` TEXT,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`),
    INDEX idx_status (`status`),
    INDEX idx_queue_number (`queue_number`),
    INDEX idx_joined_at (`joined_at`),
    INDEX idx_department_status (`department_id`, `status`)
);

-- Notifications Table
CREATE TABLE `notifications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `queue_entry_id` INT NULL,
    `type` ENUM('queue_called', 'queue_updated', 'reminder', 'alert', 'info') DEFAULT 'info',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `sent_via_sms` BOOLEAN DEFAULT FALSE,
    `sent_via_email` BOOLEAN DEFAULT FALSE,
    `sent_via_push` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries`(`id`) ON DELETE CASCADE,
    INDEX idx_user_read (`user_id`, `is_read`),
    INDEX idx_created_at (`created_at`)
);

-- Feedback Table
CREATE TABLE `feedback` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `queue_entry_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `staff_id` INT NULL,
    `rating` INT CHECK (rating >= 1 AND rating <= 5),
    `wait_time_rating` INT CHECK (wait_time_rating >= 1 AND rating <= 5),
    `service_quality` INT CHECK (service_quality >= 1 AND rating <= 5),
    `comment` TEXT,
    `suggestions` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX idx_rating (`rating`),
    INDEX idx_created_at (`created_at`)
);

-- System Settings Table
CREATE TABLE `system_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT,
    `setting_type` ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    `description` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`setting_key`)
);

-- Audit Logs Table
CREATE TABLE `audit_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX idx_action (`action`),
    INDEX idx_created_at (`created_at`),
    INDEX idx_user (`user_id`)
);

-- Staff Availability Table
CREATE TABLE `staff_availability` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `is_available` BOOLEAN DEFAULT TRUE,
    `break_start` TIME NULL,
    `break_end` TIME NULL,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_staff_date` (`staff_id`, `date`),
    INDEX idx_date (`date`)
);

-- Insert Default Data
INSERT INTO `departments` (`name`, `description`, `prefix`, `color`, `avg_service_time`) VALUES
('General Consultation', 'General medical consultation', 'GEN', '#4F46E5', 15),
('Pharmacy', 'Medicine dispensing', 'PHA', '#10B981', 10),
('Laboratory', 'Lab tests and results', 'LAB', '#F59E0B', 20),
('Registration', 'Patient registration', 'REG', '#3B82F6', 5),
('Payment', 'Billing and payments', 'PAY', '#EF4444', 8);

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('company_name', 'MediQueue Health System', 'text', 'Name of the healthcare facility'),
('company_logo', '', 'text', 'Logo URL'),
('company_address', 'Nairobi, Kenya', 'text', 'Physical address'),
('company_phone', '+254 712 345 678', 'text', 'Contact phone number'),
('company_email', 'info@mediqueue.com', 'text', 'Contact email'),
('operating_hours_start', '08:00', 'text', 'Opening time'),
('operating_hours_end', '17:00', 'text', 'Closing time'),
('max_queue_size', '100', 'number', 'Maximum queue size per department'),
('default_wait_time', '15', 'number', 'Default estimated wait time in minutes'),
('auto_call_interval', '5', 'number', 'Auto-call interval in minutes'),
('sms_enabled', 'false', 'boolean', 'Enable SMS notifications'),
('email_enabled', 'true', 'boolean', 'Enable Email notifications'),
('push_enabled', 'true', 'boolean', 'Enable Push notifications'),
('maintenance_mode', 'false', 'boolean', 'System maintenance mode'),
('queue_display_refresh', '10', 'number', 'Public display refresh interval in seconds');

-- Insert default admin user (password: Admin@123)
INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password`, `role`, `is_active`) VALUES
('ADMIN001', 'System Administrator', 'admin@mediqueue.com', '0712345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);