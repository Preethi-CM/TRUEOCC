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

$action = $_GET['action'] ?? '';
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true) ?? [];
$data   = array_merge($_POST, $data);

switch ($action) {
    case 'status':           getStatus();            break;
    case 'questions':        getQuestions();         break;
    case 'begin_batch':      beginBatch($data);      break;
    case 'complete_session': completeSession($data); break;
    case 'evaluate':         evaluateAnswer($data);  break;
    case 'results':          getResults();           break;
    default: apiError('Invalid action. Valid: status, questions, begin_batch, complete_session, evaluate, results');
}

function getStatus(): void {
    $user = requireRole('seeker');
    $cnt  = (int)db()->row(
        "SELECT COUNT(DISTINCT attempt_number) as c FROM interview_results WHERE user_id=?",
        [$user['id']]
    )['c'];
    $maxScore = (float)db()->row(
        "SELECT MAX(ai_score) as m FROM interview_results WHERE user_id=?",
        [$user['id']]
    )['m'];
    $allowed = FREE_INTERVIEW_ATTEMPTS;
    if ($cnt > 0 && $maxScore < 5.0) $allowed += 2;

    apiSuccess([
        'attempts'   => $cnt,
        'free_limit' => $allowed,
        'is_premium' => (bool)$user['is_premium'],
        'can_attempt'=> $user['is_premium'] || $cnt < $allowed
    ]);
}

function getQuestions(): void {
    requireRole('seeker');
    $jobId = (int)($_GET['job_id'] ?? 0);
    $role  = clean($_GET['role'] ?? 'Software Engineer');

    // Use custom employer questions if job_id given
    if ($jobId) {
        $qs = db()->rows(
            "SELECT question FROM job_interview_questions WHERE job_id=? ORDER BY order_num",
            [$jobId]);
        if (!empty($qs)) {
            $customQs = array_column($qs, 'question');
            if (count($customQs) < 5) {
                $defaults = [
                    "Tell me about yourself and your professional background.",
                    "What are your greatest strengths relevant to the role of {$role}?",
                    "Describe a challenging project you worked on. What was your approach?",
                    "How do you handle working under pressure or tight deadlines?",
                    "Where do you see yourself professionally in the next 3-5 years?",
                    "Why are you interested in this position and company?",
                    "Describe a time you had a conflict with a team member. How did you resolve it?",
                    "What is your biggest professional achievement so far?",
                    "How do you stay updated with industry trends and new technologies?",
                    "Do you have any questions for us?"
                ];
                shuffle($defaults);
                $needed = 5 - count($customQs);
                $customQs = array_merge($customQs, array_slice($defaults, 0, $needed));
            }
            apiSuccess(['questions' => $customQs, 'source' => 'employer_custom']);
            return;
        }
    }

    // Default questions
    $defaults = [
        "Tell me about yourself and your professional background.",
        "What are your greatest strengths relevant to the role of {$role}?",
        "Describe a challenging project you worked on. What was your approach?",
        "How do you handle working under pressure or tight deadlines?",
        "Where do you see yourself professionally in the next 3–5 years?",
        "Why are you interested in this position and company?",
        "Describe a time you had a conflict with a team member. How did you resolve it?",
        "What is your biggest professional achievement so far?",
        "How do you stay updated with industry trends and new technologies?",
        "Do you have any questions for us?"
    ];

    shuffle($defaults);
    apiSuccess(['questions' => array_slice($defaults, 0, 5), 'source' => 'default']);
}

