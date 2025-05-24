-- Create the exchange_rate table for caching exchange rates
CREATE TABLE IF NOT EXISTS exchange_rate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    base VARCHAR(10) NOT NULL,
    target VARCHAR(10) NOT NULL,
    rate DECIMAL(20, 10) NOT NULL,
    rate_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rate (base, target, rate_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for faster lookups
CREATE INDEX idx_base_target ON exchange_rate(base, target);
CREATE INDEX idx_rate_date ON exchange_rate(rate_date);
