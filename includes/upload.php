<?php
/**
 * WebsiteTailors - Secure File Upload Handler
 * 
 * Provides strict validation against malicious uploads (MIME checking via finfo,
 * extension whitelist, randomized filenames, and path traversal prevention).
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    die('Direct access not permitted.');
}

/**
 * Handle a secure file upload
 *
 * @param array<string, mixed> $file $_FILES['input_name']
 * @param string $subfolder 'projects' | 'testimonials' | 'settings' | 'general'
 * @param array<string>|null $allowedExtensions
 * @param array<string>|null $allowedMimes
 * @param int $maxSize
 * @return array{success: bool, path: ?string, url: ?string, error: ?string}
 */
function handle_file_upload(
    array $file,
    string $subfolder = 'general',
    ?array $allowedExtensions = null,
    ?array $allowedMimes = null,
    int $maxSize = MAX_UPLOAD_SIZE
): array {
    $allowedExtensions ??= ALLOWED_UPLOAD_EXTENSIONS;
    $allowedMimes ??= ALLOWED_UPLOAD_MIMES;

    // 1. Verify standard PHP upload error code
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];
        return [
            'success' => false,
            'path'    => null,
            'url'     => null,
            'error'   => $errorMessages[$errorCode] ?? 'Unknown upload error occurred.'
        ];
    }

    // 2. Validate file size
    $fileSize = (int)($file['size'] ?? 0);
    if ($fileSize <= 0 || $fileSize > $maxSize) {
        return [
            'success' => false,
            'path'    => null,
            'url'     => null,
            'error'   => sprintf('File size exceeds the allowable limit of %.1f MB.', $maxSize / (1024 * 1024))
        ];
    }

    // 3. Validate file extension strictly against whitelist
    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        return [
            'success' => false,
            'path'    => null,
            'url'     => null,
            'error'   => 'Invalid file type extension: .' . e($extension) . '. Allowed types: ' . implode(', ', $allowedExtensions)
        ];
    }

    // 4. Verify file was uploaded via HTTP POST
    $tmpPath = $file['tmp_name'] ?? '';
    if (php_sapi_name() !== 'cli' && !is_uploaded_file($tmpPath)) {
        return [
            'success' => false,
            'path'    => null,
            'url'     => null,
            'error'   => 'Potential attack detected: file was not uploaded via HTTP POST.'
        ];
    }

    // 4. Validate authentic MIME type via PHP Fileinfo extension
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);

    // If svg, do additional sanity check to avoid script injections
    if ($extension === 'svg') {
        $svgContent = file_get_contents($tmpPath);
        if ($svgContent === false || stripos($svgContent, '<script') !== false || stripos($svgContent, 'javascript:') !== false) {
            return [
                'success' => false,
                'path'    => null,
                'url'     => null,
                'error'   => 'SVG file contains potentially hazardous scripting.'
            ];
        }
    } else {
        if (!in_array($mimeType, $allowedMimes, true)) {
            return [
                'success' => false,
                'path'    => null,
                'url'     => null,
                'error'   => 'File MIME inspection failed (' . e($mimeType) . '). Allowed: ' . implode(', ', $allowedMimes)
            ];
        }
    }

    // 5. Build secure target path
    $safeSubfolder = preg_replace('/[^a-zA-Z0-9_\-]/', '', $subfolder);
    $targetDir = UPLOADS_PATH . '/' . $safeSubfolder;

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return [
                'success' => false,
                'path'    => null,
                'url'     => null,
                'error'   => 'Could not initialize upload target directory on server.'
            ];
        }
    }

    // Generate random unguessable filename
    $newFilename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $targetDir . '/' . $newFilename;

    // 6. Move file safely
    if (!move_uploaded_file($tmpPath, $destination)) {
        return [
            'success' => false,
            'path'    => null,
            'url'     => null,
            'error'   => 'Failed to move uploaded file to permanent destination.'
        ];
    }

    // Ensure permissions
    chmod($destination, 0644);

    $relativePath = '/uploads/' . $safeSubfolder . '/' . $newFilename;
    $publicUrl = UPLOADS_URL . '/' . $safeSubfolder . '/' . $newFilename;

    return [
        'success' => true,
        'path'    => $relativePath,
        'url'     => $publicUrl,
        'error'   => null
    ];
}

/**
 * Safely delete an uploaded file
 *
 * @param string $relativePath e.g. '/uploads/projects/xyz.webp'
 * @return bool
 */
function delete_uploaded_file(string $relativePath): bool
{
    $cleanPath = ltrim($relativePath, '/\\');
    // Ensure the path begins with 'uploads/' to prevent traversal
    if (!str_starts_with($cleanPath, 'uploads/')) {
        return false;
    }

    $fullPath = ROOT_PATH . '/' . $cleanPath;
    $realUploads = realpath(UPLOADS_PATH);
    $realTarget = realpath($fullPath);

    // Verify resolved path stays strictly within uploads directory
    if ($realTarget && $realUploads && str_starts_with($realTarget, $realUploads) && is_file($realTarget)) {
        return unlink($realTarget);
    }

    return false;
}