function beginBatch(array $d): void {
    $user = requireRole('seeker');
    $used = (int)db()->row(
        "SELECT COUNT(DISTINCT attempt_number) as c FROM interview_results WHERE user_id=?",
        [$user['id']]
    )['c'];
    $maxScore = (float)db()->row(
        "SELECT MAX(ai_score) as m FROM interview_results WHERE user_id=?",
        [$user['id']]
    )['m'];
    $allowed = FREE_INTERVIEW_ATTEMPTS;
    if ($used > 0 && $maxScore < 5.0) $allowed += 2;

    if (!$user['is_premium'] && $used >= $allowed) {
        apiError('Free attempt used. Upgrade to Premium.', 403);
    }

    $open = db()->row(
        "SELECT id, attempt_number FROM interview_sessions WHERE user_id=? AND completed_at IS NULL ORDER BY id DESC LIMIT 1",
        [$user['id']]
    );
    if ($open) {
        apiSuccess([
            'session_id'     => (int)$open['id'],
            'attempt_number' => (int)$open['attempt_number'],
            'resumed'        => true,
        ]);
        return;
    }

    $mr = (int)db()->row(
        "SELECT COALESCE(MAX(attempt_number), 0) as m FROM interview_results WHERE user_id=?",
        [$user['id']]
    )['m'];
    $ms = (int)db()->row(
        "SELECT COALESCE(MAX(attempt_number), 0) as m FROM interview_sessions WHERE user_id=?",
        [$user['id']]
    )['m'];
    $next = max($mr, $ms) + 1;

    $jobId = !empty($d['job_id']) ? (int)$d['job_id'] : null;
    $role  = clean($d['target_role'] ?? '');

    $sid = db()->insert(
        "INSERT INTO interview_sessions (user_id, job_id, attempt_number, target_role) VALUES (?,?,?,?)",
        [$user['id'], $jobId, $next, $role]
    );

    apiSuccess([
        'session_id'     => (int)$sid,
        'attempt_number' => $next,
        'resumed'        => false,
    ]);
}

function completeSession(array $d): void {
    $user = requireRole('seeker');
    $sid  = (int)($d['session_id'] ?? 0);
    if (!$sid) {
        apiError('session_id required');
    }
    $sess = db()->row("SELECT id FROM interview_sessions WHERE id=? AND user_id=?", [$sid, $user['id']]);
    if (!$sess) {
        apiError('Invalid session');
    }

    $avg = (float)($d['avg_ai_score'] ?? 0);
    $qc  = (int)($d['questions_count'] ?? 0);
    $summary = $d['summary'] ?? null;
    $sj = is_array($summary) ? json_encode($summary, JSON_UNESCAPED_UNICODE) : null;

    db()->exec(
        "UPDATE interview_sessions SET completed_at=NOW(), avg_ai_score=?, questions_count=?, summary_json=? WHERE id=? AND user_id=?",
        [$avg, $qc, $sj, $sid, $user['id']]
    );
    apiSuccess(null, 'Session saved.');
}

// ════════════════════════════════════════════════════════════════════════
// EVALUATE ANSWER  (5-parameter weighted scoring)
// ════════════════════════════════════════════════════════════════════════
function evaluateAnswer(array $d): void {
    $user           = requireRole('seeker');
    $question       = clean($d['question']       ?? '');
    $answer         = clean($d['answer']         ?? '');
    $jobId          = !empty($d['job_id']) ? (int)$d['job_id'] : null;
    $headViolations = (int)($d['head_violations'] ?? 0);
    $tabSwitches    = (int)($d['tab_switches']    ?? 0);
    $warnings       = (int)($d['warnings']        ?? 0);
    $interviewType  = clean($d['interview_type']  ?? 'standard');

    $skipped = !empty($d['skipped']);
    if ($skipped) {
        $answer = trim($answer) === '' ? '[Skipped by candidate]' : $answer;
    }

    if (!$question) apiError('Question is required.');
    if (!$skipped && mb_strlen($answer) < 5) apiError('Answer too short. Minimum 5 characters.');

    $sessionId = (int)($d['session_id'] ?? 0);
    if (!$sessionId) apiError('Missing interview session. Open the readiness screen and start again.');

    $sess = db()->row(
        "SELECT * FROM interview_sessions WHERE id=? AND user_id=?",
        [$sessionId, $user['id']]
    );
    if (!$sess)                    apiError('Invalid interview session.');
    if (!empty($sess['completed_at'])) apiError('This interview session is already completed.');

    $newAttempt  = (int)$sess['attempt_number'];
    $jobIdInsert = $jobId ?: (!empty($sess['job_id']) ? (int)$sess['job_id'] : null);

    // ── Evaluate ────────────────────────────────────────────────────────
    if ($skipped) {
        $result = [
            'clarity'         => 0, 'technical'      => 0,
            'confidence'      => 0, 'relevance'      => 0,
            'professionalism' => 0, 'weighted_score' => 0,
            'rating'          => 'Weak',
            'score'           => 0,
            'communication_score' => 0,
            'confidence_score'    => 0,
            'feedback'        => 'This question was skipped. Even a brief structured attempt scores significantly better than no response.',
            'strengths'       => [],
            'improvements'    => ['No response given — attempt every question', 'Use STAR even for partial answers'],
            'suggestions'     => 'Try a one-sentence STAR outline per step. Any attempt is better than skipping.',
        ];
    } else {
        $result = callGeminiEvaluate5Param($question, $answer);
    }

    // ── Discipline deduction ─────────────────────────────────────────────
    $disciplineScore = 100;
    if ($warnings >= 3)      $disciplineScore -= 25;
    elseif ($warnings === 2) $disciplineScore -= 15;
    elseif ($warnings === 1) $disciplineScore -= 5;
    if ($headViolations > 5) $disciplineScore -= 10;
    if ($tabSwitches > 0)    $disciplineScore -= (5 * $tabSwitches);
    $disciplineScore = max(0, $disciplineScore);

    // ai_score stored as 0–10 for legacy dashboard compatibility
    $aiScore10 = round(($result['weighted_score'] ?? 0) / 10, 2);

    // ── Persist ──────────────────────────────────────────────────────────
    db()->insert(
        "INSERT INTO interview_results
            (user_id,job_id,question,user_answer,ai_rating,ai_score,
             communication_score,confidence_score,ai_feedback,ai_suggestions,
             discipline_score,head_violations,tab_switches,interview_type,attempt_number)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [
            $user['id'], $jobIdInsert, $question, $answer,
            $result['rating'], $aiScore10,
            $result['clarity']    ?? 0,
            $result['confidence'] ?? 0,
            $result['feedback'],
            $result['suggestions'],
            $disciplineScore, $headViolations, $tabSwitches, $interviewType, $newAttempt
        ]
    );

    updateFitScore($user['id']);

    notify($user['id'], 'Interview Answer Evaluated',
        "Rated: {$result['rating']} ({$result['weighted_score']}/100) • Discipline: $disciplineScore",
        'interview');

    apiSuccess($result, 'Answer evaluated!');
}

