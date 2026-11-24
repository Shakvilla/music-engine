# Production deployment

## Prepare the VPS

Install Docker Engine and the Compose plugin, allow inbound TCP ports 80 and 443, and point the chosen domain's DNS records to the VPS. Keep SSH restricted to the operators who maintain the service.

Create `.env.production` from `.env.production.example`, set the real domain, and generate unique random database passwords. Keep this file out of source control. The production stack does not publish the database port and does not enable the local license bypass.

## Preserve and rehearse production data

Before deployment, create a consistent backup of the source database and media directory. Restore both into an isolated staging environment using the same application image. Confirm row counts for users, tracks, transactions, and media references; play representative audio and video; and test login, upload, payment callbacks, and rollback.

The checked-in SQL dump is for fresh local development only. Do not use it to seed production when existing records must be preserved.

## Deploy

```sh
docker compose --env-file .env.production -f compose.production.yaml config
docker compose --env-file .env.production -f compose.production.yaml up -d --build
docker compose --env-file .env.production -f compose.production.yaml ps
```

The app image contains the application code. Database and uploads use persistent Docker volumes. Caddy obtains and renews HTTPS certificates after DNS and ports are configured.

## Back up and restore

Run `scripts/backup-production.sh` before upgrades. For daily backups, install the systemd units in `deploy/systemd`, adjusting `/opt/musicengine` if the checkout uses another path, then enable `musicengine-backup.timer`. The script keeps 14 days locally and writes SHA-256 checksums. Copy completed backups off the VPS to protected storage and test restore from an off-server copy before public launch.

For cutover, use a private beta first. Keep the previous image available, pause writes during the final data sync, verify records and media after switching traffic, and roll back the app image and database/media snapshot together if the acceptance checks fail.

## Audit remediation cutover

Back up the database, uploads, and private audio together. For an existing schema, rehearse the numbered SQL files under `MYSQL/migrations` in ascending order before starting the new web and worker images. Record each applied migration. The fresh installation SQL already includes these changes. Apply the index migration once and inspect existing index names if resuming an interrupted migration.

Configure a fresh application access key in mobile settings. Updated clients send a provider-issued Facebook access token by POST. Account linking also requires authenticated approval. Mobile login can return a second-factor challenge. Preserve the session cookie and submit the emailed code by POST to the login/factor action under the same API key. Legacy persistent login cookies and mobile keys require fresh sign-in. Reissue any outstanding activation or recovery emails.

Set `STRIPE_WEBHOOK_SECRET` from the provider sandbox for the staging webhook endpoint. Exercise each enabled provider before enabling checkout publicly. Browser writes require a session CSRF token. First-party pages attach it to same-origin POST requests.

The charts service refreshes counts every five minutes. The archives service claims one ZIP job at a time, retries interrupted builds, and publishes complete files by rename. A pending ZIP request returns HTTP 202 with Retry-After. Before routing traffic, check chart_refresh.refreshed_at, failed archive jobs, worker logs, and local volume write permissions.

The media backup archive contains uploads/ and musicengine-private/. Restore them to /var/www/html/Script/uploads and /var/www/html/musicengine-private respectively with their original permissions. Older backup archives contain uploads/ only. Preserve historical media paths during the first deployment. Check legacy audio locations and external bucket access before release. FTP-hosted originals require migration to private storage. Rehearse rollback with matching database, media, and application snapshots.

Run the rollback-fixture regression suite with `docker compose exec -T app php < scripts/tests/remediation.php` and the local HTTP checks with `docker compose exec -T app php < scripts/tests/http-smoke.php`. The viewer-count comparison in scripts/tests/read-performance.php creates connection-local temporary data.

For existing local recordings, run `php app/cli/migrate-audio.php` as www-data inside the app container for a read-only inventory. After a verified staging backup and with writes paused, run the apply step as www-data so the web and archive processes can read the private copies. The command `php app/cli/migrate-audio.php --apply` copies audio to the private volume, verifies SHA-256, and switches each matching database reference. It retains the previous files for recovery. Verify that historical URLs are denied and remove legacy copies only after backup and reference reconciliation. Review external references separately.

Reconcile bank receipts submitted before the checkout migration against bank statements before cutover. The approval screen requires an order linked to the receipt. A customer can resubmit against a new checkout after an administrator reconciles and removes their legacy submission.
