<?php
require_once __DIR__ . '/config.php';

// ============================================================
// DATABASE CLASS
// ============================================================
class DB {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }

    public static function get(): DB {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function query(string $sql, array $p = []): PDOStatement {
        $s = $this->pdo->prepare($sql);
        $s->execute($p);
        return $s;
    }

    public function row(string $sql, array $p = []): ?array {
        return $this->query($sql, $p)->fetch() ?: null;
    }

    public function rows(string $sql, array $p = []): array {
        return $this->query($sql, $p)->fetchAll();
    }

    public function insert(string $sql, array $p = []): string {
        $this->query($sql, $p);
        return $this->pdo->lastInsertId();
    }

    public function exec(string $sql, array $p = []): int {
        return $this->query($sql, $p)->rowCount();
    }

    public function pdo(): PDO { return $this->pdo; }
}

function db(): DB { return DB::get(); }

// ============================================================
// SESSION
// ============================================================
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

startSession();

// ============================================================
// AUTH HELPERS
// ============================================================
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $u = null;
    if ($u === null) {
        $u = db()->row("SELECT * FROM users WHERE id=? AND is_active=1", [$_SESSION['user_id']]);
    }
    return $u;
}

function currentAdmin(): ?array {
    if (!isAdminLoggedIn()) return null;
    return db()->row("SELECT * FROM admins WHERE id=?", [$_SESSION['admin_id']]);
}

function requireRole(string $role): array {
    $u = currentUser();
    if (!$u || $u['role'] !== $role) apiError('Unauthorized', 401);
    return $u;
}

function requireAuth(): array {
    $u = currentUser();
    if (!$u) apiError('Not authenticated', 401);
    return $u;
}

function requireAdmin(): array {
    $a = currentAdmin();
    if (!$a) apiError('Admin access required', 401);
    return $a;
}

function loginUser(int $id, string $role): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $id;
    $_SESSION['user_role'] = $role;
}

function loginAdmin(int $id): void {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $id;
}

function logoutUser(): void {
    session_destroy();
}

// ============================================================
// RESPONSE HELPERS
// ============================================================
function apiSuccess($data = null, string $msg = 'Success', int $code = 200): void {
    ob_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiError(string $msg, int $code = 400, array $errors = []): void {
    ob_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $msg, 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================
// SECURITY
// ============================================================
function clean(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}

function genToken(int $len = 32): string {
    return bin2hex(random_bytes($len));
}

// ============================================================
// FILE UPLOAD
// ============================================================
function handleUpload(array $file, string $destPath, array $allowedMimes, string $prefix = ''): array {
    // Translate PHP upload error codes to readable messages
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File too large (exceeds server php.ini upload_max_filesize). Max allowed: ' . ini_get('upload_max_filesize') . '.',
        UPLOAD_ERR_FORM_SIZE  => 'File too large (exceeds form MAX_FILE_SIZE).',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded. Try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder. Contact administrator.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk. Check folder permissions.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = $uploadErrors[$file['error']] ?? 'Unknown upload error (code ' . $file['error'] . ').';
        return ['ok' => false, 'msg' => $msg];
    }
    if ($file['size'] === 0) {
        return ['ok' => false, 'msg' => 'Uploaded file is empty.'];
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'msg' => 'File too large. Max ' . MAX_UPLOAD_MB . 'MB allowed.'];
    }

    // MIME detection with fallback to extension-based check
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extMimeMap = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        // Some servers return application/octet-stream for PDFs — fallback to extension
        if ($mime === 'application/octet-stream' && isset($extMimeMap[$ext])) {
            $mime = $extMimeMap[$ext];
        }
    } else {
        // finfo not available — use extension
        $mime = $extMimeMap[$ext] ?? 'application/octet-stream';
    }

    if (!in_array($mime, $allowedMimes)) {
        return ['ok' => false, 'msg' => 'Invalid file type "' . $mime . '". Allowed: ' . implode(', ', $allowedMimes)];
    }

    // Create destination directory if missing
    if (!is_dir($destPath)) {
        if (!mkdir($destPath, 0755, true)) {
            return ['ok' => false, 'msg' => 'Cannot create upload directory: ' . $destPath];
        }
    }
    if (!is_writable($destPath)) {
        return ['ok' => false, 'msg' => 'Upload directory is not writable: ' . $destPath];
    }

    $name = $prefix . uniqid('', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $destPath . $name)) {
        return ['ok' => false, 'msg' => 'Failed to move uploaded file. Check folder write permissions.'];
    }
    return ['ok' => true, 'filename' => $name];
}

