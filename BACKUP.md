# Backup & Restore Runbook

## Stratégie

- **Dump quotidien** via le service `backup` dans `docker-compose.yml`
- Format : PostgreSQL custom (`pg_dump -Fc`) — compressé, sélectif à la restauration
- **Rétention** : 30 jours (les fichiers plus vieux sont supprimés automatiquement)
- **Stockage** : volume Docker `backup_data` (local par défaut — voir ci-dessous pour externaliser)

---

## Localisation des backups

```bash
# Lister les backups disponibles
docker compose exec backup ls /backups/

# Ou directement via le volume
docker run --rm -v emailalias_backup_data:/backups alpine ls /backups/
```

---

## Déclencher un backup manuel

```bash
docker compose exec backup sh -c '
  PGPASSWORD=$DB_PASSWORD pg_dump \
    -Fc -h db -U $DB_USERNAME $DB_DATABASE \
    > /backups/manual_$(date +%Y%m%d_%H%M%S).dump
'
```

---

## Restaurer un backup

```bash
# 1. Copier le fichier de backup sur la machine hôte (si nécessaire)
docker cp $(docker compose ps -q backup):/backups/emailalias_20260521_030000.dump ./restore.dump

# 2. Arrêter l'application (évite les écritures pendant la restauration)
docker compose stop app worker scheduler reverb

# 3. Supprimer la base existante et en recréer une vide
docker compose exec db psql -U $DB_USERNAME -c "DROP DATABASE emailalias;"
docker compose exec db psql -U $DB_USERNAME -c "CREATE DATABASE emailalias;"

# 4. Restaurer
docker run --rm \
  -v $(pwd)/restore.dump:/restore.dump \
  --network emailalias_internal \
  postgres:16-alpine \
  pg_restore -h db -U $DB_USERNAME -d $DB_DATABASE /restore.dump

# 5. Redémarrer
docker compose start app worker scheduler reverb
```

---

## Externaliser les backups (recommandé en production)

Monter un répertoire NFS, un bucket S3 (via rclone), ou utiliser `restic` :

```bash
# Exemple avec rclone vers S3/MinIO
rclone copy /backups s3:emailalias-backups/$(hostname) --min-age 0
```

Ou remplacer le service `backup` par un service `restic` avec snapshot + chiffrement.

---

## Sauvegarder les pièces jointes

Si `ATTACHMENT_DISK=local` (volume Docker `laravel_storage`) :

```bash
# Backup du volume storage
docker run --rm \
  -v emailalias_laravel_storage:/data \
  -v $(pwd)/storage-backup:/backup \
  alpine tar czf /backup/storage_$(date +%Y%m%d).tar.gz /data
```

Si `ATTACHMENT_DISK=s3` (MinIO) — MinIO gère lui-même la réplication. Configurer
le mirroring ou les snapshots au niveau de l'objet store.
