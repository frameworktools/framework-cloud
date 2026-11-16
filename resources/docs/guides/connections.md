---
id: connections
title: Connections
section: features
position: 13
description: 'Configure database and S3-compatible object storage connections in settings.yml'
---
## Overview

Framework defines database and S3 object-storage profiles in `settings.yml` while reading secret credentials from `.env`. For SQL query helpers, see [Database integration](/docs/database).

## Database connections

Declare named database connections under `connections:` in `settings.yml`:

```yaml
connections:
  analytics:
    driver: postgres
    host: env("DB_HOST")
    port: 5432
    database: analytics
    user: env("DB_USER")
    password: env("DB_PASSWORD")

  warehouse:
    driver: duckdb
    database: data/warehouse.duckdb

  local:
    driver: sqlite
    database: data/local.db
```

`env()` resolves values from environment variables at runtime so credentials stay out of `settings.yml`. Store local secrets in `.env`:

```bash
# .env (gitignored)
DB_HOST=db.example.com
DB_USER=analyst
DB_PASSWORD=secret
```

## Object storage connections

Configure S3-compatible buckets under `connections.storage_buckets` in `settings.yml` for use with `publish()`, `storage_test()`, and `framework::gui()`:

```yaml
connections:
  storage_buckets:
    raw_data:
      bucket: env("S3_BUCKET")
      region: env("AWS_REGION")
      access_key_id: env("AWS_ACCESS_KEY_ID")
      secret_access_key: env("AWS_SECRET_ACCESS_KEY")
```

## Connection listing

List all configured database and storage connections with `connections_list()` or list database connections alone with `db_list()`:

```r
connections_list()
db_list()
```

## Configuration conventions

### Credential references

Always read passwords and access keys through `env()`:

```yaml
# Recommended
password: env("DB_PASSWORD")

# Avoid plaintext secrets in settings.yml
# password: "my_secret_password"
```

### Explicit connection names

Include the target environment and role in connection names:

```yaml
connections:
  production_analytics:
    driver: postgres
    host: env("PROD_DB_HOST")
  staging_analytics:
    driver: postgres
    host: env("STAGING_DB_HOST")
```
