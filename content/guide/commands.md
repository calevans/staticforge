---
title: 'Additional Commands'
description: 'Reference guide for StaticForge CLI commands, including rendering, uploading, and auditing.'
template: docs
menu: '2.1.4'
url: "https://calevans.com/staticforge/guide/commands.html"
og_image: "Hacker terminal screen with green command line interface, typing fast, system control, matrix background, code flowing, --ar 16:9"
---
# Additional Commands

## Rendering Commands

### site:render - Generate Full Site

```bash
# Generate site
php bin/staticforge.php site:render

# With options
php bin/staticforge.php site:render --template=staticforce --clean

# List all commands
php bin/staticforge.php list
```

### site:page - Render Single Page

Render a specific page or pattern of pages instead of the entire site.

```bash
# Render single page
php bin/staticforge.php site:page content/about.md

# Render pattern
php bin/staticforge.php site:page "content/blog/*.md"
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
php bin/staticforge.php site:upload

# Upload with production URL override (Overrides .env)
php bin/staticforge.php site:upload --url="https://staging.mysite.com/"

# Upload from custom directory (Advanced)
php bin/staticforge.php site:upload --input=/path/to/custom/output

# Verbose output shows each file uploaded
php bin/staticforge.php site:upload -v
```

#### Typical Workflow

```bash
# 1. Deploy to production
# This will:
#   a. Read UPLOAD_URL from .env (or --url)
#   b. Re-render the site to a temporary directory with that URL
#   c. Upload the generated files via SFTP
#   d. Clean up the temporary directory
php bin/staticforge.php site:upload
```

#### How It Works

1. **Validates Configuration**: Checks all required SFTP settings and `UPLOAD_URL` are configured
2. **Production Build (Optional)**: If `--url` is provided, re-renders the site to a temporary directory using that URL
3. **Establishes Connection**: Connects to remote server and authenticates
4. **Creates Directory Structure**: Recursively creates any missing directories on remote server
5. **Smart Sync**:
   - Compares local files against the remote `staticforge-manifest.json`
   - Uploads new or changed files
   - Removes stale files that were present in the previous build but removed from the current one
   - Updates the remote manifest
6. **Security Check**: automatically configures `.htaccess` to prevent public access to the manifest file
7. **Cleanup**: Removes temporary build directory if one was created
8. **Error Handling**: Logs errors but continues uploading remaining files
9. **Reports Results**: Shows summary of files uploaded and any errors encountered

#### Important Notes

- **Smart Cleanup**: StaticForge tracks the files it uploads. It will cleanly remove old files from previous builds (like renamed assets or deleted posts).
- **Non-Destructive**: Files *not* in the manifest (like your `.htaccess` or other subdirectories you created manually) are completely ignored and safe.
- **Overwrites Files**: Upload always replaces existing remote files with the same name.
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
php bin/staticforge.php system:features

# Alias
php bin/staticforge.php system:plugins
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