// ============================================================
// NOTIFICATIONS
// ============================================================
function notify(int $userId, string $title, string $msg, string $type = 'system', string $url = ''): void {
    db()->insert(
        "INSERT INTO notifications (user_id, title, message, type, action_url) VALUES (?,?,?,?,?)",
        [$userId, $title, $msg, $type, $url]
    );
}

// ============================================================
// CAREER READINESS (0–100, multi-signal)
// ============================================================
function computeReadinessScore(int $userId): array {
    $fit = db()->row("SELECT aptitude_score, interview_score, skill_match_score FROM job_fit_scores WHERE user_id=?", [$userId]);
    $apt = round((float)($fit['aptitude_score'] ?? 0), 2);
    $int = round((float)($fit['interview_score'] ?? 0), 2);
    $sk  = round((float)($fit['skill_match_score'] ?? 0), 2);

    $resume = db()->row(
        "SELECT full_name,email,skills,education,experience,summary,projects,phone,location FROM resumes WHERE user_id=?",
        [$userId]
    );
    $resumeKeys = ['full_name', 'email', 'skills', 'education', 'experience', 'summary', 'projects', 'phone', 'location'];
    $rFilled = 0;
    if ($resume) {
        foreach ($resumeKeys as $k) {
            if (trim((string)($resume[$k] ?? '')) !== '') {
                $rFilled++;
            }
        }
    }
    $resumePct = $resume ? (int)round(100 * $rFilled / count($resumeKeys)) : 0;

    $user = db()->row("SELECT name, phone, location FROM users WHERE id=?", [$userId]);
    $prof = 0;
    if ($user) {
        if (trim((string)($user['name'] ?? '')) !== '') {
            $prof += 40;
        }
        if (trim((string)($user['phone'] ?? '')) !== '') {
            $prof += 30;
        }
        if (trim((string)($user['location'] ?? '')) !== '') {
            $prof += 30;
        }
    }

    $recentApps = (int)(db()->row(
        "SELECT COUNT(*) as c FROM applications WHERE user_id=? AND applied_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)",
        [$userId]
    )['c'] ?? 0);
    $recentTests = (int)(db()->row(
        "SELECT COUNT(*) as c FROM aptitude_results WHERE user_id=? AND taken_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)",
        [$userId]
    )['c'] ?? 0);
    $recentInt = (int)(db()->row(
        "SELECT COUNT(*) as c FROM interview_results WHERE user_id=? AND taken_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)",
        [$userId]
    )['c'] ?? 0);
    $actPts = (int)min(100, $recentApps * 20 + $recentTests * 25 + ($recentInt > 0 ? 25 : 0) + min(30, $recentInt * 5));

    $w = ['resume' => 0.20, 'aptitude' => 0.20, 'interview' => 0.20, 'skills' => 0.15, 'profile' => 0.10, 'activity' => 0.15];
    $score = round(
        $resumePct * $w['resume']
        + $apt * $w['aptitude']
        + $int * $w['interview']
        + $sk * $w['skills']
        + $prof * $w['profile']
        + $actPts * $w['activity'],
        2
    );
    $score = min(100, max(0, $score));

    $components = [
        ['key' => 'resume', 'label' => 'Resume depth', 'value' => $resumePct, 'weight_pct' => 20],
        ['key' => 'aptitude', 'label' => 'Aptitude', 'value' => $apt, 'weight_pct' => 20],
        ['key' => 'interview', 'label' => 'Interview', 'value' => $int, 'weight_pct' => 20],
        ['key' => 'skill_match', 'label' => 'Skill match', 'value' => $sk, 'weight_pct' => 15],
        ['key' => 'profile', 'label' => 'Profile', 'value' => $prof, 'weight_pct' => 10],
        ['key' => 'activity', 'label' => 'Activity (28d)', 'value' => $actPts, 'weight_pct' => 15],
    ];

    $suggestions = [];
    if ($resumePct < 65) {
        $suggestions[] = 'Complete resume sections (education, projects, summary) to raise the resume component.';
    }
    if ($apt < 55) {
        $suggestions[] = 'Retake mixed aptitude tests to improve numerical, logical, and verbal scores.';
    }
    if ($int < 55) {
        $suggestions[] = 'Run more mock interviews with structured STAR answers to lift the interview score.';
    }
    if ($sk < 50) {
        $suggestions[] = 'Align skills on your resume with target roles and job descriptions.';
    }
    if ($prof < 70) {
        $suggestions[] = 'Add phone and location on your profile for a stronger profile signal.';
    }
    if ($actPts < 40) {
        $suggestions[] = 'Build a weekly habit: one application, one short test, or one mock interview.';
    }
    if (!$suggestions) {
        $suggestions[] = 'Solid progress — keep iterating on your weakest category below.';
    }

    return [
        'score' => $score,
        'components' => $components,
        'suggestions' => array_slice($suggestions, 0, 5),
    ];
}

