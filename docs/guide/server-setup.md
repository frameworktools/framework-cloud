# Server setup

Administrators open **Server administration** in the sidebar to configure GitHub, artifact storage, email, and connected AI agents. Every integration is optional, though email sign-in links and password resets require working email delivery.

## Email

Configure email delivery so users can receive sign-in links and password reset emails:

1. Under **Email**, select **SMTP** (Postmark, Mailgun, Resend, or an institutional mail server) or **Amazon SES**.
2. Enter the sender address, sender name, and provider credentials. Select **Save**.
3. Select **Send a test email** to verify delivery to your administrator address.

Before email is active, administrators can issue links with `php artisan fw:login-link` or assign passwords with `php artisan fw:password`.

## GitHub

GitHub integration enables GitHub sign-in and repository creation on GitHub:

1. In the **GitHub** panel, select **Create GitHub App** (optionally entering a GitHub organization login). GitHub opens with the application name, permissions, callback URL, and webhook URL prefilled.
2. Confirm creation on GitHub to return to **Server administration** with the app credentials saved automatically. Select **Or enter an app you already have** to paste existing credentials manually.
3. Select **Install the app** to authorize it on your GitHub account or organization.

Individual users then link their GitHub accounts in **Account > GitHub connection**.

## Published artifacts

New artifacts write to the private `local` filesystem disk by default. Framework Cloud streams reader pages, original files, and bundle assets through authenticated application routes so password protection applies to every request. Sandboxed headers isolate uploaded HTML from application cookies.

Set `FW_ARTIFACTS_DISK` in `.env` to target another private disk defined in `config/filesystems.php`. The chosen disk must remain private without public bucket access or direct web-root exposure. Multi-node deployments require shared private storage. Back up the artifact disk alongside PostgreSQL, which stores metadata, version histories, collections, and reports.

The **Artifact storage** panel in **Server administration** configures the `FW_DOCUMENTS_DISK` object-storage bucket rather than `FW_ARTIFACTS_DISK`.

### Artifact bucket

To store `FW_DOCUMENTS_DISK` files in S3-compatible object storage (Amazon S3, Cloudflare R2, MinIO, or Backblaze B2) rather than local disk:

1. Under **Artifact storage**, enter the bucket name, region, endpoint URL (leave blank for Amazon S3), access key ID, and secret access key.
2. Select **Test connection** to write, read back, and delete a verification object.
3. Select **Save**.

### Artifact domain

Uploaded HTML artifacts can include scripts. On multi-user servers, attach a dedicated domain such as `https://files.example.edu` to the bucket or configure `FW_CONTENT_URL` so user-authored scripts execute on a separate origin from Framework Cloud session cookies. Enter a public bucket domain in **Public address** under **Artifact storage** when applicable.

## Connected agents

The **Connected agents** panel in **Server administration** toggles the `/mcp` server across the deployment. When set to **Off** (or when `FW_AGENTS=false` is set in `.env`), new agent connections are refused, existing agent tokens stop working, and the sidebar agent item is hidden until re-enabled. See [AI apps](ai-apps.md).

## User accounts

Administrators open **Users** (`/admin/users`) in the sidebar to review every account on the server, newest first. The page counts accounts, sign-ups in the past week, verified email addresses, and finished guided setups. Search by name or email. Each row shows the sign-up date, last activity, project count, and **Admin**, **Unverified**, **Setup not finished**, or **Publishing** badges. The page is read-only. Use `fw:login-link`, `fw:password`, and `fw:publishing` to act on accounts. See [Maintenance](maintenance.md#administration-commands).

## Credential storage

Each field in **Server administration** maps to an environment variable displayed beside its input. Framework Cloud resolves settings in precedence order:

1. **Environment variables.** Any non-empty environment variable overrides database values and renders as read-only in **Server administration**.
2. **Saved database credentials.** Values saved in **Server administration** are encrypted at rest with `APP_KEY`.

You can configure all credentials through `.env`, all through **Server administration**, or a combination of both. See [Configuration reference](configuration.md#server-credentials) for variable names.

Saving changes in **Server administration** applies immediately and signals queue workers to restart. Changing `.env` requires restarting Docker containers (`docker compose up -d`) or refreshing the configuration cache (`php artisan config:cache`).

## Application key protection

Credentials stored in **Server administration** are encrypted with `APP_KEY`. If `APP_KEY` changes without preserving the old key, stored credentials cannot be decrypted and **Server administration** flags the unreadable fields for re-entry.

When rotating `APP_KEY`, list previous keys in `APP_PREVIOUS_KEYS` (comma-separated) so existing encrypted values remain readable.
