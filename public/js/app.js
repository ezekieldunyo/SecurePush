// SecurePush Frontend Application
// Handles file upload, scan progress, results display, and GitHub integration

document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const uploadForm = document.getElementById('upload-form');
    const uploadBtn = document.getElementById('upload-btn');
    const uploadSection = document.getElementById('upload-section');
    const scanProgressSection = document.getElementById('scan-progress-section');
    const resultsSection = document.getElementById('results-section');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    const newScanBtn = document.getElementById('new-scan-btn');
    const githubBtn = document.getElementById('github-btn');
    const githubModal = document.getElementById('github-modal');
    const modalClose = document.getElementById('modal-close');
    const githubForm = document.getElementById('github-form');
    const fileInput = document.getElementById('project-file');
    const selectedFileDisplay = document.getElementById('selected-file-display');
    const folderInput = document.getElementById('project-folder');
    const selectedFolderDisplay = document.getElementById('selected-folder-display');
    const zipUploadGroup = document.getElementById('zip-upload-group');
    const folderUploadGroup = document.getElementById('folder-upload-group');
    const uploadTypeRadios = document.querySelectorAll('input[name="upload-type"]');
    
    // State
    let currentScanData = null;
    let uploadedProjectData = null;
    let currentUploadType = 'zip';
    
    // Notification System
    function showNotification(message, type = 'error', title = '') {
        const container = document.getElementById('notification-container');
        if (!container) {
            console.error('Notification container not found');
            return;
        }
        
        console.log('Showing notification:', { message, type, title }); // Debug log
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const titleElement = title ? `<div class="notification-title">${title}</div>` : '';
        
        notification.innerHTML = `
            <div class="notification-content">
                ${titleElement}
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close">&times;</button>
        `;
        
        // Add click handler for close button
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => window.dismissNotification(closeBtn));
        
        container.appendChild(notification);
        
        // Direct DOM verification
        console.log('DOM verification after insertion:');
        console.log('Container exists:', !!container);
        console.log('Container children count:', container.children.length);
        console.log('Container innerHTML:', container.innerHTML);
        console.log('Notification element:', notification);
        console.log('Notification in DOM:', document.body.contains(notification));
        console.log('Notification computed display:', window.getComputedStyle(notification).display);
        console.log('Notification computed visibility:', window.getComputedStyle(notification).visibility);
        
        const rect = notification.getBoundingClientRect();
        console.log('Notification position:', rect.top, rect.left, rect.width, rect.height);
        
        // Auto-dismiss success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                window.dismissNotification(closeBtn);
            }, 5000);
        }
    }
    
    // Make dismissNotification globally accessible
    window.dismissNotification = function(button) {
        const notification = button.closest('.notification');
        if (notification) {
            notification.classList.add('slide-out');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
    };
    
    // Upload Type Toggle Handler
    uploadTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            currentUploadType = this.value;
            
            if (currentUploadType === 'zip') {
                zipUploadGroup.style.display = 'block';
                folderUploadGroup.style.display = 'none';
                fileInput.required = true;
                folderInput.required = false;
            } else {
                zipUploadGroup.style.display = 'none';
                folderUploadGroup.style.display = 'block';
                fileInput.required = false;
                folderInput.required = true;
                
                // Ensure webkitdirectory attribute is set for folder picker
                folderInput.setAttribute('webkitdirectory', '');
                folderInput.setAttribute('directory', '');
            }
            
            // Clear selections
            selectedFileDisplay.textContent = '';
            selectedFileDisplay.classList.remove('visible');
            selectedFolderDisplay.textContent = '';
            selectedFolderDisplay.classList.remove('visible');
        });
    });
    
    // File Selection Display
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            const fileName = this.files[0].name;
            selectedFileDisplay.textContent = 'Selected: ' + fileName;
            selectedFileDisplay.classList.add('visible');
        } else {
            selectedFileDisplay.textContent = '';
            selectedFileDisplay.classList.remove('visible');
        }
    });
    
    // Folder Selection Display
    folderInput.addEventListener('change', function() {
        console.log('Folder input changed:', this.files);
        console.log('Number of files:', this.files ? this.files.length : 0);
        
        if (this.files && this.files.length > 0) {
            const fileCount = this.files.length;
            selectedFolderDisplay.textContent = `Selected: ${fileCount} files from folder`;
            selectedFolderDisplay.classList.add('visible');
            
            // Log first few files to verify folder structure
            for (let i = 0; i < Math.min(3, this.files.length); i++) {
                console.log(`File ${i}:`, this.files[i].name, this.files[i].webkitRelativePath);
            }
        } else {
            selectedFolderDisplay.textContent = '';
            selectedFolderDisplay.classList.remove('visible');
        }
    });
    
    // Upload Form Handler
    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const projectName = document.getElementById('project-name').value;
        let fileToUpload = null;
        
        if (currentUploadType === 'zip') {
            // Handle ZIP file upload
            if (!fileInput.files || fileInput.files.length === 0) {
                showNotification('Please select a ZIP file to upload', 'error', 'Upload Error');
                return;
            }
            
            fileToUpload = fileInput.files[0];
            
            // Validate file size (50MB max)
            if (fileToUpload.size > 50 * 1024 * 1024) {
                showNotification('File size exceeds 50MB limit', 'error', 'File Size Error');
                return;
            }
            
            // Validate file type
            if (fileToUpload.type !== 'application/zip' && fileToUpload.type !== 'application/x-zip-compressed' && !fileToUpload.name.endsWith('.zip')) {
                showNotification('Only ZIP files are allowed', 'error', 'Invalid File Type');
                return;
            }
            
        } else {
            // Handle folder upload - compress client-side first
            if (!folderInput.files || folderInput.files.length === 0) {
                showNotification('Please select a folder to upload', 'error', 'Upload Error');
                return;
            }
            
            // Show loading state for compression
            setButtonLoading(uploadBtn, true);
            uploadBtn.querySelector('.btn-loading').textContent = 'Compressing folder...';
            
            try {
                fileToUpload = await compressFolderToZip(folderInput.files, projectName);
            } catch (error) {
                console.error('Compression error:', error);
                showNotification('Folder compression failed: ' + error.message, 'error', 'Compression Error');
                setButtonLoading(uploadBtn, false);
                return;
            }
            
            // Update loading state for upload
            uploadBtn.querySelector('.btn-loading').textContent = 'Uploading...';
        }
        
        // Show loading state
        setButtonLoading(uploadBtn, true);
        
        // Create FormData
        const formData = new FormData();
        formData.append('project', fileToUpload);
        
        try {
            // Upload file
            showSection('scan-progress');
            updateScanProgress('upload', 10, 'Uploading file...');
            
            const uploadResponse = await fetch('upload.php', {
                method: 'POST',
                body: formData
            });
            
            const uploadData = await uploadResponse.json();
            
            if (!uploadData.success) {
                throw new Error(uploadData.error || 'Upload failed');
            }
            
            uploadedProjectData = uploadData;
            
            // Start scan
            await startScan(uploadData.extractPath, uploadData.zipPath, projectName);
            
        } catch (error) {
            console.error('Upload error:', error);
            showNotification('Upload failed: ' + error.message, 'error', 'Upload Error');
            showSection('upload');
            setButtonLoading(uploadBtn, false);
        }
    });
    
    // Compress folder to ZIP using JSZip
    async function compressFolderToZip(files, projectName) {
        return new Promise((resolve, reject) => {
            const zip = new JSZip();
            
            // Process all files and add to zip
            let processedCount = 0;
            const totalFiles = files.length;
            
            // Show compression progress in notification
            showNotification(`Compressing ${totalFiles} files...`, 'warning', 'Compressing Folder');
            
            Array.from(files).forEach(file => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    try {
                        // Get the relative path from the file's webkitRelativePath
                        const relativePath = file.webkitRelativePath;
                        
                        // Add file to zip with its relative path
                        zip.file(relativePath, e.target.result);
                        
                        processedCount++;
                        
                        // Update progress
                        if (processedCount % 10 === 0 || processedCount === totalFiles) {
                            const progress = Math.round((processedCount / totalFiles) * 100);
                            // Could update progress UI here if needed
                        }
                        
                        // When all files are processed, generate the zip
                        if (processedCount === totalFiles) {
                            zip.generateAsync({ type: 'blob' })
                                .then(function(content) {
                                    // Dismiss compression notification
                                    const notifications = document.querySelectorAll('.notification');
                                    notifications.forEach(notif => {
                                        if (notif.querySelector('.notification-title')?.textContent === 'Compressing Folder') {
                                            const closeBtn = notif.querySelector('.notification-close');
                                            if (closeBtn) window.dismissNotification(closeBtn);
                                        }
                                    });
                                    
                                    // Create a File object from the blob
                                    const zipFile = new File([content], `${projectName}.zip`, {
                                        type: 'application/zip'
                                    });
                                    
                                    // Validate size
                                    if (zipFile.size > 50 * 1024 * 1024) {
                                        reject(new Error('Compressed folder exceeds 50MB limit'));
                                        return;
                                    }
                                    
                                    resolve(zipFile);
                                })
                                .catch(function(error) {
                                    reject(new Error('ZIP generation failed: ' + error.message));
                                });
                        }
                    } catch (error) {
                        reject(new Error('Failed to process file: ' + file.name));
                    }
                };
                
                reader.onerror = function() {
                    reject(new Error('Failed to read file: ' + file.name));
                };
                
                reader.readAsArrayBuffer(file);
            });
        });
    }
    
    // Scan Function
    async function startScan(extractPath, zipPath, projectName) {
        try {
            updateScanProgress('secrets', 25, 'Scanning for secrets...');
            
            const scanResponse = await fetch('scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    extractPath: extractPath,
                    projectName: projectName,
                    zipPath: zipPath
                })
            });
            
            const scanData = await scanResponse.json();
            
            if (!scanData.success) {
                throw new Error(scanData.error || 'Scan failed');
            }
            
            updateScanProgress('complete', 100, 'Scan complete!');
            
            // Store scan data including file paths for GitHub push
            currentScanData = scanData.results;
            uploadedProjectData = {
                extractPath: scanData.results.extractPath,
                zipPath: scanData.results.zipPath
            };
            
            // Display results
            displayResults(scanData.results);
            
            // Show results section
            setTimeout(() => {
                showSection('results');
                setButtonLoading(uploadBtn, false);
            }, 500);
            
        } catch (error) {
            console.error('Scan error:', error);
            showNotification('Scan failed: ' + error.message, 'error', 'Scan Error');
            showSection('upload');
            setButtonLoading(uploadBtn, false);
        }
    }
    
    // Display Results
    function displayResults(results) {
        // Update summary
        document.getElementById('result-project-name').textContent = results.project_name;
        document.getElementById('result-scan-date').textContent = results.scan_date;
        document.getElementById('result-total-issues').textContent = results.summary.total_issues;
        
        const statusBadge = document.getElementById('result-status');
        statusBadge.textContent = results.summary.pass_fail.toUpperCase();
        statusBadge.className = 'summary-value status-badge ' + results.summary.pass_fail;
        
        // Update severity counts
        document.getElementById('count-critical').textContent = results.summary.critical;
        document.getElementById('count-high').textContent = results.summary.high;
        document.getElementById('count-medium').textContent = results.summary.medium;
        document.getElementById('count-low').textContent = results.summary.low;
        
        // Display findings
        const findingsContainer = document.getElementById('findings-container');
        findingsContainer.innerHTML = '';
        
        if (results.summary.total_issues === 0) {
            findingsContainer.innerHTML = '<div class="no-findings">✓ No security issues found!</div>';
            return;
        }
        
        // Display findings by category
        const categoryLabels = {
            secrets: 'Secrets Detected',
            env: 'Environment File Issues',
            files: 'Dangerous Files',
            php_security: 'PHP Security Issues'
        };
        
        for (const [category, findings] of Object.entries(results.findings)) {
            if (findings && findings.length > 0) {
                const categorySection = document.createElement('div');
                categorySection.className = 'finding-category';
                
                const categoryHeader = document.createElement('div');
                categoryHeader.className = 'finding-category-header';
                categoryHeader.innerHTML = `
                    <span class="finding-category-title">${categoryLabels[category] || category}</span>
                    <span class="finding-count">${findings.length}</span>
                `;
                
                categorySection.appendChild(categoryHeader);
                
                findings.forEach(finding => {
                    const findingItem = document.createElement('div');
                    findingItem.className = 'finding-item';
                    
                    let matchHtml = '';
                    if (finding.match) {
                        matchHtml = `<div class="finding-match">${escapeHtml(finding.match)}</div>`;
                    }
                    
                    findingItem.innerHTML = `
                        <div class="finding-severity ${finding.severity}">${finding.severity}</div>
                        <div class="finding-details">
                            <div class="finding-file">${escapeHtml(finding.file)}</div>
                            <div class="finding-line">Line: ${finding.line}</div>
                            <div class="finding-description">${escapeHtml(finding.description)}</div>
                            ${matchHtml}
                        </div>
                    `;
                    
                    categorySection.appendChild(findingItem);
                });
                
                findingsContainer.appendChild(categorySection);
            }
        }
    }
    
    // Update Scan Progress
    function updateScanProgress(step, progress, text) {
        // Update progress bar
        progressFill.style.width = progress + '%';
        progressText.textContent = text;
        
        // Update step indicators
        const steps = ['upload', 'secrets', 'env', 'files', 'php', 'complete'];
        const stepIndex = steps.indexOf(step);
        
        steps.forEach((stepName, index) => {
            const stepElement = document.getElementById('step-' + stepName);
            if (stepElement) {
                stepElement.classList.remove('active', 'completed');
                
                if (index < stepIndex) {
                    stepElement.classList.add('completed');
                } else if (index === stepIndex) {
                    stepElement.classList.add('active');
                }
            }
        });
    }
    
    // Show Section
    function showSection(section) {
        uploadSection.style.display = 'none';
        scanProgressSection.style.display = 'none';
        resultsSection.style.display = 'none';
        
        switch (section) {
            case 'upload':
                uploadSection.style.display = 'block';
                break;
            case 'scan-progress':
                scanProgressSection.style.display = 'block';
                break;
            case 'results':
                resultsSection.style.display = 'block';
                break;
        }
    }
    
    // Set Button Loading State
    function setButtonLoading(button, isLoading) {
        const btnText = button.querySelector('.btn-text');
        const btnLoading = button.querySelector('.btn-loading');
        
        if (isLoading) {
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
            button.disabled = true;
        } else {
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
            button.disabled = false;
        }
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // New Scan Button
    newScanBtn.addEventListener('click', function() {
        showSection('upload');
        uploadForm.reset();
        selectedFileDisplay.textContent = '';
        selectedFileDisplay.classList.remove('visible');
        selectedFolderDisplay.textContent = '';
        selectedFolderDisplay.classList.remove('visible');
        currentScanData = null;
        uploadedProjectData = null;
        currentUploadType = 'zip';
        
        // Reset upload type display
        zipUploadGroup.style.display = 'block';
        folderUploadGroup.style.display = 'none';
        fileInput.required = true;
        folderInput.required = false;
        
        // Also trigger cleanup of old files via the backend
        fetch('cleanup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cleanup_all' })
        }).catch(err => console.log('Cleanup request failed:', err));
    });
    
    // GitHub Button
    githubBtn.addEventListener('click', function() {
        if (!currentScanData) {
            showNotification('No scan data available. Please upload and scan a project first.', 'error', 'No Scan Data');
            return;
        }
        
        githubModal.style.display = 'flex';
        
        // Pre-fill repository name with project name
        const projectName = currentScanData.project_name;
        const sanitizedRepoName = projectName.toLowerCase().replace(/[^a-z0-9-]/g, '-');
        document.getElementById('repo-name').value = sanitizedRepoName;
    });
    
    // Modal Close
    modalClose.addEventListener('click', function() {
        githubModal.style.display = 'none';
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === githubModal) {
            githubModal.style.display = 'none';
        }
    });
    
    // GitHub Form Submit
    githubForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const githubToken = document.getElementById('github-token').value;
        const repoOwner = document.getElementById('repo-owner').value;
        const repoName = document.getElementById('repo-name').value;
        const privateRepo = document.getElementById('private-repo').checked;
        const commitMessage = document.getElementById('commit-message').value;
        
        const submitBtn = githubForm.querySelector('button[type="submit"]');
        setButtonLoading(submitBtn, true);
        
        try {
            const response = await fetch('github.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    githubToken: githubToken,
                    repoOwner: repoOwner,
                    repoName: repoName,
                    privateRepo: privateRepo,
                    commitMessage: commitMessage,
                    projectPath: uploadedProjectData ? uploadedProjectData.extractPath : '',
                    scanId: currentScanData ? currentScanData.scan_id : null
                })
            });
            
            const data = await response.json();
            
            console.log('GitHub response:', data); // Debug log
            
            if (!data.success) {
                throw new Error(data.error || 'GitHub push failed');
            }
            
            // Use the message from the response if available, otherwise use default
            const successMessage = data.message || 'Project successfully pushed to GitHub!';
            const repositoryInfo = data.repository ? '<br>Repository: ' + data.repository : '';
            
            // Close modal first
            githubModal.style.display = 'none';
            githubForm.reset();
            
            // Then show notification (with slight delay to ensure modal is closed)
            setTimeout(() => {
                showNotification(successMessage + repositoryInfo, 'success', 'Push Successful');
            }, 100);
            
        } catch (error) {
            console.error('GitHub error:', error);
            showNotification('GitHub push failed: ' + error.message, 'error', 'GitHub Push Error');
        } finally {
            setButtonLoading(submitBtn, false);
        }
    });
});
