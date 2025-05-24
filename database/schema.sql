-- Create the exchange_rate table for caching exchange rates
CREATE TABLE IF NOT EXISTS exchange_rate (
    `from` VARCHAR(10) NOT NULL,
    `to` VARCHAR(10) NOT NULL,
    rate DECIMAL(20, 10) NOT NULL,
    date DATE NOT NULL,
    PRIMARY KEY (`from`, `to`, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
