<?php
/**
 * PHP Security Scanner Module
 * 
 * Performs basic PHP security checks by detecting obviously unsafe patterns.
 * This is a basic scanner and should not be considered a comprehensive security audit.
 * 
 * What it checks for:
 * - Dangerous function usage (eval, exec, system, etc.)
 * - SQL injection vulnerabilities
 * - XSS vulnerabilities
 * - File inclusion vulnerabilities
 * - Hardcoded credentials
 * - Insecure configuration
 * - Deprecated/unsafe PHP features
 */

// Include shared helper functions
require_once __DIR__ . '/helpers.php';

/**
 * Scan a directory for PHP security issues
 * 
 * @param string $directory Path to the directory to scan
 * @return array Array of found security issues with file paths and line numbers
 */
function scanForPHPSecurityIssues($directory) {
    $issues = [];
    $phpFiles = getPHPFiles($directory);
    
    // Dangerous PHP functions/patterns
    $dangerousPatterns = [
        // Code execution
        'eval' => [
            'pattern' => '/\beval\s*\(/i',
            'severity' => 'critical',
            'description' => 'eval() function detected - allows arbitrary code execution'
        ],
        'assert' => [
            'pattern' => '/\bassert\s*\(/i',
            'severity' => 'critical',
            'description' => 'assert() function detected - can be used for code execution'
        ],
        'create_function' => [
            'pattern' => '/\bcreate_function\s*\(/i',
            'severity' => 'critical',
            'description' => 'create_function() detected - deprecated and dangerous'
        ],
        
        // Command execution
        'exec' => [
            'pattern' => '/\bexec\s*\(/i',
            'severity' => 'high',
            'description' => 'exec() function detected - allows command execution'
        ],
        'system' => [
            'pattern' => '/\bsystem\s*\(/i',
            'severity' => 'high',
            'description' => 'system() function detected - allows command execution'
        ],
        'passthru' => [
            'pattern' => '/\bpassthru\s*\(/i',
            'severity' => 'high',
            'description' => 'passthru() function detected - allows command execution'
        ],
        'shell_exec' => [
            'pattern' => '/\bshell_exec\s*\(/i',
            'severity' => 'high',
            'description' => 'shell_exec() function detected - allows command execution'
        ],
        'backtick_operator' => [
            'pattern' => '/`[^`]+`/',
            'severity' => 'high',
            'description' => 'Backtick operator detected - equivalent to shell_exec()'
        ],
        'popen' => [
            'pattern' => '/\bpopen\s*\(/i',
            'severity' => 'high',
            'description' => 'popen() function detected - allows command execution'
        ],
        'proc_open' => [
            'pattern' => '/\bproc_open\s*\(/i',
            'severity' => 'high',
            'description' => 'proc_open() function detected - allows command execution'
        ],
        
        // File inclusion
        'include' => [
            'pattern' => '/\binclude\s*\(/i',
            'severity' => 'medium',
            'description' => 'include() detected with variable - potential file inclusion vulnerability'
        ],
        'require' => [
            'pattern' => '/\brequire\s*\(/i',
            'severity' => 'medium',
            'description' => 'require() detected with variable - potential file inclusion vulnerability'
        ],
        'include_once' => [
            'pattern' => '/\binclude_once\s*\(/i',
            'severity' => 'medium',
            'description' => 'include_once() detected with variable - potential file inclusion vulnerability'
        ],
        'require_once' => [
            'pattern' => '/\brequire_once\s*\(/i',
            'severity' => 'medium',
            'description' => 'require_once() detected with variable - potential file inclusion vulnerability'
        ],
        
        // SQL injection
        'mysql_query' => [
            'pattern' => '/\bmysql_query\s*\(/i',
            'severity' => 'critical',
            'description' => 'mysql_query() detected - deprecated mysql extension, vulnerable to SQL injection'
        ],
        'mysql_connect' => [
            'pattern' => '/\bmysql_connect\s*\(/i',
            'severity' => 'critical',
            'description' => 'mysql_connect() detected - deprecated mysql extension'
        ],
        'mysqli_query_concat' => [
            'pattern' => '/\bmysqli_query\s*\([^)]*\$_(GET|POST|REQUEST)[^)]*\)/i',
            'severity' => 'critical',
            'description' => 'mysqli_query() with direct $_GET/$_POST/$_REQUEST concatenation - SQL injection vulnerability'
        ],
        'sql_concat_user_input' => [
            'pattern' => '/(mysql_query|mysqli_query|pg_query|db_query)\s*\([^)]*\.\s*\$_(GET|POST|REQUEST)/i',
            'severity' => 'critical',
            'description' => 'SQL query function with direct user input concatenation - SQL injection vulnerability'
        ],
        'unprepared_query' => [
            'pattern' => '/->query\s*\([^)]*\$[^)]*\)/i',
            'severity' => 'high',
            'description' => 'Potential unprepared SQL query - vulnerable to SQL injection'
        ],
        
        // XSS
        'echo_unsanitized' => [
            'pattern' => '/echo\s+\$[^;]+;/i',
            'severity' => 'medium',
            'description' => 'echo without output escaping - potential XSS vulnerability'
        ],
        'print_unsanitized' => [
            'pattern' => '/print\s+\$[^;]+;/i',
            'severity' => 'medium',
            'description' => 'print without output escaping - potential XSS vulnerability'
        ],
        
        // Hardcoded credentials
        'hardcoded_password' => [
            'pattern' => '/(password|passwd|pwd)\s*=\s*["\'][^"\']{4,}["\']/i',
            'severity' => 'high',
            'description' => 'Hardcoded password detected - credentials should be in environment variables'
        ],
        'hardcoded_api_key' => [
            'pattern' => '/(api[_-]?key|apikey)\s*=\s*["\'][^"\']{10,}["\']/i',
            'severity' => 'high',
            'description' => 'Hardcoded API key detected - credentials should be in environment variables'
        ],
        
        // Insecure configuration
        'error_reporting_off' => [
            'pattern' => '/error_reporting\s*\(\s*0\s*\)/i',
            'severity' => 'low',
            'description' => 'error_reporting(0) detected - hides errors and makes debugging difficult'
        ],
        'display_errors_on' => [
            'pattern' => '/ini_set\s*\(\s*["\']display_errors["\']\s*,\s*1\s*\)/i',
            'severity' => 'medium',
            'description' => 'display_errors enabled in production - may expose sensitive information'
        ],
        
        // Deprecated features
        'ereg' => [
            'pattern' => '/\bereg\s*\(/i',
            'severity' => 'medium',
            'description' => 'ereg() function detected - deprecated and removed in PHP 7'
        ],
        'eregi' => [
            'pattern' => '/\beregi\s*\(/i',
            'severity' => 'medium',
            'description' => 'eregi() function detected - deprecated and removed in PHP 7'
        ],
        'split' => [
            'pattern' => '/\bsplit\s*\(/i',
            'severity' => 'medium',
            'description' => 'split() function detected - deprecated and removed in PHP 7'
        ],
        
        // Serialization issues
        'unserialize_user_input' => [
            'pattern' => '/\bunserialize\s*\(\s*\$/i',
            'severity' => 'high',
            'description' => 'unserialize() with user input - potential object injection vulnerability'
        ],
        
        // Variable variables
        'variable_variable' => [
            'pattern' => '/\$\$/',
            'severity' => 'medium',
            'description' => 'Variable variable detected - can lead to security issues'
        ],
        
        // Extract on user input
        'extract_user_input' => [
            'pattern' => '/\bextract\s*\(\s*\$/i',
            'severity' => 'high',
            'description' => 'extract() on user input - can overwrite variables and lead to security issues'
        ],
    ];
    
    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        
        $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file);
        $lines = explode("\n", $content);
        
        // Track variables assigned from user input for SQL injection detection
        $userInputVariables = trackUserInputVariables($lines);
        
        foreach ($lines as $lineNumber => $line) {
            foreach ($dangerousPatterns as $issueType => $config) {
                if (preg_match($config['pattern'], $line)) {
                    // Skip commented lines
                    if (preg_match('/^\s*\/\//', $line) || preg_match('/^\s*#/', $line)) {
                        continue;
                    }
                    
                    $issues[] = [
                        'type' => $issueType,
                        'file' => $relativePath,
                        'line' => $lineNumber + 1,
                        'severity' => $config['severity'],
                        'description' => $config['description'],
                        'match' => trim(substr($line, 0, 80)) . (strlen($line) > 80 ? '...' : '')
                    ];
                }
            }
            
            // Check for SQL injection through variable assignment
            $sqlInjectionIssue = checkSQLInjectionThroughVariables($line, $lineNumber + 1, $userInputVariables, $relativePath);
            if ($sqlInjectionIssue) {
                $issues[] = $sqlInjectionIssue;
            }
        }
    }
    
    return $issues;
}

