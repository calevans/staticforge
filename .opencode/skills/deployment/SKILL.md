---
name: deployment
description: Deploy the stock-picking application to production. Manual invocation only.
disable-model-invocation: true
---

# Deployment

This skill handles deployment of the stock-picking application to production.

**IMPORTANT**: This skill can only be invoked manually by you using `/deployment` or similar command. Claude will not automatically trigger deployments.

## Pre-Deployment Checklist

Before deploying, verify:

- [ ] All tests pass (`composer test` or `phpunit`)
- [ ] Code review completed
- [ ] Security audit completed
- [ ] Database migrations tested
- [ ] Environment variables configured
- [ ] Backup of production database created
- [ ] Deployment window scheduled (if needed)

## Environment Setup

### Production Environment Variables
Create `.env.production`:
```bash
APP_ENV=production
APP_DEBUG=false
DB_PATH=/var/www/html/data/stocks.db
STOCK_API_KEY=your_production_api_key
SESSION_SECURE=true
LOG_LEVEL=error
```

### File Permissions
```bash
# Set proper ownership
chown -R www-data:www-data /var/www/html

# Set directory permissions
find /var/www/html -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/html -type f -exec chmod 644 {} \;

# Writable directories
chmod 775 /var/www/html/data
chmod 775 /var/www/html/cache
chmod 775 /var/www/html/logs
```

## Deployment Steps

### 1. Backup Production
```bash
# Backup database
cp /var/www/html/data/stocks.db /backups/stocks_$(date +%Y%m%d_%H%M%S).db

# Backup configuration
tar -czf /backups/config_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/config
```

### 2. Deploy Code
```bash
# Option A: Git pull (if using git deployment)
cd /var/www/html
git pull origin main

# Option B: FTP/SFTP upload
# Upload files via your preferred method

# Option C: rsync
rsync -avz --exclude='.git' --exclude='data/' --exclude='cache/' \
  ./  user@server:/var/www/html/
```

### 3. Install Dependencies
```bash
# If using Composer for autoloading or dependencies
composer install --no-dev --optimize-autoloader
```

### 4. Run Database Migrations
```bash
# Run migration script
php scripts/migrate.php

# Or manually apply migrations
sqlite3 /var/www/html/data/stocks.db < migrations/latest.sql
```

### 5. Clear Caches
```bash
# Clear application cache
rm -rf /var/www/html/cache/*

# If using OpCache, restart PHP-FPM
sudo systemctl restart php8.5-fpm
```

### 6. Verify Deployment
```bash
# Check PHP syntax
php -l /var/www/html/index.php

# Test database connection
php scripts/test-db.php

# Check file permissions
ls -la /var/www/html/data

# Verify web server configuration
sudo nginx -t  # or apache2ctl configtest
```

### 7. Restart Services
```bash
# Restart PHP-FPM
sudo systemctl restart php8.5-fpm

# Restart web server
sudo systemctl restart nginx  # or apache2
```

## Rollback Procedure

If deployment fails:

```bash
# Restore database
cp /backups/stocks_TIMESTAMP.db /var/www/html/data/stocks.db

# Restore previous code version
git revert HEAD  # or restore from backup
```

## Post-Deployment

### Monitor Logs
```bash
# Watch application logs
tail -f /var/www/html/logs/app.log

# Watch web server logs
tail -f /var/log/nginx/error.log

# Watch PHP-FPM logs
tail -f /var/log/php8.5-fpm.log
```

### Verify Functionality
- [ ] Homepage loads
- [ ] User login works
- [ ] Stock search works
- [ ] API endpoints respond
- [ ] Database queries execute
- [ ] No PHP errors in logs

### Performance Check
```bash
# Check database size
ls -lh /var/www/html/data/stocks.db

# Check cache hit rate
# (implementation specific)

# Monitor response times
# (use application monitoring)
```

## Quick Deploy Script

Create `scripts/deploy.sh`:
```bash
#!/bin/bash
set -e

echo "Starting deployment..."

# Backup
echo "Creating backup..."
cp data/stocks.db backups/stocks_$(date +%Y%m%d_%H%M%S).db

# Pull changes
echo "Pulling latest code..."
git pull origin main

# Dependencies
echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Migrations
echo "Running migrations..."
php scripts/migrate.php

# Clear cache
echo "Clearing cache..."
rm -rf cache/*

# Restart services
echo "Restarting services..."
sudo systemctl restart php8.5-fpm
sudo systemctl restart nginx

echo "Deployment complete!"
echo "Please verify the application is working correctly."
```

Make it executable:
```bash
chmod +x scripts/deploy.sh
```

## Zero-Downtime Deployment

For larger deployments:

1. Use a blue-green deployment strategy
2. Deploy to staging server first
3. Switch traffic after verification
4. Keep previous version ready for instant rollback

## Security Notes

- Never commit `.env` files
- Use HTTPS in production
- Enable firewall rules
- Keep PHP and dependencies updated
- Regular security audits
- Monitor for suspicious activity

## Troubleshooting

### Common Issues

**500 Internal Server Error**
- Check PHP error logs
- Verify file permissions
- Check `.htaccess` configuration

**Database Locked**
- Stop all processes accessing database
- Check for long-running queries
- Verify WAL mode is enabled

**Session Issues**
- Clear session directory
- Check session configuration
- Verify write permissions

**Performance Issues**
- Enable OpCache
- Optimize database indexes
- Check slow query log
- Review API rate limits

