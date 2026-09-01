<?php
/**
 * File Upload Handler
 * 
 * Receives the uploaded project file(s), validates them, and stores them temporarily in uploads/.
 * Security: Uploaded files are treated as untrusted and stored outside web-executable paths.
 */

// Ensure this file can only be accessed via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Set JSON response header
header('Content-Type: application/json');

// Enable output buffering to catch any unexpected output
ob_start();

// Disable error reporting to output (warnings will go to error log instead)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Configuration
$uploadDir = __DIR__ . '/../uploads/';
$maxFileSize = 50 * 1024 * 1024; // 50MB max file size
$allowedMimeTypes = [
    'application/zip',
    'application/x-zip-compressed',
    'multipart/x-zip'
];

// Ensure uploads directory exists and is not web-accessible
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Protect uploads directory with .htaccess (if Apache)
$htaccessContent = "Order Deny,Allow\nDeny from all";
file_put_contents($uploadDir . '.htaccess', $htaccessContent);

// Check if file was uploaded
if (!isset($_FILES['project']) || $_FILES['project']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error occurred']);
    exit;
}

$file = $_FILES['project'];

// Validate file size
if ($file['size'] > $maxFileSize) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds maximum limit of 50MB']);
    exit;
}

// Validate file type (MIME type check)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only ZIP files are allowed']);
    exit;
}

// Validate file extension
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($extension !== 'zip') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file extension. Only .zip files are allowed']);
    exit;
}

// Generate a safe, unique filename
$safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$uniqueFilename = $safeFilename . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.zip';
$uploadPath = $uploadDir . $uniqueFilename;

// Move uploaded file to secure location
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file']);
    exit;
}

// Extract the ZIP file to a temporary directory
$extractDir = $uploadDir . $safeFilename . '_' . time() . '_' . bin2hex(random_bytes(8));
mkdir($extractDir, 0755, true);

$zip = new ZipArchive;
if ($zip->open($uploadPath) === TRUE) {
    // Security: Check for zip slip vulnerability (path traversal)
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (strpos($filename, '..') !== false || strpos($filename, '/') === 0) {
            $zip->close();
            // Clean up
            unlink($uploadPath);
            array_map('unlink', glob($extractDir . '/*.*'));
            rmdir($extractDir);
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ZIP file: contains path traversal']);
            exit;
        }
    }
    
    $zip->extractTo($extractDir);
    $zip->close();
} else {
    // Clean up on failure
    unlink($uploadPath);
    rmdir($extractDir);
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Failed to extract ZIP file']);
    exit;
}

// Return success response with file information
ob_end_clean();
echo json_encode([
    'success' => true,
    'filename' => $uniqueFilename,
    'originalName' => $file['name'],
    'extractPath' => $extractDir,
    'zipPath' => $uploadPath,
    'message' => 'File uploaded and extracted successfully'
]);