/**
 * Track variables that are assigned directly from user input ($_GET, $_POST, $_REQUEST)
 * 
 * @param array $lines Array of file lines
 * @return array Array of variable names that contain user input, with their line numbers
 */
function trackUserInputVariables($lines) {
    $userInputVariables = [];
    
    foreach ($lines as $lineNumber => $line) {
        // Skip commented lines
        if (preg_match('/^\s*\/\//', $line) || preg_match('/^\s*#/', $line)) {
            continue;
        }
        
        // Pattern: $variable = $_GET['key'] or $variable = $_POST['key'] or $variable = $_REQUEST['key']
        if (preg_match('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*\$_(GET|POST|REQUEST)\[/', $line, $matches)) {
            $variableName = $matches[1];
            $userInputVariables[$variableName] = $lineNumber + 1;
        }
        
        // Pattern: $variable = $_GET->key or $variable = $_POST->key (object syntax)
        if (preg_match('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*\$_(GET|POST|REQUEST)->/', $line, $matches)) {
            $variableName = $matches[1];
            $userInputVariables[$variableName] = $lineNumber + 1;
        }
    }
    
    return $userInputVariables;
}

/**
 * Check if a line contains SQL injection through variables that contain user input
 * 
 * @param string $line The line to check
 * @param int $lineNumber The current line number
 * @param array $userInputVariables Array of variables containing user input
 * @param string $relativePath Relative path of the file
 * @return array|null Issue array if found, null otherwise
 */
