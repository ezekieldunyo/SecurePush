<?php
/**
 * Environment File Scanner Module
 * 
 * Checks whether .env files are present and improperly exposed/committed.
 * .env files typically contain sensitive configuration and should not be in version control.
 * 
 * What it checks for:
 * - Presence of .env files
 * - .env files with sensitive content
 * - .env files in suspicious locations
 * - .env.example files that might contain real values
 * - Environment variable assignments in code
 */

// Include shared helper functions
require_once __DIR__ . '/helpers.php';

/**
 * Scan a directory for .env file issues
 * 
 * @param string $directory Path to the directory to scan
 * @return array Array of found .env issues with file paths and descriptions
 */
function scanForEnvFiles($directory) {
    $issues = [];
    $files = getAllFiles($directory);
    
    // Patterns for environment variable assignments in code
    $envPatterns = [
        'getenv_call' => '/getenv\s*\(\s*["\']([A-Z_][A-Z0-9_]*)["\']\s*\)/i',
        'env_helper' => '/env\s*\(\s*["\']([A-Z_][A-Z0-9_]*)["\']\s*\)/i',
        'superglobal' => '/\$_ENV\[["\']([A-Z_][A-Z0-9_]*)["\']\]/i',
        'server_env' => '/\$_SERVER\[["\']([A-Z_][A-Z0-9_]*)["\']\]/i',
    ];
    
    // Sensitive environment variable names
    $sensitiveEnvVars = [
        'API_KEY', 'API_SECRET', 'SECRET_KEY', 'PRIVATE_KEY', 'PASSWORD',
        'DB_PASSWORD', 'DATABASE_URL', 'MONGO_URI', 'REDIS_PASSWORD',
        'AWS_ACCESS_KEY', 'AWS_SECRET_KEY', 'STRIPE_KEY', 'SLACK_TOKEN',
        'JWT_SECRET', 'ENCRYPTION_KEY', 'OAUTH_SECRET', 'CLIENT_SECRET'
    ];
    
    foreach ($files as $file) {
        $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file);
        $fileName = basename($file);
        
        // Check for .env files
        if (preg_match('/^\.env(\..*)?$/', $fileName)) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            // Check if .env file contains sensitive data
            $hasSensitiveContent = false;
            $sensitiveLines = [];
            
            $lines = explode("\n", $content);
            foreach ($lines as $lineNumber => $line) {
                // Skip comments and empty lines
                if (preg_match('/^\s*#/', $line) || trim($line) === '') {
                    continue;
                }
                
                // Check for sensitive variable names
                foreach ($sensitiveEnvVars as $sensitiveVar) {
                    if (stripos($line, $sensitiveVar) !== false) {
                        $hasSensitiveContent = true;
                        $sensitiveLines[] = [
                            'line' => $lineNumber + 1,
                            'content' => substr($line, 0, 60) . (strlen($line) > 60 ? '...' : '')
                        ];
                        break;
                    }
                }
            }
            
            $severity = 'high';
            $description = '.env file detected';
            
            // Special case for .env.example files
            if ($fileName === '.env.example' || $fileName === '.env.sample') {
                $severity = 'medium';
                $description = '.env.example file detected - should contain placeholders only';
                
                // Check if it might contain real values
                if ($hasSensitiveContent) {
                    $severity = 'high';
                    $description = '.env.example file may contain real sensitive values instead of placeholders';
                }
            } else {
                $description = '.env file detected - contains sensitive configuration and should not be committed';
            }
            
            $issues[] = [
                'type' => 'env_file',
                'file' => $relativePath,
                'line' => 1,
                'severity' => $severity,
                'description' => $description,
                'details' => $sensitiveLines
            ];
        }
        
        // Check for hardcoded environment variables in code files
        if (isCodeFile($file) && !isBinaryFile($file)) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $lines = explode("\n", $content);
            
            foreach ($lines as $lineNumber => $line) {
                foreach ($envPatterns as $patternType => $pattern) {
                    if (preg_match($pattern, $line, $matches)) {
                        $envVar = $matches[1];
                        
                        // Check if it's a sensitive variable
                        foreach ($sensitiveEnvVars as $sensitiveVar) {
                            if (stripos($envVar, $sensitiveVar) !== false) {
                                $issues[] = [
                                    'type' => 'env_hardcoded',
                                    'file' => $relativePath,
                                    'line' => $lineNumber + 1,
                                    'severity' => 'medium',
                                    'description' => "Environment variable '{$envVar}' accessed in code - ensure it's properly configured",
                                    'match' => substr($line, 0, 60) . (strlen($line) > 60 ? '...' : '')
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Check for .gitignore presence to verify .env is ignored
    $gitignorePath = $directory . DIRECTORY_SEPARATOR . '.gitignore';
    if (!file_exists($gitignorePath)) {
        $issues[] = [
            'type' => 'missing_gitignore',
            'file' => '.gitignore',
            'line' => 1,
            'severity' => 'medium',
            'description' => 'No .gitignore file found - .env files should be gitignored',
            'details' => []
        ];
    } else {
        $gitignoreContent = file_get_contents($gitignorePath);
        if (strpos($gitignoreContent, '.env') === false) {
            $issues[] = [
                'type' => 'gitignore_missing_env',
                'file' => '.gitignore',
                'line' => 1,
                'severity' => 'high',
                'description' => '.gitignore does not exclude .env files - add ".env" to .gitignore',
                'details' => []
            ];
        }
    }
    
    return $issues;
}
