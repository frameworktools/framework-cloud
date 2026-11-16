---
id: database
title: Database integration
section: features
position: 14
description: 'Run SQL queries and statements against configured database connections'
---
## Overview

Framework's database helpers run SQL queries and statements against profiles defined in `settings.yml` (see [Connections](/docs/connections)) as well as the local `framework.db` SQLite database.

## SQL execution

### Query results

Run a `SELECT` query and return a data frame with `db_query()`:

```r
users <- db_query("SELECT * FROM users WHERE active = true", "analytics")
```

### Non-query statements

Run `INSERT`, `UPDATE`, `DELETE`, or DDL statements with `db_execute()`:

```r
db_execute("UPDATE stats SET last_run = NOW()", "analytics")
```

### Direct DBI handles

Open a raw `DBI` connection with `db_connect()` when calling `DBI` methods directly. Close it with `DBI::dbDisconnect()`:

```r
conn <- db_connect("analytics")
DBI::dbListTables(conn)
DBI::dbDisconnect(conn)
```

`db_query()` and `db_execute()` open and close connections automatically on each call.

## Supported drivers

| Driver | R package | Target database |
|---|---|---|
| `postgres` / `postgresql` | `RPostgres` | PostgreSQL |
| `sqlite` | `RSQLite` | Local SQLite files |
| `duckdb` | `duckdb` | DuckDB files and Parquet queries |
| `mysql` / `mariadb` | `RMariaDB` | MySQL and MariaDB |
| `sqlserver` / `mssql` | `odbc` | Microsoft SQL Server |

S3-compatible object storage profiles are documented under [Object storage connections](/docs/connections#object-storage-connections).

### DuckDB options

DuckDB profiles accept `read_only`, `memory_limit`, and `threads` in `settings.yml`:

```yaml
connections:
  warehouse:
    driver: duckdb
    database: data/warehouse.duckdb
    read_only: false
    memory_limit: "4GB"
    threads: 4
```

## Internal project database

Query the project's local `framework.db` SQLite database using the default `"framework"` connection:

```r
cache_entries <- db_query("SELECT * FROM cache", "framework")
```

`framework.db` stores dataset checksums (`data`), cache records (`cache`), and saved artifact metadata (`results`).
