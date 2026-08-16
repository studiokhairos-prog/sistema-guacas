<?php
require __DIR__ . '/config.php';
$count = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count === 0) { header('Location: setup.php'); exit; }
$u = current_user();
if (!$u) { header('Location: login.php'); exit; }
header('Location: ' . (($u['role'] === 'CAMPO') ? 'campo.php' : 'base.php'));
