<?php
require_once __DIR__ . '/helpers.php';

// ============================================================
// EMAIL SERVICE (Uses PHPMailer if available, fallback to mail())
// ============================================================

function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    if (!MAIL_ENABLED) {
        // Log email instead of sending
        db()->insert(
            "INSERT INTO email_log (from_email, to_email, subject, body, status) VALUES (?,?,?,?,?)",
            [MAIL_FROM_EMAIL, $toEmail, $subject, $htmlBody, 'sent']
        );
        return true;
    }

    // Try PHPMailer
    $phpmailerPath = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/';
    if (is_dir($phpmailerPath)) {
        return sendWithPHPMailer($toEmail, $toName, $subject, $htmlBody, $phpmailerPath);
    }

    // Fallback: PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
    $result = mail($toEmail, $subject, $htmlBody, $headers);

    db()->insert(
        "INSERT INTO email_log (from_email, to_email, subject, body, status) VALUES (?,?,?,?,?)",
        [MAIL_FROM_EMAIL, $toEmail, $subject, $htmlBody, $result ? 'sent' : 'failed']
    );
    return $result;
}

function sendWithPHPMailer(string $toEmail, string $toName, string $subject, string $html, string $path): bool {
    require_once $path . 'Exception.php';
    require_once $path . 'PHPMailer.php';
    require_once $path . 'SMTP.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->send();

        db()->insert("INSERT INTO email_log (from_email,to_email,subject,body,status) VALUES (?,?,?,?,?)",
            [MAIL_FROM_EMAIL, $toEmail, $subject, $html, 'sent']);
        return true;
    } catch (Exception $e) {
        db()->insert("INSERT INTO email_log (from_email,to_email,subject,body,status) VALUES (?,?,?,?,?)",
            [MAIL_FROM_EMAIL, $toEmail, $subject, $html, 'failed']);
        return false;
    }
}

// ============================================================
// EMAIL TEMPLATES
// ============================================================

function emailBase(string $title, string $body): string {
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <style>
    body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;}
    .wrap{max-width:600px;margin:30px auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 20px rgba(0,0,0,0.1);}
    .header{background:linear-gradient(135deg,#0A66C2,#00A0DC);padding:30px 40px;color:white;text-align:center;}
    .header h1{margin:0;font-size:24px;}
    .header p{margin:5px 0 0;opacity:0.85;font-size:14px;}
    .body{padding:30px 40px;color:#333;}
    .body h2{color:#0A66C2;margin-top:0;}
    .btn{display:inline-block;padding:12px 28px;background:#0A66C2;color:white;text-decoration:none;border-radius:6px;font-weight:bold;margin:16px 0;}
    .footer{background:#f8f9fa;padding:20px 40px;text-align:center;font-size:12px;color:#888;border-top:1px solid #eee;}
    .highlight{background:#EBF5FF;border-left:4px solid #0A66C2;padding:12px 16px;border-radius:0 6px 6px 0;margin:16px 0;}
    </style></head>
    <body><div class='wrap'>
    <div class='header'><h1>🎯 True Occupation</h1><p>$title</p></div>
    <div class='body'>$body</div>
    <div class='footer'>© " . date('Y') . " True Occupation. All rights reserved.<br>This is an automated email.</div>
    </div></body></html>";
}

// A: Application submitted → employer
function emailApplicationToEmployer(string $empEmail, string $empName, array $job, array $seeker, int $matchPct): bool {
    $body = emailBase("New Job Application", "
    <h2>New Application Received</h2>
    <p>Hi $empName,</p>
    <p>A new candidate has applied for the position of <strong>{$job['title']}</strong>.</p>
    <div class='highlight'>
        <strong>Candidate:</strong> {$seeker['name']}<br>
        <strong>Email:</strong> {$seeker['email']}<br>
        <strong>Match Score:</strong> {$matchPct}%<br>
        <strong>Position:</strong> {$job['title']} at {$job['company']}
    </div>
    <a href='" . APP_URL . "/frontend/pages/employer-dashboard.html' class='btn'>View Applicants →</a>
    <p style='color:#888;font-size:13px;'>Log in to your employer dashboard to review and take action on this application.</p>
    ");
    return sendEmail($empEmail, $empName, "New Application: {$job['title']} — {$seeker['name']}", $body);
}

// B: Status update → seeker
function emailStatusToSeeker(string $seekerEmail, string $seekerName, array $job, string $status, string $note = ''): bool {
    $statusColors = ['Shortlisted'=>'#22c55e','Rejected'=>'#ef4444','Hired'=>'#10b981','Interview'=>'#3b82f6'];
    $color = $statusColors[$status] ?? '#0A66C2';
    $emoji = ['Shortlisted'=>'🌟','Rejected'=>'😔','Hired'=>'🎉','Interview'=>'📞'][$status] ?? '📋';

    $body = emailBase("Application Update", "
    <h2>{$emoji} Application Update</h2>
    <p>Hi $seekerName,</p>
    <p>Your application for <strong>{$job['title']}</strong> at <strong>{$job['company']}</strong> has been updated.</p>
    <div class='highlight' style='border-color:$color;'>
        <strong>New Status:</strong> <span style='color:$color;font-weight:bold;'>$status</span><br>
        " . ($note ? "<strong>Note from employer:</strong> $note" : "") . "
    </div>
    " . ($status === 'Hired' ? "<p>🎊 Congratulations! The employer will contact you soon.</p>" : "") . "
    " . ($status === 'Interview' ? "<p>📅 Please check your email for interview schedule details.</p>" : "") . "
    <a href='" . APP_URL . "/frontend/pages/applications.html' class='btn'>View My Applications →</a>
    ");
    return sendEmail($seekerEmail, $seekerName, "{$emoji} Application {$status}: {$job['title']}", $body);
}

// C: Admin email to user
function emailAdminToUser(string $toEmail, string $toName, string $subject, string $message): bool {
    $body = emailBase("Message from Admin", "
    <h2>📬 Message from True Occupation Admin</h2>
    <p>Hi $toName,</p>
    <div class='highlight'>$message</div>
    <p>If you have questions, please reply to this email.</p>
    ");
    return sendEmail($toEmail, $toName, $subject, $body);
}

// D: Job recommendation email
function emailJobRecommendation(string $seekerEmail, string $seekerName, array $jobs): bool {
    $jobList = '';
    foreach ($jobs as $j) {
        $jobList .= "<div style='border:1px solid #eee;border-radius:8px;padding:14px;margin:10px 0;'>
            <strong>{$j['title']}</strong> at {$j['company']}<br>
            <span style='color:#666;font-size:13px;'>📍 {$j['location']} &nbsp;·&nbsp; {$j['job_type']}</span><br>
            " . (isset($j['match_pct']) ? "<span style='color:#0A66C2;font-weight:bold;'>🎯 {$j['match_pct']}% Match</span>" : "") . "
        </div>";
    }
    $body = emailBase("Jobs Matching Your Profile", "
    <h2>🔥 Jobs Recommended For You</h2>
    <p>Hi $seekerName,</p>
    <p>We found some jobs that match your profile. Don't miss out!</p>
    $jobList
    <a href='" . APP_URL . "/frontend/pages/jobs.html' class='btn'>View All Jobs →</a>
    ");
    return sendEmail($seekerEmail, $seekerName, "🔥 New Job Matches for You!", $body);
}
