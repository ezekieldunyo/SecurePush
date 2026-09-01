<?php
/**
 * Secrets Scanner Module
 * 
 * Scans files for exposed API keys, tokens, and other secrets.
 * Uses pattern matching to detect common secret formats.
 * 
 * What it checks for:
 * - API keys (AWS, Google, Stripe, etc.)
 * - Database connection strings
 * - JWT tokens
 * - Private keys (SSH, SSL)
 * - OAuth tokens
 * - Generic password/API key patterns
 */

// Include shared helper functions
require_once __DIR__ . '/helpers.php';

/**
 * Scan a directory for secrets
 * 
 * @param string $directory Path to the directory to scan
 * @return array Array of found secrets with file paths and line numbers
 */
function scanForSecrets($directory) {
    $secrets = [];
    $files = getAllFiles($directory);
    
    // Common secret patterns (regex)
    $patterns = [
        // AWS Access Key ID
        'aws_access_key' => '/AKIA[0-9A-Z]{16}/i',
        
        // AWS Secret Access Key (approximate pattern)
        'aws_secret_key' => '/aws[._-]?secret[._-]?key[^\w]*[A-Za-z0-9\/\+=]{40}/i',
        
        // Google API Key
        'google_api_key' => '/AIza[0-9A-Z\-_]{35}/i',
        
        // Stripe API Key
        'stripe_api_key' => '/(sk|pk)_(live|test)_[0-9a-zA-Z]{24,}/i',
        
        // GitHub Personal Access Token
        'github_token' => '/ghp_[a-zA-Z0-9]{36}/i',
        
        // Slack Token
        'slack_token' => '/xox[baprs]-[0-9]{12}-[0-9]{12}-[0-9a-zA-Z]{24}/i',
        
        // Database connection strings
        'database_url' => '/(mysql|postgresql|mongodb):\/\/[^\s"\'<>]+:[^\s"\'<>]+@[^\s"\'<>]+/i',
        
        // JWT tokens
        'jwt_token' => '/eyJ[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+/i',
        
        // SSH private key headers
        'ssh_private_key' => '/-----BEGIN[^\n]*RSA PRIVATE KEY-----/i',
        
        // SSL certificate private key
        'ssl_private_key' => '/-----BEGIN[^\n]*PRIVATE KEY-----/i',
        
        // Generic API key patterns
        'api_key_generic' => '/(api[_-]?key|apikey)[\s:=]+["\']?[a-zA-Z0-9_\-]{20,}["\']?/i',
        
        // Password assignments
        'password_assignment' => '/(password|passwd|pwd)[\s:=]+["\']?[^\s"\'<>]{4,}["\']?/i',
        
        // Token assignments
        'token_assignment' => '/(token|access[_-]?token|auth[_-]?token)[\s:=]+["\']?[a-zA-Z0-9_\-]{20,}["\']?/i',
    ];
    
    foreach ($files as $file) {
        // Skip binary files and common non-code files
        if (isBinaryFile($file)) {
            continue;
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            foreach ($patterns as $secretType => $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file);
                    
                    $secrets[] = [
                        'type' => $secretType,
                        'file' => $relativePath,
                        'line' => $lineNumber + 1,
                        'match' => substr($matches[0], 0, 50) . (strlen($matches[0]) > 50 ? '...' : ''),
                        'severity' => 'high',
                        'description' => getDescriptionForSecretType($secretType)
                    ];
                }
            }
        }
    }
    
    return $secrets;
}

/**
 * Get human-readable description for secret type
 * 
 * @param string $secretType The type of secret
 * @return string Description
 */
function getDescriptionForSecretType($secretType) {
    $descriptions = [
        'aws_access_key' => 'AWS Access Key ID detected - provides access to AWS resources',
        'aws_secret_key' => 'AWS Secret Access Key detected - provides full AWS account access',
        'google_api_key' => 'Google API Key detected - provides access to Google services',
        'stripe_api_key' => 'Stripe API Key detected - provides access to payment processing',
        'github_token' => 'GitHub Personal Access Token detected - provides GitHub API access',
        'slack_token' => 'Slack Token detected - provides access to Slack workspace',
        'database_url' => 'Database connection string detected - exposes database credentials',
        'jwt_token' => 'JWT Token detected - may provide authentication bypass',
        'ssh_private_key' => 'SSH Private Key detected - provides server access',
        'ssl_private_key' => 'SSL Private Key detected - compromises SSL/TLS certificates',
        'api_key_generic' => 'Generic API key detected - may provide service access',
        'password_assignment' => 'Password assignment detected - hardcoded credentials',
        'token_assignment' => 'Token assignment detected - hardcoded authentication token',
    ];
    
    return $descriptions[$secretType] ?? 'Potential secret detected';
}