function checkSQLInjectionThroughVariables($line, $lineNumber, $userInputVariables, $relativePath) {
    // Skip commented lines
    if (preg_match('/^\s*\/\//', $line) || preg_match('/^\s*#/', $line)) {
        return null;
    }
    
    // SQL query functions to check
    $sqlFunctions = ['mysql_query', 'mysqli_query', 'pg_query', 'db_query', 'query'];
    
    foreach ($sqlFunctions as $function) {
        // Check if the line contains a SQL query function
        if (stripos($line, $function) !== false) {
            // Check if any user input variable is used in this line
            foreach ($userInputVariables as $varName => $assignmentLine) {
                // Pattern: $variable (the user input variable) appears in the line
                if (preg_match('/\$' . preg_quote($varName, '/') . '\b/', $line)) {
                    return [
                        'type' => 'sql_injection_variable',
                        'file' => $relativePath,
                        'line' => $lineNumber,
                        'severity' => 'critical',
                        'description' => "Variable \${$varName} (assigned from user input on line {$assignmentLine}) used in SQL query - SQL injection vulnerability",
                        'match' => trim(substr($line, 0, 80)) . (strlen($line) > 80 ? '...' : '')
                    ];
                }
            }
        }
    }
    
    // Also check for string concatenation patterns that might indicate SQL building
    // Pattern: $query = "..." . $variable where $variable contains user input
    if (preg_match('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*["\'].*["\']\s*\.\s*\$([a-zA-Z_][a-zA-Z0-9_]*)/', $line, $matches)) {
        $queryVar = $matches[1];
        $concatenatedVar = $matches[2];
        
        // Check if the concatenated variable contains user input
        if (isset($userInputVariables[$concatenatedVar])) {
            $assignmentLine = $userInputVariables[$concatenatedVar];
            return [
                'type' => 'sql_injection_concatenation',
                'file' => $relativePath,
                'line' => $lineNumber,
                'severity' => 'critical',
                'description' => "Query string built by concatenating user input variable \${$concatenatedVar} (assigned on line {$assignmentLine}) - SQL injection vulnerability",
                'match' => trim(substr($line, 0, 80)) . (strlen($line) > 80 ? '...' : '')
            ];
        }
    }
    
    return null;
}
