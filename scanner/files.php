<?php
/**
 * Dangerous Files Scanner Module
 * 
 * Flags dangerous or unwanted files that shouldn't be pushed to a repository.
 * These files may contain sensitive data, be compiled binaries, or are development artifacts.
 * 
 * What it checks for:
 * - Compiled binaries and executables
 * - OS-specific files (Thumbs.db, .DS_Store)
 * - IDE/editor configuration files with potential secrets
 * - Large files that shouldn't be in repos
 * - Backup files
 * - Temporary files
 * - Sensitive system files
 */

// Include shared helper functions
require_once __DIR__ . '/helpers.php';

/**
 * Scan a directory for dangerous/unwanted files
 * 
 * @param string $directory Path to the directory to scan
 * @return array Array of found problematic files with descriptions
 */
function scanForDangerousFiles($directory) {
    $issues = [];
    $files = getAllFiles($directory);
    
    // File patterns that shouldn't be in repositories
    $dangerousPatterns = [
        // Compiled binaries
        'compiled_binary' => [
            'extensions' => ['exe', 'dll', 'so', 'dylib', 'bin', 'o', 'a', 'lib'],
            'severity' => 'high',
            'description' => 'Compiled binary detected - binaries should not be in version control'
        ],
        
        // OS-specific files
        'os_file' => [
            'extensions' => ['ds_store', 'thumbs.db', 'desktop.ini'],
            'severity' => 'low',
            'description' => 'OS-specific file detected - should be excluded via .gitignore'
        ],
        
        // Backup files
        'backup_file' => [
            'patterns' => ['/\.bak$/i', '/\.backup$/i', '/\.old$/i', '/~$/i', '/\.swp$/i'],
            'severity' => 'medium',
            'description' => 'Backup file detected - backup files should not be committed'
        ],
        
        // Temporary files
        'temp_file' => [
            'patterns' => ['/\.tmp$/i', '/\.temp$/i', '/^#.*#$/', '/\.cache$/i'],
            'severity' => 'medium',
            'description' => 'Temporary file detected - temporary files should not be committed'
        ],
        
        // Log files
        'log_file' => [
            'extensions' => ['log'],
            'severity' => 'medium',
            'description' => 'Log file detected - logs may contain sensitive information and grow indefinitely'
        ],
        
        // Database files
        'database_file' => [
            'extensions' => ['sqlite', 'sqlite3', 'db', 'mdb'],
            'severity' => 'high',
            'description' => 'Database file detected - databases should not be in version control'
        ],
        
        // Certificate/key files
        'certificate_file' => [
            'extensions' => ['pem', 'crt', 'cer', 'key', 'p12', 'pfx'],
            'severity' => 'high',
            'description' => 'Certificate or key file detected - cryptographic material should not be committed'
        ],
        
        // Compressed archives (other than project distributions)
        'archive_file' => [
            'extensions' => ['zip', 'tar', 'gz', 'bz2', 'rar', '7z'],
            'severity' => 'medium',
            'description' => 'Archive file detected - consider if this should be in version control'
        ],
        
        // IDE/editor specific files
        'ide_file' => [
            'patterns' => ['/\.vscode\//i', '/\.idea\//i', '/\.sublime-\//i', '/\.vim\//i'],
            'severity' => 'low',
            'description' => 'IDE configuration file detected - should be excluded via .gitignore'
        ],
        
        // Node modules (if present)
        'node_modules' => [
            'patterns' => ['/node_modules\//i'],
            'severity' => 'medium',
            'description' => 'node_modules directory detected - dependencies should be installed via package manager'
        ],
        
        // Python cache
        'python_cache' => [
            'patterns' => ['/__pycache__\//i', '/\.pyc$/i', '/\.pyo$/i'],
            'severity' => 'low',
            'description' => 'Python cache file detected - should be excluded via .gitignore'
        ],
        
        // Java compiled classes
        'java_class' => [
            'extensions' => ['class'],
            'severity' => 'high',
            'description' => 'Java class file detected - compiled code should not be in version control'
        ],
        
        // Sensitive configuration files
        'sensitive_config' => [
            'patterns' => ['/\.ssh\//i', '/\.aws\//i', '/\.azure\//i', '/\.credentials$/i'],
            'severity' => 'high',
            'description' => 'Sensitive configuration directory/file detected - may contain credentials'
        ],
    ];
    
    foreach ($files as $file) {
        $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file);
        $fileName = basename($file);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        // Check file size (files larger than 10MB shouldn't typically be in repos)
        $fileSize = filesize($file);
        if ($fileSize > 10 * 1024 * 1024) { // 10MB
            $issues[] = [
                'type' => 'large_file',
                'file' => $relativePath,
                'line' => 1,
                'severity' => 'medium',
                'description' => 'Large file detected (' . formatFileSize($fileSize) . ') - consider using Git LFS or external storage',
                'details' => ['size' => $fileSize]
            ];
        }
        
        // Check against dangerous patterns
        foreach ($dangerousPatterns as $category => $config) {
            $isMatch = false;
            
            // Check by extension
            if (isset($config['extensions']) && in_array($extension, $config['extensions'])) {
                $isMatch = true;
            }
            
            // Check by regex patterns
            if (isset($config['patterns'])) {
                foreach ($config['patterns'] as $pattern) {
                    if (preg_match($pattern, $relativePath) || preg_match($pattern, $fileName)) {
                        $isMatch = true;
                        break;
                    }
                }
            }
            
            if ($isMatch) {
                $issues[] = [
                    'type' => $category,
                    'file' => $relativePath,
                    'line' => 1,
                    'severity' => $config['severity'],
                    'description' => $config['description'],
                    'details' => []
                ];
            }
        }
    }
    
    return $issues;
}
