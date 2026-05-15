<?php
require 'backend/includes/helpers.php';

$email = 'preethiacharya2005@gmail.com';
$password = 'Preethi@1';
$role = 'seeker';

$user = db()->row("SELECT id, email, password, role, is_active FROM users WHERE email = ? AND role = ?", [$email, $role]);

if (!$user) {
    echo "User not found: $email / $role\n";
} else {
    echo "User found: ID={$user['id']}, Role={$user['role']}, Active={$user['is_active']}\n";
    if (password_verify($password, $user['password'])) {
        echo "✓ Password matches!\n";
    } else {
        echo "✗ Password does NOT match\n";
        echo "Hash: " . substr($user['password'], 0, 30) . "...\n";
    }
}
?>
