# GUACAS V4.2.2 — Web + PWA

Sistema de comunicação e operações para a Guarnição CAS, preparado para uso pelo navegador e instalação como PWA em celular ou computador.

## Recursos preservados

- Portal público e solicitação rápida de ocorrência.
- Central operacional, equipes, viaturas, materiais e GPS.
- Fichas APH, relatórios, assinaturas e carteirinhas.
- Perfis ADMIN, BASE, CAMPO e STAFF.
- 2FA de administradores, auditoria, backup e recuperação.
- GUACAS Público e GUACAS Bombeiros instaláveis como aplicativo.

## Dados que nunca entram no GitHub

O `.gitignore` e o fluxo de publicação bloqueiam bancos SQLite, chaves, fotos, assinaturas, backups, logs e arquivos de restauração. Esses dados existem somente na instalação da hospedagem e nas cópias de segurança autorizadas.

## Implantação

O GitHub Actions usa os segredos `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_REMOTE_DIR` e `GUACAS_SETUP_CODE`. O código de implantação protege a criação do primeiro Administrador e deve ter pelo menos 12 caracteres.

## Uso responsável

Antes de registrar dados reais, execute a homologação completa, ative HTTPS e 2FA, teste a restauração de backup, defina retenção/LGPD e mantenha rádio, telefone e outros canais redundantes. Hospedagem gratuita deve ser tratada como ambiente inicial de teste/homologação até revisão independente de segurança e capacidade.
