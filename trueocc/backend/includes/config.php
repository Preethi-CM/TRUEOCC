<?php
// ============================================================
// TRUE OCCUPATION - Configuration (FULLY FIXED)
// ============================================================

// ─── DATABASE ────────────────────────────────────────────────
// ⚠️  IMPORTANT: Change DB_NAME to match YOUR database name in phpMyAdmin
// If you imported schema.sql it created 'true_occupation'
// If you named it 'database' during setup, change to 'database'
define('DB_HOST',    'localhost');
define('DB_NAME',    'true_occupation');   // ← Change if your DB has a different name
define('DB_USER',    'root');
define('DB_PASS',    '');                  // ← Empty by default in XAMPP
define('DB_CHARSET', 'utf8mb4');

// ─── APP ─────────────────────────────────────────────────────
define('APP_NAME', 'True Occupation');

// Auto-detect APP_URL — works on any XAMPP setup (fixed for PHP 8)
$_configFile  = str_replace(DIRECTORY_SEPARATOR, '/', __FILE__);
$_backendPath = dirname(dirname($_configFile));
$_projectRoot = dirname($_backendPath);
$_docRoot     = str_replace(DIRECTORY_SEPARATOR, '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? getcwd()));
$_relPath     = str_replace($_docRoot, '', $_projectRoot);
$_relPath     = '/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $_relPath), '/');
$_relPath     = rtrim($_relPath, '/');
$_proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = "127.0.0.1";
$port = "3306";

$dsn = "mysql:host=$host;port=$port;dbname=database;charset=utf8mb4";

define('APP_URL', $_proto . '://' . $host . $_relPath);
unset($_configFile, $_backendPath, $_projectRoot, $_docRoot, $_relPath, $_proto, $_host);
define('APP_VERSION', '2.0.0');

// ─── GEMINI API (Optional - for AI interview feedback) ────────
// Get FREE key: https://aistudio.google.com/app/apikey
define('GEMINI_API_KEY', 'AIzaSyBo_HmGSwFTTXjHexrUKSoSztjKVPMvaoI');   // ← Paste your key here
define('GEMINI_MODEL',   'gemini-1.5-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

// ─── EMAIL (Optional - leave MAIL_ENABLED false to skip) ──────
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'your_email@gmail.com');
define('MAIL_PASSWORD',   'your_app_password_here');
define('MAIL_FROM_EMAIL', 'noreply@trueocc.com');
define('MAIL_FROM_NAME',  'True Occupation');
define('MAIL_ENABLED',    false);  // ← Set true only after configuring Gmail above

// ─── UPLOADS ─────────────────────────────────────────────────
// FIXED: __DIR__ = backend/includes  →  /../ = backend/  →  /uploads/
define('UPLOAD_PATH',      __DIR__ . '/../uploads/');
define('RESUME_PATH',      UPLOAD_PATH . 'resumes/');
define('DOCS_PATH',        UPLOAD_PATH . 'docs/');
define('BOOKS_PATH',       UPLOAD_PATH . 'books/');
define('MAX_UPLOAD_MB',    10);
define('MAX_UPLOAD_BYTES', MAX_UPLOAD_MB * 1024 * 1024);

// ─── SECURITY ─────────────────────────────────────────────────
define('BCRYPT_COST',      12);
define('SESSION_NAME',     'trueocc_sess');
define('SESSION_LIFETIME', 86400);

// ─── FREEMIUM LIMITS ──────────────────────────────────────────
define('FREE_TEST_ATTEMPTS',      1);   // ← Increase to allow more free tests
define('FREE_INTERVIEW_ATTEMPTS', 1);   // ← Increase to allow more free interviews

// ─── TIMEZONE ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ─── ERROR REPORTING ──────────────────────────────────────────
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);  // Never expose errors in JSON output
    ini_set('log_errors',     1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
