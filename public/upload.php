<?php
/**
 * Upload Route Handler
 * 
 * This file receives browser requests and routes them to the backend upload logic.
 * The actual backend logic is kept outside the web root for security.
 */

// Include the actual backend upload handler
require_once __DIR__ . '/../backend/upload.php';
