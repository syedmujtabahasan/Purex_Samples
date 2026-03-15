<?php
// ==================== FILE UPLOAD HANDLER (HARDENED) ====================
// Defense-in-depth: 7 layers of validation before a file is saved.
//
// Layer 1: Auth check (only logged-in users)
// Layer 2: Upload type whitelist
// Layer 3: PHP upload error check
// Layer 4: File size limit (PHP-enforced)
// Layer 5: Magic bytes verification via finfo (real MIME, not $_FILES['type'])
// Layer 6: Image integrity check via GD (proves it's a real image, strips payloads)
// Layer 7: Re-encode the image through GD to destroy any embedded PHP/JS payloads

require_once __DIR__ . '/../config/constants.php';

// ---- Layer 1: Auth ----
if ($method !== 'POST') error_response('POST only', 405);
$user = require_auth();

// ---- Layer 2: Upload type whitelist ----
$type = $id ?? '';
if (!in_array($type, ['product-image', 'invoice-photo'], true)) {
    error_response('Invalid upload type. Use: product-image or invoice-photo');
}

// ---- Layer 3: Upload error check ----
if (!isset($_FILES['file'])) {
    error_response('No file uploaded');
}

$file = $_FILES['file'];

switch ($file['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        error_response('File exceeds maximum size limit.', 413);
    case UPLOAD_ERR_PARTIAL:
        error_response('File upload was incomplete. Please try again.');
    case UPLOAD_ERR_NO_FILE:
        error_response('No file was selected.');
    case UPLOAD_ERR_NO_TMP_DIR:
    case UPLOAD_ERR_CANT_WRITE:
    case UPLOAD_ERR_EXTENSION:
        error_log('Purex upload server error: code ' . $file['error']);
        error_response('Server upload error. Please try again later.', 500);
    default:
        error_response('Unknown upload error.', 500);
}

// ---- Layer 4: File size limit ----
if ($file['size'] <= 0) {
    error_response('Empty file.');
}
if ($file['size'] > MAX_UPLOAD_SIZE) {
    $maxMB = round(MAX_UPLOAD_SIZE / 1024 / 1024, 1);
    error_response("File too large. Maximum {$maxMB}MB.");
}

// Verify this is actually an uploaded file (defense against path injection)
if (!is_uploaded_file($file['tmp_name'])) {
    error_response('Invalid upload.', 400);
}

// ---- Layer 5: Magic bytes verification ----
// finfo reads actual file content (magic bytes), NOT the user-provided MIME type.
// $_FILES['type'] is NEVER trusted — it's set by the client and trivially forged.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// Strict whitelist — only real image MIME types
$MIME_WHITELIST = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

if (!isset($MIME_WHITELIST[$detectedMime])) {
    error_response('Invalid file type. Allowed: JPG, PNG, WebP');
}

// ---- Layer 6: Image integrity — prove it's a real image ----
// getimagesize() parses the image headers. A PHP shell renamed to .jpg will fail here.
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    error_response('File is not a valid image.');
}

// Cross-check: the image type constant must match the MIME we detected
$GD_TYPE_MAP = [
    IMAGETYPE_JPEG => 'image/jpeg',
    IMAGETYPE_PNG  => 'image/png',
    IMAGETYPE_WEBP => 'image/webp',
];

$gdMime = $GD_TYPE_MAP[$imageInfo[2]] ?? null;
if ($gdMime !== $detectedMime) {
    error_response('File type mismatch. The file content does not match its declared format.');
}

// Sanity check dimensions (prevent decompression bombs)
$maxDimension = 8000; // 8000x8000 max
if ($imageInfo[0] > $maxDimension || $imageInfo[1] > $maxDimension || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
    error_response("Image dimensions too large. Maximum {$maxDimension}x{$maxDimension} pixels.");
}

// ---- Layer 7: Re-encode through GD to strip embedded payloads ----
// Even if a file passes all checks above, it could be a polyglot (valid JPEG with PHP
// code hidden in EXIF/comments). Re-encoding creates a clean image from pixel data only.
$srcImage = null;
switch ($detectedMime) {
    case 'image/jpeg':
        $srcImage = @imagecreatefromjpeg($file['tmp_name']);
        break;
    case 'image/png':
        $srcImage = @imagecreatefrompng($file['tmp_name']);
        break;
    case 'image/webp':
        $srcImage = @imagecreatefromwebp($file['tmp_name']);
        break;
}

if (!$srcImage) {
    error_response('Image could not be processed. It may be corrupt.');
}

// Determine extension from verified MIME (user-provided filename is NEVER used)
$ext = $MIME_WHITELIST[$detectedMime];

// Generate collision-resistant filename: 16 random bytes = 32 hex chars
$random = bin2hex(random_bytes(16));
$timestamp = time();

if ($type === 'product-image') {
    $dir = UPLOAD_DIR . 'products/';
    $filename = "prod_{$timestamp}_{$random}.{$ext}";
} else {
    $dir = UPLOAD_DIR . 'invoices/';
    $filename = "inv_{$timestamp}_{$random}.{$ext}";
}

// Create directory with restrictive permissions (owner read/write/execute only)
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

$destPath = $dir . $filename;

// Write the re-encoded (clean) image — NOT the original uploaded file
$writeSuccess = false;
switch ($detectedMime) {
    case 'image/jpeg':
        // Quality 90 — good balance of quality vs size
        $writeSuccess = imagejpeg($srcImage, $destPath, 90);
        break;
    case 'image/png':
        // Compression level 6 (0=none, 9=max)
        // Preserve alpha channel
        imagesavealpha($srcImage, true);
        $writeSuccess = imagepng($srcImage, $destPath, 6);
        break;
    case 'image/webp':
        $writeSuccess = imagewebp($srcImage, $destPath, 85);
        break;
}

// Free GD memory
imagedestroy($srcImage);

if (!$writeSuccess) {
    error_log('Purex upload: GD write failed for ' . $filename);
    error_response('Failed to save image.', 500);
}

// Set restrictive file permissions (owner read/write, group read, no world)
chmod($destPath, 0640);

$relativePath = ($type === 'product-image' ? 'uploads/products/' : 'uploads/invoices/') . $filename;
json_response([
    'path' => $relativePath,
    'url' => '/' . $relativePath,
    'filename' => $filename,
]);