// ============================================================
// FIT SCORE CALCULATOR
// ============================================================
function updateFitScore(int $userId): array {
    // Aptitude average
    $apt = db()->row("SELECT AVG(score_percentage) as s FROM aptitude_results WHERE user_id=?", [$userId]);
    $aptScore = round((float)($apt['s'] ?? 0), 2);

    // Interview average (ai_score is 1-10, convert to %)
    $int = db()->row("SELECT AVG(ai_score * 10) as s FROM interview_results WHERE user_id=?", [$userId]);
    $intScore = round((float)($int['s'] ?? 0), 2);

    // Skill match (average across active jobs)
    $resume = db()->row("SELECT skills FROM resumes WHERE user_id=?", [$userId]);
    $skillScore = 0;
    if ($resume && !empty($resume['skills'])) {
        $userSkills = array_map('strtolower', array_map('trim', explode(',', $resume['skills'])));
        $jobs = db()->rows("SELECT skills_required FROM jobs WHERE is_active=1 LIMIT 20");
        if (!empty($jobs)) {
            $totalMatch = 0;
            foreach ($jobs as $j) {
                $jobSkills = array_map('strtolower', array_map('trim', explode(',', $j['skills_required'] ?? '')));
                if (!$jobSkills) {
                    continue;
                }
                $matches = count(array_intersect($userSkills, $jobSkills));
                $totalMatch += ($matches / count($jobSkills)) * 100;
            }
            $skillScore = round($totalMatch / count($jobs), 2);
        }
    }

    $total = round($aptScore * 0.4 + $intScore * 0.4 + $skillScore * 0.2, 2);

    db()->exec(
        "INSERT INTO job_fit_scores (user_id, aptitude_score, interview_score, skill_match_score, total_fit_score)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE aptitude_score=VALUES(aptitude_score),
         interview_score=VALUES(interview_score),
         skill_match_score=VALUES(skill_match_score),
         total_fit_score=VALUES(total_fit_score)",
        [$userId, $aptScore, $intScore, $skillScore, $total]
    );

    $readiness = computeReadinessScore($userId);
    db()->exec(
        "UPDATE job_fit_scores SET readiness_score=?, readiness_breakdown=? WHERE user_id=?",
        [
            $readiness['score'],
            json_encode(
                ['components' => $readiness['components'], 'suggestions' => $readiness['suggestions']],
                JSON_UNESCAPED_UNICODE
            ),
            $userId,
        ]
    );

    return array_merge(compact('aptScore', 'intScore', 'skillScore', 'total'), ['readiness' => $readiness]);
}

// ============================================================
// SKILL MATCH %
// ============================================================
function matchPercent(string $resumeSkills, string $jobSkills): int {
    if (!$resumeSkills || !$jobSkills) return 0;
    $user = array_map('strtolower', array_map('trim', explode(',', $resumeSkills)));
    $job  = array_map('strtolower', array_map('trim', explode(',', $jobSkills)));
    if (!$job) return 0;
    $matched = 0;
    foreach ($job as $js) {
        foreach ($user as $us) {
            if ($us && $js && (str_contains($us, $js) || str_contains($js, $us))) {
                $matched++;
                break;
            }
        }
    }
    return (int)min(100, round(($matched / count($job)) * 100));
}

// ============================================================
// SKILL GAP + ROADMAP HELPERS (seeker dashboard)
// ============================================================
function skillTokens(?string $s): array {
    $parts = preg_split('/[,;\/|]+/', strtolower($s ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && strlen($p) >= 2) {
            $out[] = $p;
        }
    }
    return array_values(array_unique($out));
}

