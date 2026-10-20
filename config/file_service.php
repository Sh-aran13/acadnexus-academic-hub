<?php
// config/file_service.php
// Centralized Secure File Management Service

require_once __DIR__ . '/database.php';

class FileService {
    // Category configurations defining allowed extensions, MIME types, and maximum sizes
    private static $categories = [
        'avatar' => [
            'max_size'   => 5242880, // 5 MB
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'mimes'      => ['image/jpeg', 'image/png', 'image/webp']
        ],
        'material' => [
            'max_size'   => 26214400, // 25 MB
            'extensions' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip'],
            'mimes'      => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'application/zip',
                'application/x-zip-compressed'
            ]
        ],
        'submission' => [
            'max_size'   => 26214400, // 25 MB
            'extensions' => ['pdf', 'doc', 'docx', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'txt'],
            'mimes'      => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/x-zip-compressed',
                'application/x-rar',
                'application/x-rar-compressed',
                'application/vnd.rar',
                'image/jpeg',
                'image/png',
                'text/plain'
            ]
        ],
        'assignment_attachment' => [
            'max_size'   => 26214400, // 25 MB
            'extensions' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png'],
            'mimes'      => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
                'application/x-zip-compressed',
                'image/jpeg',
                'image/png'
            ]
        ],
        'announcement' => [
            'max_size'   => 10485760, // 10 MB
            'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
            'mimes'      => [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif'
            ]
        ],
        'project' => [
            'max_size'   => 26214400, // 25 MB
            'extensions' => ['pdf', 'doc', 'docx', 'zip', 'txt'],
            'mimes'      => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/x-zip-compressed',
                'text/plain'
            ]
        ]
    ];

    // Explicit blacklist of dangerous extensions
    private static $blacklistedExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
        'exe', 'bat', 'cmd', 'sh', 'bash', 'bin', 'pl', 'py', 'cgi',
        'js', 'jsp', 'asp', 'aspx', 'htm', 'html', 'shtml', 'svg', 'vbs'
    ];

    /**
     * Upload and validate a file securely.
     *
     * @param array  $fileEntry     The $_FILES item
     * @param string $category      One of the defined categories
     * @param string $targetSubDir  Subdirectory relative to uploads/
     * @param array  $customOptions Optional overrides
     * @return array ['success' => bool, 'file_path' => string, 'error' => string, ...]
     */
    public static function upload($fileEntry, $category, $targetSubDir, $customOptions = []) {
        if (!isset($fileEntry) || !is_array($fileEntry) || !isset($fileEntry['error'])) {
            return ['success' => false, 'error' => 'No file was provided or upload request was invalid.'];
        }

        // Validate PHP upload status
        if ($fileEntry['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => self::getErrorMessage($fileEntry['error'])];
        }

        if (!is_uploaded_file($fileEntry['tmp_name'])) {
            return ['success' => false, 'error' => 'File upload tampering detected.'];
        }

        $config = self::$categories[$category] ?? self::$categories['material'];
        $maxSize = $customOptions['max_size'] ?? $config['max_size'];
        $allowedExts = $customOptions['extensions'] ?? $config['extensions'];
        $allowedMimes = $customOptions['mimes'] ?? $config['mimes'];

        $fileSize = (int)$fileEntry['size'];
        if ($fileSize <= 0) {
            return ['success' => false, 'error' => 'The uploaded file is empty.'];
        }

        if ($fileSize > $maxSize) {
            $maxMb = round($maxSize / (1024 * 1024));
            return ['success' => false, 'error' => "The uploaded file exceeds the {$maxMb}MB maximum limit for this category."];
        }

        // Validate extension
        $originalName = basename($fileEntry['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (empty($ext) || in_array($ext, self::$blacklistedExtensions, true)) {
            return ['success' => false, 'error' => 'Executable, script, or unsafe file types are strictly prohibited.'];
        }

        if (!in_array($ext, $allowedExts, true)) {
            return ['success' => false, 'error' => 'Invalid file extension. Permitted extensions: ' . implode(', ', $allowedExts)];
        }

        // MIME-type sniffing using finfo with robust fallback
        $detectedMime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $fileEntry['tmp_name']);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $detectedMime = mime_content_type($fileEntry['tmp_name']);
        } else {
            // Robust binary magic bytes inspection fallback
            $handle = @fopen($fileEntry['tmp_name'], 'rb');
            if ($handle) {
                $magic = fread($handle, 16);
                fclose($handle);
                if (str_starts_with($magic, "%PDF-")) {
                    $detectedMime = 'application/pdf';
                } elseif (str_starts_with($magic, "\xFF\xD8\xFF")) {
                    $detectedMime = 'image/jpeg';
                } elseif (str_starts_with($magic, "\x89PNG\r\n\x1a\n")) {
                    $detectedMime = 'image/png';
                } elseif (str_starts_with($magic, "GIF87a") || str_starts_with($magic, "GIF89a")) {
                    $detectedMime = 'image/gif';
                } elseif (str_starts_with($magic, "RIFF") && substr($magic, 8, 4) === "WEBP") {
                    $detectedMime = 'image/webp';
                } elseif (str_starts_with(trim($magic), "<?php") || str_starts_with(trim($magic), "<script")) {
                    $detectedMime = 'application/x-php';
                }
            }
        }

        if (!$detectedMime) {
            return ['success' => false, 'error' => 'Could not determine file type.'];
        }

        // Disallow dangerous MIME types
        if (str_starts_with($detectedMime, 'application/x-php') || 
            str_starts_with($detectedMime, 'text/html') || 
            str_starts_with($detectedMime, 'application/javascript') ||
            str_starts_with($detectedMime, 'application/x-executable')) {
            return ['success' => false, 'error' => 'Unsafe file MIME type detected. Upload rejected.'];
        }

        if (!in_array($detectedMime, $allowedMimes, true)) {
            return ['success' => false, 'error' => "Content type mismatch ({$detectedMime}) for extension .{$ext}."];
        }

        // Image integrity validation for image uploads
        if (str_starts_with($detectedMime, 'image/') && $ext !== 'pdf') {
            $imgInfo = @getimagesize($fileEntry['tmp_name']);
            if ($imgInfo === false) {
                return ['success' => false, 'error' => 'Uploaded file is not a valid, intact image.'];
            }
        }

        // Sanitize subdirectory and create destination
        $cleanSubDir = self::sanitizeSubDir($targetSubDir);
        $baseUploadDir = dirname(__DIR__) . '/uploads/' . $cleanSubDir . '/';

        if (!is_dir($baseUploadDir)) {
            if (!mkdir($baseUploadDir, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create storage directory on server.'];
            }
        }

        // Generate cryptographically unpredictable filename
        $storedName = 'doc_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPhysicalPath = $baseUploadDir . $storedName;
        $relativePath = 'uploads/' . $cleanSubDir . '/' . $storedName;

        if (move_uploaded_file($fileEntry['tmp_name'], $targetPhysicalPath)) {
            // Set safe file permissions
            @chmod($targetPhysicalPath, 0644);

            return [
                'success'       => true,
                'file_path'     => $relativePath,
                'original_name' => htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'),
                'file_type'     => $ext,
                'file_size'     => $fileSize,
                'mime_type'     => $detectedMime
            ];
        }

        return ['success' => false, 'error' => 'Failed to save uploaded file to persistent storage.'];
    }

    /**
     * Decode and safely store a base64 camera image (for announcements or camera captures).
     *
     * @param string $dataUrl     The "data:image/...;base64,..." string
     * @param string $targetSubDir Subdirectory under uploads/
     * @return array
     */
    public static function uploadBase64Image($dataUrl, $targetSubDir) {
        if (empty($dataUrl) || !preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $dataUrl, $m)) {
            return ['success' => false, 'error' => 'Invalid image format or missing base64 image data.'];
        }

        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $parts = explode(',', $dataUrl, 2);
        if (count($parts) !== 2) {
            return ['success' => false, 'error' => 'Malformed base64 image payload.'];
        }

        $decoded = base64_decode($parts[1], true);
        if ($decoded === false || strlen($decoded) === 0) {
            return ['success' => false, 'error' => 'Failed to decode base64 image data.'];
        }

        if (strlen($decoded) > 10485760) { // 10 MB limit
            return ['success' => false, 'error' => 'Captured image exceeds 10MB limit.'];
        }

        // Verify MIME type of decoded buffer with robust fallback
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($finfo, $decoded);
            finfo_close($finfo);
        } else {
            if (str_starts_with($decoded, "\xFF\xD8\xFF")) {
                $mime = 'image/jpeg';
            } elseif (str_starts_with($decoded, "\x89PNG\r\n\x1a\n")) {
                $mime = 'image/png';
            } elseif (str_starts_with($decoded, "RIFF") && substr($decoded, 8, 4) === "WEBP") {
                $mime = 'image/webp';
            }
        }

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['success' => false, 'error' => 'Decoded content is not a supported image format.'];
        }

        // Validate with getimagesizefromstring if available
        if (function_exists('getimagesizefromstring')) {
            if (!@getimagesizefromstring($decoded)) {
                return ['success' => false, 'error' => 'Corrupted image data.'];
            }
        }

        $cleanSubDir = self::sanitizeSubDir($targetSubDir);
        $baseUploadDir = dirname(__DIR__) . '/uploads/' . $cleanSubDir . '/';
        if (!is_dir($baseUploadDir)) {
            mkdir($baseUploadDir, 0755, true);
        }

        $storedName = 'cam_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPhysicalPath = $baseUploadDir . $storedName;
        $relativePath = 'uploads/' . $cleanSubDir . '/' . $storedName;

        if (file_put_contents($targetPhysicalPath, $decoded)) {
            @chmod($targetPhysicalPath, 0644);
            return [
                'success'   => true,
                'file_path' => $relativePath,
                'file_type' => $ext,
                'file_size' => strlen($decoded),
                'mime_type' => $mime
            ];
        }

        return ['success' => false, 'error' => 'Failed to write image data to disk.'];
    }

    /**
     * Safely unlinks a file located within the uploads directory.
     * Prevents path traversal and never deletes files outside uploads/.
     *
     * @param string|null $relativePath Relative path starting with 'uploads/'
     * @return bool
     */
    public static function delete($relativePath) {
        if (empty($relativePath)) {
            return false;
        }

        $uploadsBase = realpath(dirname(__DIR__) . '/uploads');
        if (!$uploadsBase) {
            return false;
        }

        $cleanRel = ltrim(str_replace(['../', '..\\'], '', $relativePath), '/\\');
        $fullPath = dirname(__DIR__) . '/' . $cleanRel;
        $realTarget = realpath($fullPath);

        if ($realTarget && str_starts_with($realTarget, $uploadsBase) && is_file($realTarget)) {
            return @unlink($realTarget);
        }

        return false;
    }

    /**
     * Streams an authorized file download with strict headers.
     *
     * @param string      $relativePath Relative path under project root (e.g. 'uploads/...')
     * @param string|null $downloadName Display name for download dialog
     * @param bool        $inline       Whether to display inline (e.g. PDF view) or force attachment
     */
    public static function serveDownload($relativePath, $downloadName = null, $inline = false) {
        $uploadsBase = realpath(dirname(__DIR__) . '/uploads');
        if (!$uploadsBase) {
            http_response_code(404);
            die("File not found.");
        }

        $cleanRel = ltrim(str_replace(['../', '..\\'], '', $relativePath), '/\\');
        $fullPath = dirname(__DIR__) . '/' . $cleanRel;
        $realTarget = realpath($fullPath);

        if (!$realTarget || !str_starts_with($realTarget, $uploadsBase) || !is_file($realTarget)) {
            http_response_code(404);
            die("Requested document does not exist or has been removed.");
        }

        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $realTarget) ?: 'application/octet-stream';
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($realTarget) ?: 'application/octet-stream';
        } else {
            // Extension-based mapping fallback
            $targetExt = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
            $extMimes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'txt' => 'text/plain',
                'zip' => 'application/zip'
            ];
            $mime = $extMimes[$targetExt] ?? 'application/octet-stream';
        }

        $safeName = $downloadName ? basename($downloadName) : basename($realTarget);
        // Sanitize download filename for Content-Disposition
        $safeName = preg_replace('/[^A-Za-z0-9\.\-\_\(\) ]/', '_', $safeName);

        $disposition = $inline ? 'inline' : 'attachment';

        // Clear output buffers to prevent corrupting binary files
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($realTarget));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-transform, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($realTarget);
        exit();
    }

    /**
     * Sanitizes subdirectory path, preventing path traversal attacks.
     */
    private static function sanitizeSubDir($subDir) {
        $subDir = str_replace(['../', '..\\', './', '.\\'], '', $subDir);
        $parts = explode('/', str_replace('\\', '/', $subDir));
        $cleanParts = [];
        foreach ($parts as $p) {
            $clean = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', trim($p));
            if (!empty($clean)) {
                $cleanParts[] = $clean;
            }
        }
        return implode('/', $cleanParts);
    }

    /**
     * Formats PHP file upload errors into human-readable strings.
     */
    private static function getErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds upload_max_filesize in server configuration.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE limit in the form.';
            case UPLOAD_ERR_PARTIAL:
                return 'The file was only partially uploaded. Please try again.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was selected for upload.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Server configuration error: missing temporary folder.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk. Check storage permissions.';
            case UPLOAD_ERR_EXTENSION:
                return 'A server extension stopped the file upload.';
            default:
                return 'Unknown file upload error (Code: ' . $errorCode . ').';
        }
    }
}
