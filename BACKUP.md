# Backup & Restore Runbook

## Strategy

- **Daily dump** via the `backup` service in `docker-compose.yml`
- Format: PostgreSQL custom format (`pg_dump -Fc`) — compressed, supports selective restore
- **Retention**: 30 days (older files are deleted automatically)
- **Storage**: Docker volume `backup_data` (local by default — see below to externalise)

---

## List available backups

```bash
# Via the running backup container
docker compose exec backup ls /backups/

# Or directly via the named volume
docker run --rm -v emailalias_backup_data:/backups alpine ls /backups/
```

---

## Trigger a manual backup

```bash
docker compose exec backup sh -c '
  PGPASSWORD=$DB_PASSWORD pg_dump \
    -Fc -h db -U $DB_USERNAME $DB_DATABASE \
    > /backups/manual_$(date +%Y%m%d_%H%M%S).dump
'
```

---

## Restore a backup

```bash
# 1. Copy the dump to the host machine (if needed)
docker cp $(docker compose ps -q backup):/backups/emailalias_20260521_030000.dump ./restore.dump

# 2. Stop the application to prevent writes during restore
docker compose stop app worker scheduler reverb

# 3. Drop and recreate the database
docker compose exec db psql -U $DB_USERNAME -c "DROP DATABASE emailalias;"
docker compose exec db psql -U $DB_USERNAME -c "CREATE DATABASE emailalias;"

# 4. Restore
docker run --rm \
  -v $(pwd)/restore.dump:/restore.dump \
  --network emailalias_internal \
  postgres:16-alpine \
  pg_restore -h db -U $DB_USERNAME -d $DB_DATABASE /restore.dump

# 5. Restart
docker compose start app worker scheduler reverb
```

---

## Externalise backups (recommended for production)

Mount an NFS directory, sync to an S3 bucket via rclone, or use `restic` for encrypted snapshots:

```bash
# Example: rclone sync to S3/MinIO
rclone copy /backups s3:emailalias-backups/$(hostname) --min-age 0
```

Or replace the `backup` service entirely with a `restic` container for deduplication and encryption at rest.

---

## Back up email attachments

If `ATTACHMENT_DISK=local` (Docker volume `laravel_storage`):

```bash
docker run --rm \
  -v emailalias_laravel_storage:/data \
  -v $(pwd)/storage-backup:/backup \
  alpine tar czf /backup/storage_$(date +%Y%m%d).tar.gz /data
```

If `ATTACHMENT_DISK=s3` (MinIO): configure replication or snapshots at the object-store level.
MinIO supports bucket replication natively — see https://min.io/docs/minio/linux/administration/bucket-replication.html
