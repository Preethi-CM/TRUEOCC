<?php
require 'backend/includes/helpers.php';

// Create or update a test user with password 'password'
$email = 'test@email.com';
$password = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);

// First, try to update if exists
$existing = db()->row("SELECT id FROM users WHERE email = ?", [$email]);

if ($existing) {
    // Update existing
    db()->exec("UPDATE users SET password = ? WHERE email = ?", [$password, $email]);
    echo "Updated test user: $email\n";
} else {
    // Insert new
    $id = db()->insert(
        "INSERT INTO users (email, password, role, is_active) VALUES (?, ?, ?, ?)",
        [$email, $password, 'seeker', 1]
    );
    echo "Created test user: $email (ID: $id)\n";
}

// Verify it works
$user = db()->row("SELECT id, email, role FROM users WHERE email = ? AND role = ?", [$email, 'seeker']);
if ($user && password_verify('password', db()->row("SELECT password FROM users WHERE id = ?", [$user['id']])['password'])) {
    echo "✓ Test credentials work: $email / password\n";
} else {
    echo "✗ Test credentials failed\n";
}
?>
