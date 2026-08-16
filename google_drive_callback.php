<?php
require __DIR__ . '/config.php';
require_user(['ADMIN']);
header('Location: nuvem.php?legacy=1');
exit;
