<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_exception_handler(function(Throwable $e) { ob_clean(); http_response_code(500); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]); exit; });
set_error_handler(function($errno,$errstr,$errfile,$errline) { if(!($errno & error_reporting())) return false; ob_clean(); http_response_code(500); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>"PHP Error: $errstr in ".basename($errfile)." line $errline"]); exit; });
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/helpers.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$raw    = file_get_contents('php://input');
$json   = json_decode($raw, true) ?? [];
$data   = array_merge($_POST, $json);

switch ($action) {
    case 'login':       doLogin($data);      break;
    case 'signup':      doSignup($data);     break;
    case 'logout':      doLogout();          break;
    case 'me':          doMe();              break;
    case 'admin_login': doAdminLogin($data); break;
    default: apiError('Invalid action');
}

// ── LOGIN ──────────────────────────────────────────────────
function doLogin(array $d): void {
    $email = trim($d['email'] ?? '');
    $pass  = $d['password'] ?? '';
    $role  = $d['role'] ?? '';

    if (!$email || !$pass) apiError('Email and password required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) apiError('Invalid email.');

    $user = db()->row("SELECT * FROM users WHERE email=? AND role=? AND is_active=1", [$email, $role]);
    if (!$user || !password_verify($pass, $user['password'])) {
        apiError('Invalid credentials. Check email, password and role.');
    }

    loginUser($user['id'], $user['role']);

    $extra = [];
    if ($user['role'] === 'employer') {
        $extra['employer'] = db()->row("SELECT * FROM employers WHERE user_id=?", [$user['id']]);
    }
    $extra['unread_notifs'] = (int)db()->row(
        "SELECT COUNT(*) as c FROM notifications WHERE user_id=? AND is_read=0", [$user['id']]
    )['c'];

    $redirect = $user['role'] === 'seeker'
        ? APP_URL . '/frontend/pages/user-dashboard.html'
        : APP_URL . '/frontend/pages/employer-dashboard.html';

    unset($user['password']);
    apiSuccess(array_merge(['user' => $user, 'redirect' => $redirect], $extra), 'Login successful!');
}

// ── SIGNUP ─────────────────────────────────────────────────
function doSignup(array $d): void {
    $name    = trim($d['name'] ?? '');
    $email   = trim($d['email'] ?? '');
    $pass    = $d['password'] ?? '';
    $confirm = $d['confirm_password'] ?? '';
    $role    = $d['role'] ?? '';

    $errs = [];
    if (strlen($name) < 2) $errs[] = 'Name too short.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Invalid email.';
    if (strlen($pass) < 6) $errs[] = 'Password min 6 characters.';
    if ($pass !== $confirm) $errs[] = 'Passwords do not match.';
    if (!in_array($role, ['seeker','employer'])) $errs[] = 'Invalid role.';
    if ($errs) apiError('Validation failed.', 422, $errs);

    if (db()->row("SELECT id FROM users WHERE email=?", [$email])) {
        apiError('Email already registered.');
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $uid  = (int)db()->insert("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)",
        [$name, $email, $hash, $role]);

    if ($role === 'employer') {
        db()->insert("INSERT INTO employers (user_id, company_name, company_email) VALUES (?,?,?)",
            [$uid, $name, $email]);
    }

    notify($uid, 'Welcome to True Occupation! 🎉',
        'Complete your profile and ' . ($role === 'seeker' ? 'upload your resume' : 'verify your company') . ' to get started.');

    loginUser($uid, $role);

    $redirect = $role === 'seeker'
        ? APP_URL . '/frontend/pages/user-dashboard.html'
        : APP_URL . '/frontend/pages/employer-dashboard.html';

    apiSuccess(['redirect' => $redirect], 'Account created successfully!');
}

// ── LOGOUT ─────────────────────────────────────────────────
function doLogout(): void {
    logoutUser();
    apiSuccess(['redirect' => APP_URL . '/frontend/pages/login.html'], 'Logged out.');
}

// ── ME ─────────────────────────────────────────────────────
function doMe(): void {
    if (isAdminLoggedIn()) {
        $a = currentAdmin();
        unset($a['password']);
        apiSuccess(['type' => 'admin', 'user' => $a]);
    }
    $u = currentUser();
    if (!$u) apiError('Not authenticated.', 401);

    $emp    = $u['role'] === 'employer' ? db()->row("SELECT * FROM employers WHERE user_id=?", [$u['id']]) : null;
    $resume = $u['role'] === 'seeker'   ? db()->row("SELECT * FROM resumes WHERE user_id=?", [$u['id']])  : null;
    $fit    = db()->row("SELECT * FROM job_fit_scores WHERE user_id=?", [$u['id']]);
    $notifs = (int)db()->row("SELECT COUNT(*) as c FROM notifications WHERE user_id=? AND is_read=0", [$u['id']])['c'];

    unset($u['password']);
    apiSuccess(['type'=>'user','user'=>$u,'employer'=>$emp,'resume'=>$resume,'fit_score'=>$fit,'unread_notifs'=>$notifs]);
}

// ── ADMIN LOGIN ────────────────────────────────────────────
function doAdminLogin(array $d): void {
    $email = trim($d['email'] ?? '');
    $pass  = $d['password'] ?? '';
    if (!$email || !$pass) apiError('Email and password required.');

    $admin = db()->row("SELECT * FROM admins WHERE email=?", [$email]);
    if (!$admin || !password_verify($pass, $admin['password'])) {
        apiError('Invalid admin credentials.');
    }

    loginAdmin($admin['id']);
    db()->exec("UPDATE admins SET last_login=NOW() WHERE id=?", [$admin['id']]);

    unset($admin['password']);
    apiSuccess(['admin' => $admin, 'redirect' => APP_URL . '/frontend/pages/admin-dashboard.html'], 'Admin login successful!');
}
