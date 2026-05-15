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
require_once __DIR__ . '/../includes/email.php';

requireAdmin(); // All admin routes require admin session

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true) ?? [];
$data   = array_merge($_POST, $data);

switch ($action) {
    case 'stats':             getStats();              break;
    case 'users':             getUsers();              break;
    case 'user_get':          getUser();               break;
    case 'user_update':       updateUser($data);       break;
    case 'user_delete':       deleteUser();            break;
    case 'user_toggle':       toggleUser();            break;
    case 'employers':         getEmployers();          break;
    case 'employer_verify':   verifyEmployer($data);   break;
    case 'employer_update':   updateEmployer($data);   break;
    case 'employer_delete':   deleteEmployer();        break;
    case 'jobs':              getJobs();               break;
    case 'job_update':        updateJob($data);        break;
    case 'job_delete':        deleteJob();             break;
    case 'job_toggle':        toggleJob();             break;
    case 'applications':      getApplications();       break;
    case 'send_email':        sendAdminEmail($data);   break;
    case 'email_log':         getEmailLog();           break;
    case 'test_results':      getTestResults();        break;
    case 'books':             getBooks();              break;
    case 'book_add':          addBook($data);          break;
    case 'book_delete':       deleteBook();            break;
    case 'send_job_recs':     sendJobRecs();           break;
    default: apiError('Invalid admin action');
}

function getStats(): void {
    apiSuccess([
        'total_users'          => (int)db()->row("SELECT COUNT(*) as c FROM users")['c'],
        'total_employers'      => (int)db()->row("SELECT COUNT(*) as c FROM employers")['c'],
        'verified_employers'   => (int)db()->row("SELECT COUNT(*) as c FROM employers WHERE verification_status='verified'")['c'],
        'pending_verification' => (int)db()->row("SELECT COUNT(*) as c FROM employers WHERE verification_status='pending'")['c'],
        'total_jobs'           => (int)db()->row("SELECT COUNT(*) as c FROM jobs")['c'],
        'active_jobs'          => (int)db()->row("SELECT COUNT(*) as c FROM jobs WHERE is_active=1")['c'],
        'total_applications'   => (int)db()->row("SELECT COUNT(*) as c FROM applications")['c'],
        'hired'                => (int)db()->row("SELECT COUNT(*) as c FROM applications WHERE status='Hired'")['c'],
        'total_tests'          => (int)db()->row("SELECT COUNT(*) as c FROM aptitude_results")['c'],
        'total_interviews'     => (int)db()->row("SELECT COUNT(*) as c FROM interview_results")['c'],
        'emails_sent'          => (int)db()->row("SELECT COUNT(*) as c FROM email_log WHERE status='sent'")['c'],
        'recent_users'         => db()->rows("SELECT id,name,email,role,created_at FROM users ORDER BY created_at DESC LIMIT 5"),
        'recent_jobs'          => db()->rows("SELECT id,title,company,posted_at FROM jobs ORDER BY posted_at DESC LIMIT 5"),
    ]);
}

function getUsers(): void {
    $q    = '%'.clean($_GET['q']??'').'%';
    $role = $_GET['role']??'';
    $w    = "(name LIKE ? OR email LIKE ?)"; $p=[$q,$q];
    if ($role) { $w.=" AND role=?"; $p[]=$role; }
    $users = db()->rows("SELECT id,name,email,role,phone,location,is_premium,is_active,created_at FROM users WHERE $w ORDER BY created_at DESC", $p);
    apiSuccess(['users'=>$users]);
}

function getUser(): void {
    $id   = (int)($_GET['id']??0);
    $user = db()->row("SELECT id,name,email,role,phone,location,is_premium,is_active,created_at FROM users WHERE id=?",[$id]);
    if (!$user) apiError('Not found.',404);
    $resume = db()->row("SELECT * FROM resumes WHERE user_id=?",[$id]);
    $fit    = db()->row("SELECT * FROM job_fit_scores WHERE user_id=?",[$id]);
    apiSuccess(['user'=>$user,'resume'=>$resume,'fit_score'=>$fit]);
}

