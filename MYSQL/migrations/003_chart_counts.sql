CREATE TABLE IF NOT EXISTS chart_counts (
    track INT NOT NULL,
    period VARCHAR(16) NOT NULL,
    plays BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (period,track)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS chart_refresh (
    id TINYINT PRIMARY KEY,
    refreshed_at BIGINT NOT NULL
) ENGINE=InnoDB;