function skillMatchesResumeToken(string $req, array $resumeTokens): bool {
    foreach ($resumeTokens as $u) {
        if ($u === '' || $req === '') {
            continue;
        }
        if ($u === $req || str_contains($u, $req) || str_contains($req, $u)) {
            return true;
        }
    }
    return false;
}

/** @return array{coverage:int,gaps:array<int,array{skill:string,tier:string,action:string}>,matched_count:int,required_count:int} */
function computeSkillGapAnalysis(?string $resumeSkills, ?string $jobSkills): array {
    $jobTokens = skillTokens($jobSkills);
    $resumeTokens = skillTokens($resumeSkills);
    if (!$jobTokens) {
        return ['coverage' => 100, 'gaps' => [], 'matched_count' => 0, 'required_count' => 0];
    }
    $missing = [];
    foreach ($jobTokens as $req) {
        if (!skillMatchesResumeToken($req, $resumeTokens)) {
            $missing[] = $req;
        }
    }
    $n = count($missing);
    $criticalCut = max(1, (int)ceil($n * 0.35));
    $moderateCut = max($criticalCut, (int)ceil($n * 0.7));
    $gaps = [];
    foreach ($missing as $i => $skill) {
        $tier = $i < $criticalCut ? 'critical' : ($i < $moderateCut ? 'moderate' : 'optional');
        $gaps[] = [
            'skill'  => $skill,
            'tier'   => $tier,
            'action' => 'Add proof of ' . $skill . ' (project, course, or certification) to your resume.',
        ];
    }
    $matched = count($jobTokens) - $n;
    $coverage = (int)min(100, round(100 * max(0, $matched) / max(1, count($jobTokens))));

    return [
        'coverage'        => $coverage,
        'gaps'            => $gaps,
        'matched_count'   => $matched,
        'required_count'  => count($jobTokens),
    ];
}

/** Best single job for gap analysis (same spirit as recommended jobs). */
function seekerTopRecommendedJob(int $userId): ?array {
    $res = db()->row("SELECT skills FROM resumes WHERE user_id=?", [$userId]);
    if (!$res || empty($res['skills'])) {
        return null;
    }
    $jobs = db()->rows("SELECT id, title, company, skills_required FROM jobs WHERE is_active=1 ORDER BY posted_at DESC LIMIT 30");
    $best = null;
    $bestPct = -1;
    foreach ($jobs as $j) {
        $pct = matchPercent($res['skills'], $j['skills_required'] ?? '');
        if ($pct >= 30 && $pct > $bestPct) {
            $best = $j;
            $bestPct = $pct;
        }
    }
    if ($best) {
        $best['match_pct'] = $bestPct;
    }
    return $best;
}

/** @return list<array{week:int,title:string,focus:string,tasks:list<string>}> */
function buildWeeklyRoadmap(float $readiness, int $gapCount, float $apt, float $int, int $apps): array {
    $weakInt = $int < 55;
    $weakApt = $apt < 55;
    $lowApps = $apps < 2;
    $wk1Focus = $gapCount > 2
        ? 'Close top skill gaps while polishing your CV.'
        : ($readiness < 55 ? 'Lift baseline readiness with a stronger CV.' : 'Strengthen your CV and online presence.');

    return [
        [
            'week'  => 1,
            'title' => 'Resume & profile',
            'focus' => $wk1Focus,
            'tasks' => [
                'Fill education, projects, and summary on your resume.',
                'Add phone and location on your profile.',
                'List 8–12 skills aligned with your target role.',
            ],
        ],
        [
            'week'  => 2,
            'title' => 'Aptitude & fundamentals',
            'focus' => $weakApt ? 'Lift numerical / logical / verbal scores.' : 'Maintain aptitude momentum.',
            'tasks' => [
                'Take one mixed aptitude test; review wrong answers.',
                'Drill your weakest category for 3 short sessions.',
                'Track accuracy vs time to build speed.',
            ],
        ],
        [
            'week'  => 3,
            'title' => 'Interview practice',
            'focus' => $weakInt ? 'Raise structured speaking scores.' : 'Polish delivery and STAR stories.',
            'tasks' => [
                'Complete one full mock interview on camera.',
                'Record STAR answers (2 min each) for top 5 questions.',
                'Review AI feedback and retry one weak prompt.',
            ],
        ],
        [
            'week'  => 4,
            'title' => 'Applications & follow-up',
            'focus' => $lowApps ? 'Convert readiness into real pipelines.' : 'Keep quality applications flowing.',
            'tasks' => [
                'Apply to 5–8 roles with tailored bullets per posting.',
                'Track status in Applications; send polite follow-ups.',
                'Refresh skill keywords from job descriptions weekly.',
            ],
        ],
    ];
}

