<?php

namespace Klytron\GoogleDriveFilesystem\Adapters;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

class GoogleDriveAdapter implements FilesystemAdapter
{
    private Drive $service;
    private PathPrefixer $prefixer;
    private ?string $rootFolderId;
    private array $defaultParameters;

    public function __construct(Client $client, ?string $rootFolderId = null, array $options = [])
    {
        $this->service = new Drive($client);
        $this->prefixer = new PathPrefixer('');
        $this->rootFolderId = $rootFolderId;

        $teamDrive = $options['team_drive'] ?? config('google-drive.team_drive', false);
        $this->defaultParameters = $teamDrive ? [
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ] : [];
    }

    private function applyDriveOptions(array $parameters = []): array
    {
        return array_merge($this->defaultParameters, $parameters);
    }

    public function fileExists(string $path): bool
    {
        try {
            $file = $this->getFileByPath($path);
            return $file !== null;
        } catch (\Exception $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $folder = $this->getFolderByPath($path);
            return $folder !== null;
        } catch (\Exception $e) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $e);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $existingFile = $this->getFileByPath($path);

            if ($existingFile) {
                // Update existing file content instead of creating duplicate
                $fileMetadata = new DriveFile();
                $fileMeta = $this->service->files->update($existingFile->getId(), $fileMetadata, $this->applyDriveOptions([
                    'data' => $contents,
                    'mimeType' => $this->getMimeType($path),
                    'uploadType' => 'multipart',
                    'fields' => 'id,size,modifiedTime'
                ]));
                $fileId = $existingFile->getId();
            } else {
                $pathInfo = pathinfo($path);
                $parentId = $this->getOrCreateParentFolder($pathInfo['dirname'] ?? '');
                
                $file = new DriveFile();
                $file->setName($pathInfo['basename']);
                $file->setParents([$parentId]);

                $createdFile = $this->service->files->create($file, $this->applyDriveOptions([
                    'data' => $contents,
                    'mimeType' => $this->getMimeType($path),
                    'uploadType' => 'multipart',
                    'fields' => 'id'
                ]));
                $fileId = $createdFile->getId();
                $fileMeta = $this->service->files->get($fileId, $this->applyDriveOptions(['fields' => 'id,size,modifiedTime']));
            }
            
