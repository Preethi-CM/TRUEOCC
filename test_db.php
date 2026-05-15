<?php
require 'backend/includes/helpers.php';

echo "=== Testing Database Connection ===\n";
try {
    $users = db()->rows('SELECT id, email, role, is_active FROM users LIMIT 10');
    echo "Connected successfully! Found " . count($users) . " users:\n";
    foreach ($users as $user) {
        echo "- ID {$user['id']}: {$user['email']} ({$user['role']}) Active: {$user['is_active']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Auth Test ===\n";
$email = 'alice@email.com';
$password = 'password';
$role = 'seeker';

$user = db()->row("SELECT id, password, role, is_active FROM users WHERE email = ? AND role = ?", [$email, $role]);

if (!$user) {
    echo "User not found: $email / $role\n";
} else {
    echo "User found: ID={$user['id']}, Role={$user['role']}, Active={$user['is_active']}\n";
    if (password_verify($password, $user['password'])) {
        echo "Password matches!\n";
    } else {
        echo "Password does NOT match\n";
    }
}
?>
