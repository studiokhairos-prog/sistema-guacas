<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);
$name=basename((string)($_GET['file']??''));
$path=BACKUP_DIR.'/'.$name;
if(!preg_match('/\.sqlite$/i',$name)||!is_file($path)){http_response_code(404);exit('Backup não encontrado.');}
security_audit(db(),(int)$admin['id'],'BACKUP_DOWNLOADED','BACKUP',$name,true,'Arquivo de backup baixado.');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.filesize($path));
header('Cache-Control: no-store');
readfile($path);