// ════════════════════════════════════════════════════════════════════════
// 5-PARAMETER WEIGHTED SCORING
//
//   clarity       × 2   (Communication clarity)
//   technical     × 3   (Technical correctness)   ← highest weight
//   confidence    × 2   (Confidence & fluency)
//   relevance     × 2   (Relevance to question)
//   professionalism × 1 (Professional delivery)
//   ─────────────────────────────────────────────
//   Total out of 100
//
// Bands: Excellent ≥ 85 | Good ≥ 70 | Average ≥ 50 | Weak ≥ 30 | Poor < 30
// ════════════════════════════════════════════════════════════════════════
function callGeminiEvaluate5Param(string $question, string $answer): array {
    $prompt = <<<PROMPT
You are a senior HR interviewer evaluating a job interview response.

Question: {$question}

Candidate's Answer: {$answer}

Score the answer on exactly these 5 parameters, each from 0 to 10:

1. clarity (0-10): Was the answer understandable, well-structured, and easy to follow?
2. technical (0-10): Did the candidate give correct, role-relevant content? Award higher for specific knowledge, real examples, or measurable outcomes.
3. confidence (0-10): Did the answer sound natural, complete, and fluent — not broken or overly hesitant?
4. relevance (0-10): Did the answer directly address what was asked, without going off-topic?
5. professionalism (0-10): Was the tone interview-appropriate, with a reasonable opening and conclusion?

Compute the weighted score using this exact formula:
weighted_score = (clarity × 2) + (technical × 3) + (confidence × 2) + (relevance × 2) + (professionalism × 1)
Maximum = 100.

Rating bands (apply strictly based on weighted_score):
- "Excellent" if weighted_score >= 85
- "Good"      if weighted_score >= 70
- "Average"   if weighted_score >= 50
- "Weak"      if weighted_score >= 30
- "Poor"      if weighted_score <  30

Respond ONLY with valid JSON — no markdown, no code fences, no extra text:
{
  "clarity": <0-10>,
  "technical": <0-10>,
  "confidence": <0-10>,
  "relevance": <0-10>,
  "professionalism": <0-10>,
  "weighted_score": <0-100>,
  "rating": "Excellent|Good|Average|Weak|Poor",
  "feedback": "<2-3 honest sentences of overall feedback>",
  "strengths": ["<strength 1>", "<strength 2>"],
  "improvements": ["<improvement 1>", "<improvement 2>"],
  "suggestions": "<One concrete STAR direction — a brief approach hint, not a full copied answer>"
}

CRITICAL: weighted_score must equal exactly (clarity×2)+(technical×3)+(confidence×2)+(relevance×2)+(professionalism×1). Compute it; do not guess.
PROMPT;

    $text = callGemini($prompt, 900);
    if (!$text) return getFallback5Param($answer);

    $text   = preg_replace('/```(?:json)?|```/', '', $text);
    $parsed = json_decode(trim($text), true);
    if (!$parsed || !array_key_exists('clarity', $parsed)) return getFallback5Param($answer);

    // Clamp all parameters
    foreach (['clarity','technical','confidence','relevance','professionalism'] as $k) {
        $parsed[$k] = max(0, min(10, (int)($parsed[$k] ?? 5)));
    }

    // Always recompute weighted_score from the sanitised values — Gemini can mis-compute
    $ws = ($parsed['clarity'] * 2)
        + ($parsed['technical'] * 3)
        + ($parsed['confidence'] * 2)
        + ($parsed['relevance'] * 2)
        + ($parsed['professionalism'] * 1);
    $parsed['weighted_score'] = $ws;

    // Enforce rating band
    if ($ws >= 85)     $parsed['rating'] = 'Excellent';
    elseif ($ws >= 70) $parsed['rating'] = 'Good';
    elseif ($ws >= 50) $parsed['rating'] = 'Average';
    elseif ($ws >= 30) $parsed['rating'] = 'Weak';
    else               $parsed['rating'] = 'Poor';

    // Legacy compat fields
    $parsed['score']               = round($ws / 10, 1);
    $parsed['communication_score'] = $parsed['clarity'];
    $parsed['confidence_score']    = $parsed['confidence'];

    return $parsed;
}

