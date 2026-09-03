<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecurePush - Security Scanner</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body>
    <div class="container">
        <!-- Notification Container -->
        <div id="notification-container"></div>
        
        <header>
            <h1>SecurePush</h1>
            <p class="subtitle">Upload • Scan • Secure</p>
        </header>

        <main>
            <!-- Upload Section -->
            <section id="upload-section" class="card">
                <h2>Upload Project</h2>
                <p class="instruction">Upload your project as a ZIP file or folder for security scanning</p>
                
                <form id="upload-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Upload Type</label>
                        <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <label style="font-weight: normal; cursor: pointer;">
                                <input type="radio" name="upload-type" value="zip" checked>
                                ZIP File
                            </label>
                            <label style="font-weight: normal; cursor: pointer;">
                                <input type="radio" name="upload-type" value="folder">
                                Folder
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="zip-upload-group">
                        <label for="project-file">Project ZIP File</label>
                        <input type="file" id="project-file" name="project" accept=".zip" required>
                        <small class="help-text">Maximum file size: 50MB</small>
                        <div id="selected-file-display" class="selected-file"></div>
                    </div>
                    
                    <div class="form-group" id="folder-upload-group" style="display: none;">
                        <label for="project-folder">Project Folder</label>
                        <input type="file" id="project-folder" name="projectFolder" webkitdirectory multiple>
                        <small class="help-text">Select a folder - contents will be compressed automatically</small>
                        <div id="selected-folder-display" class="selected-file"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="project-name">Project Name</label>
                        <input type="text" id="project-name" name="projectName" placeholder="My Awesome Project" required>
                    </div>
                    
                    <button type="submit" id="upload-btn" class="btn btn-primary">
                        <span class="btn-text">Upload & Scan</span>
                        <span class="btn-loading" style="display: none;">Uploading...</span>
                    </button>
                </form>
            </section>

            <!-- Scan Progress Section -->
            <section id="scan-progress-section" class="card" style="display: none;">
                <h2>Scanning in Progress</h2>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <p class="progress-text" id="progress-text">Initializing scan...</p>
                </div>
                
                <div class="scan-steps">
                    <div class="scan-step" id="step-upload">
                        <span class="step-icon">📤</span>
                        <span class="step-text">Uploading</span>
                    </div>
                    <div class="scan-step" id="step-secrets">
                        <span class="step-icon">🔑</span>
                        <span class="step-text">Scanning for secrets</span>
                    </div>
                    <div class="scan-step" id="step-env">
                        <span class="step-icon">🔒</span>
                        <span class="step-text">Checking .env files</span>
                    </div>
                    <div class="scan-step" id="step-files">
                        <span class="step-icon">📁</span>
                        <span class="step-text">Analyzing files</span>
                    </div>
                    <div class="scan-step" id="step-php">
                        <span class="step-icon">⚡</span>
                        <span class="step-text">PHP security check</span>
                    </div>
                    <div class="scan-step" id="step-complete">
                        <span class="step-icon">✅</span>
                        <span class="step-text">Complete</span>
                    </div>
                </div>
            </section>

            <!-- Results Section -->
            <section id="results-section" class="card" style="display: none;">
                <h2>Scan Results</h2>
                
                <div class="summary">
                    <div class="summary-item">
                        <span class="summary-label">Project:</span>
                        <span class="summary-value" id="result-project-name">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Scan Date:</span>
                        <span class="summary-value" id="result-scan-date">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Status:</span>
                        <span class="summary-value status-badge" id="result-status">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Issues:</span>
                        <span class="summary-value" id="result-total-issues">0</span>
                    </div>
                </div>

                <div class="severity-breakdown">
                    <div class="severity-item critical">
                        <span class="severity-label">Critical:</span>
                        <span class="severity-count" id="count-critical">0</span>
                    </div>
                    <div class="severity-item high">
                        <span class="severity-label">High:</span>
                        <span class="severity-count" id="count-high">0</span>
                    </div>
                    <div class="severity-item medium">
                        <span class="severity-label">Medium:</span>
                        <span class="severity-count" id="count-medium">0</span>
                    </div>
                    <div class="severity-item low">
                        <span class="severity-label">Low:</span>
                        <span class="severity-count" id="count-low">0</span>
                    </div>
                </div>

                <div class="findings-container" id="findings-container">
                    <!-- Findings will be dynamically inserted here -->
                </div>

                <div class="actions">
                    <button id="new-scan-btn" class="btn btn-secondary">New Scan</button>
                    <button id="github-btn" class="btn btn-primary">Push to GitHub</button>
                </div>
            </section>

            <!-- GitHub Configuration Modal -->
            <div id="github-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Push to GitHub</h3>
                        <button class="modal-close" id="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="github-form">
                            <div class="form-group">
                                <label for="github-token">GitHub Personal Access Token</label>
                                <input type="password" id="github-token" name="githubToken" placeholder="ghp_xxxxxxxxxxxx" required>
                                <small class="help-text">Requires repo scope permissions</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="repo-owner">Repository Owner</label>
                                <input type="text" id="repo-owner" name="repoOwner" placeholder="username or organization" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="repo-name">Repository Name</label>
                                <input type="text" id="repo-name" name="repoName" placeholder="my-repo" required>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="private-repo" name="privateRepo">
                                    Private Repository
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label for="commit-message">Commit Message</label>
                                <input type="text" id="commit-message" name="commitMessage" value="Initial commit via SecurePush">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <span class="btn-text">Push to GitHub</span>
                                <span class="btn-loading" style="display: none;">Pushing...</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; 2026 SecurePush. Security scanning for code projects.</p>
        </footer>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
