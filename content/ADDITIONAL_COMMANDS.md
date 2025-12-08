---
title: 'Additional Commands'
template: docs
menu: '1.10, 2.10'
category: docs
---
# Additional Commands

## Rendering Commands

### site:render - Generate Full Site

```bash
# Generate site
php bin/console.php site:render

# With options
php bin/console.php site:render --template=staticforce --clean

# List all commands
php bin/console.php list
```

### site:page - Render Single Page

Render a specific page or pattern of pages instead of the entire site.

```bash
# Render single page
php bin/console.php site:page content/about.md

# Render pattern
php bin/console.php site:page "content/blog/*.md"
```

## Deployment Commands

### site:upload - Upload to Production

Upload your generated static site to a remote server via SFTP. This is the recommended way to deploy your site to production hosting.

#### Configuration

Before using `site:upload`, configure your SFTP connection in `.env`. You should also set the `UPLOAD_URL` to your production URL.

**Password Authentication** (simple but less secure):
```bash
# The URL to use when building for production upload
UPLOAD_URL="https://www.mysite.com"

SFTP_HOST="example.com"
SFTP_PORT=22
SFTP_USERNAME="your-username"
SFTP_PASSWORD="your-password"
SFTP_REMOTE_PATH="/var/www/html"
```

**SSH Key Authentication** (recommended, more secure):
```bash
# The URL to use when building for production upload
UPLOAD_URL="https://www.mysite.com"

SFTP_HOST="example.com"
SFTP_PORT=22
SFTP_USERNAME="your-username"
SFTP_PRIVATE_KEY_PATH="/home/user/.ssh/id_rsa"
SFTP_PRIVATE_KEY_PASSPHRASE="optional-key-passphrase"
SFTP_REMOTE_PATH="/var/www/html"
```

**Note**: You must configure either `SFTP_PASSWORD` OR `SFTP_PRIVATE_KEY_PATH`, not both. Key-based authentication is recommended for security.

#### Usage

The `site:upload` command **always** re-renders your site for production before uploading. It requires a production URL, which can be set in `.env` (as `UPLOAD_URL`) or passed via the `--url` option.

```bash
# Upload using UPLOAD_URL from .env (Recommended)
php bin/console.php site:upload

# Upload with production URL override (Overrides .env)
php bin/console.php site:upload --url="https://staging.mysite.com/"

# Upload from custom directory (Advanced)
php bin/console.php site:upload --input=/path/to/custom/output

# Verbose output shows each file uploaded
php bin/console.php site:upload -v
```

#### Typical Workflow

```bash
# 1. Deploy to production
# This will:
#   a. Read UPLOAD_URL from .env (or --url)
#   b. Re-render the site to a temporary directory with that URL
#   c. Upload the generated files via SFTP
#   d. Clean up the temporary directory
php bin/console.php site:upload
```

#### How It Works

1. **Validates Configuration**: Checks all required SFTP settings and `UPLOAD_URL` are configured
2. **Production Build (Optional)**: If `--url` is provided, re-renders the site to a temporary directory using that URL
3. **Establishes Connection**: Connects to remote server and authenticates
4. **Creates Directory Structure**: Recursively creates any missing directories on remote server
5. **Uploads All Files**: Uploads every file from output directory (HTML, CSS, JS, images, PDFs, etc.)
6. **Cleanup**: Removes temporary build directory if one was created
7. **Error Handling**: Logs errors but continues uploading remaining files
6. **Reports Results**: Shows summary of files uploaded and any errors encountered

#### Important Notes

- **Overwrites Files**: Upload always replaces existing remote files
- **Preserves Remote Files**: Does NOT delete files on server (preserves `.htaccess`, etc.)
- **Full Upload**: Uploads ALL files every time (no incremental/diff logic)
- **Continues on Error**: If a file fails to upload, remaining files continue
- **Security**: Never commit `.env` file to git - keep credentials secure
- **Private Keys**: SSH key files should have restrictive permissions (`chmod 600 ~/.ssh/id_rsa`)

#### Exit Codes

- `0`: Success - all files uploaded without errors
- `1`: Failure - connection failed, configuration error, or upload errors occurred

#### Troubleshooting

**Connection Failed**:
- Verify `SFTP_HOST` is correct and reachable
- Check `SFTP_PORT` (default is 22)
- Ensure firewall allows SFTP connections

**Authentication Failed**:
- For password: Verify `SFTP_USERNAME` and `SFTP_PASSWORD` are correct
- For key: Verify `SFTP_PRIVATE_KEY_PATH` points to correct file
- For encrypted key: Verify `SFTP_PRIVATE_KEY_PASSPHRASE` is correct

**Permission Denied on Remote**:
- Verify `SFTP_USERNAME` has write access to `SFTP_REMOTE_PATH`
- Check remote directory permissions

**No Files Uploaded**:
- Verify `OUTPUT_DIR` contains generated files
- Check that `site:render` completed successfully

## System Commands

### system:features - List Features

List all available features and their current status (enabled/disabled). This is useful for verifying which features are active, especially when using `disabled_features` in `siteconfig.yaml`.

```bash
# List features
php bin/console.php system:features

# Alias
php bin/console.php system:plugins
```

**Example Output:**

```text
+--------------------+----------+
| Feature Name       | Status   |
+--------------------+----------+
| CacheBuster        | Enabled  |
| Categories         | Enabled  |
| ...                | ...      |
| Sitemap            | Disabled |
+--------------------+----------+
```

