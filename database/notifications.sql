-- Notification System Migration
-- Bulk SMS System

CREATE TABLE IF NOT EXISTS notifications (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(10) UNSIGNED NULL, -- NULL means all users (broadcast)
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    image_url VARCHAR(255) NULL,
    is_popup BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Track which users have seen/read which notifications (especially for broadcasts)
CREATE TABLE IF NOT EXISTS notification_interactions (
    user_id INT(10) UNSIGNED NOT NULL,
    notification_id INT(10) UNSIGNED NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_dismissed BOOLEAN DEFAULT FALSE,
    interacted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, notification_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
);
