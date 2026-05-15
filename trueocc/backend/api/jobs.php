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

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$raw    = file_get_contents('php://input');
$json   = json_decode($raw, true) ?? [];
$data   = array_merge($_POST, $json);

switch ($action) {
    case 'list':          listJobs();            break;
    case 'get':           getJob();              break;
    case 'skill_gap':      skillGapForJob();      break;
    case 'search':        searchJobs($data);     break;
    case 'recommended':   recommendedJobs();     break;
    case 'post':          postJob($data);        break;
    case 'update':        updateJob($data);      break;
    case 'delete':        deleteJob();           break;
    case 'toggle':        toggleJob();           break;
    case 'my_jobs':       myJobs();              break;
    case 'apply':         applyJob($data);       break;
    case 'my_applications': myApplications();   break;
    case 'update_status': updateAppStatus($data); break;
    case 'applicants':    getApplicants();       break;
    case 'get_questions': getJobQuestions();     break;
    case 'send_recommendation': sendRecs();     break;
    default: apiError('Invalid action');
}

function listJobs(): void {
    $type   = $_GET['type'] ?? '';
    $page   = max(1,(int)($_GET['page']??1));
    $limit  = 12;
    $offset = ($page-1)*$limit;
    $where  = "j.is_active=1";
    $params = [];
    if ($type) { $where .= " AND j.job_type=?"; $params[] = $type; }

    $total = db()->row("SELECT COUNT(*) as c FROM jobs j WHERE $where", $params)['c'];
    $jobs  = db()->rows("SELECT j.* FROM jobs j WHERE $where ORDER BY j.posted_at DESC LIMIT $limit OFFSET $offset", $params);

    if (isLoggedIn()) {
        $res = db()->row("SELECT skills FROM resumes WHERE user_id=?", [$_SESSION['user_id']]);
        foreach ($jobs as &$j) {
            $j['match_pct'] = matchPercent($res['skills'] ?? '', $j['skills_required'] ?? '');
        }
    }
    apiSuccess(['jobs'=>$jobs,'total'=>(int)$total,'page'=>$page]);
}

function getJob(): void {
    $id = (int)($_GET['id']??0);
    if (!$id) apiError('Job ID required.');
    $job = db()->row("SELECT j.*, e.company_name, e.industry, e.company_size FROM jobs j JOIN employers e ON j.employer_id=e.id WHERE j.id=?", [$id]);
    if (!$job) apiError('Job not found.', 404);
    $applied = false;
    $matchPct = 0;
    if (isLoggedIn()) {
        $applied = !!db()->row("SELECT id FROM applications WHERE job_id=? AND user_id=?", [$id, $_SESSION['user_id']]);
        $res = db()->row("SELECT skills FROM resumes WHERE user_id=?", [$_SESSION['user_id']]);
        $matchPct = matchPercent($res['skills']??'', $job['skills_required']??'');
    }
    $questions = db()->rows("SELECT question FROM job_interview_questions WHERE job_id=? ORDER BY order_num", [$id]);
    apiSuccess(['job'=>$job,'already_applied'=>$applied,'match_pct'=>$matchPct,'interview_questions'=>$questions]);
}

