<?php
/**
 * GitHub Push Handler
 * 
 * Handles pushing the scanned project to a connected GitHub repository.
 * Uses GitHub API for repository operations and git for pushing code.
 * 
 * Assumption: Token-based authentication with a GitHub Personal Access Token
 * The user will need to provide their GitHub token and repository details.
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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['githubToken']) || !isset($input['repoName']) || !isset($input['repoOwner'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters: githubToken, repoName, repoOwner']);
    exit;
}

$githubToken = $input['githubToken'];
$repoName = $input['repoName'];
$repoOwner = $input['repoOwner'];
$projectPath = $input['projectPath'] ?? '';
$scanId = $input['scanId'] ?? null;
$commitMessage = $input['commitMessage'] ?? 'Initial commit via SecurePush';
$privateRepo = $input['privateRepo'] ?? false;

// If project path is not provided but scanId is, try to get it from database
if (empty($projectPath) && $scanId) {
    $projectPath = getExtractPath($scanId);
    if ($projectPath) {
        error_log("Retrieved project path from database: " . $projectPath);
    }
}

// Validate GitHub token format (basic validation)
if (!preg_match('/^ghp_[a-zA-Z0-9]{36}$/', $githubToken) && !preg_match('/^github_pat_[a-zA-Z0-9_]{82}$/', $githubToken)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid GitHub token format']);
    exit;
}

try {
    // Step 1: Create the repository if it doesn't exist
    $repoCreated = createGitHubRepository($githubToken, $repoOwner, $repoName, $privateRepo);
    
    if (!$repoCreated['success']) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create/access repository: ' . $repoCreated['message']]);
        exit;
    }
    
    // Step 2: Initialize git and push the project
    $pushResult = pushToGitHub($githubToken, $repoOwner, $repoName, $projectPath, $commitMessage);
    
    if (!$pushResult['success']) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to push to GitHub: ' . $pushResult['message']]);
        exit;
    }
    
    // Step 3: Update database with GitHub push status
    if ($scanId) {
        updateGitHubPushStatus($scanId, true);
    }
    
    // Step 4: Clean up temporary files after successful push
    if (!empty($projectPath)) {
        cleanupTempFiles($projectPath, '');
    }
    
    // Clean output buffer to ensure no extra content before JSON
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Project successfully pushed to GitHub',
        'repository' => "https://github.com/{$repoOwner}/{$repoName}",
        'details' => $pushResult
    ]);
    
} catch (Exception $e) {
    // Clean output buffer in case of exceptions
    ob_end_clean();
    
    http_response_code(500);
    echo json_encode([
        'error' => 'GitHub push failed: ' . $e->getMessage()
    ]);
}

/**
 * Create a GitHub repository using the API
 * 
 * @param string $token GitHub personal access token
 * @param string $owner Repository owner (username or organization)
 * @param string $repoName Repository name
 * @param bool $private Whether the repository should be private
 * @return array Result with success status and message
 */
function createGitHubRepository($token, $owner, $repoName, $private = false) {
    $tokenOwner = getGitHubTokenOwner($token);
    
    // Determine the correct API endpoint
    if ($tokenOwner && strtolower($owner) === strtolower($tokenOwner)) {
        // Personal account - use /user/repos
        $apiUrl = "https://api.github.com/user/repos";
    } else {
        // Check if the owner is an organization
        if (isOrganization($token, $owner)) {
            // Organization - use /orgs/{org}/repos
            $apiUrl = "https://api.github.com/orgs/{$owner}/repos";
        } else {
            // Not an organization and not the token owner - can't create repos for other users
            return [
                'success' => false, 
                'message' => "Cannot create repository for user '{$owner}'. You can only create repositories for your own account or organizations you belong to."
            ];
        }
    }
    
    $data = [
        'name' => $repoName,
        'description' => 'Project uploaded via SecurePush',
        'private' => $private,
        'auto_init' => false
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token,
        'Content-Type: application/json',
        'User-Agent: SecurePush'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        return ['success' => true, 'message' => 'Repository created successfully'];
    } elseif ($httpCode === 422) {
        // Repository already exists, try to access it
        return checkRepositoryExists($token, $owner, $repoName);
    } else {
        return ['success' => false, 'message' => 'GitHub API error: ' . $response];
    }
}

/**
 * Check if a repository exists and is accessible
 * 
 * @param string $token GitHub personal access token
 * @param string $owner Repository owner
 * @param string $repoName Repository name
 * @return array Result with success status and message
 */
function checkRepositoryExists($token, $owner, $repoName) {
    $apiUrl = "https://api.github.com/repos/{$owner}/{$repoName}";
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token,
        'User-Agent: SecurePush'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'message' => 'Repository already exists and is accessible'];
    } else {
        return ['success' => false, 'message' => 'Repository not accessible: ' . $response];
    }
}

