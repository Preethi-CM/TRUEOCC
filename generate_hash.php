<?php
// Run this file once to generate hashes, then delete it
// Visit: http://localhost/trueocc/generate_hash.php
$passwords = ['password', 'Admin@1234', 'pass123'];
foreach ($passwords as $p) {
    $hash = password_hash($p, PASSWORD_BCRYPT, ['cost'=>12]);
    echo "<p><strong>$p</strong>: <code>$hash</code></p>";
}
echo "<p style='color:red'><strong>DELETE THIS FILE after use!</strong></p>";
echo "<p>To fix admin password, run in MySQL:<br><code>UPDATE admins SET password='HASH_HERE' WHERE email='admin@trueocc.com';</code></p>";
?>
