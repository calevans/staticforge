---
title = "Additional Commands"
template = "docs"
menu = 1.9, 2.17
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

Before using `site:upload`, configure your SFTP connection in `.env`:

**Password Authentication** (simple but less secure):
```bash
SFTP_HOST="example.com"
SFTP_PORT=22
SFTP_USERNAME="your-username"
SFTP_PASSWORD="your-password"
SFTP_REMOTE_PATH="/var/www/html"
```

**SSH Key Authentication** (recommended, more secure):
```bash
SFTP_HOST="example.com"
SFTP_PORT=22
SFTP_USERNAME="your-username"
SFTP_PRIVATE_KEY_PATH="/home/user/.ssh/id_rsa"
SFTP_PRIVATE_KEY_PASSPHRASE="optional-key-passphrase"
SFTP_REMOTE_PATH="/var/www/html"
```

**Note**: You must configure either `SFTP_PASSWORD` OR `SFTP_PRIVATE_KEY_PATH`, not both. Key-based authentication is recommended for security.

#### Usage

```bash
# Upload using OUTPUT_DIR from .env
php bin/console.php site:upload

# Upload from custom directory
php bin/console.php site:upload --input=/path/to/custom/output

# Verbose output shows each file uploaded
php bin/console.php site:upload -v
```

#### Typical Workflow

```bash
# 1. Generate your site
php bin/console.php site:render --clean

# 2. Upload to production
php bin/console.php site:upload

# Or combine with custom output
php bin/console.php site:render --clean --output=/tmp/mysite
php bin/console.php site:upload --input=/tmp/mysite
```

#### How It Works

1. **Validates Configuration**: Checks all required SFTP settings are configured
2. **Establishes Connection**: Connects to remote server and authenticates
3. **Creates Directory Structure**: Recursively creates any missing directories on remote server
4. **Uploads All Files**: Uploads every file from output directory (HTML, CSS, JS, images, PDFs, etc.)
5. **Error Handling**: Logs errors but continues uploading remaining files
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

