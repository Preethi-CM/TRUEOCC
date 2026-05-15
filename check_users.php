<?php
require 'backend/includes/config.php';

echo "=== All Users ===\n";
$result = $db->query('SELECT id, email, role, is_active FROM users');
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
  echo "ID={$r['id']}, Email={$r['email']}, Role={$r['role']}, Active={$r['is_active']}\n";
}

echo "\n=== Auth Test ===\n";
// Test basic auth
$email = 'alice@email.com';
$password = 'password';
$role = 'seeker';

$u = $db->prepare('SELECT id, password, role, is_active FROM users WHERE email = ? AND role = ?');
$u->execute(array($email, $role));
$user = $u->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  echo "User not found: $email / $role\n";
} else {
  echo "User found: ID={$user['id']}, Role={$user['role']}, Active={$user['is_active']}\n";
  if (password_verify($password, $user['password'])) {
    echo "Password matches!\n";
  } else {
    echo "Password does not match\n";
  }
}
?>
