<?php
$db = new PDO('mysql:host=localhost;dbname=database;charset=utf8mb4', 'root', '');
$stmt = $db->prepare('SELECT id, role, is_active, email FROM users WHERE id = ?');
$stmt->execute([3]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
var_export($result);
