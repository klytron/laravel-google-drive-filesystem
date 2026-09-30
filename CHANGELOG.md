# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- **Shared Drives (Team Drives) Support**: Configurable `team_drive` setting (`GOOGLE_DRIVE_TEAM_DRIVE`) enabling `supportsAllDrives` and `includeItemsFromAllDrives` across all Google Drive API operations.
- **Expanded MIME Types**: Added common types including `webp`, `svg`, `csv`, `tar`, `gz`, `sql`, `mp4`, `mp3`.
- **Packaging Hygiene**: Added `.gitattributes` and `.gitignore`.

### Changed
- **True Stream Reading**: `readStream()` now directly detaches the PSR-7 response socket/stream or streams in 1MB chunks without buffering entire large files into PHP memory.

### Fixed
- **Duplicate File Creation (`write()` / `writeStream()`)**: Flysystem writes now check if the file already exists and call `files->update()` instead of endlessly creating duplicate files with the same name.
- **Directory Traversal Pagination**: `listContents()` now paginates using `pageToken` with `pageSize=1000`, preventing silent omission of files in directories with >100 items.
- **Null Parent Deprecation in `move()`**: Made `$file->getParents()` null-safe to prevent PHP 8.1+ deprecations when moving root-level files.
- **Actionable OAuth Token Refresh Errors**: Detects `error` in token response array and throws actionable instructions instead of allowing cryptic downstream 401s.

## [1.3.1] - 2026-03-31

### Fixed
- Fixed token cache persistence across multiple requests.

## [1.3.0] - 2026-03-31

### Added
- Support for Laravel 13 and PHP 8.4.

## [1.2.0] - 2025-08-15

### Added
- Rebranded from scryba to klytron.
- Updated namespaces, configuration prefixes, and documentation.

## [1.1.3] - 2025-01-27

### Fixed

- **Production Debug Logging Issue**: Fixed hardcoded debug logging that was executing in production environments
- Added configurable debug logging options to prevent unnecessary log output in production
- Implemented conditional logging based on configuration settings

### Added

- New configuration options for debug logging control:
  - `GOOGLE_DRIVE_DEBUG`: Enable detailed debug logging for operations (defaults to `APP_DEBUG`)
  - `GOOGLE_DRIVE_LOG_PAYLOAD`: Enable logging of HTTP payloads and detailed operation info (defaults to `APP_DEBUG`)
- Updated configuration file with debug logging settings
- Enhanced README.md with environment variable documentation

### Changed

- All debug logging statements now respect configuration settings
- Error logging for non-critical operations is now conditional
- Follows the same pattern as other packages in the ecosystem for consistent debug logging behavior

## [1.1.2] - 2025-07-28

### Changed

- Removed outdated file size limitation notice from README.md
- Documentation now reflects that file sizes and modification dates work correctly immediately

## [1.1.1] - 2025-07-28

### Fixed

- Fixed file size and modification time retrieval issue by explicitly requesting required fields in Google Drive API calls
- Added proper fields parameter ('id,name,size,modifiedTime,mimeType,parents') to listFiles() calls
- Enhanced fileSize() and lastModified() methods with fallback metadata retrieval
- Added getFileMetadata() helper method for reliable metadata fetching
- Improved error logging and debugging for file metadata operations

### Technical Details

- Resolved Google Drive API issue where size and modifiedTime fields were not populated
- Implemented workaround for googleapis/google-api-php-client#1257
- Enhanced both getFileByPath() and listContents() methods to request complete file metadata

## [1.0.0] - 2024-06-09

### Added

- Initial release of scryba/laravel-google-drive-filesystem
- Google Drive Flysystem adapter for Laravel 10, 11, 12
- Service provider for Laravel integration
- Configuration publishing and environment variable support
- Usage and setup documentation
