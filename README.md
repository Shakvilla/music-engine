# musicengine

## Local development with Docker

Requirements: Docker Desktop or Docker Engine with the Compose plugin.

Start the app and MySQL:

```sh
docker compose up --build
```

Open <http://localhost:8082>. The app uses PHP 8.5 and MySQL 8.4. On the first database start, Compose imports `MYSQL/MusicEngine.sql`. The database files persist in the `musicengine-db` volume. Compose sets a local-only flag to skip the old online license check; non-Docker installs keep the original validation behavior.

To stop the containers, run `docker compose down`. To reset the local database and import the SQL dump again, run `docker compose down -v` and then `docker compose up --build`.

You can override `APP_PORT`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_ROOT_PASSWORD` in a local `.env` file. The default credentials are for local development only.

## Production deployment

The production stack uses a separate Compose file, keeps MySQL private, stores uploads and database files in persistent volumes, and terminates HTTPS through Caddy. Start from [the production deployment guide](docs/production-deployment.md); do not initialize production from the bundled development SQL dump when migrating an existing installation.

The repository does not contain production credentials or a production data backup. The deployment guide includes the required data rehearsal, rollback, and scheduled backup steps.
# musicengine
