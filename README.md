# SecurePush

SecurePush is a web application that allows users to upload code projects, run security scans against them, view the results, and push the projects to GitHub.

## Core Flow

```
Project Upload → Security Scan → Results Display → GitHub Push
```

## Tech Stack

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP
- **Database**: MySQL

## Version 1 Features

- Upload projects (ZIP files) via web interface
- Four security scanner modules:
  - **Secrets Scanner**: Detects exposed API keys, tokens, and other secrets
  - **Environment File Scanner**: Checks for .env files and improper exposure
  - **Dangerous Files Scanner**: Flags files that shouldn't be in repositories
  - **PHP Security Scanner**: Basic PHP security checks
- Clear results display showing what was found, where, and why it matters
- GitHub integration to push scanned projects
- MySQL storage of scan history

## Project Structure

```
SecurePush/
├── public/
│   ├── index.php          # Main page with upload form and results display
│   ├── css/
│   │   └── style.css      # Styling
│   └── js/
│       └── app.js         # Frontend behavior
├── backend/
│   ├── upload.php         # File upload handling
│   ├── scan.php           # Scan orchestration
│   └── github.php         # GitHub push functionality
├── scanner/
│   ├── secrets.php        # Secret detection
│   ├── env.php            # Environment file checks
│   ├── files.php          # Dangerous file detection
│   └── php-security.php   # PHP security checks
├── config/
│   └── database.php       # MySQL connection configuration
├── uploads/               # Temporary file storage (protected)
└── README.md
```

## Assumptions

### GitHub Integration
- **Authentication Method**: Token-based authentication using GitHub Personal Access Tokens
- **Token Format**: Classic tokens (`ghp_...`) or fine-grained tokens (`github_pat_...`)
- **Permissions**: Token requires `repo` scope to create/access repositories and push code
- **Repository Creation**: Application can create new repositories or push to existing ones
- **Git Operations**: Assumes `git` command is available on the server for push operations
- **User Responsibility**: Users must provide their own GitHub token and repository details

### Database
- **MySQL Version**: MySQL 5.7+ or MariaDB 10.2+
- **Database Name**: `securepush` (configurable in `config/database.php`)
- **Table Creation**: Application automatically creates required tables on first run
- **Credentials**: Default assumes local MySQL with root user (configurable)

### File Upload
- **File Size Limit**: 50MB maximum
- **File Type**: Only ZIP files accepted
- **Storage**: Temporary storage in `uploads/` directory with .htaccess protection
- **Cleanup**: Temporary files are automatically deleted after scanning

### Security Scanner
- **Pattern Matching**: Uses regex patterns for secret detection (may have false positives/negatives)
- **PHP Focus**: PHP security scanner is basic and not a comprehensive security audit
- **Read-Only**: Scanners only read files and do not execute any code from uploaded projects
- **Sanitization**: All output is sanitized to prevent XSS from scanned content

## Setup Instructions

### Prerequisites

1. **PHP** 7.4 or higher
2. **MySQL** 5.7+ or MariaDB 10.2+
3. **Web Server** (Apache with mod_php or Nginx with PHP-FPM)
4. **Git** (for GitHub push functionality)
5. **PHP Extensions**: `zip`, `pdo_mysql`, `curl`, `fileinfo`

### Installation

1. **Clone or Download** the project to your web server directory

2. **Configure Database**:
   ```bash
   # Create MySQL database
   mysql -u root -p
   CREATE DATABASE securepush;
   EXIT;
   ```

3. **Update Database Configuration**:
   Copy `config/database.example.php` to `config/database.php` and fill in your own local database credentials before running the app.
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'securepush');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **Set Permissions**:
   ```bash
   # Make uploads directory writable
   chmod 755 uploads/
   
   # Ensure proper ownership (adjust user/group as needed)
   chown www-data:www-data uploads/
   ```

5. **Configure Web Server**:
   
   **Apache**: Ensure `.htaccess` files are processed
   ```apache
   <Directory "/path/to/SecurePush">
       AllowOverride All
   </Directory>
   ```

   **Nginx**: Add appropriate location block for PHP processing

6. **Test Installation**:
   - Navigate to `http://your-server/SecurePush/public/`
   - You should see the SecurePush upload interface

### GitHub Token Setup

To use the GitHub push feature:

1. Go to GitHub Settings → Developer settings → Personal access tokens
2. Generate a new token with `repo` scope
3. Copy the token (format: `ghp_xxxxxxxxxxxx` or `github_pat_xxxxxxxxxxxx`)
4. Use this token when prompted in the SecurePush interface

## Usage

1. **Upload Project**:
   - Click "Upload & Scan"
   - Select a ZIP file of your project
   - Enter a project name
   - Wait for upload and scan to complete

2. **Review Results**:
   - View scan summary with severity breakdown
   - Examine detailed findings by category
   - Understand why each issue was flagged

3. **Push to GitHub** (optional):
   - Click "Push to GitHub" button
   - Enter your GitHub token
   - Specify repository owner and name
   - Choose private/public repository
   - Review and push

## Security Considerations

- **Uploaded Files**: Stored in protected `uploads/` directory, never web-executable
- **File Validation**: Type and size validation before processing
- **No Code Execution**: Scanners read files only; no `eval()`, `include()`, or execution
- **Output Sanitization**: All displayed content is sanitized to prevent XSS
- **Temporary Cleanup**: Uploaded files are deleted after scanning
- **Database**: Uses prepared statements to prevent SQL injection
- **GitHub Token**: Token is only used for API calls and git operations, never stored

## Limitations (Version 1)

- No user accounts or authentication system
- No CI/CD integration beyond manual GitHub push
- Basic PHP security scanner (not comprehensive)
- Pattern-based secret detection (may miss some secrets)
- No shell/terminal access for advanced analysis
- Single scan at a time (no queue system)

## Future Enhancements (Out of Scope for V1)

- User authentication and authorization
- CI/CD pipeline integration
- Advanced security tools and analysis
- Shell access for deeper inspection
- Multi-project batch scanning
- Scan scheduling and automation
- Additional language security scanners

## Troubleshooting

**Upload fails**:
- Check PHP `upload_max_filesize` and `post_max_size` settings
- Verify uploads directory permissions
- Ensure ZIP extension is enabled in PHP

**Scan fails**:
- Check database connection in `config/database.php`
- Verify scanner files are present and readable
- Check PHP error logs for specific errors

**GitHub push fails**:
- Verify git is installed and accessible
- Check GitHub token has correct permissions
- Ensure repository name doesn't conflict with existing repos
- Check network connectivity to GitHub API

**Database errors**:
- Verify MySQL is running
- Check database credentials in configuration
- Ensure database exists and user has proper permissions

## License

This project is provided as-is for educational and development purposes.

## Support

For issues or questions, please refer to the project documentation or contact the development team.
