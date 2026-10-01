<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filesystem Driver
    |--------------------------------------------------------------------------
    |
    | Identifies this configuration as the "google" filesystem driver. The
    | service provider merges this file into `filesystems.disks.google`,
    | and Laravel requires every disk to declare its driver at boot.
    |
    */

    'driver' => 'google',

    /*
    |--------------------------------------------------------------------------
    | Google Drive API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Google Drive API settings. You will need
    | to create a project in Google Cloud Console and enable the Drive API.
    |
    */

    'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI', 'http://localhost'),
    'access_token' => env('GOOGLE_DRIVE_ACCESS_TOKEN'),
    'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
    
    /*
    |--------------------------------------------------------------------------
    | Google Drive Folder ID
    |--------------------------------------------------------------------------
    |
    | The folder ID where files should be stored. If not set, files will be
    | stored in the root directory of Google Drive.
    |
    */
    'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Debug Logging
    |--------------------------------------------------------------------------
    |
    | If true, logs detailed debug info for Google Drive operations.
    | Defaults to app.debug, but can be overridden here.
    |
    */
    'debug' => env('GOOGLE_DRIVE_DEBUG', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Log Payload
    |--------------------------------------------------------------------------
    |
    | If true, logs HTTP payload and detailed operation information.
    | Defaults to app.debug, but can be overridden here.
    |
    */
    'log_payload' => env('GOOGLE_DRIVE_LOG_PAYLOAD', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | OAuth Scopes
    |--------------------------------------------------------------------------
    |
    | The OAuth scopes granted to the credentials above. Defaults to full
    | read/write access. For least privilege, restrict this to one of:
    |
    | - https://www.googleapis.com/auth/drive          (full read/write)
    | - https://www.googleapis.com/auth/drive.readonly (read-only)
    | - https://www.googleapis.com/auth/drive.appdata  (app data folder only)
    |
    | May be a single scope, a comma-separated list
    | (GOOGLE_DRIVE_SCOPES="scope-a,scope-b"), or an array when set
    | programmatically. IMPORTANT: the refresh token must have been
    | generated with (at least) these scopes, otherwise API calls fail
    | with permission errors — re-run the OAuth flow after narrowing.
    |
    */
    'scopes' => env('GOOGLE_DRIVE_SCOPES', ['https://www.googleapis.com/auth/drive']),

    /*
    |--------------------------------------------------------------------------
    | Shared Drives (Team Drives) Support
    |--------------------------------------------------------------------------
    |
    | When true, sets supportsAllDrives and includeItemsFromAllDrives flags
    | to allow operations on files and folders within Shared Drives.
    |
    */
    'team_drive' => env('GOOGLE_DRIVE_TEAM_DRIVE', false),
];
