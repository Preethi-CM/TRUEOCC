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
// Also merge POST for form submissions
$data   = array_merge($_POST, $data);

switch ($action) {
    case 'status':    getStatus();          break;
    case 'questions': getQuestions();       break;
    case 'submit':    submitTest($data);    break;
    case 'results':   getResults();         break;
    default: apiError('Invalid action. Valid: status, questions, submit, results');
}

function getStatus(): void {
    $user = requireRole('seeker');
    $cnt  = (int)db()->row("SELECT COUNT(*) as c FROM aptitude_results WHERE user_id=?", [$user['id']])['c'];
    $maxScore = (float)db()->row("SELECT MAX(score_percentage) as m FROM aptitude_results WHERE user_id=?", [$user['id']])['m'];
    $allowed = FREE_TEST_ATTEMPTS;
    if ($cnt > 0 && $maxScore < 50) $allowed += 2;

    apiSuccess([
        'attempts'   => $cnt,
        'free_limit' => $allowed,
        'is_premium' => (bool)$user['is_premium'],
        'can_attempt'=> $user['is_premium'] || $cnt < $allowed
    ]);
}

function getQuestions(): void {
    $user     = requireRole('seeker');
    $category = $_GET['category'] ?? 'Mixed';

    // Check attempt limit
    $cnt = (int)db()->row("SELECT COUNT(*) as c FROM aptitude_results WHERE user_id=?", [$user['id']])['c'];
    $maxScore = (float)db()->row("SELECT MAX(score_percentage) as m FROM aptitude_results WHERE user_id=?", [$user['id']])['m'];
    $allowed = FREE_TEST_ATTEMPTS;
    if ($cnt > 0 && $maxScore < 50) $allowed += 2;

    if (!$user['is_premium'] && $cnt >= $allowed) {
        apiError('Free attempt used. Upgrade to Premium.', 403);
    }

    $validCats = ['Numerical','Logical','Verbal','Coding','Mixed'];
    if (!in_array($category, $validCats)) $category = 'Mixed';

    if ($category === 'Mixed') {
        $questions = [];
        foreach (['Numerical','Logical','Verbal','Coding'] as $cat) {
            $qs = db()->rows(
                "SELECT id,category,question,option_a,option_b,option_c,option_d,difficulty
                 FROM questions WHERE category=? AND is_active=1 ORDER BY RAND() LIMIT 7", [$cat]);
            $questions = array_merge($questions, $qs);
        }
        shuffle($questions);
    } else {
        $questions = db()->rows(
            "SELECT id,category,question,option_a,option_b,option_c,option_d,difficulty
             FROM questions WHERE category=? AND is_active=1 ORDER BY RAND() LIMIT 25", [$category]);
    }

    if (empty($questions)) {
        apiError('No questions found for this category. Please ensure questions are seeded in the database.', 404);
    }

    apiSuccess([
        'questions'  => $questions,
        'total'      => count($questions),
        'time_limit' => count($questions) * 60,
        'category'   => $category
    ]);
}

function submitTest(array $d): void {
    $user      = requireRole('seeker');
    $answers   = $d['answers'] ?? null;
    $category  = $d['category'] ?? 'Mixed';
    $timeTaken = (int)($d['time_taken'] ?? 0);

    // Validate answers
    if (empty($answers) || !is_array($answers)) {
        apiError('No answers submitted. Send: {"answers":{"1":"a","2":"b",...}, "category":"Mixed", "time_taken":120}');
    }

    // Check attempt limit
    $cnt = (int)db()->row("SELECT COUNT(*) as c FROM aptitude_results WHERE user_id=?", [$user['id']])['c'];
    $maxScore = (float)db()->row("SELECT MAX(score_percentage) as m FROM aptitude_results WHERE user_id=?", [$user['id']])['m'];
    $allowed = FREE_TEST_ATTEMPTS;
    if ($cnt > 0 && $maxScore < 50) $allowed += 2;

    $attemptNum = $cnt + 1;
    if (!$user['is_premium'] && $attemptNum > $allowed) {
        apiError('Free attempt limit reached. Upgrade to Premium.', 403);
    }

    // Fetch correct answers
    $qIds  = array_map('intval', array_keys($answers));
    if (empty($qIds)) apiError('No valid question IDs found in answers.');

    $phs   = implode(',', array_fill(0, count($qIds), '?'));
    $qRows = db()->rows(
        "SELECT id,correct_answer,question,option_a,option_b,option_c,option_d,explanation FROM questions WHERE id IN ($phs)",
        $qIds
    );

    if (empty($qRows)) {
        apiError('Question IDs not found in database. Ensure you are using IDs from the questions endpoint.');
    }

    $correct = 0; $wrong = 0; $skipped = 0;
    $review  = [];

    foreach ($qRows as $q) {
        $qid     = (string)$q['id'];
        $userAns = $answers[$qid] ?? null;

        if (!$userAns) {
            $skipped++;
        } elseif (strtolower($userAns) === $q['correct_answer']) {
            $correct++;
        } else {
            $wrong++;
        }

        $review[] = [
            'question'       => $q['question'],
            'options'        => ['a'=>$q['option_a'],'b'=>$q['option_b'],'c'=>$q['option_c'],'d'=>$q['option_d']],
            'user_answer'    => $userAns,
            'correct_answer' => $q['correct_answer'],
            'explanation'    => $q['explanation'],
            'is_correct'     => $userAns && strtolower($userAns) === $q['correct_answer']
        ];
    }

    $total    = count($qRows);
    $scorePct = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

    // Save result
    $resultId = db()->insert(
        "INSERT INTO aptitude_results (user_id,category,total_questions,correct_answers,score_percentage,time_taken,attempt_number)
         VALUES (?,?,?,?,?,?,?)",
        [$user['id'], $category, $total, $correct, $scorePct, $timeTaken, $attemptNum]
    );

    // Update fit score
    updateFitScore($user['id']);

    // Notify
    notify($user['id'],
        "Test Complete – {$scorePct}%",
        "You scored {$correct}/{$total} on the {$category} aptitude test.",
        'test', APP_URL.'/frontend/pages/user-dashboard.html');

    apiSuccess([
        'result_id'  => $resultId,
        'total'      => $total,
        'correct'    => $correct,
        'wrong'      => $wrong,
        'skipped'    => $skipped,
        'score_pct'  => $scorePct,
        'time_taken' => $timeTaken,
        'attempt'    => $attemptNum,
        'review'     => $review
    ], 'Test submitted!');
}

function getResults(): void {
    $user = requireRole('seeker');
    $res  = db()->rows(
        "SELECT * FROM aptitude_results WHERE user_id=? ORDER BY taken_at DESC LIMIT 10",
        [$user['id']]);
    apiSuccess(['results'=>$res]);
}
