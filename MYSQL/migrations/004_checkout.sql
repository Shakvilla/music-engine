CREATE TABLE IF NOT EXISTS checkout_orders (
    id CHAR(32) PRIMARY KEY, userid INT NOT NULL, item_type VARCHAR(32) NOT NULL,
    item_id VARCHAR(255) NOT NULL, base_price DECIMAL(14,2) NOT NULL,
    amount DECIMAL(14,2) NOT NULL, currency CHAR(3) NOT NULL,
    provider VARCHAR(16), reference_id VARCHAR(255), settlement_id VARCHAR(255),
    state VARCHAR(16) NOT NULL DEFAULT 'pending', created_at BIGINT NOT NULL,
    UNIQUE KEY settlement (provider,settlement_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS stripe_events (
    id VARCHAR(255) PRIMARY KEY
) ENGINE=InnoDB;