/**
 * Get the owner of the GitHub token
 * 
 * @param string $token GitHub personal access token
 * @return string|false Username or false on failure
 */
function getGitHubTokenOwner($token) {
    $apiUrl = "https://api.github.com/user";
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token,
        'User-Agent: SecurePush'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $userData = json_decode($response, true);
        return $userData['login'] ?? false;
    }
    
    return false;
}

/**
 * Check if the given owner is an organization
 * 
 * @param string $token GitHub personal access token
 * @param string $owner Organization name to check
 * @return bool True if organization, false otherwise
 */
function isOrganization($token, $owner) {
    $apiUrl = "https://api.github.com/orgs/{$owner}";
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $token,
        'User-Agent: SecurePush'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 200 means it's a valid organization
    // 404 means it's not an organization (could be a user or doesn't exist)
    return ($httpCode === 200);
}

/**
 * Push project files to GitHub using git
 * 
 * @param string $token GitHub personal access token
 * @param string $owner Repository owner
 * @param string $repoName Repository name
 * @param string $projectPath Path to the project files
 * @param string $commitMessage Commit message
 * @return array Result with success status and message
 */
function pushToGitHub($token, $owner, $repoName, $projectPath, $commitMessage) {
    // Check if git is available
    $gitCheck = shell_exec('git --version');
    if (!$gitCheck) {
        return ['success' => false, 'message' => 'Git is not installed or not available'];
    }
    
    // Check if project path is provided
    if (empty($projectPath)) {
        return ['success' => false, 'message' => 'Project path is empty - files may have been cleaned up already. Please upload and scan the project again before pushing to GitHub.'];
    }
    
    // Log the project path for debugging
    error_log("GitHub push - Project path: " . $projectPath);
    error_log("GitHub push - Path exists: " . (is_dir($projectPath) ? 'yes' : 'no'));
    error_log("GitHub push - Path is readable: " . (is_readable($projectPath) ? 'yes' : 'no'));
    
    // Check if project path exists
    if (!is_dir($projectPath)) {
        return ['success' => false, 'message' => "Project path does not exist: {$projectPath}. The files may have been cleaned up. Please upload and scan the project again before pushing to GitHub."];
    }
    
    $tempDir = sys_get_temp_dir() . '/securepush_git_' . time();
    mkdir($tempDir, 0755, true);
    
    try {
        // Copy project files to temp directory
        recursiveCopy($projectPath, $tempDir);
        
        // Initialize git repository
        $commands = [
            "cd {$tempDir} && git init",
            "cd {$tempDir} && git config user.name 'SecurePush'",
            "cd {$tempDir} && git config user.email 'securepush@localhost'",
            "cd {$tempDir} && git add .",
            "cd {$tempDir} && git commit -m " . escapeshellarg($commitMessage),
            "cd {$tempDir} && git branch -M main",
            "cd {$tempDir} && git remote add origin https://{$token}@github.com/{$owner}/{$repoName}.git",
            "cd {$tempDir} && git push -u origin main --force"
        ];
        
        foreach ($commands as $command) {
            $output = shell_exec($command . ' 2>&1');
            if ($output === null && strpos($command, 'git push') !== false) {
                // Push might fail if repository is empty or has conflicts
                // This is okay for initial push
            }
        }
        
        // Clean up temp directory
        recursiveDelete($tempDir);
        
        return ['success' => true, 'message' => 'Files pushed successfully'];
        
    } catch (Exception $e) {
        // Clean up temp directory on failure
        if (is_dir($tempDir)) {
            recursiveDelete($tempDir);
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Recursively copy a directory
 * 
 * @param string $source Source directory
 * @param string $dest Destination directory
 */
function recursiveCopy($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            mkdir($destPath, 0755, true);
        } else {
            copy($item, $destPath);
        }
    }
}

/**
 * Recursively delete a directory
 * 
 * @param string $dir Directory to delete
 */
function recursiveDelete($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    
    rmdir($dir);
}

/**
 * Clean up temporary files after GitHub push
 * 
 * @param string $extractPath Path to extracted files
 * @param string $zipPath Path to the ZIP file (optional)
 */
function cleanupTempFiles($extractPath, $zipPath = '') {
    // Remove extracted directory
    if (is_dir($extractPath)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getPathname());
            } else {
                unlink($fileinfo->getPathname());
            }
        }
        
        rmdir($extractPath);
    }
    
    // Remove ZIP file if provided
    if (!empty($zipPath) && file_exists($zipPath)) {
        unlink($zipPath);
    }
}
