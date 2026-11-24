CREATE TABLE IF NOT EXISTS auth_credentials (
    digest BINARY(32) PRIMARY KEY,
    userid INT NOT NULL,
    kind VARCHAR(12) NOT NULL,
    expires BIGINT NOT NULL,
    KEY credential_owner (userid, kind)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_challenges (
    userid INT NOT NULL,
    purpose VARCHAR(16) NOT NULL,
    digest BINARY(32) NOT NULL,
    expires BIGINT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (userid, purpose)
) ENGINE=InnoDB;
