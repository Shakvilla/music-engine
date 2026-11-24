CREATE TABLE IF NOT EXISTS media_tickets (
 digest BINARY(32) PRIMARY KEY, userid INT NOT NULL, track INT NOT NULL,
 purpose VARCHAR(12) NOT NULL, expires BIGINT NOT NULL, KEY media_expiry (expires)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS archive_jobs (
 id CHAR(64) PRIMARY KEY, manifest JSON NOT NULL, state VARCHAR(12) NOT NULL DEFAULT 'pending',
 attempts INT NOT NULL DEFAULT 0, updated_at BIGINT NOT NULL, KEY job_queue (state,updated_at)
) ENGINE=InnoDB;
