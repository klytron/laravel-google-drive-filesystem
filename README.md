# klytron/laravel-google-drive-filesystem

[![Latest Version on Packagist](https://img.shields.io/packagist/v/klytron/laravel-google-drive-filesystem.svg?style=flat-square)](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
[![Total Downloads](https://img.shields.io/packagist/dt/klytron/laravel-google-drive-filesystem.svg?style=flat-square)](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
[![License](https://img.shields.io/packagist/l/klytron/laravel-google-drive-filesystem.svg?style=flat-square)](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
[![PHP Version](https://img.shields.io/packagist/php-v/klytron/laravel-google-drive-filesystem.svg?style=flat-square)](https://packagist.org/packages/klytron/laravel-google-drive-filesystem)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x%20%7C%2013.x-orange.svg?style=flat-square)](https://laravel.com/)

A robust Google Drive filesystem adapter for Laravel that provides seamless integration with Google Drive as a storage disk. Features configurable debug logging, automatic folder creation, and full Laravel Filesystem API compatibility.

## ✨ Features

- 🚀 **Full Laravel Filesystem API Support** - Use Google Drive like any other Laravel disk
- 🔧 **Configurable Debug Logging** - Control debug output in production environments
- 📁 **Automatic Folder Creation** - Folders are created automatically when needed
- 🔐 **Secure Authentication** - Support for both access tokens and refresh tokens
- 📊 **Metadata Support** - File sizes, modification times, and MIME types
- 🛡️ **Production Ready** - Proper error handling and logging configuration
- 📚 **Comprehensive Documentation** - Detailed setup and usage guides

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x
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

# Debug Logging (optional)
GOOGLE_DRIVE_DEBUG=false
GOOGLE_DRIVE_LOG_PAYLOAD=false
```

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
