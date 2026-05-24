#!/bin/sh
# Initialisation du projet EmailAlias
# Usage: ./scripts/init.sh [--dev]

set -e

DEV=false
if [ "$1" = "--dev" ]; then
  DEV=true
fi

echo "==> Vérification du .env..."
if [ ! -f .env ]; then
  cp .env.example .env
  echo "    .env créé depuis .env.example — pensez à remplir les valeurs !"
fi

echo "==> Création du projet Laravel 13..."
if [ ! -f laravel/composer.json ]; then
  # Utilise le container Composer pour ne pas nécessiter PHP en local
  docker run --rm -v "$(pwd)/laravel:/app" composer:2 \
    create-project laravel/laravel:^12.0 . --prefer-dist --no-interaction
  echo "    Laravel 13 installé."
else
  echo "    laravel/composer.json déjà présent, skip."
fi

echo "==> Installation des dépendances npm (smtp-server)..."
docker run --rm -v "$(pwd)/smtp-server:/app" -w /app node:22-alpine \
  npm install

echo "==> Démarrage des containers..."
if [ "$DEV" = true ]; then
  docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
else
  docker compose up -d
fi

echo "==> Attente de la base de données..."
sleep 5

echo "==> Migrations + seed..."
docker compose exec app php artisan migrate --seed --force

echo "==> Création du premier admin..."
docker compose exec app php artisan admin:create

echo ""
echo "✓ EmailAlias est prêt !"
echo "  App    : ${APP_URL:-http://localhost:8000}"
echo "  BDD    : localhost:5432 (dev)"
echo ""
echo "  MX record à configurer : pointez votre domaine vers cette machine (port 25)"
