<?php
require __DIR__ . '/config.php';
$admin=require_user(['ADMIN']);$pdo=db();
$key=cloud_backup_recovery_key_hex();
cloud_setting_write($pdo,'cloud_key_exported_at',now_iso(),(int)$admin['id']);
security_audit($pdo,(int)$admin['id'],'CLOUD_RECOVERY_KEY_EXPORTED','CLOUD','RECOVERY_KEY',true,'Chave de recuperação dos backups da nuvem exportada.');
admin_audit($pdo,(int)$admin['id'],'EXPORT','CLOUD_RECOVERY_KEY','GLOBAL','Cópia de segurança da chave','Chave de recuperação do backup criptografado exportada.');
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="GUACAS_CHAVE_RECUPERACAO_NUVEM.txt"');
header('Cache-Control: no-store');
echo "GUACAS - CHAVE DE RECUPERAÇÃO DA NUVEM\n";
echo "========================================\n\n";
echo "GUARDE ESTE ARQUIVO FORA DO SERVIDOR E FORA DA MESMA CONTA DRIVE.\n";
echo "Sem esta chave, um backup criptografado pode não ser recuperável se a instalação do servidor for perdida.\n\n";
echo "CHAVE:\n".$key."\n\n";
echo "Gerada/baixada em: ".now_iso()."\n";
