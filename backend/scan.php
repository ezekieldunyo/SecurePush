<?php
/**
 * Scan Orchestrator
 * 
 * Orchestrates the security scan by calling each scanner module and aggregating results.
 * This is the main scanning endpoint that coordinates all security checks.
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

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../scanner/helpers.php';
require_once __DIR__ . '/../scanner/secrets.php';
require_once __DIR__ . '/../scanner/env.php';
require_once __DIR__ . '/../scanner/files.php';
require_once __DIR__ . '/../scanner/php-security.php';

// Initialize database and create table if it doesn't exist
try {
    $databaseInitialized = initializeDatabase();
    if ($databaseInitialized !== true) {
        error_log("Database initialization failed: " . $databaseInitialized);
    }
} catch (PDOException $e) {
    $databaseInitialized = $e->getMessage();
    error_log("Database initialization exception: " . $e->getMessage());
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['extractPath']) || !isset($input['projectName'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters: extractPath and projectName']);
    exit;
}

$extractPath = $input['extractPath'];
$projectName = $input['projectName'];
$zipPath = $input['zipPath'] ?? '';

// Validate the extract path exists and is within uploads directory
$uploadsDir = __DIR__ . '/../uploads/';
$realExtractPath = realpath($extractPath);
$realUploadsDir = realpath($uploadsDir);

if ($realExtractPath === false || strpos($realExtractPath, $realUploadsDir) !== 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid extract path']);
    exit;
}

// Initialize database
initializeDatabase();

try {
    // Run all scanner modules
    $results = [
        'scan_id' => null,
        'project_name' => $projectName,
        'scan_date' => date('Y-m-d H:i:s'),
        'summary' => [
            'total_issues' => 0,
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ],
        'findings' => []
    ];
    
    // 1. Scan for secrets
    $secrets = scanForSecrets($extractPath);
    if (!empty($secrets)) {
        $results['findings']['secrets'] = $secrets;
        foreach ($secrets as $secret) {
            $results['summary']['total_issues']++;
            $results['summary'][$secret['severity']]++;
        }
    }
    
    // 2. Scan for env file issues
    $envIssues = scanForEnvFiles($extractPath);
    if (!empty($envIssues)) {
        $results['findings']['env'] = $envIssues;
        foreach ($envIssues as $issue) {
            $results['summary']['total_issues']++;
            $results['summary'][$issue['severity']]++;
        }
    }
    
    // 3. Scan for dangerous files
    $dangerousFiles = scanForDangerousFiles($extractPath);
    if (!empty($dangerousFiles)) {
        $results['findings']['files'] = $dangerousFiles;
        foreach ($dangerousFiles as $file) {
            $results['summary']['total_issues']++;
            $results['summary'][$file['severity']]++;
        }
    }
    
    // 4. Scan for PHP security issues
    $phpIssues = scanForPHPSecurityIssues($extractPath);
    if (!empty($phpIssues)) {
        $results['findings']['php_security'] = $phpIssues;
        foreach ($phpIssues as $issue) {
            $results['summary']['total_issues']++;
            $results['summary'][$issue['severity']]++;
        }
    }
    
    // Determine pass/fail status
    $passFail = 'pass';
    if ($results['summary']['critical'] > 0 || $results['summary']['high'] > 0) {
        $passFail = 'fail';
    }
    
    $results['summary']['pass_fail'] = $passFail;
    
    // Save scan result to database with extract path
    $dbError = null;
    try {
        $saved = saveScanResult($projectName, $passFail, $results['summary']['total_issues'], $extractPath);
        
        // Check if saveScanResult returned an error message instead of true
        if ($saved !== true) {
            $dbError = $saved;
            $saved = false;
        }
    } catch (PDOException $e) {
        $saved = false;
        $dbError = $e->getMessage();
        error_log("Save scan result error: " . $e->getMessage());
    }
    
    // Debug logging for database operations
    $debugInfo = [
        'database_initialized' => $databaseInitialized,
        'save_scan_result' => $saved,
        'project_name' => $projectName,
        'pass_fail' => $passFail,
        'issues_count' => $results['summary']['total_issues'],
        'db_error' => $dbError
    ];
    
    if ($saved) {
        // Get the last insert ID for the scan
        try {
            $pdo = getDbConnection();
            $results['scan_id'] = $pdo->lastInsertId();
            $debugInfo['scan_id'] = $results['scan_id'];
        } catch (PDOException $e) {
            $debugInfo['scan_id_error'] = $e->getMessage();
            error_log("Get scan ID error: " . $e->getMessage());
        }
    } else {
        $debugInfo['error'] = 'Failed to save scan result to database';
        if ($dbError) {
            $debugInfo['error'] = $dbError;
        }
    }
    
    // Add debug info to results (temporary for testing)
    $results['debug'] = $debugInfo;
    
    // Sanitize output to prevent XSS from scanned content
    $results = sanitizeScanResults($results);
    
    // Return the paths to the frontend so GitHub push can access the files
    $results['extractPath'] = $extractPath;
    $results['zipPath'] = $zipPath;
    
    // Clean output buffer to ensure no extra content before JSON
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    // Clean output buffer in case of exceptions
    ob_end_clean();
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Scan failed: ' . $e->getMessage()
    ]);
}

/**
 * Sanitize scan results to prevent XSS
 * 
 * @param array $results Raw scan results
 * @return array Sanitized results
 */
function sanitizeScanResults($results) {
    $sanitized = $results;
    
    if (isset($sanitized['findings'])) {
        foreach ($sanitized['findings'] as $category => $findings) {
            foreach ($findings as $key => $finding) {
                if (isset($finding['file'])) {
                    $sanitized['findings'][$category][$key]['file'] = htmlspecialchars($finding['file'], ENT_QUOTES, 'UTF-8');
                }
                if (isset($finding['description'])) {
                    $sanitized['findings'][$category][$key]['description'] = htmlspecialchars($finding['description'], ENT_QUOTES, 'UTF-8');
                }
                if (isset($finding['match'])) {
                    $sanitized['findings'][$category][$key]['match'] = htmlspecialchars($finding['match'], ENT_QUOTES, 'UTF-8');
                }
            }
        }
    }
    
    return $sanitized;
}