function skillGapForJob(): void {
    $user  = requireRole('seeker');
    $jobId = (int)($_GET['job_id'] ?? 0);
    if (!$jobId) apiError('Job ID required.');

    $resume = db()->row("SELECT * FROM resumes WHERE user_id=?", [$user['id']]);
    if (!$resume) {
        apiSuccess(['has_resume' => false, 'match_pct' => 0, 'gap_summary' => null, 'matched_skills' => [], 'missing_skills' => [], 'weekly_plan' => [], 'recommended_resources' => [], 'coach_suggestions' => []]);
    }

    $job = db()->row("SELECT j.*, e.company_name, e.industry, e.company_size FROM jobs j JOIN employers e ON j.employer_id=e.id WHERE j.id=?", [$jobId]);
    if (!$job) apiError('Job not found.', 404);

    $gapSummary = computeSkillGapAnalysis($resume['skills'] ?? '', $job['skills_required'] ?? '');
    $resumeTokens = skillTokens($resume['skills'] ?? '');
    $jobTokens = skillTokens($job['skills_required'] ?? '');
    $matchedSkills = [];
    foreach ($jobTokens as $token) {
        if (skillMatchesResumeToken($token, $resumeTokens)) {
            $matchedSkills[] = $token;
        }
    }

    $gapSkills = array_column($gapSummary['gaps'], 'skill');
    $fit = db()->row("SELECT aptitude_score, interview_score, skill_match_score, total_fit_score FROM job_fit_scores WHERE user_id=?", [$user['id']]);
    $aptitude = (float)($fit['aptitude_score'] ?? 0);
    $interview = (float)($fit['interview_score'] ?? 0);
    $skillScore = (float)($fit['skill_match_score'] ?? 0);
    $totalFit = (float)($fit['total_fit_score'] ?? 0);
    $apps = (int)db()->row("SELECT COUNT(*) as c FROM applications WHERE user_id=?", [$user['id']])['c'];

    $weeklyPlan = buildWeeklyRoadmap($totalFit, count($gapSkills), $aptitude, $interview, $apps);
    $recommendedResources = personalizedBookPicks($user['id'], $gapSkills, [], $interview < 55, 5);

    $coachSuggestions = [];
    if (empty($gapSkills)) {
        $coachSuggestions[] = ['title' => 'Nice match', 'text' => 'Your resume already covers the required skills. Apply confidently and keep refining your interview responses.'];
    } else {
        $coachSuggestions[] = ['title' => 'Focus on top gaps', 'text' => 'Start by adding concrete examples for the missing skills in your resume or cover letter.'];
        $coachSuggestions[] = ['title' => 'Practice job keywords', 'text' => 'Use the exact job skill names in your profile and application to improve discoverability.'];
        if ($interview < 60) {
            $coachSuggestions[] = ['title' => 'Interview practice helps', 'text' => 'Polish your answers for common questions and review AI feedback from mock interviews.'];
        }
    }

    apiSuccess([
        'has_resume' => true,
        'match_pct' => matchPercent($resume['skills'] ?? '', $job['skills_required'] ?? ''),
        'gap_summary' => $gapSummary,
        'matched_skills' => array_values(array_unique($matchedSkills)),
        'missing_skills' => $gapSkills,
        'weekly_plan' => $weeklyPlan,
        'recommended_resources' => $recommendedResources,
        'coach_suggestions' => $coachSuggestions,
    ]);
}

function searchJobs(array $d): void {
    $q    = '%'.clean($d['q']??'').'%';
    $loc  = '%'.clean($d['location']??'').'%';
    $type = $d['type'] ?? '';
    $lvl  = $d['level'] ?? '';
    $page = max(1,(int)($d['page']??1));
    $limit = 12;
    $offset = ($page-1)*$limit;

    $where  = "j.is_active=1 AND (j.title LIKE ? OR j.company LIKE ? OR j.skills_required LIKE ?)";
    $params = [$q,$q,$q];
    if ($loc !== '%%') { $where .= " AND j.location LIKE ?"; $params[] = $loc; }
    if ($type) { $where .= " AND j.job_type=?"; $params[] = $type; }
    if ($lvl)  { $where .= " AND j.experience_level=?"; $params[] = $lvl; }

    $total = db()->row("SELECT COUNT(*) as c FROM jobs j WHERE $where", $params)['c'];
    $jobs  = db()->rows("SELECT j.* FROM jobs j WHERE $where ORDER BY j.posted_at DESC LIMIT $limit OFFSET $offset", $params);

    $resumeSkills = '';
    if (isLoggedIn()) {
        $r = db()->row("SELECT skills FROM resumes WHERE user_id=?", [$_SESSION['user_id']]);
        $resumeSkills = $r['skills'] ?? '';
    }
    foreach ($jobs as &$j) {
        $j['match_pct'] = matchPercent($resumeSkills, $j['skills_required']??'');
    }
    apiSuccess(['jobs'=>$jobs,'total'=>(int)$total,'page'=>$page]);
}

