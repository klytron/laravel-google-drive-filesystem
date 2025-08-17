# Installation Guide

## Via Packagist (Recommended)

Install via Composer:

```
composer require klytron/laravel-google-drive-filesystem
```

## Via VCS (Development)

If you want to use the latest development version directly from GitHub:

1. Add the following to your `composer.json`:

```
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/klytron/laravel-google-drive-filesystem"
    }
],
"require": {
    "klytron/laravel-google-drive-filesystem": "@dev"
}
```

2. Then run:

```
composer update
```

## Troubleshooting

- Make sure your PHP and Laravel versions meet the requirements.
- If you have issues with package discovery, run `composer dump-autoload`.
- For more help, open an issue on [GitHub](https://github.com/klytron/laravel-google-drive-filesystem/issues).
