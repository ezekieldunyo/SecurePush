<?php
/**
 * Scan Route Handler
 * 
 * This file receives browser requests and routes them to the backend scan logic.
 * The actual backend logic is kept outside the web root for security.
 */

// Include the actual backend scan handler
require_once __DIR__ . '/../backend/scan.php';