function recommendedJobs(): void {
    $user = requireRole('seeker');
    $res  = db()->row("SELECT skills FROM resumes WHERE user_id=?", [$user['id']]);
    if (!$res || !$res['skills']) { apiSuccess(['jobs'=>[]]); return; }

    $jobs   = db()->rows("SELECT * FROM jobs WHERE is_active=1 ORDER BY posted_at DESC LIMIT 30");
    $scored = [];
    foreach ($jobs as $j) {
        $pct = matchPercent($res['skills'], $j['skills_required']??'');
        if ($pct >= 30) { $j['match_pct'] = $pct; $scored[] = $j; }
    }
    usort($scored, fn($a,$b) => $b['match_pct'] - $a['match_pct']);
    apiSuccess(['jobs'=>array_slice($scored,0,6)]);
}

function postJob(array $d): void {
    $user = requireRole('employer');
    $emp  = db()->row("SELECT * FROM employers WHERE user_id=?", [$user['id']]);
    if (!$emp || $emp['verification_status'] !== 'verified') {
        apiError('Company must be verified before posting jobs.', 403);
    }

    $title  = clean($d['title'] ?? '');
    $loc    = clean($d['location'] ?? '');
    $desc   = clean($d['description'] ?? '');
    $skills = clean($d['skills_required'] ?? '');
    if (!$title || !$loc || !$desc || !$skills) apiError('Required fields missing.');

    $jobId = (int)db()->insert(
        "INSERT INTO jobs (employer_id,user_id,title,company,location,job_type,salary_range,description,requirements,skills_required,experience_level,require_test,require_interview)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$emp['id'],$user['id'],$title,$emp['company_name'],$loc,
         $d['job_type']??'Full-time', clean($d['salary_range']??''),
         $desc, clean($d['requirements']??''), $skills,
         $d['experience_level']??'Entry',
         !empty($d['require_test'])?1:0, !empty($d['require_interview'])?1:0]
    );

    // Save custom interview questions
    if (!empty($d['interview_questions']) && is_array($d['interview_questions'])) {
        foreach ($d['interview_questions'] as $i => $q) {
            $q = trim($q);
            if ($q) {
                db()->insert("INSERT INTO job_interview_questions (job_id,question,order_num) VALUES (?,?,?)",
                    [$jobId, $q, $i+1]);
            }
        }
    }

    // Notify matched seekers
    $seekers = db()->rows("SELECT r.user_id, r.skills, u.email, u.name FROM resumes r JOIN users u ON r.user_id=u.id");
    $matched = [];
    foreach ($seekers as $s) {
        $pct = matchPercent($s['skills']??'', $skills);
        if ($pct >= 50) {
            notify($s['user_id'], "New job match: $title",
                "A new job at {$emp['company_name']} matches your profile ({$pct}% match).",
                'job_match', APP_URL."/frontend/pages/job-detail.html?id=$jobId");
            $matched[] = array_merge($s, ['title'=>$title,'company'=>$emp['company_name'],'location'=>$loc,'job_type'=>$d['job_type']??'Full-time','match_pct'=>$pct]);
        }
    }

    // Batch send recommendation emails
    $grouped = [];
    foreach ($matched as $m) { $grouped[$m['user_id']][] = $m; }
    foreach ($grouped as $uid => $jobs) {
        $user2 = db()->row("SELECT name,email FROM users WHERE id=?", [$uid]);
        if ($user2) emailJobRecommendation($user2['email'], $user2['name'], $jobs);
    }

    apiSuccess(['job_id'=>$jobId], 'Job posted successfully!');
}

