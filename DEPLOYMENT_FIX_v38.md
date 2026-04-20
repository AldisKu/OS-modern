# Deployment Fix - v38 Version Mismatch

## Problem Identified

The deployment script was working correctly and copying all versioned asset files to the server. However, there was a **version mismatch** in the JavaScript files:

- Files were named: `app.38.js`, `styles.38.css`, `customer.38.js`, `customer.38.css`
- But the `APP_VERSION` constant inside `app.38.js` was still `"37"`
- The API paths were pointing to the old location: `../php/modernapi.php`

## Root Cause

When files were renamed from v37 to v38, the version numbers and API paths inside the JavaScript files were not updated.

## Fixes Applied

### 1. app.38.js
- **Changed**: `const APP_VERSION = "37"` → `const APP_VERSION = "38"`
- **Changed**: `const API = "../php/modernapi.php"` → `const API = "./modernapi.php"`

### 2. customer.38.js
- **Changed**: `const API = "../php/modernapi.php"` → `const API = "./modernapi.php"`

## Deployment Script Status

✅ **The deployment script is working correctly!**

The script properly:
- Copies all versioned asset files (app.38.js, styles.38.css, etc.)
- Cleans up old/unused files
- Handles old structure migration
- Supports `--v` verbose flag for debugging

## Next Steps

1. Commit these fixes to git:
   ```bash
   git add orders/orders/modern/app.38.js orders/orders/modern/customer.38.js
   git commit -m "Fix v38: Update APP_VERSION and API paths"
   ```

2. Run deployment script on server:
   ```bash
   cd /home/aldis/ordersprinter
   git pull
   WEBROOT=/var/www/webapp bash deploy-modern.sh --v
   ```

3. Verify on server:
   ```bash
   ls -lh /var/www/webapp/modern/ | grep "\.38\."
   curl http://127.0.0.1:3077/health
   ```

## Version Numbering Rule

**JEDES UPDATE BEKOMMT NEUE VERSION!** (Every update gets new version!)

When making changes:
1. Increment `APP_VERSION` in all JavaScript files
2. Update all query parameters (`?v=NN`) in HTML files
3. Rename asset files with new version number
4. Delete old version files after deployment
5. Update version in filename: `app.39.js`, `styles.39.css`, etc.

