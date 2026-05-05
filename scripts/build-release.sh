#!/bin/bash
set -euo pipefail

VERSION="${1:?Usage: ./scripts/build-release.sh <version>}"
PROJECT_NAME="cms-platform"
BUILD_DIR=$(mktemp -d)
RELEASE_DIR="${BUILD_DIR}/${PROJECT_NAME}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "=== Building ${PROJECT_NAME} v${VERSION} ==="

# Copy project to build dir
echo "--- Copying project files..."
rsync -a --exclude='.git' --exclude='node_modules' --exclude='.env' \
    --exclude='storage/logs/*.log' --exclude='storage/framework/cache/data/*' \
    --exclude='storage/framework/sessions/*' --exclude='storage/framework/views/*' \
    --exclude='storage/app/builds/*' --exclude='storage/app/rollback/*' \
    "${PROJECT_DIR}/" "${RELEASE_DIR}/"

cd "${RELEASE_DIR}"

# Install PHP dependencies (production only)
echo "--- Installing Composer dependencies (production)..."
composer install --no-dev --optimize-autoloader --no-scripts --no-interaction 2>/dev/null

# Build React admin
echo "--- Building React admin SPA..."
cd resources/admin
npm ci --silent 2>/dev/null
npm run build 2>/dev/null
cd "${RELEASE_DIR}"

# Remove dev files
echo "--- Removing dev files..."
rm -rf tests/ .github/ docker-compose.yml Dockerfile .phpunit* phpunit.xml
rm -rf resources/admin/node_modules/ resources/admin/src/ resources/admin/package*.json
rm -rf resources/admin/vite.config.ts resources/admin/tsconfig*.json resources/admin/index.html
rm -rf node_modules/ package*.json vite.config.js postcss.config.js tailwind.config.js
rm -rf scripts/build-release.sh
rm -f .env .env.example

# Ensure storage structure
echo "--- Creating storage structure..."
mkdir -p storage/logs
mkdir -p storage/framework/{cache/data,sessions,views}
mkdir -p storage/app/{builds,rollback,assets,imports,updates}
touch storage/logs/.gitkeep
touch storage/framework/cache/data/.gitkeep
touch storage/framework/sessions/.gitkeep
touch storage/framework/views/.gitkeep

# Create .htaccess for Apache
cat > public/.htaccess << 'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Deny access to sensitive files
<FilesMatch "^\.env">
    Order allow,deny
    Deny from all
</FilesMatch>

<IfModule mod_headers.c>
    # Security headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
HTACCESS

# Create web.config for IIS
cat > public/web.config << 'WEBCONFIG'
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Imported Rule 1" stopProcessing="true">
                    <match url="^(.*)/$" ignoreCase="false" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                    </conditions>
                    <action type="Redirect" redirectType="Permanent" url="/{R:1}" />
                </rule>
                <rule name="Imported Rule 2" stopProcessing="true">
                    <match url="^" ignoreCase="false" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
WEBCONFIG

# Update version in config
sed -i "s/'version' => env('CMS_VERSION', '.*')/'version' => env('CMS_VERSION', '${VERSION}')/" config/cms.php

# Set permissions
echo "--- Setting permissions..."
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 storage/ bootstrap/cache/
chmod +x artisan

# Package
echo "--- Creating release archive..."
cd "${BUILD_DIR}"
zip -r -q "${PROJECT_DIR}/${PROJECT_NAME}-v${VERSION}.zip" "${PROJECT_NAME}/"

# Generate checksum
cd "${PROJECT_DIR}"
sha256sum "${PROJECT_NAME}-v${VERSION}.zip" > "${PROJECT_NAME}-v${VERSION}.zip.sha256"

# Cleanup
rm -rf "${BUILD_DIR}"

echo ""
echo "=== Release built successfully ==="
echo "  Archive: ${PROJECT_NAME}-v${VERSION}.zip"
echo "  Checksum: ${PROJECT_NAME}-v${VERSION}.zip.sha256"
echo "  Size: $(du -h "${PROJECT_NAME}-v${VERSION}.zip" | cut -f1)"
