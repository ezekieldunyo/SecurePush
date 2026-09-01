<?php
/**
 * MySQL Database Configuration — EXAMPLE FILE
 *
 * Copy this file to config/database.php and fill in your own
 * local database credentials before running the app.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'securepush');
define('DB_USER', 'root');
define('DB_PASS', 'your_password_here');
define('DB_CHARSET', 'utf8mb4');

function getDbConnection() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
}

function initializeDatabase() {
    try {
        $pdo = getDbConnection();
        
        $sql = "CREATE TABLE IF NOT EXISTS scan_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_name VARCHAR(255) NOT NULL,
            scan_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            pass_fail VARCHAR(10) NOT NULL,
            issues_found INT DEFAULT 0,
            github_pushed BOOLEAN DEFAULT FALSE,
            extract_path VARCHAR(512),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql);
        
        try {
            $pdo->exec("ALTER TABLE scan_history ADD COLUMN extract_path VARCHAR(512)");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                error_log("Failed to add extract_path column: " . $e->getMessage());
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return false;
    }
}

function saveScanResult($projectName, $passFail, $issuesFound = 0, $extractPath = '') {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("INSERT INTO scan_history (project_name, pass_fail, issues_found, extract_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$projectName, $passFail, $issuesFound, $extractPath]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to save scan result: " . $e->getMessage());
        return false;
    }
}

function updateGitHubPushStatus($scanId, $pushed = true) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("UPDATE scan_history SET github_pushed = ? WHERE id = ?");
        $stmt->execute([$pushed, $scanId]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to update GitHub push status: " . $e->getMessage());
        return false;
    }
}

function getExtractPath($scanId) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("SELECT extract_path FROM scan_history WHERE id = ?");
        $stmt->execute([$scanId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['extract_path'] : false;
    } catch (PDOException $e) {
        error_log("Failed to get extract path: " . $e->getMessage());
        return false;
    }
}
