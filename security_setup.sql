-- Security Enhancement: Users Table
-- Add this to your clinic_db database

-- Create users table for authentication
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff','viewer') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create default admin user
-- Username: admin
-- Password: Admin@123 (CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN!)
INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$12$LQv3c1yIf3RP3O7fF.Y5O.xVCLhX9S1qC7v8JGJ5jWzL5ZkNxN7Vy', 'admin@cnhs.edu.ph', 'admin');

-- Note: The password above is hashed using password_hash() with BCRYPT
-- To create a new user with custom password, use the create_user.php script
-- or use PHP to hash: password_hash('YourPassword', PASSWORD_BCRYPT, ['cost' => 12]);

-- Add index for better query performance
CREATE INDEX idx_user_active ON users(is_active);
CREATE INDEX idx_user_role ON users(role);

-- Create activity logs table (optional but recommended)
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `activity` varchar(255) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