            if (config('google-drive.log_payload', config('app.debug', false))) {
                \Log::debug('[GoogleDriveAdapter] write: Fetched file metadata after upload', [
                    'id' => $fileId,
                    'size' => $fileMeta->getSize(),
                    'modifiedTime' => $fileMeta->getModifiedTime(),
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('[GoogleDriveAdapter] write error', ['path' => $path, 'error' => $e->getMessage()]);
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $existingFile = $this->getFileByPath($path);

            if ($existingFile) {
                $fileMetadata = new DriveFile();
                $this->service->files->update($existingFile->getId(), $fileMetadata, $this->applyDriveOptions([
                    'data' => $contents,
                    'mimeType' => $this->getMimeType($path),
                    'uploadType' => 'resumable',
                    'fields' => 'id'
                ]));
            } else {
                $pathInfo = pathinfo($path);
                $parentId = $this->getOrCreateParentFolder($pathInfo['dirname'] ?? '');
                
                $file = new DriveFile();
                $file->setName($pathInfo['basename']);
                $file->setParents([$parentId]);

                $this->service->files->create($file, $this->applyDriveOptions([
                    'data' => $contents,
                    'mimeType' => $this->getMimeType($path),
                    'uploadType' => 'resumable',
                    'fields' => 'id'
                ]));
            }
        } catch (\Exception $e) {
            \Log::error('[GoogleDriveAdapter] writeStream error', ['path' => $path, 'error' => $e->getMessage()]);
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $file = $this->getFileByPath($path);
            if (!$file) {
                throw new \Exception("File not found: {$path}");
            }

            $response = $this->service->files->get($file->getId(), $this->applyDriveOptions(['alt' => 'media']));
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $file = $this->getFileByPath($path);
            if (!$file) {
                throw new \Exception("File not found: {$path}");
            }

            $response = $this->service->files->get($file->getId(), $this->applyDriveOptions(['alt' => 'media']));
            $body = $response->getBody();

            $resource = $body->detach();
            if (is_resource($resource)) {
                return $resource;
            }

            // Fallback chunked streaming into temp stream
            $stream = fopen('php://temp', 'r+');
            while (!$body->eof()) {
                fwrite($stream, $body->read(1048576));
            }
            rewind($stream);
            return $stream;
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $file = $this->getFileByPath($path);
            if (!$file) {
                throw new \Exception("File not found: {$path}");
            }

            $this->service->files->delete($file->getId(), $this->defaultParameters);
        } catch (\Exception $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $folder = $this->getFolderByPath($path);
            if (!$folder) {
                throw new \Exception("Directory not found: {$path}");
            }

            $this->service->files->delete($folder->getId(), $this->defaultParameters);
        } catch (\Exception $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->getOrCreateParentFolder($path);
        } catch (\Exception $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Google Drive does not support visibility settings');
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'Google Drive does not support visibility settings');
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $file = $this->getFileByPath($path);
            if (!$file) {
                throw new \Exception("File not found: {$path}");
            }

            return new FileAttributes($path, null, null, null, $file->getMimeType());
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $file = $this->getFileByPath($path);
            if (!$file) {
                throw new \Exception("File not found: {$path}");
            }

            // If modifiedTime is null, try to get fresh metadata
            $modifiedTime = $file->getModifiedTime();
            if ($modifiedTime === null) {
                if (config('google-drive.debug', config('app.debug', false))) {
                    \Log::debug('[GoogleDriveAdapter] lastModified: ModifiedTime is null, fetching fresh metadata', ['path' => $path]);
                }
                $freshFile = $this->getFileMetadata($file->getId());
                if ($freshFile) {
                    $modifiedTime = $freshFile->getModifiedTime();
                }
            }

            $timestamp = strtotime($modifiedTime);
            return new FileAttributes($path, null, null, $timestamp);
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $file = $this->getFileByPath($path);
            if (!$file) {
                if (config('google-drive.debug', config('app.debug', false))) {
                    \Log::debug('[GoogleDriveAdapter] fileSize: File not found', ['path' => $path]);
                }
                throw new \Exception("File not found: {$path}");
            }

            // If size is null or 0, try to get fresh metadata
            $size = $file->getSize();
            if ($size === null || $size === 0) {
                if (config('google-drive.debug', config('app.debug', false))) {
                    \Log::debug('[GoogleDriveAdapter] fileSize: Size is null/0, fetching fresh metadata', ['path' => $path]);
                }
                $freshFile = $this->getFileMetadata($file->getId());
                if ($freshFile) {
                    $size = $freshFile->getSize();
                }
            }

            if (config('google-drive.debug', config('app.debug', false))) {
                \Log::debug('[GoogleDriveAdapter] fileSize', ['path' => $path, 'size' => $size]);
            }
            return new FileAttributes($path, (int) $size);
        } catch (\Exception $e) {
            \Log::error('[GoogleDriveAdapter] fileSize error', ['path' => $path, 'error' => $e->getMessage()]);
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $folderId = $path === '' ? $this->rootFolderId : $this->getFolderByPath($path)?->getId();
            if (!$folderId) {
                return [];
            }

            $query = "'{$folderId}' in parents and trashed=false";
            $pageToken = null;

            do {
                $parameters = $this->applyDriveOptions([
                    'q' => $query,
                    'pageSize' => 1000,
                    'fields' => 'nextPageToken,files(id,name,size,modifiedTime,mimeType,parents)'
                ]);

                if ($pageToken !== null) {
                    $parameters['pageToken'] = $pageToken;
                }

                $fileList = $this->service->files->listFiles($parameters);
                $files = $fileList->getFiles() ?? [];

                foreach ($files as $file) {
                    $filePath = $path === '' ? $file->getName() : $path . '/' . $file->getName();
                    
                    if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                        yield new DirectoryAttributes($filePath);
                        
                        if ($deep) {
                            yield from $this->listContents($filePath, true);
                        }
                    } else {
                        $modifiedTime = $file->getModifiedTime();
                        $timestamp = $modifiedTime ? (strtotime($modifiedTime) ?: null) : null;

                        yield new FileAttributes(
                            $filePath,
                            $file->getSize() !== null ? (int) $file->getSize() : null,
                            null,
                            $timestamp,
                            $file->getMimeType()
                        );
                    }
                }

                $pageToken = $fileList->getNextPageToken();
            } while ($pageToken !== null);

        } catch (\Exception $e) {
            throw UnableToListContents::atLocation($path, $deep, $e);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $file = $this->getFileByPath($source);
            if (!$file) {
                throw new \Exception("Source file not found: {$source}");
            }

            $destinationInfo = pathinfo($destination);
            $newParentId = $this->getOrCreateParentFolder($destinationInfo['dirname'] ?? '');
            
            $updatedFile = new DriveFile();
            $updatedFile->setName($destinationInfo['basename']);
            
            $params = [
                'addParents' => $newParentId,
            ];

            $parents = $file->getParents();
            if (!empty($parents)) {
                $params['removeParents'] = implode(',', $parents);
            }
            
            $this->service->files->update($file->getId(), $updatedFile, $this->applyDriveOptions($params));
        } catch (\Exception $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $sourceFile = $this->getFileByPath($source);
            if (!$sourceFile) {
                throw new \Exception("Source file not found: {$source}");
            }

            $destinationInfo = pathinfo($destination);
            $parentId = $this->getOrCreateParentFolder($destinationInfo['dirname'] ?? '');
            
            $copiedFile = new DriveFile();
            $copiedFile->setName($destinationInfo['basename']);
            $copiedFile->setParents([$parentId]);

            $this->service->files->copy($sourceFile->getId(), $copiedFile, $this->defaultParameters);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($destination, $e->getMessage(), $e);
        }
    }

    private function getFileByPath(string $path): ?DriveFile
    {
        $pathParts = explode('/', trim($path, '/'));
        $fileName = array_pop($pathParts);
        $parentPath = implode('/', $pathParts);
        
        $parentId = $parentPath === '' ? $this->rootFolderId : $this->getFolderByPath($parentPath)?->getId();
        if (!$parentId) {
            if (config('google-drive.debug', config('app.debug', false))) {
                \Log::debug('[GoogleDriveAdapter] getFileByPath: Parent folder not found', ['path' => $path, 'parentPath' => $parentPath]);
            }
            return null;
        }

        $escapedFileName = $this->escapeQueryString($fileName);
        $query = "name='{$escapedFileName}' and '{$parentId}' in parents and trashed=false and mimeType!='application/vnd.google-apps.folder'";
        $files = $this->service->files->listFiles($this->applyDriveOptions([
            'q' => $query,
            'fields' => 'files(id,name,size,modifiedTime,mimeType,parents)'
        ]))->getFiles();
        $file = $files[0] ?? null;
        
        if (config('google-drive.log_payload', config('app.debug', false))) {
            \Log::debug('[GoogleDriveAdapter] getFileByPath', ['path' => $path, 'file' => $file]);
        }
        return $file;
    }

    private function getFolderByPath(string $path): ?DriveFile
    {
        if ($path === '' || $path === '.') {
            return $this->rootFolderId ? $this->service->files->get($this->rootFolderId, $this->defaultParameters) : null;
        }

        $pathParts = explode('/', trim($path, '/'));
        $currentId = $this->rootFolderId;

        foreach ($pathParts as $folderName) {
            $escapedFolderName = $this->escapeQueryString($folderName);
            $query = "name='{$escapedFolderName}' and '{$currentId}' in parents and trashed=false and mimeType='application/vnd.google-apps.folder'";
            $folders = $this->service->files->listFiles($this->applyDriveOptions(['q' => $query]))->getFiles();
            
            if (empty($folders)) {
                return null;
            }
            
            $currentId = $folders[0]->getId();
        }

        return $this->service->files->get($currentId, $this->defaultParameters);
    }

    private function getOrCreateParentFolder(string $path): string
    {
        if ($path === '' || $path === '.') {
            return $this->rootFolderId ?? 'root';
        }

        $folder = $this->getFolderByPath($path);
        if ($folder) {
            return $folder->getId();
        }

        // Create the folder structure
        $pathParts = explode('/', trim($path, '/'));
        $currentId = $this->rootFolderId ?? 'root';

        foreach ($pathParts as $folderName) {
            $escapedFolderName = $this->escapeQueryString($folderName);
            $query = "name='{$escapedFolderName}' and '{$currentId}' in parents and trashed=false and mimeType='application/vnd.google-apps.folder'";
            $folders = $this->service->files->listFiles($this->applyDriveOptions(['q' => $query]))->getFiles();
            
            if (empty($folders)) {
                $folder = new DriveFile();
                $folder->setName($folderName);
                $folder->setMimeType('application/vnd.google-apps.folder');
                $folder->setParents([$currentId]);
                
                $createdFolder = $this->service->files->create($folder, $this->defaultParameters);
                $currentId = $createdFolder->getId();
            } else {
                $currentId = $folders[0]->getId();
            }
        }

        return $currentId;
    }

    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'sql' => 'application/sql',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Escape special characters in query strings for Google Drive API.
     * Google Drive API queries use single quotes as string delimiters.
     * Single quotes within the string must be escaped by replacing with \'.
     *
     * @param string $str The string to escape
     * @return string The escaped string safe for use in queries
     */
    private function escapeQueryString(string $str): string
    {
        // Escape single quotes by replacing ' with \'
        // Also escape backslash to prevent injection
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $str);
    }

    /**
     * Get file metadata with all required fields
     * This method ensures that size and modifiedTime are properly retrieved
     */
    private function getFileMetadata(string $fileId): ?DriveFile
    {
        try {
            return $this->service->files->get($fileId, $this->applyDriveOptions([
                'fields' => 'id,name,size,modifiedTime,mimeType,parents'
            ]));
        } catch (\Exception $e) {
            if (config('google-drive.debug', config('app.debug', false))) {
                \Log::error('[GoogleDriveAdapter] getFileMetadata error', [
                    'fileId' => $fileId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
}