// ── Offline / Gemini-unavailable fallback ────────────────────────────────
function getFallback5Param(string $answer): array {
    $len = mb_strlen(trim($answer));

    // Heuristic scores based on answer length
    if ($len > 250) {
        [$c, $t, $cf, $r, $p] = [7, 6, 6, 7, 6];
        $fb  = 'Detailed response. Adding specific metrics and an explicit STAR structure would push this higher.';
        $str = ['Good length and detail', 'Demonstrates relevant experience'];
        $imp = ['Add specific numbers or outcomes', 'Close with a clear result statement'];
        $sug = 'Structure: context (S) → your role (T) → exact actions (A) → measurable outcome (R).';
    } elseif ($len > 80) {
        [$c, $t, $cf, $r, $p] = [5, 4, 5, 5, 5];
        $fb  = 'Covers the basics but lacks depth. Expand with a real example and a concrete result.';
        $str = ['On-topic', 'Appropriate starting length'];
        $imp = ['Expand with a specific example', 'Show a measurable result or learning'];
        $sug = '"In my [project/role], I [specific action], which led to [result]." — that one addition changes the score significantly.';
    } else {
        [$c, $t, $cf, $r, $p] = [3, 2, 3, 3, 3];
        $fb  = 'Answer is too brief. Interviewers need evidence, not just assertions.';
        $str = [];
        $imp = ['Much too short — aim for 120-200 words', 'No example or structure provided'];
        $sug = 'STAR outline: Situation (1 sentence) → Task (1 sentence) → Action (2-3 sentences) → Result (1-2 sentences).';
    }

    $ws     = ($c*2)+($t*3)+($cf*2)+($r*2)+($p*1);
    $rating = $ws >= 85 ? 'Excellent' : ($ws >= 70 ? 'Good' : ($ws >= 50 ? 'Average' : ($ws >= 30 ? 'Weak' : 'Poor')));

    return [
        'clarity'          => $c,   'technical'      => $t,
        'confidence'       => $cf,  'relevance'      => $r,
        'professionalism'  => $p,   'weighted_score' => $ws,
        'rating'           => $rating,
        'score'            => round($ws / 10, 1),
        'communication_score' => $c,
        'confidence_score'    => $cf,
        'feedback'         => $fb,
        'strengths'        => $str,
        'improvements'     => $imp,
        'suggestions'      => $sug,
    ];
}

// ════════════════════════════════════════════════════════════════════════
// RESULTS
// ════════════════════════════════════════════════════════════════════════
function getResults(): void {
    $user = requireRole('seeker');
    $res  = db()->rows(
        "SELECT * FROM interview_results WHERE user_id=? ORDER BY taken_at DESC LIMIT 20",
        [$user['id']]);
    apiSuccess(['results' => $res]);
}