function updateJob(array $d): void {
    $user  = requireRole('employer');
    $jobId = (int)($d['id']??0);
    $emp   = db()->row("SELECT id FROM employers WHERE user_id=?", [$user['id']]);
    $job   = db()->row("SELECT * FROM jobs WHERE id=? AND employer_id=?", [$jobId,$emp['id']]);
    if (!$job) apiError('Job not found.', 404);

    $allowed = ['title','location','job_type','salary_range','description','requirements','skills_required','experience_level'];
    $sets=[]; $params=[];
    foreach ($allowed as $f) {
        if (isset($d[$f])) { $sets[] = "$f=?"; $params[] = clean($d[$f]); }
    }
    if (isset($d['require_test']))      { $sets[] = "require_test=?";      $params[] = (int)$d['require_test']; }
    if (isset($d['require_interview'])) { $sets[] = "require_interview=?"; $params[] = (int)$d['require_interview']; }
    if (!$sets) apiError('Nothing to update.');
    $params[] = $jobId;
    db()->exec("UPDATE jobs SET ".implode(',',$sets)." WHERE id=?", $params);

    // Update interview questions if provided
    if (!empty($d['interview_questions'])) {
        db()->exec("DELETE FROM job_interview_questions WHERE job_id=?", [$jobId]);
        foreach ($d['interview_questions'] as $i => $q) {
            $q = trim($q);
            if ($q) db()->insert("INSERT INTO job_interview_questions (job_id,question,order_num) VALUES (?,?,?)", [$jobId,$q,$i+1]);
        }
    }
    apiSuccess(null, 'Job updated.');
}

function deleteJob(): void {
    $user  = requireRole('employer');
    $jobId = (int)($_GET['id']??0);
    $emp   = db()->row("SELECT id FROM employers WHERE user_id=?", [$user['id']]);
    $deleted = db()->exec("DELETE FROM jobs WHERE id=? AND employer_id=?", [$jobId,$emp['id']]);
    if (!$deleted) apiError('Job not found.');
    apiSuccess(null, 'Job deleted.');
}

function toggleJob(): void {
    $user  = requireRole('employer');
    $jobId = (int)($_GET['id']??0);
    $emp   = db()->row("SELECT id FROM employers WHERE user_id=?", [$user['id']]);
    db()->exec("UPDATE jobs SET is_active=!is_active WHERE id=? AND employer_id=?", [$jobId,$emp['id']]);
    apiSuccess(null, 'Status toggled.');
}

