<?php
/**
 * GitHub Route Handler
 * 
 * This file receives browser requests and routes them to the backend GitHub logic.
 * The actual backend logic is kept outside the web root for security.
 */

// Include the actual backend GitHub handler
require_once __DIR__ . '/../backend/github.php';