function bookMetaFromCategory(string $category): array {
    $c = strtolower($category);
    if (str_contains($c, 'interview') || str_contains($c, 'system') || str_contains($c, 'ai')) {
        $d = 'advanced';
    } elseif (str_contains($c, 'python') || str_contains($c, 'javascript') || str_contains($c, 'design')) {
        $d = 'beginner';
    } else {
        $d = 'intermediate';
    }
    $hrs = $d === 'advanced' ? 6 : ($d === 'beginner' ? 3 : 4);

    return ['difficulty' => $d, 'weekly_hours' => $hrs];
}

/** Score books for seeker; returns up to $limit rows with meta. */
function personalizedBookPicks(int $userId, array $gapSkills, array $weakAptCats, bool $weakInterview, int $limit = 5): array {
    $books = db()->rows("SELECT * FROM books WHERE is_active=1 ORDER BY is_premium ASC, id ASC LIMIT 24");
    $scored = [];
    foreach ($books as $b) {
        $meta = bookMetaFromCategory($b['category'] ?? '');
        $tags = strtolower($b['skill_tags'] ?? '');
        $score = 0;
        foreach ($gapSkills as $g) {
            $g = strtolower($g);
            if ($g && str_contains($tags, $g)) {
                $score += 4;
            }
        }
        foreach ($weakAptCats as $cat) {
            $c = strtolower($cat);
            if ($c && (str_contains($tags, $c) || str_contains(strtolower($b['category'] ?? ''), $c))) {
                $score += 2;
            }
        }
        if ($weakInterview && (str_contains($tags, 'interview') || str_contains(strtolower($b['category'] ?? ''), 'interview'))) {
            $score += 3;
        }
        if ($score === 0) {
            $score = 1;
        }
        $b['pick_score'] = $score;
        $b['difficulty'] = $meta['difficulty'];
        $b['weekly_hours'] = $meta['weekly_hours'];
        $scored[] = $b;
    }
    usort($scored, fn ($a, $b) => ($b['pick_score'] <=> $a['pick_score']) ?: ((int)$a['is_premium'] <=> (int)$b['is_premium']));

    return array_slice($scored, 0, $limit);
}

function nextBestActionForSeeker(
    ?array $resume,
    array $gapSummary,
    float $apt,
    float $int,
    int $apps,
    ?array $focusJob
): array {
    if (!$resume || empty($resume['skills'])) {
        return [
            'label' => 'Create your resume',
            'detail'=> 'Add skills so we can match jobs and skill gaps.',
            'href'  => 'resume.html',
        ];
    }
    if (($gapSummary['coverage'] ?? 100) < 55 && $focusJob) {
        return [
            'label' => 'Close skill gaps for ' . $focusJob['title'],
            'detail'=> 'You are strong on fit but missing listed skills — add proof this week.',
            'href'  => 'job-detail.html?id=' . (int)$focusJob['id'],
        ];
    }
    if ($apt < 50) {
        return ['label' => 'Take an aptitude test', 'detail' => 'Mixed mode builds numerical and logical speed.', 'href' => 'test.html'];
    }
    if ($int < 50) {
        return ['label' => 'Practice mock interview', 'detail' => 'STAR answers on camera raise your interview score.', 'href' => 'interview.html'];
    }
    if ($apps < 1) {
        return ['label' => 'Apply to matched jobs', 'detail' => 'Turn readiness into real applications.', 'href' => 'jobs.html'];
    }

    return [
        'label' => 'Review applications',
        'detail'=> 'Follow up and keep your pipeline warm.',
        'href'  => 'applications.html',
    ];
}

// ============================================================
// GEMINI API CALL
// ============================================================
function callGemini(string $prompt, int $maxTokens = 1024): ?string {
    if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') return null;

    $payload = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => $maxTokens]
    ];

    $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code !== 200) return null;
    $data = json_decode($resp, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
}