function myJobs(): void {
    $user = requireRole('employer');
    $emp  = db()->row("SELECT id FROM employers WHERE user_id=?", [$user['id']]);
    if (!$emp) { apiSuccess(['jobs',[]]); return; }
    $jobs = db()->rows(
        "SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id=j.id) as app_count
         FROM jobs j WHERE j.employer_id=? ORDER BY j.posted_at DESC", [$emp['id']]);
    apiSuccess(['jobs'=>$jobs]);
}

function applyJob(array $d): void {
    $user  = requireRole('seeker');
    $jobId = (int)($d['job_id']??0);
    if (!$jobId) apiError('Job ID required.');

    $resume = db()->row("SELECT * FROM resumes WHERE user_id=?", [$user['id']]);
    if (!$resume) apiError('Create or upload your resume before applying.', 403);

    if (db()->row("SELECT id FROM applications WHERE job_id=? AND user_id=?", [$jobId,$user['id']])) {
        apiError('Already applied.', 409);
    }

    $job = db()->row("SELECT j.*, e.user_id as emp_user_id FROM jobs j JOIN employers e ON j.employer_id=e.id WHERE j.id=? AND j.is_active=1", [$jobId]);
    if (!$job) apiError('Job not found.', 404);

    $matchPct    = matchPercent($resume['skills']??'', $job['skills_required']??'');
    $fit         = db()->row("SELECT total_fit_score FROM job_fit_scores WHERE user_id=?", [$user['id']]);
    $readiness   = $fit ? round((float)$fit['total_fit_score'], 0) : 0;

    db()->insert(
        "INSERT INTO applications (job_id,user_id,employer_id,cover_letter,match_percentage,readiness_score) VALUES (?,?,?,?,?,?)",
        [$jobId,$user['id'],$job['employer_id'], clean($d['cover_letter']??''), $matchPct, $readiness]
    );
    db()->exec("UPDATE jobs SET applications_count=applications_count+1 WHERE id=?", [$jobId]);

    // Notify seeker
    notify($user['id'], "Applied: {$job['title']}",
        "Your application to {$job['company']} has been received.", 'application_update',
        APP_URL.'/frontend/pages/applications.html');

    // Email employer
    $empUser = db()->row("SELECT name,email FROM users WHERE id=?", [$job['emp_user_id']]);
    if ($empUser) emailApplicationToEmployer($empUser['email'], $empUser['name'], $job, $user, $matchPct);

    apiSuccess(['match_pct'=>$matchPct], 'Application submitted!');
}

function myApplications(): void {
    $user = requireRole('seeker');
    $apps = db()->rows(
        "SELECT a.*, j.title as job_title, j.company, j.location, j.job_type, j.salary_range, j.skills_required
         FROM applications a JOIN jobs j ON a.job_id=j.id WHERE a.user_id=? ORDER BY a.applied_at DESC",
        [$user['id']]);
    apiSuccess(['applications'=>$apps]);
}

function updateAppStatus(array $d): void {
    $user  = requireRole('employer');
    $appId = (int)($d['app_id']??0);
    $status= $d['status']??'';
    $note  = clean($d['note']??'');
    $valid = ['Applied','Shortlisted','Interview','Rejected','Hired'];
    if (!in_array($status,$valid)) apiError('Invalid status.');

    $app = db()->row(
        "SELECT a.*, j.title as job_title, j.company, u.name as seeker_name, u.email as seeker_email
         FROM applications a JOIN jobs j ON a.job_id=j.id JOIN users u ON a.user_id=u.id WHERE a.id=?", [$appId]);
    if (!$app) apiError('Application not found.', 404);

    db()->exec("UPDATE applications SET status=?,employer_note=? WHERE id=?", [$status,$note,$appId]);

    // Notify seeker
    notify($app['user_id'], "Application Update: {$app['job_title']}",
        "Your application status changed to: $status", 'application_update',
        APP_URL.'/frontend/pages/applications.html');

    // Email seeker
    emailStatusToSeeker($app['seeker_email'], $app['seeker_name'],
        ['title'=>$app['job_title'],'company'=>$app['company']], $status, $note);

    apiSuccess(null, "Status updated to $status.");
}

function getApplicants(): void {
    $user = requireRole('employer');
    $emp  = db()->row("SELECT id FROM employers WHERE user_id=?", [$user['id']]);
    $apps = db()->rows(
        "SELECT a.*, u.name, u.email, u.phone, j.title as job_title, r.skills as resume_skills, r.uploaded_file, r.resume_type
         FROM applications a
         JOIN jobs j ON a.job_id=j.id
         JOIN users u ON a.user_id=u.id
         LEFT JOIN resumes r ON r.user_id=a.user_id
         WHERE j.employer_id=? ORDER BY a.applied_at DESC",
        [$emp['id']]);
    apiSuccess(['applications'=>$apps]);
}

function getJobQuestions(): void {
    $jobId = (int)($_GET['job_id']??0);
    if (!$jobId) apiError('Job ID required.');
    $questions = db()->rows("SELECT question FROM job_interview_questions WHERE job_id=? ORDER BY order_num", [$jobId]);
    $qs = array_column($questions, 'question');
    apiSuccess(['questions'=>$qs, 'has_custom'=>count($qs)>0]);
}

function sendRecs(): void {
    requireAdmin();
    $seekers = db()->rows(
        "SELECT u.id, u.name, u.email, r.skills FROM users u
         LEFT JOIN resumes r ON r.user_id=u.id WHERE u.role='seeker' AND u.is_active=1"
    );
    $jobs = db()->rows("SELECT * FROM jobs WHERE is_active=1 ORDER BY posted_at DESC LIMIT 10");
    $sent = 0;
    foreach ($seekers as $s) {
        if (!$s['skills']) continue;
        $matched = [];
        foreach ($jobs as $j) {
            $pct = matchPercent($s['skills'], $j['skills_required']??'');
            if ($pct >= 40) { $j['match_pct'] = $pct; $matched[] = $j; }
        }
        if (!empty($matched)) {
            emailJobRecommendation($s['email'], $s['name'], $matched);
            $sent++;
        }
    }
    apiSuccess(['sent'=>$sent], "Recommendation emails sent to $sent seekers.");
}
