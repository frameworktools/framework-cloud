// Database and storage bucket connections, as the editors hold them. The server
// (App\Support\Connections) checks them and turns them into the layer's
// connections and env keys. Drivers and fields follow framework (R) and
// framework-py, which read the same ones. Stata has no connections.

export const connectionLanguages = ['r', 'python'];

export const databaseDrivers = [
    { value: 'postgres', label: 'PostgreSQL', fields: ['host', 'port', 'database', 'schema', 'user', 'password'], port: '5432' },
    { value: 'mysql', label: 'MySQL or MariaDB', fields: ['host', 'port', 'database', 'user', 'password'], port: '3306' },
    { value: 'sqlserver', label: 'SQL Server', fields: ['host', 'port', 'database', 'user', 'password'], port: '' },
    { value: 'duckdb', label: 'DuckDB', fields: ['database'], file: 'data/study.duckdb' },
    { value: 'sqlite', label: 'SQLite', fields: ['database'], file: 'data/study.sqlite' },
];

export const bucketDrivers = [
    { value: 's3', label: 'S3 or S3-compatible', fields: ['bucket', 'region', 'endpoint', 'prefix', 'access_key_id', 'secret_access_key', 'session_token'] },
];

// Each field's label, the suffix of its suggested variable, and an example
export const fields = {
    host: { label: 'Host', suffix: 'HOST', placeholder: 'db.example.org' },
    port: { label: 'Port', suffix: 'PORT', placeholder: '5432' },
    database: { label: 'Database', suffix: 'NAME', placeholder: 'analytics' },
    schema: { label: 'Schema', suffix: 'SCHEMA', placeholder: 'public', optional: true },
    user: { label: 'User', suffix: 'USER', placeholder: 'analyst' },
    password: { label: 'Password', suffix: 'PASSWORD', secret: true },
    bucket: { label: 'Bucket', suffix: 'BUCKET', placeholder: 'study-outputs' },
    region: { label: 'Region', suffix: 'REGION', placeholder: 'us-east-1', optional: true },
    endpoint: { label: 'Endpoint', suffix: 'ENDPOINT', placeholder: 'https://s3.example.org', optional: true },
    prefix: { label: 'Prefix', suffix: 'PREFIX', placeholder: 'project-x', optional: true },
    access_key_id: { label: 'Access key ID', suffix: 'ACCESS_KEY_ID', secret: true },
    secret_access_key: { label: 'Secret access key', suffix: 'SECRET_ACCESS_KEY', secret: true },
    session_token: { label: 'Session token', suffix: 'SESSION_TOKEN', secret: true, optional: true },
};

// What each driver needs installed, by language
export const driverNeeds = {
    r: {
        postgres: 'Needs the RPostgres package.',
        mysql: 'Needs the RMariaDB package.',
        sqlserver: 'Needs the odbc package and Microsoft\'s ODBC driver for SQL Server.',
        duckdb: 'Needs the duckdb package. The path is relative to the project folder.',
        sqlite: 'Needs the RSQLite package. The path is relative to the project folder.',
        s3: 'Needs the aws.s3 package.',
    },
    python: {
        postgres: 'Needs framework-py[postgres]: uv add "framework-py[postgres]".',
        mysql: 'Needs framework-py[mysql]: uv add "framework-py[mysql]".',
        sqlserver: 'Needs framework-py[sqlserver] and Microsoft\'s ODBC driver for SQL Server.',
        duckdb: 'Needs framework-py[duckdb]. The path is relative to the project folder.',
        sqlite: 'Built into Python. The path is relative to the project folder.',
        s3: 'Needs framework-py[s3]: uv add "framework-py[s3]".',
    },
};

const envPattern = /^env\(\s*["']([A-Za-z_][A-Za-z0-9_]*)["']/;
export const envVar = (value) => envPattern.exec(String(value ?? '').trim())?.[1] ?? null;
export const envRef = (name) => `env("${name}")`;
export const suggested = (name, field) => `${String(name || 'connection').toUpperCase()}_${fields[field].suffix}`;

export const emptyConnections = () => ({ default_database: '', default_storage_bucket: '', databases: [], storage_buckets: [] });
export const copyConnections = (c) => ({ ...emptyConnections(), ...JSON.parse(JSON.stringify(c ?? {})) });

const driversOf = (kind) => (kind === 'databases' ? databaseDrivers : bucketDrivers);

// A driver's fields for a connection: secrets and server details read the .env
const fieldsFor = (kind, driver, name) => {
    const d = driversOf(kind).find((item) => item.value === driver);
    return Object.fromEntries(d.fields.map((field) => {
        if (field === 'port') return [field, d.port];
        if (field === 'database' && d.file) return [field, ''];
        if (fields[field].optional && !fields[field].secret) return [field, ''];
        if (field === 'session_token' || field === 'bucket') return [field, ''];
        return [field, envRef(suggested(name, field))];
    }));
};

const freeName = (rows, base) => {
    const names = new Set(rows.map((row) => row.name));
    if (!names.has(base)) return base;
    let i = 2;
    while (names.has(`${base}_${i}`)) i++;
    return `${base}_${i}`;
};

export const newConnection = (kind, rows) => {
    const name = freeName(rows, kind === 'databases' ? 'study_db' : 'study_bucket');
    const driver = kind === 'databases' ? 'postgres' : 's3';
    return { name, driver, ...fieldsFor(kind, driver, name) };
};

// A new driver starts from its own fields
export const changeDriver = (kind, row, driver) => ({ name: row.name, driver, ...fieldsFor(kind, driver, row.name) });

// Renaming a connection renames the variables still named after it
export const rename = (row, name) => {
    const next = { ...row, name };
    for (const field of Object.keys(fields)) {
        if (field in row && envVar(row[field]) === suggested(row.name, field)) next[field] = envRef(suggested(name, field));
    }
    return next;
};

// The variables connections read, for the .env
export const variables = (c) => [...new Set([...(c?.databases ?? []), ...(c?.storage_buckets ?? [])]
    .flatMap((row) => Object.entries(row).filter(([key]) => key in fields).map(([, value]) => envVar(value)).filter(Boolean)))].sort();
