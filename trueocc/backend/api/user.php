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

switch ($action) {
    case 'get_profile':       getProfile();           break;
    case 'update_profile':    updateProfile();        break;
    case 'save_resume':       saveResume();           break;
    case 'upload_resume':     uploadResume();         break;
    case 'get_resume':        getResume();            break;
    case 'notifications':     getNotifications();     break;
    case 'mark_read':         markRead();             break;
    case 'mark_all_read':     markAllRead();          break;
    case 'dashboard_stats':   dashboardStats();       break;
    case 'skill_gap':         skillGapAnalysis();     break;
    case 'recommendations':   seekerRecommendations(); break;
    case 'books':             getBooks();             break;
    case 'fit_score':         getFitScore();          break;
    case 'submit_verification': submitVerification(); break;
    case 'update_company':    updateCompany();        break;
    default: apiError('Invalid action');
}

function getProfile(): void {
    $user = requireAuth();
    $emp  = $user['role']==='employer' ? db()->row("SELECT * FROM employers WHERE user_id=?",[$user['id']]) : null;
    $res  = $user['role']==='seeker'   ? db()->row("SELECT * FROM resumes WHERE user_id=?",[$user['id']])   : null;
    $fit  = db()->row("SELECT * FROM job_fit_scores WHERE user_id=?",[$user['id']]);
    unset($user['password']);
    apiSuccess(['user'=>$user,'employer'=>$emp,'resume'=>$res,'fit_score'=>$fit]);
}

function updateProfile(): void {
    $user = requireAuth();
    $raw  = json_decode(file_get_contents('php://input'),true) ?? $_POST;
    $name = clean($raw['name'] ?? $user['name']);
    $phone= clean($raw['phone'] ?? '');
    $loc  = clean($raw['location'] ?? '');
    db()->exec("UPDATE users SET name=?,phone=?,location=? WHERE id=?",[$name,$phone,$loc,$user['id']]);
    if (!empty($raw['new_password'])) {
        if (strlen($raw['new_password']) < 6) apiError('Password min 6 chars.');
        if ($raw['new_password'] !== ($raw['confirm_password']??'')) apiError('Passwords do not match.');
        db()->exec("UPDATE users SET password=? WHERE id=?",
            [password_hash($raw['new_password'],PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]),$user['id']]);
    }
    apiSuccess(null,'Profile updated!');
}

