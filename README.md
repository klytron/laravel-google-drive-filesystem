# klytron/laravel-google-drive-filesystem

[![Latest Version on Packagist](https://img.shields.io/packagist/v/klytron/laravel-google-drive-filesystem.svg?style=flat-square)](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
[![Total Downloads](https://img.shields.io/packagist/dt/klytron/laravel-google-drive-filesystem.svg?style=flat-square)](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
[![License](https://img.shields.io/packagist/l/klytron/laravel-google-drive-filesystem.svg?style=flat-square)](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
[![PHP Version](https://img.shields.io/packagist/php-v/klytron/laravel-google-drive-filesystem.svg?style=flat-square)](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x%20%7C%2013.x-orange.svg?style=flat-square)](https://laravel.com/)

A robust Google Drive filesystem adapter for Laravel that provides seamless integration with Google Drive as a storage disk. Features configurable debug logging, automatic folder creation, and full Laravel Filesystem API compatibility.

## ✨ Features

- 🚀 **Full Laravel Filesystem API Support** - Use Google Drive like any other Laravel disk
- 👥 **Shared Drive (Team Drive) Support** - Full support for Team Drives and shared corporate storage
- 🔄 **In-Place File Updates** - Safely overwrites existing files instead of creating duplicate Drive IDs
- 📑 **Complete Pagination** - Cursor-based pagination (`nextPageToken`) for directories with >1,000 items
- 🌊 **Memory-Safe Streaming** - 1MB chunked reading for downloading large files without memory exhaustion
- 🔧 **Configurable Debug Logging** - Control debug output in production environments
- 📁 **Automatic Folder Creation** - Folders are created automatically when needed
- 🔐 **Secure Authentication** - Support for both access tokens and refresh tokens with error detection
- ♻️ **Resilient Token Refresh** - Automatic retry with backoff on transient auth failures
- 🩺 **Credential Health Check** - `php artisan google-drive:check` validates credentials without side effects
- 🔑 **Configurable OAuth Scopes** - Least-privilege scopes (`drive`, `drive.readonly`, `drive.appdata`)
- 📊 **Metadata Support** - File sizes, modification times, and comprehensive MIME types
- 🛡️ **Production Ready** - Proper error handling and logging configuration
- 📚 **Comprehensive Documentation** - Detailed setup and usage guides

## 📋 Requirements

- PHP 8.1, 8.2, 8.3, or 8.4
- Laravel 10.x, 11.x, 12.x, or 13.x
- Google Cloud Platform project with Drive API enabled

## 🚀 Quick Installation

Install via Composer:

```bash
composer require klytron/laravel-google-drive-filesystem
```

For advanced installation and VCS/development setup, see [docs/INSTALLATION.md](docs/INSTALLATION.md).

## ⚙️ Quick Configuration

Publish the config file and set up your `.env`:

```bash
php artisan vendor:publish --tag=google-drive-config
```

Add your Google Drive credentials to your `.env` file. For a detailed step-by-step guide, see [docs/GETTING-TOKENS.md](docs/GETTING-TOKENS.md).

### 🔑 Environment Variables

```env
# Google Drive API Credentials
GOOGLE_DRIVE_CLIENT_ID=your-client-id
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret
GOOGLE_DRIVE_ACCESS_TOKEN=your-access-token
GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token
GOOGLE_DRIVE_FOLDER_ID=your-folder-id

# Shared Drive / Team Drive Support (optional)
GOOGLE_DRIVE_TEAM_DRIVE=false

# OAuth Scopes (optional, comma-separated; defaults to full access)
GOOGLE_DRIVE_SCOPES=https://www.googleapis.com/auth/drive

# Debug Logging (optional)
GOOGLE_DRIVE_DEBUG=false
GOOGLE_DRIVE_LOG_PAYLOAD=false
```

#### Shared Drives (Team Drives)

If you are using Google Workspace Shared Drives (formerly Team Drives), set:

```env
GOOGLE_DRIVE_TEAM_DRIVE=true
```

When enabled, all Drive operations automatically pass `supportsAllDrives=true` and `includeItemsFromAllDrives=true`.

#### OAuth Scopes (Least Privilege)

By default the package requests full read/write access. To follow least
privilege, restrict the `scopes` config value (`GOOGLE_DRIVE_SCOPES`) to the
minimum your app needs:

| Use case | Scope | Notes |
|---|---|---|
| Full read/write (default) | `https://www.googleapis.com/auth/drive` | Required for uploads, updates, deletes |
| Read-only | `https://www.googleapis.com/auth/drive.readonly` | Listing and downloading only; writes fail |
| App data only | `https://www.googleapis.com/auth/drive.appdata` | Only the app's own hidden folder |

```env
GOOGLE_DRIVE_SCOPES=https://www.googleapis.com/auth/drive.readonly
```

Multiple scopes can be comma-separated
(`GOOGLE_DRIVE_SCOPES="scope-a,scope-b"`) or set as an array in
`config/google-drive.php`.

> **Important:** the refresh token must have been generated with (at least)
> these scopes. After narrowing scopes, re-run the OAuth flow from
> [docs/GETTING-TOKENS.md](docs/GETTING-TOKENS.md) using the narrowed scope
> in Step 1, otherwise API calls fail with permission errors.

#### Health Check

Validate credentials without touching any files (safe for scheduled jobs):

```bash
php artisan google-drive:check
php artisan google-drive:check --disk=google-backup
```

Exit code `0` means credentials are valid; anything else prints an actionable
error (e.g. which env var to rotate). Token refresh is retried once
automatically on transient `401`/`5xx` failures before giving up.

#### Debug Logging Options

The debug logging options default to `APP_DEBUG` but can be overridden:

- **`GOOGLE_DRIVE_DEBUG`**: Enable detailed debug logging for operations (defaults to `APP_DEBUG`)
- **`GOOGLE_DRIVE_LOG_PAYLOAD`**: Enable logging of HTTP payloads and detailed operation info (defaults to `APP_DEBUG`)

> **Production Tip**: Set both debug options to `false` in production to prevent unnecessary log output.

## 📖 Usage

After configuring, you can use the Google Drive disk in your Laravel application like this:

```php
use Illuminate\Support\Facades\Storage;

// Store a file
Storage::disk('google')->put('example.txt', 'Hello, Google Drive!');

// Retrieve a file
$content = Storage::disk('google')->get('example.txt');

// List files in a directory
$files = Storage::disk('google')->files('/');

// Delete a file
Storage::disk('google')->delete('example.txt');

// Check if file exists
if (Storage::disk('google')->exists('example.txt')) {
    // File exists
}

// Get file size
$size = Storage::disk('google')->size('example.txt');

// Get last modified time
$modified = Storage::disk('google')->lastModified('example.txt');
```

### 🗂️ Working with Folders

```php
// Create a directory (folders are created automatically when uploading files)
Storage::disk('google')->makeDirectory('uploads/images');

// List directories
$directories = Storage::disk('google')->directories('/');

// List all contents (files and folders)
$contents = Storage::disk('google')->allFiles('/');

// Delete a directory and all its contents
Storage::disk('google')->deleteDirectory('uploads/images');
```

For advanced usage and more examples, see [docs/USAGE.md](docs/USAGE.md).

## 🔧 Laravel Compatibility

- **Laravel**: 10.x, 11.x, 12.x, 13.x
- **PHP**: 8.1 or higher
- **Google API Client**: ^2.15
- **Flysystem**: ^3.0

## 📝 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## 🔗 Links

- **Author**: [Michael K. Laweh](https://www.klytron.com)
- **GitHub**: [klytron](https://github.com/klytron)
- **Packagist**: [klytron/laravel-google-drive-filesystem](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
- **Issues**: [GitHub Issues](https://github.com/klytron/laravel-google-drive-filesystem/issues)
- **Funding**: [Buy me a coffee](https://www.klytron.com/buy-me-a-coffee)

## 📚 Documentation

- [Installation Guide](docs/INSTALLATION.md)
- [Getting Google Drive Tokens](docs/GETTING-TOKENS.md)
- [Advanced Usage](docs/USAGE.md)
