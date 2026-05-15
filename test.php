<?php
// ============================================================
// TRUE OCCUPATION — Setup Diagnostic
// Visit: http://localhost/trueocc/test.php
// ============================================================
header('Content-Type: text/html; charset=utf-8');

$checks = [];

// 1. PHP Version
$checks[] = ['label'=>'PHP Version','value'=>phpversion(),'ok'=>version_compare(phpversion(),'7.4','>='),'fix'=>'Upgrade to PHP 7.4+'];

// 2. Extensions
foreach (['pdo_mysql','curl','fileinfo'] as $ext) {
    $checks[] = ['label'=>$ext.' Extension','value'=>extension_loaded($ext)?'Loaded ✓':'Missing','ok'=>extension_loaded($ext),'fix'=>"Enable $ext in php.ini"];
}

// 3. Detect project root and APP_URL
$configFile  = str_replace('\\', '/', __FILE__);        // .../trueocc/test.php
$projectRoot = str_replace('\\', '/', dirname($configFile));
$docRoot     = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$relPath     = str_replace($docRoot, '', $projectRoot);
$relPath     = '/' . ltrim($relPath, '/');
$proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
$detectedUrl = $proto . '://' . $host . rtrim($relPath, '/');

$checks[] = ['label'=>'Detected APP_URL','value'=>$detectedUrl,'ok'=>true,'fix'=>''];
$checks[] = ['label'=>'Project root path','value'=>$projectRoot,'ok'=>true,'fix'=>''];
$checks[] = ['label'=>'Document root','value'=>$docRoot,'ok'=>true,'fix'=>''];

// 4. Uploads folder
$uploadsOk = is_dir($projectRoot.'/backend/uploads/resumes') && is_writable($projectRoot.'/backend/uploads/resumes');
$checks[] = ['label'=>'Uploads folder writable','value'=>$uploadsOk?'OK ✓':'Missing or not writable','ok'=>$uploadsOk,'fix'=>"Manually create: backend/uploads/resumes/ and backend/uploads/docs/ inside your trueocc folder"];

// 5. API file reachability (check file exists)
$apiFiles = ['auth.php','jobs.php','test.php','interview.php','user.php','admin.php'];
foreach ($apiFiles as $af) {
    $exists = file_exists($projectRoot.'/backend/api/'.$af);
    $checks[] = ['label'=>"API: $af",'value'=>$exists?'Found ✓':'MISSING','ok'=>$exists,'fix'=>"backend/api/$af is missing — re-extract the ZIP"];
}

// 6. Database
$dbOk = false; $dbMsg = ''; $tablesOk = false; $tablesMsg = '';
try {
    require_once $projectRoot . '/backend/includes/config.php';
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $dbOk  = true;
    $dbMsg = "Connected to '".DB_NAME."' (".count($tables)." tables)";

    $required = ['users','employers','jobs','applications','resumes','questions','books','admins'];
    $missing  = array_diff($required, $tables);
    $tablesOk = empty($missing);
    $tablesMsg = $tablesOk
        ? 'All tables present ('.count($tables).' total)'
        : 'Missing tables: '.implode(', ', $missing);

    if ($tablesOk) {
        $uc = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $jc = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
        $qc = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
        $tablesMsg .= " | Users: $uc | Jobs: $jc | Questions: $qc";
    }
} catch (PDOException $e) {
    $dbMsg = 'FAILED: '.$e->getMessage();
}
$checks[] = ['label'=>'Database Connection','value'=>$dbMsg,'ok'=>$dbOk,'fix'=>"Edit backend/includes/config.php — set DB_HOST, DB_NAME, DB_USER, DB_PASS"];
$checks[] = ['label'=>'Database Tables','value'=>$tablesMsg,'ok'=>$tablesOk,'fix'=>"Import database/schema.sql via phpMyAdmin into '".DB_NAME."' database"];

// 7. Gemini API key
$geminiOk = defined('GEMINI_API_KEY') && GEMINI_API_KEY !== 'YOUR_GEMINI_API_KEY_HERE';
$checks[] = ['label'=>'Gemini API Key','value'=>$geminiOk?'Configured ✓ ('.substr(GEMINI_API_KEY,0,8).'...)':'Not configured (fallback AI will be used)','ok'=>$geminiOk,'fix'=>'Get free key: https://aistudio.google.com/app/apikey → paste in config.php'];

// 8. API JSON test
$apiTestUrl = $detectedUrl . '/backend/api/auth.php?action=me';
$checks[] = ['label'=>'API URL (auth.php)','value'=>$apiTestUrl,'ok'=>true,'fix'=>''];

