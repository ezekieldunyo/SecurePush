<?php
/**
 * Cleanup Handler
 * 
 * Handles cleanup of temporary files when user starts a new scan.
 * This ensures old files don't accumulate in the uploads directory.
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

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action parameter']);
    exit;
}

$action = $input['action'];
$uploadsDir = __DIR__ . '/../uploads/';

try {
    if ($action === 'cleanup_all') {
        // Clean up all files in uploads directory older than 1 hour
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        $oneHourAgo = time() - 3600;
        $cleanedCount = 0;
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $modTime = $file->getMTime();
                if ($modTime < $oneHourAgo) {
                    unlink($file->getPathname());
                    $cleanedCount++;
                }
            } elseif ($file->isDir()) {
                // Try to remove empty directories
                @rmdir($file->getPathname());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Cleaned up {$cleanedCount} old files"
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Cleanup failed: ' . $e->getMessage()]);
}