function updateUser(array $d): void {
    $id = (int)($d['id']??0);
    $sets=[]; $params=[];
    if (!empty($d['name']))  { $sets[]="name=?";  $params[]=clean($d['name']); }
    if (!empty($d['email'])) { $sets[]="email=?"; $params[]=clean($d['email']); }
    if (isset($d['is_premium'])) { $sets[]="is_premium=?"; $params[]=(int)$d['is_premium']; }
    if (isset($d['is_active']))  { $sets[]="is_active=?";  $params[]=(int)$d['is_active']; }
    if (!empty($d['password'])) {
        $sets[]="password=?";
        $params[]=password_hash($d['password'],PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
    }
    if (!$sets) apiError('Nothing to update.');
    $params[] = $id;
    db()->exec("UPDATE users SET ".implode(',',$sets)." WHERE id=?", $params);
    apiSuccess(null,'User updated!');
}

function deleteUser(): void {
    $id = (int)($_GET['id']??0);
    db()->exec("DELETE FROM users WHERE id=?",[$id]);
    apiSuccess(null,'User deleted.');
}

function toggleUser(): void {
    $id = (int)($_GET['id']??0);
    db()->exec("UPDATE users SET is_active=!is_active WHERE id=?",[$id]);
    apiSuccess(null,'User toggled.');
}

function getEmployers(): void {
    $status = $_GET['status']??'';
    $w='1=1'; $p=[];
    if ($status) { $w="e.verification_status=?"; $p[]=$status; }
    $emps = db()->rows(
        "SELECT e.*,u.name as user_name,u.email as user_email,u.created_at as user_created
         FROM employers e JOIN users u ON e.user_id=u.id WHERE $w ORDER BY e.created_at DESC",$p);
    apiSuccess(['employers'=>$emps]);
}

function verifyEmployer(array $d): void {
    $id     = (int)($d['id']??0);
    $status = $d['status']??'';
    $note   = clean($d['note']??'');
    if (!in_array($status,['verified','rejected'])) apiError('Status must be verified or rejected.');

    $va = $status==='verified' ? 'NOW()' : 'NULL';
    db()->exec("UPDATE employers SET verification_status=?,verification_note=?,verified_at=$va WHERE id=?",
        [$status,$note,$id]);

    // Email employer
    $emp = db()->row("SELECT e.*,u.name,u.email FROM employers e JOIN users u ON e.user_id=u.id WHERE e.id=?",[$id]);
    if ($emp) {
        $msg = $status==='verified'
            ? "🎉 Your company '{$emp['company_name']}' has been verified! You can now post jobs."
            : "❌ Verification for '{$emp['company_name']}' was rejected. Reason: $note Please resubmit.";
        emailAdminToUser($emp['email'], $emp['name'], "Verification $status: {$emp['company_name']}", $msg);
        notify($emp['user_id'], "Company Verification: $status", $msg);
    }
    apiSuccess(null,"Employer $status.");
}

function updateEmployer(array $d): void {
    $id = (int)($d['id']??0);
    $allowed=['company_name','company_email','industry','company_size','verification_status'];
    $sets=[]; $params=[];
    foreach ($allowed as $f) { if (isset($d[$f])) { $sets[]="$f=?"; $params[]=clean($d[$f]); } }
    if (!$sets) apiError('Nothing to update.');
    $params[] = $id;
    db()->exec("UPDATE employers SET ".implode(',',$sets)." WHERE id=?", $params);
    apiSuccess(null,'Employer updated!');
}

function deleteEmployer(): void {
    $id  = (int)($_GET['id']??0);
    $emp = db()->row("SELECT user_id FROM employers WHERE id=?",[$id]);
    if ($emp) db()->exec("DELETE FROM users WHERE id=?",[$emp['user_id']]);
    db()->exec("DELETE FROM employers WHERE id=?",[$id]);
    apiSuccess(null,'Employer deleted.');
}

function getJobs(): void {
    $q = '%'.clean($_GET['q']??'').'%';
    $jobs = db()->rows(
        "SELECT j.*,(SELECT COUNT(*) FROM applications WHERE job_id=j.id) as app_count
         FROM jobs j WHERE j.title LIKE ? OR j.company LIKE ? ORDER BY j.posted_at DESC",[$q,$q]);
    apiSuccess(['jobs'=>$jobs]);
}

function updateJob(array $d): void {
    $id = (int)($d['id']??0);
    $allowed=['title','location','job_type','salary_range','description','is_active','experience_level'];
    $sets=[]; $params=[];
    foreach ($allowed as $f) { if (isset($d[$f])) { $sets[]="$f=?"; $params[]=clean($d[$f]); } }
    if (!$sets) apiError('Nothing to update.');
    $params[] = $id;
    db()->exec("UPDATE jobs SET ".implode(',',$sets)." WHERE id=?", $params);
    apiSuccess(null,'Job updated!');
}

function deleteJob(): void {
    $id = (int)($_GET['id']??0);
    db()->exec("DELETE FROM jobs WHERE id=?",[$id]);
    apiSuccess(null,'Job deleted.');
}

function toggleJob(): void {
    $id = (int)($_GET['id']??0);
    db()->exec("UPDATE jobs SET is_active=!is_active WHERE id=?",[$id]);
    apiSuccess(null,'Job toggled.');
}

function getApplications(): void {
    $apps = db()->rows(
        "SELECT a.*,u.name as applicant_name,u.email as applicant_email,j.title as job_title,j.company
         FROM applications a JOIN users u ON a.user_id=u.id JOIN jobs j ON a.job_id=j.id
         ORDER BY a.applied_at DESC LIMIT 200");
    apiSuccess(['applications'=>$apps]);
}

function sendAdminEmail(array $d): void {
    $toEmail = clean($d['to_email']??'');
    $toName  = clean($d['to_name']??'User');
    $subject = clean($d['subject']??'');
    $message = clean($d['message']??'');
    if (!$toEmail || !$subject || !$message) apiError('Email, subject and message required.');

    // Support "all_seekers" or "all_employers"
    if ($toEmail === 'all_seekers' || $toEmail === 'all_employers') {
        $role = $toEmail === 'all_seekers' ? 'seeker' : 'employer';
        $users = db()->rows("SELECT name,email FROM users WHERE role=? AND is_active=1",[$role]);
        $sent = 0;
        foreach ($users as $u) {
            if (emailAdminToUser($u['email'],$u['name'],$subject,$message)) $sent++;
        }
        apiSuccess(['sent'=>$sent],"Sent to $sent {$role}s.");
    }

    $ok = emailAdminToUser($toEmail,$toName,$subject,$message);
    apiSuccess(['sent'=>$ok?1:0], $ok ? 'Email sent!' : 'Email failed.');
}

function getEmailLog(): void {
    $logs = db()->rows("SELECT * FROM email_log ORDER BY sent_at DESC LIMIT 100");
    apiSuccess(['logs'=>$logs]);
}

function getTestResults(): void {
    $res = db()->rows(
        "SELECT r.*,u.name,u.email FROM aptitude_results r JOIN users u ON r.user_id=u.id
         ORDER BY r.taken_at DESC LIMIT 100");
    apiSuccess(['results'=>$res]);
}

function getBooks(): void {
    $books = db()->rows("SELECT * FROM books ORDER BY is_premium ASC, id ASC");
    apiSuccess(['books'=>$books]);
}

function addBook(array $d): void {
    $title = clean($d['title']??'');
    if (!$title) apiError('Title required.');
    db()->insert(
        "INSERT INTO books (title,author,description,category,skill_tags,is_premium,external_url) VALUES (?,?,?,?,?,?,?)",
        [$title,clean($d['author']??''),clean($d['description']??''),
         clean($d['category']??''),clean($d['skill_tags']??''),
         !empty($d['is_premium'])?1:0, clean($d['external_url']??'')]);
    apiSuccess(null,'Book added!');
}

function deleteBook(): void {
    $id = (int)($_GET['id']??0);
    db()->exec("DELETE FROM books WHERE id=?",[$id]);
    apiSuccess(null,'Book deleted.');
}

function sendJobRecs(): void {
    $seekers = db()->rows("SELECT u.id,u.name,u.email,r.skills FROM users u LEFT JOIN resumes r ON r.user_id=u.id WHERE u.role='seeker' AND u.is_active=1");
    $jobs    = db()->rows("SELECT * FROM jobs WHERE is_active=1 ORDER BY posted_at DESC LIMIT 10");
    $sent    = 0;
    foreach ($seekers as $s) {
        if (!$s['skills']) continue;
        $matched = [];
        foreach ($jobs as $j) {
            $pct = matchPercent($s['skills'],$j['skills_required']??'');
            if ($pct >= 40) { $j['match_pct']=$pct; $matched[]=$j; }
        }
        if (!empty($matched)) {
            // require email.php
            require_once __DIR__ . '/../includes/email.php';
            emailJobRecommendation($s['email'],$s['name'],$matched);
            $sent++;
        }
    }
    apiSuccess(['sent'=>$sent],"Recommendations sent to $sent seekers.");
}
