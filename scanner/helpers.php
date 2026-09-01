<?php
/**
 * Scanner Helper Functions
 * 
 * Shared utility functions used across all scanner modules.
 * This file prevents function redeclaration errors and provides common functionality.
 */

/**
 * Get all files in a directory recursively
 * 
 * @param string $directory Path to the directory
 * @return array Array of file paths
 */
function getAllFiles($directory) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

/**
 * Check if a file is binary
 * 
 * @param string $file Path to the file
 * @return bool True if binary, false if text
 */
function isBinaryFile($file) {
    $handle = fopen($file, 'rb');
    $chunk = fread($handle, 512);
    fclose($handle);
    
    // Check for null bytes (common in binary files)
    if (strpos($chunk, "\0") !== false) {
        return true;
    }
    
    // Check file extension
    $binaryExtensions = ['exe', 'dll', 'so', 'dylib', 'bin', 'png', 'jpg', 'jpeg', 'gif', 'pdf', 'zip', 'tar', 'gz'];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    return in_array($extension, $binaryExtensions);
}

/**
 * Check if a file is a code file
 * 
 * @param string $file Path to the file
 * @return bool True if code file, false otherwise
 */
function isCodeFile($file) {
    $codeExtensions = [
        'php', 'js', 'ts', 'py', 'rb', 'go', 'java', 'c', 'cpp', 'h', 'cs',
        'swift', 'kt', 'rs', 'scala', 'pl', 'pm', 'sh', 'bash', 'zsh',
        'html', 'htm', 'xml', 'json', 'yml', 'yaml', 'ini', 'conf', 'cfg'
    ];
    
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($extension, $codeExtensions);
}

/**
 * Format file size for human readability
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get all PHP files in a directory recursively
 * 
 * @param string $directory Path to the directory
 * @return array Array of PHP file paths
 */
function getPHPFiles($directory) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}