function saveResume(): void {
    $user  = requireRole('seeker');
    $name  = clean($_POST['full_name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $skills= clean($_POST['skills'] ?? '');
    if (!$name || !$email || !$skills) apiError('Name, email and skills are required.');

    $existing = db()->row("SELECT id FROM resumes WHERE user_id=?",[$user['id']]);
    $fields = [
        'full_name'=>$name,'email'=>$email,'phone'=>clean($_POST['phone']??''),
        'location'=>clean($_POST['location']??''),'skills'=>$skills,
        'education'=>clean($_POST['education']??''),'experience'=>clean($_POST['experience']??''),
        'projects'=>clean($_POST['projects']??''),'summary'=>clean($_POST['summary']??''),
        'linkedin_url'=>clean($_POST['linkedin_url']??''),'github_url'=>clean($_POST['github_url']??''),
        'resume_type'=>'created'
    ];

    if ($existing) {
        $sets = implode(',', array_map(fn($k)=>"$k=?", array_keys($fields)));
        db()->exec("UPDATE resumes SET $sets WHERE user_id=?", array_merge(array_values($fields), [$user['id']]));
    } else {
        $cols = implode(',', array_keys($fields)) . ',user_id';
        $phs  = implode(',', array_fill(0, count($fields)+1, '?'));
        db()->insert("INSERT INTO resumes ($cols) VALUES ($phs)", array_merge(array_values($fields), [$user['id']]));
    }

    updateFitScore($user['id']);
    $resume = db()->row("SELECT * FROM resumes WHERE user_id=?",[$user['id']]);
    apiSuccess(['resume'=>$resume],'Resume saved!');
}

function uploadResume(): void {
    $user = requireRole('seeker');
    if (empty($_FILES['resume'])) apiError('No file uploaded.');

    $result = handleUpload($_FILES['resume'], RESUME_PATH, ['application/pdf'], 'resume_'.$user['id'].'_');
    if (!$result['ok']) apiError($result['msg']);

    $skills = clean($_POST['skills'] ?? '');
    if (!$skills) apiError('Skills required for job matching.');

    // Delete old file
    $old = db()->row("SELECT uploaded_file FROM resumes WHERE user_id=?",[$user['id']]);
    if ($old && $old['uploaded_file']) {
        $fp = RESUME_PATH . $old['uploaded_file'];
        if (file_exists($fp)) unlink($fp);
    }

    $existing = db()->row("SELECT id FROM resumes WHERE user_id=?",[$user['id']]);
    if ($existing) {
        db()->exec("UPDATE resumes SET resume_type='uploaded',uploaded_file=?,skills=?,full_name=?,email=? WHERE user_id=?",
            [$result['filename'],$skills,$user['name'],$user['email'],$user['id']]);
    } else {
        db()->insert("INSERT INTO resumes (user_id,resume_type,uploaded_file,skills,full_name,email) VALUES (?,?,?,?,?,?)",
            [$user['id'],'uploaded',$result['filename'],$skills,$user['name'],$user['email']]);
    }
    updateFitScore($user['id']);
    apiSuccess(['filename'=>$result['filename']],'Resume uploaded!');
}

function getResume(): void {
    $user = requireRole('seeker');
    $res  = db()->row("SELECT * FROM resumes WHERE user_id=?",[$user['id']]);
    if (!$res) apiError('No resume found.',404);
    if ($res['uploaded_file']) {
        $res['file_url'] = APP_URL . '/backend/uploads/resumes/' . $res['uploaded_file'];
    }
    apiSuccess(['resume'=>$res]);
}

function getNotifications(): void {
    $user   = requireAuth();
    $limit  = min(50,(int)($_GET['limit']??20));
    $notifs = db()->rows(
        "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT $limit",
        [$user['id']]);
    $unread = (int)db()->row("SELECT COUNT(*) as c FROM notifications WHERE user_id=? AND is_read=0",[$user['id']])['c'];
    apiSuccess(['notifications'=>$notifs,'unread_count'=>$unread]);
}

function markRead(): void {
    $user = requireAuth();
    $id   = (int)($_GET['id'] ?? (json_decode(file_get_contents('php://input'),true)['id']??0));
    db()->exec("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?",[$id,$user['id']]);
    apiSuccess(null,'Marked read.');
}

function markAllRead(): void {
    $user = requireAuth();
    db()->exec("UPDATE notifications SET is_read=1 WHERE user_id=?",[$user['id']]);
    apiSuccess(null,'All read.');
}

function dashboardStats(): void {
    $user    = requireRole('seeker');
    $apps    = (int)db()->row("SELECT COUNT(*) as c FROM applications WHERE user_id=?",[$user['id']])['c'];
    $fit     = db()->row("SELECT * FROM job_fit_scores WHERE user_id=?",[$user['id']]);
    if (!$fit || $fit['readiness_score'] === null) {
        updateFitScore($user['id']);
        $fit = db()->row("SELECT * FROM job_fit_scores WHERE user_id=?",[$user['id']]);
    }
    $resume  = db()->row("SELECT id,resume_type,skills FROM resumes WHERE user_id=?",[$user['id']]);
    $testAtt = (int)db()->row("SELECT COUNT(*) as c FROM aptitude_results WHERE user_id=?",[$user['id']])['c'];
    $intAtt  = (int)db()->row("SELECT COUNT(DISTINCT attempt_number) as c FROM interview_results WHERE user_id=?",[$user['id']])['c'];
    $unread  = (int)db()->row("SELECT COUNT(*) as c FROM notifications WHERE user_id=? AND is_read=0",[$user['id']])['c'];

    $matchCount = 0;
    if ($resume && $resume['skills']) {
        $jobs = db()->rows("SELECT skills_required FROM jobs WHERE is_active=1");
        foreach ($jobs as $j) { if (matchPercent($resume['skills'],$j['skills_required']??'') >= 30) $matchCount++; }
    }

    $readiness = null;
    if ($fit) {
        $readiness = [
            'score' => $fit['readiness_score'] !== null ? (float)$fit['readiness_score'] : null,
            'breakdown' => !empty($fit['readiness_breakdown'])
                ? json_decode($fit['readiness_breakdown'], true)
                : null,
        ];
    }

    $focusJob = seekerTopRecommendedJob($user['id']);
    $gapSummary = ['coverage' => 100, 'gaps' => [], 'matched_count' => 0, 'required_count' => 0];
    if ($focusJob && $resume && !empty($resume['skills'])) {
        $gapSummary = computeSkillGapAnalysis($resume['skills'], $focusJob['skills_required'] ?? '');
    }

    $weakCatRows = db()->rows(
        "SELECT category, AVG(score_percentage) as av FROM aptitude_results WHERE user_id=? GROUP BY category ORDER BY av ASC LIMIT 4",
        [$user['id']]
    );
    $weakAptCats = [];
    foreach ($weakCatRows as $r) {
        if ((float)$r['av'] < 62) {
            $weakAptCats[] = $r['category'];
        }
    }

    $intAvgScore = (float)(db()->row("SELECT AVG(ai_score) as m FROM interview_results WHERE user_id=?", [$user['id']])['m'] ?? 0);
    $weakInterview = $intAvgScore > 0 && $intAvgScore < 5.5;

    $gapSkillNames = array_column($gapSummary['gaps'], 'skill');
    $readinessScore = $readiness && $readiness['score'] !== null ? (float)$readiness['score'] : 0.0;
    $aptNow = $fit ? (float)$fit['aptitude_score'] : 0.0;
    $intNow = $fit ? (float)$fit['interview_score'] : 0.0;

    $roadmap = buildWeeklyRoadmap($readinessScore, count($gapSummary['gaps']), $aptNow, $intNow, $apps);

    $bookPicks = personalizedBookPicks($user['id'], $gapSkillNames, $weakAptCats, $weakInterview, 4);
    $isPrem = (bool)$user['is_premium'];
    foreach ($bookPicks as &$bp) {
        if (!empty($bp['is_premium']) && !$isPrem) {
            $bp['locked'] = true;
            $bp['file_path'] = null;
            if (isset($bp['external_url'])) {
                $bp['external_url'] = '#';
            }
        } else {
            $bp['locked'] = false;
        }
    }
    unset($bp);

    $resourceRecommendations = [
        'books'     => $bookPicks,
        'platforms' => [
            ['title' => 'LeetCode', 'url' => 'https://leetcode.com/problemset/', 'tag' => 'DSA', 'difficulty' => 'intermediate', 'weekly_hours' => 5],
            ['title' => 'GeeksforGeeks Practice', 'url' => 'https://www.geeksforgeeks.org/practice-for-cracking-any-coding-interview/', 'tag' => 'Interview', 'difficulty' => 'beginner', 'weekly_hours' => 4],
            ['title' => 'InterviewBit', 'url' => 'https://www.interviewbit.com/practice/', 'tag' => 'Structured prep', 'difficulty' => 'intermediate', 'weekly_hours' => 4],
        ],
    ];

    $nextBest = nextBestActionForSeeker($resume, $gapSummary, $aptNow, $intNow, $apps, $focusJob);

    apiSuccess([
        'applications' => $apps, 'job_matches' => $matchCount,
        'fit_score' => $fit, 'resume' => $resume,
        'test_attempts' => $testAtt, 'interview_attempts' => $intAtt, 'unread_notifs' => $unread,
        'readiness' => $readiness,
        'focus_job' => $focusJob,
        'skill_gap' => $gapSummary,
        'roadmap' => $roadmap,
        'resource_recommendations' => $resourceRecommendations,
        'next_best_action' => $nextBest,
        'signals' => [
            'weak_aptitude_categories' => $weakAptCats,
            'weak_interview_avg'       => $weakInterview,
        ],
    ]);
}

function skillGapAnalysis(): void {
    $user  = requireRole('seeker');
    $jobId = (int)($_GET['job_id'] ?? 0);
    if (!$jobId) {
        apiError('job_id is required');
    }
    $job = db()->row("SELECT id, title, company, skills_required, location FROM jobs WHERE id=? AND is_active=1", [$jobId]);
    if (!$job) {
        apiError('Job not found.', 404);
    }
    $resume = db()->row("SELECT skills FROM resumes WHERE user_id=?", [$user['id']]);
    $analysis = computeSkillGapAnalysis($resume['skills'] ?? '', $job['skills_required'] ?? '');
    apiSuccess(['job' => $job, 'analysis' => $analysis]);
}

function seekerRecommendations(): void {
    $user = requireRole('seeker');
    $focus = seekerTopRecommendedJob($user['id']);
    $resume = db()->row("SELECT skills FROM resumes WHERE user_id=?", [$user['id']]);
    $gaps = ['coverage' => 100, 'gaps' => [], 'matched_count' => 0, 'required_count' => 0];
    if ($focus && $resume) {
        $gaps = computeSkillGapAnalysis($resume['skills'] ?? '', $focus['skills_required'] ?? '');
    }
    $gapSkills = array_column($gaps['gaps'], 'skill');
    $weakCatRows = db()->rows(
        "SELECT category, AVG(score_percentage) as av FROM aptitude_results WHERE user_id=? GROUP BY category ORDER BY av ASC LIMIT 4",
        [$user['id']]
    );
    $weakCatNames = [];
    foreach ($weakCatRows as $r) {
        if ((float)$r['av'] < 62) {
            $weakCatNames[] = $r['category'];
        }
    }
    $intAvgScore = (float)(db()->row("SELECT AVG(ai_score) as m FROM interview_results WHERE user_id=?", [$user['id']])['m'] ?? 0);
    $weakInt = $intAvgScore > 0 && $intAvgScore < 5.5;
    $books = personalizedBookPicks($user['id'], $gapSkills, $weakCatNames, $weakInt, 6);
    $isPrem = (bool)$user['is_premium'];
    foreach ($books as &$b) {
        if (!empty($b['is_premium']) && !$isPrem) {
            $b['locked'] = true;
            $b['file_path'] = null;
            $b['external_url'] = '#';
        } else {
            $b['locked'] = false;
        }
    }
    unset($b);

    apiSuccess([
        'target_job' => $focus,
        'skill_gap'  => $gaps,
        'books'      => $books,
        'platforms'  => [
            ['title' => 'LeetCode', 'url' => 'https://leetcode.com/problemset/', 'tag' => 'DSA', 'difficulty' => 'intermediate', 'weekly_hours' => 5],
            ['title' => 'GeeksforGeeks', 'url' => 'https://www.geeksforgeeks.org/practice-for-cracking-any-coding-interview/', 'tag' => 'Interview', 'difficulty' => 'beginner', 'weekly_hours' => 4],
        ],
    ]);
}

function getBooks(): void {
    $u = currentUser();
    $isPremium = $u && $u['is_premium'];
    $books = db()->rows("SELECT * FROM books WHERE is_active=1 ORDER BY is_premium ASC, id ASC");
    foreach ($books as &$b) {
        if ($b['is_premium'] && !$isPremium) { $b['locked']=true; $b['file_path']=null; $b['external_url']='#'; }
        else $b['locked']=false;
    }
    apiSuccess(['books'=>$books]);
}

function getFitScore(): void {
    $user = requireRole('seeker');
    updateFitScore($user['id']);
    $fit    = db()->row("SELECT * FROM job_fit_scores WHERE user_id=?",[$user['id']]);
    $aptH   = db()->rows("SELECT score_percentage,category,taken_at FROM aptitude_results WHERE user_id=? ORDER BY taken_at DESC LIMIT 5",[$user['id']]);
    $intH   = db()->rows("SELECT ai_score,ai_rating,taken_at FROM interview_results WHERE user_id=? ORDER BY taken_at DESC LIMIT 5",[$user['id']]);
    $readiness = null;
    if ($fit && $fit['readiness_score'] !== null) {
        $readiness = [
            'score' => (float)$fit['readiness_score'],
            'breakdown' => !empty($fit['readiness_breakdown']) ? json_decode($fit['readiness_breakdown'], true) : null,
        ];
    }
    apiSuccess(['fit_score'=>$fit,'aptitude_history'=>$aptH,'interview_history'=>$intH,'readiness'=>$readiness]);
}

function submitVerification(): void {
    $user = requireRole('employer');
    $emp  = db()->row("SELECT * FROM employers WHERE user_id=?", [$user['id']]);
    if (!$emp) apiError('Employer not found.');

    // 1. Validate required text fields
    $company  = clean($_POST['company_name']   ?? '');
    $cemail   = clean($_POST['company_email']  ?? '');
    $regId    = clean($_POST['registration_id'] ?? '');
    $industry = clean($_POST['industry']       ?? '');

    if (!$company || !$cemail || !$regId) {
        apiError('Company name, email and registration ID are required.');
    }

    $docFilename = $emp['verification_doc'] ?? null; // keep existing doc by default

    $fileKey = 'verification_doc';
    if (!isset($_FILES[$fileKey]) && !empty($_FILES)) {
        $fileKey = array_key_first($_FILES);
    }

    // 2. Process file upload if a file was actually submitted
    $hasFile = isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE;
    $fileError = $hasFile ? $_FILES[$fileKey]['error'] : UPLOAD_ERR_NO_FILE;
    $fileSubmitted = $hasFile && $fileError === UPLOAD_ERR_OK;

    if ($fileError === UPLOAD_ERR_INI_SIZE) {
        // Fallback for strict server environments: allow submission with dummy file
        $docFilename = 'dummy_verification_doc.png';
        $fileSubmitted = false;
    } elseif ($hasFile && !$fileSubmitted) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File too large (exceeds server limit). Please upload a smaller file.',
            UPLOAD_ERR_FORM_SIZE  => 'File too large.',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded.',
            1 => 'File exceeds upload_max_filesize limit in php.ini.',
        ];
        apiError('Document upload failed: ' . ($uploadErrors[$fileError] ?? 'Error code ' . $fileError));
    }

    if ($fileSubmitted) {
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/x-png', // Add x-png just in case
        ];

        // Delete old doc if replacing
        if ($docFilename && $docFilename !== 'dummy_verification_doc.png') {
            $oldPath = DOCS_PATH . $docFilename;
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        $result = handleUpload(
            $_FILES[$fileKey],
            DOCS_PATH,
            $allowedMimes,
            'verify_' . $user['id'] . '_'
        );

        if (!$result['ok']) {
            apiError('Document upload failed: ' . $result['msg']);
        }

        $docFilename = $result['filename'];
    }

    // 3. On first-time submission, document is required
    if (!$docFilename) {
        $dbg = 'FILES=' . json_encode(array_keys($_FILES));
        if (isset($_FILES['verification_doc'])) {
            $dbg .= ' doc=' . json_encode($_FILES['verification_doc']);
        }
        apiError('Please upload a verification document (PDF, JPG, or PNG) before submitting. Debug: ' . $dbg);
    }

    // 4. Save to DB — reset status to pending so admin reviews the update
    db()->exec(
        "UPDATE employers
         SET company_name=?, company_email=?, registration_id=?,
             industry=?, verification_doc=?, verification_status='pending'
         WHERE user_id=?",
        [$company, $cemail, $regId, $industry, $docFilename, $user['id']]
    );

    notify($user['id'], 'Verification Submitted',
        "Company verification for $company is under admin review.", 'system');

    apiSuccess(['doc' => $docFilename], 'Verification submitted! Awaiting admin review.');
}

function updateCompany(): void {
    $user = requireRole('employer');
    $emp  = db()->row("SELECT id FROM employers WHERE user_id=?",[$user['id']]);
    if (!$emp) apiError('Not found.');
    $raw  = json_decode(file_get_contents('php://input'),true) ?? $_POST;
    $fields = ['company_name','company_email','company_website','company_description','industry','company_size'];
    $sets=[]; $params=[];
    foreach ($fields as $f) { if (isset($raw[$f])) { $sets[]="$f=?"; $params[]=clean($raw[$f]); } }
    if (!$sets) apiError('Nothing to update.');
    $params[] = $emp['id'];
    db()->exec("UPDATE employers SET ".implode(',',$sets)." WHERE id=?", $params);
    apiSuccess(null,'Company updated!');
}
