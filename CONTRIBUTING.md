# Contributing to EmailAlias

Thanks for your interest in contributing! This document covers everything you need to get started.

## Prerequisites

- Docker & Docker Compose (no local PHP or Node.js required)
- Git

## Local setup

```bash
git clone <repo>
cd Email-Alias

# Copy environment file and start services
cp .env.example .env
docker compose up -d

# Run database migrations
docker compose exec app php artisan migrate
```

See the [Development section in README.md](README.md#development) for a complete developer guide (API examples, project structure, seeding demo data).

## Running tests

```bash
# Run the full test suite
docker compose run --rm test

# With code coverage (HTML report in laravel/coverage/)
docker compose run --rm test vendor/bin/pest --coverage-html coverage/

# A single test file
docker compose run --rm test vendor/bin/pest tests/Feature/Api/AliasApiTest.php

# Stop on first failure
docker compose run --rm test vendor/bin/pest --stop-on-failure
```

> **Note:** The test image is built from the `tester` stage of `laravel/Dockerfile` and includes PCOV for coverage. A `docker compose build test` is required after any change to PHP source files.

## Code style

PHP code is formatted with **Laravel Pint**:

```bash
docker compose run --rm test ./vendor/bin/pint
```

The CI lint workflow runs Pint automatically on every PR. Fix any issues before pushing.

## Pull request process

1. **Fork** the repository and create a branch from `main`.
2. **Write tests** for any new behaviour.
3. **Run the test suite** and ensure it passes.
4. **Run Pint** and fix any style issues.
5. **Open a PR** against `main` with a clear description of what changed and why.

Keep PRs focused — one logical change per PR. Large refactors should be discussed in an issue first.

## Commit messages

Use the imperative mood and keep the first line under 72 characters:

```
Add webhook retry counter to metrics endpoint
Fix 451 temp-fail when Laravel is unreachable
Update AGPL-3.0 license header
```

## Reporting bugs and requesting features

Use the GitHub issue templates:
- **Bug report** — unexpected behaviour with reproduction steps
- **Feature request** — new capability with use case

For security vulnerabilities, see [SECURITY.md](SECURITY.md) — do not open a public issue.
