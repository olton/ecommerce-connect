# Welcome to UPC E-commerce Connect for WooCommerce

UPC E-commerce Connect is a set of plugins for Wordpress WooCommerce.

## Build Chain Overview

`npm run build` in this package runs `node build.js` and now uses staging packaging.

What `build.js` does:

1. Reads version from `stores/woocommerce/package.json` (`version` field).
2. Clears `stores/woocommerce/dist` (unless `-no-clear` is passed).
3. Creates temporary staging folder at `stores/woocommerce/.build-staging/<id>/src`.
4. Copies `stores/woocommerce/src` to staging.
5. Runs `node scripts/i18n.mjs build --src-dir <staged-src>`.
6. Creates ZIP from staged `src` using a cross-platform Node.js archiver implementation.
7. Removes staging folder.
8. Creates MD5 file for the ZIP.

Build output:

1. `stores/woocommerce/dist/woocommerce-ecommerce-connect.<version>.zip`
2. `stores/woocommerce/dist/woocommerce-ecommerce-connect.<version>.zip.md5`

## Versioning And Constants

How version changes now work:

1. Update `version` in `stores/woocommerce/package.json`.
2. Run build. ZIP/MD5 filenames are generated from this version.

Important: `build.js` currently does not modify plugin PHP files. It reads version only for archive naming.

Plugin header/constants are still maintained in `stores/woocommerce/src/woocommerce-gateway-ecommerceconnect.php`:

1. Header `Version: ...`
2. `define('WC_GATEWAY_ECOMMERCECONNECT_VERSION', '...')`
3. Other constants (`WC_GATEWAY_ECOMMERCECONNECT_URL`, `WC_GATEWAY_ECOMMERCECONNECT_PATH`) are static path/url constants and are not versioned.

Recommended release step:

1. Keep `package.json` version and `WC_GATEWAY_ECOMMERCECONNECT_VERSION` in sync.
2. Keep plugin header `Version` in sync with the same value.


## Installing Required Components

Required tools for full i18n+build flow:

1. Node.js + npm
2. WP-CLI (`wp`)
3. GNU gettext tools (`msgmerge`, `msgfmt`)

### Windows

1. Install Node.js LTS:
	- Download installer from https://nodejs.org/en/download
2. Install PHP (required by WP-CLI):
	- Recommended: `winget install --id PHP.PHP`
3. Install WP-CLI:
	- Download `wp-cli.phar` from https://wp-cli.org/
	- Place it as `wp`/`wp.cmd` in PATH (example: `C:\wp-cli\wp.cmd`).
4. Install GNU gettext:
	- Via MSYS2 or Chocolatey: `choco install gettext`
    - Via Scoop: `scoop install gettext`
5. Verify:

```powershell
node -v
npm -v
php -v
wp --info
msgmerge --version
msgfmt --version
```

### Linux (Red Hat Enterprise Linux)

1. Install Node.js + npm (example for Node 22 from NodeSource):

```bash
sudo dnf module disable -y nodejs
curl -fsSL https://rpm.nodesource.com/setup_22.x | sudo bash -
sudo dnf install -y nodejs
```

2. Install PHP CLI + gettext + unzip/curl:

```bash
sudo dnf install -y php-cli php-mbstring php-xml gettext curl unzip
```

3. Install WP-CLI:

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp
```

4. Verify:

```bash
node -v
npm -v
php -v
wp --info
msgmerge --version
msgfmt --version
```

### Ubuntu Linux

1. Install Node.js + npm:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs
```

2. Install PHP CLI + gettext + curl/unzip:

```bash
sudo apt-get update
sudo apt-get install -y php-cli php-mbstring php-xml gettext curl unzip
```

3. Install WP-CLI:

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp
```

4. Verify:

```bash
node -v
npm -v
php -v
wp --info
msgmerge --version
msgfmt --version
```


## WooCommerce Local i18n Workflow (No Docker)

The WooCommerce package includes local scripts to update and compile translation files directly in the project.

### Prerequisites (Windows)

Install these CLI tools and make sure they are available in your PATH:

1. `wp` (WP-CLI)
2. `msgmerge` and `msgfmt` (GNU gettext)

Quick checks:

```powershell
wp --info
msgmerge --version
msgfmt --version
```

### Commands

Run from monorepo root:

```powershell
npm run i18n:woocommerce
```

Or run from `stores/woocommerce`:

```powershell
npm run i18n:update-pot
npm run i18n:update-po-all
npm run i18n:compile-mo-all
```

One-shot all-in-one command:

```powershell
npm run i18n:all
```

### What each step does

1. `i18n:update-pot`: generates `stores/woocommerce/src/languages/ecommerceconnect.pot` from source strings in `stores/woocommerce/src`.
2. `i18n:update-po-all`: updates all `.po` files in `stores/woocommerce/src/languages` using the POT template.
3. `i18n:compile-mo-all`: compiles all `.po` files into `.mo` files.

### Typical edit cycle

1. Change translatable strings in WooCommerce PHP source.
2. Run `npm run i18n:all` in `stores/woocommerce`.
3. Commit updated `.po` files.

## Build Packaging (Staging)

`npm run build` now uses a staging directory:

1. Copies `src` into a temporary staging folder.
2. Runs `i18n:build` against staged `src` (`--src-dir` mode).
3. Creates ZIP from staged contents.
4. Deletes staging folder.

This keeps the working tree clean while still producing a ZIP that contains generated `.pot` and `.mo` files.

Build-time tool behavior:

1. If `wp` and `msgmerge` are available, POT/PO are refreshed before MO compilation.
2. If `wp` is missing, POT/PO refresh is skipped, but MO files are still compiled from existing PO files.
3. `msgfmt` is always required for build packaging.

Repository policy:

1. Keep `.po` files in git.
2. Do not track generated `.pot` and `.mo` files.