$allGood = !array_filter($checks, fn($c)=>!$c['ok'] && strpos($c['label'],'Gemini')===false && strpos($c['label'],'Detected')===false && strpos($c['label'],'root')===false && strpos($c['label'],'Document')===false && strpos($c['label'],'API URL')===false);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>TrueOcc — Diagnostic</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:system-ui,sans-serif;background:#f1f5f9;padding:40px 20px;color:#1e293b;}
.wrap{max-width:780px;margin:0 auto;}
h1{font-size:22px;font-weight:800;margin-bottom:4px;}
.sub{color:#64748b;font-size:14px;margin-bottom:24px;}
.card{background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:16px;}
.banner{padding:16px 20px;font-weight:600;font-size:15px;}
.ok-banner{background:#dcfce7;color:#166534;}
.bad-banner{background:#fee2e2;color:#991b1b;}
.row{display:flex;align-items:flex-start;gap:12px;padding:13px 18px;border-bottom:1px solid #f1f5f9;}
.row:last-child{border-bottom:none;}
.dot{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;color:white;flex-shrink:0;margin-top:1px;}
.dot-ok{background:#22c55e;} .dot-fail{background:#ef4444;}
.lbl{font-weight:600;font-size:13px;min-width:220px;flex-shrink:0;}
.val{font-size:13px;color:#475569;flex:1;word-break:break-all;}
.fix{font-size:12px;color:#dc2626;margin-top:4px;background:#fff5f5;padding:6px 10px;border-radius:6px;border-left:3px solid #dc2626;}
.links{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;}
.btn{display:inline-block;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;}
.btn-blue{background:#0A66C2;color:white;}
.btn-gray{background:#e2e8f0;color:#1e293b;}
.info{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:16px;font-size:13px;color:#1e40af;}
</style></head>
<body>
<div class="wrap">
  <h1>🔧 True Occupation — Setup Diagnostic</h1>
  <p class="sub">Checks your XAMPP configuration. URL: <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></p>

  <div class="info">
    <strong>Quick Check:</strong> If you see this page, PHP is working! ✓<br>
    Fix any ❌ items below, then try <a href="frontend/pages/signup.html" style="color:#0A66C2;font-weight:bold;">signup</a> or <a href="frontend/pages/login.html" style="color:#0A66C2;font-weight:bold;">login</a>.
  </div>

  <div class="card">
    <div class="banner <?= $allGood ? 'ok-banner' : 'bad-banner' ?>">
      <?= $allGood ? '✅ All systems ready! Your app should work.' : '⚠️ Issues found — fix the ❌ items below.' ?>
    </div>
    <?php foreach ($checks as $c): ?>
    <div class="row">
      <div class="dot <?= $c['ok'] ? 'dot-ok' : 'dot-fail' ?>"><?= $c['ok'] ? '✓' : '✗' ?></div>
      <div style="flex:1;">
        <div class="lbl"><?= htmlspecialchars($c['label']) ?></div>
        <div class="val"><?= htmlspecialchars($c['value']) ?></div>
        <?php if (!$c['ok'] && $c['fix']): ?>
        <div class="fix">→ <?= htmlspecialchars($c['fix']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Quick Fix Instructions -->
  <?php if (!$tablesOk): ?>
  <div class="card">
    <div class="row"><div style="flex:1;">
      <div style="font-weight:700;margin-bottom:8px;">📋 How to Import Database</div>
      <ol style="font-size:13px;color:#475569;line-height:2;padding-left:20px;">
        <li>Open <a href="http://localhost/phpmyadmin" target="_blank" style="color:#0A66C2;">phpMyAdmin</a></li>
        <li>Click "New" in left panel → Name it <strong>true_occupation</strong> → Create</li>
        <li>Click "Import" tab → Choose File → Select <code>trueocc/database/schema.sql</code></li>
        <li>Click "Go" at the bottom → Refresh this page</li>
      </ol>
    </div></div>
  </div>
  <?php endif; ?>

  <?php if (!$uploadsOk): ?>
  <div class="card">
    <div class="row"><div style="flex:1;">
      <div style="font-weight:700;margin-bottom:8px;">📁 How to Create Upload Folders</div>
      <div style="font-size:13px;color:#475569;">Inside your <code>trueocc/backend/</code> folder, create:</div>
      <pre style="background:#f8fafc;padding:10px;border-radius:6px;font-size:12px;margin-top:8px;">backend/
└── uploads/
    ├── resumes/    ← create this
    ├── docs/       ← create this
    └── books/      ← create this</pre>
    </div></div>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="row"><div style="flex:1;">
      <div style="font-weight:700;margin-bottom:8px;">🔑 Fix Admin Password</div>
      <div style="font-size:13px;color:#475569;line-height:1.8;">
        1. Visit <a href="generate_hash.php" target="_blank" style="color:#0A66C2;">generate_hash.php</a> to get the hash for <strong>Admin@1234</strong><br>
        2. In phpMyAdmin → <code>true_occupation</code> → <code>admins</code> table → Edit → paste hash<br>
        3. Delete <code>generate_hash.php</code> after use
      </div>
    </div></div>
  </div>

  <div class="links">
    <a href="frontend/pages/login.html" class="btn btn-blue">→ Go to Login</a>
    <a href="frontend/pages/signup.html" class="btn btn-gray">→ Go to Signup</a>
    <a href="frontend/pages/admin-login.html" class="btn btn-gray">→ Admin Login</a>
  </div>

  <p style="font-size:12px;color:#94a3b8;margin-top:16px;">
    Demo: alice@email.com / password &nbsp;|&nbsp; hr@techcorp.com / password &nbsp;|&nbsp; Admin: admin@trueocc.com / Admin@1234
  </p>
</div>
</body></html>
