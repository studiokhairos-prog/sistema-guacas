<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

const APP_NAME = 'GUACAS';
const APP_FULL_NAME = 'Sistema Integrado de Comunicação e Operações de Bombeiros Civis';
const ORG_NAME = 'Corpo de Bombeiro Civil — Guarnição CAS';
const DB_PATH = __DIR__ . '/data/sicobc.sqlite';
const MAX_MAIN_ADMINS = 4;
const SIGNATURE_DIR = __DIR__ . '/data/signatures';
const USER_PHOTO_DIR = __DIR__ . '/data/user_photos';
const USER_SIGNATURE_DIR = __DIR__ . '/data/user_signatures';
const BACKUP_DIR = __DIR__ . '/data/backups';
const APP_SECRET_FILE = __DIR__ . '/data/app_secret.key';
const SETUP_CODE_HASH_FILE = __DIR__ . '/data/setup_code.hash';
const RESTORE_PENDING_FILE = __DIR__ . '/data/restore_pending.sqlite';
const CLOUD_BACKUP_KEY_FILE = __DIR__ . '/data/cloud_backup.key';
const SIMPLE_CLOUD_DIR = __DIR__ . '/data/cloud_sync';

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(self)');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cache-Control: no-store');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('GUACASSESSID');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $isHttps,
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ]);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0775, true);
    if (!is_dir(SIGNATURE_DIR)) mkdir(SIGNATURE_DIR, 0775, true);
    if (!is_dir(USER_PHOTO_DIR)) mkdir(USER_PHOTO_DIR, 0775, true);
    if (!is_dir(USER_SIGNATURE_DIR)) mkdir(USER_SIGNATURE_DIR, 0775, true);
    if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0775, true);
    if (!is_dir(SIMPLE_CLOUD_DIR)) mkdir(SIMPLE_CLOUD_DIR, 0775, true);

    apply_pending_restore_before_database_open();

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode=WAL;');
    $pdo->exec('PRAGMA foreign_keys=ON;');
    $pdo->exec('PRAGMA busy_timeout=5000;');

    migrate($pdo);
    if (is_file(SETUP_CODE_HASH_FILE) && (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) {
        @unlink(SETUP_CODE_HASH_FILE);
    }
    maybe_create_automatic_backup($pdo);
    return $pdo;
}

function table_columns(PDO $pdo, string $table): array {
    $cols = [];
    foreach ($pdo->query("PRAGMA table_info($table)") as $row) $cols[] = $row['name'];
    return $cols;
}

function migrate(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            war_name TEXT,
            bc_name TEXT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('ADMIN','BASE','CAMPO','STAFF')),
            team TEXT,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS occurrences (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            protocol TEXT NOT NULL UNIQUE,
            nature TEXT,
            type TEXT NOT NULL,
            address TEXT NOT NULL,
            priority TEXT NOT NULL DEFAULT 'MEDIA',
            team TEXT,
            status TEXT NOT NULL DEFAULT 'ABERTA',
            details TEXT,
            lat REAL,
            lng REAL,
            created_by INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(created_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS occurrence_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            occurrence_id INTEGER NOT NULL,
            op_uuid TEXT UNIQUE,
            event_type TEXT NOT NULL,
            old_status TEXT,
            new_status TEXT,
            note TEXT,
            user_id INTEGER,
            device_id TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY(occurrence_id) REFERENCES occurrences(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS aph_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            client_uuid TEXT UNIQUE,
            occurrence_id INTEGER,
            patient_name TEXT,
            cns TEXT,
            status TEXT NOT NULL DEFAULT 'RASCUNHO',
            data_json TEXT NOT NULL DEFAULT '{}',
            content_hash TEXT NOT NULL DEFAULT '',
            version INTEGER NOT NULL DEFAULT 1,
            created_by INTEGER,
            updated_by INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            archived_at TEXT,
            FOREIGN KEY(occurrence_id) REFERENCES occurrences(id),
            FOREIGN KEY(created_by) REFERENCES users(id),
            FOREIGN KEY(updated_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS aph_signatures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            aph_id INTEGER NOT NULL,
            signer_user_id INTEGER NOT NULL,
            signer_name TEXT NOT NULL,
            signer_bc_name TEXT,
            signer_system_role TEXT NOT NULL,
            signature_capacity TEXT NOT NULL,
            signature_path TEXT NOT NULL,
            signed_at TEXT NOT NULL,
            document_hash TEXT NOT NULL,
            valid INTEGER NOT NULL DEFAULT 1,
            invalidated_at TEXT,
            invalidated_reason TEXT,
            FOREIGN KEY(aph_id) REFERENCES aph_records(id),
            FOREIGN KEY(signer_user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS aph_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            aph_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            user_id INTEGER,
            details TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY(aph_id) REFERENCES aph_records(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS teams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            code TEXT,
            active INTEGER NOT NULL DEFAULT 1,
            notes TEXT,
            created_by INTEGER,
            updated_by INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(created_by) REFERENCES users(id),
            FOREIGN KEY(updated_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS team_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            assigned_at TEXT NOT NULL,
            removed_at TEXT,
            UNIQUE(team_id,user_id),
            FOREIGN KEY(team_id) REFERENCES teams(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS team_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            admin_user_id INTEGER NOT NULL,
            justification TEXT NOT NULL,
            observations TEXT NOT NULL DEFAULT '',
            before_json TEXT,
            after_json TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY(team_id) REFERENCES teams(id),
            FOREIGN KEY(admin_user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL DEFAULT '',
            updated_by INTEGER,
            updated_at TEXT,
            FOREIGN KEY(updated_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS occurrence_catalog (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nature TEXT NOT NULL,
            type TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 100,
            UNIQUE(nature,type)
        );

        CREATE TABLE IF NOT EXISTS admin_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_user_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id TEXT,
            justification TEXT,
            details TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY(admin_user_id) REFERENCES users(id)
        );

        CREATE INDEX IF NOT EXISTS idx_occ_status ON occurrences(status);
        CREATE INDEX IF NOT EXISTS idx_occ_updated ON occurrences(updated_at);
        CREATE INDEX IF NOT EXISTS idx_event_occ ON occurrence_events(occurrence_id);
        CREATE INDEX IF NOT EXISTS idx_aph_patient ON aph_records(patient_name);
        CREATE INDEX IF NOT EXISTS idx_aph_cns ON aph_records(cns);
        CREATE INDEX IF NOT EXISTS idx_aph_occ ON aph_records(occurrence_id);
        CREATE INDEX IF NOT EXISTS idx_aph_updated ON aph_records(updated_at);
        CREATE INDEX IF NOT EXISTS idx_aph_sig ON aph_signatures(aph_id,valid);
        CREATE INDEX IF NOT EXISTS idx_team_members_team ON team_members(team_id,active);
        CREATE UNIQUE INDEX IF NOT EXISTS idx_team_member_one_active_team ON team_members(user_id) WHERE active=1;
        CREATE INDEX IF NOT EXISTS idx_team_audit_team ON team_audit(team_id,id);
        CREATE INDEX IF NOT EXISTS idx_occ_catalog ON occurrence_catalog(active,nature,sort_order);
        CREATE INDEX IF NOT EXISTS idx_admin_audit ON admin_audit(created_at);

        CREATE TRIGGER IF NOT EXISTS trg_max_admin_insert
        BEFORE INSERT ON users
        WHEN NEW.role='ADMIN' AND NEW.active=1 AND (SELECT COUNT(*) FROM users WHERE role='ADMIN' AND active=1) >= 4
        BEGIN
            SELECT RAISE(ABORT, 'Limite de 4 Administradores Gerais');
        END;

        CREATE TRIGGER IF NOT EXISTS trg_max_admin_update
        BEFORE UPDATE OF role,active ON users
        WHEN NEW.role='ADMIN' AND NEW.active=1
             AND NOT (OLD.role='ADMIN' AND OLD.active=1)
             AND (SELECT COUNT(*) FROM users WHERE role='ADMIN' AND active=1) >= 4
        BEGIN
            SELECT RAISE(ABORT, 'Limite de 4 Administradores Gerais');
        END;
    ");

    $userCols = table_columns($pdo, 'users');
    if (!in_array('war_name', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN war_name TEXT");
    if (!in_array('bc_name', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN bc_name TEXT");
    if (!in_array('financial_status', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN financial_status TEXT NOT NULL DEFAULT 'REGULAR'");
    if (!in_array('deleted_at', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN deleted_at TEXT");
    if (!in_array('deleted_by', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN deleted_by INTEGER");
    if (!in_array('delete_reason', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN delete_reason TEXT");
    if (!in_array('blood_type', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN blood_type TEXT NOT NULL DEFAULT 'NÃO SABE'");
    if (!in_array('registration_number', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN registration_number TEXT");
    if (!in_array('firefighter_certificate_number', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN firefighter_certificate_number TEXT");
    if (!in_array('photo_path', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN photo_path TEXT");
    if (!in_array('card_issued_at', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN card_issued_at TEXT");
    if (!in_array('card_updated_at', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN card_updated_at TEXT");
    if (!in_array('card_updated_by', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN card_updated_by INTEGER");
    if (!in_array('email', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
    if (!in_array('registered_signature_path', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN registered_signature_path TEXT");
    if (!in_array('registered_signature_updated_at', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN registered_signature_updated_at TEXT");
    if (!in_array('registered_signature_updated_by', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN registered_signature_updated_by INTEGER");
    if (!in_array('cpf_hash', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN cpf_hash TEXT");
    if (!in_array('cpf_last4', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN cpf_last4 TEXT");
    if (!in_array('birth_date', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN birth_date TEXT");
    if (!in_array('two_factor_secret', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_secret TEXT");
    if (!in_array('two_factor_enabled', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER NOT NULL DEFAULT 0");
    if (!in_array('two_factor_recovery_hashes', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_recovery_hashes TEXT");
    if (!in_array('two_factor_enabled_at', $userCols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled_at TEXT");

    // Padroniza registros existentes sem quebrar o login ao migrar versões antigas.
    $existingUsers = $pdo->query("SELECT id,name,war_name,username,email,firefighter_certificate_number FROM users ORDER BY id")->fetchAll();
    $upExisting = $pdo->prepare("UPDATE users SET name=?,war_name=?,username=?,email=?,firefighter_certificate_number=? WHERE id=?");
    foreach ($existingUsers as $existingUser) {
        $normalizedUsername = upper_text((string)$existingUser['username']);
        $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id<>? AND username=?");
        $dup->execute([(int)$existingUser['id'],$normalizedUsername]);
        if ((int)$dup->fetchColumn() > 0) $normalizedUsername = (string)$existingUser['username'];
        $upExisting->execute([
            upper_text((string)$existingUser['name']),
            upper_text((string)$existingUser['war_name']),
            $normalizedUsername,
            lower_email((string)($existingUser['email']??'')) ?: null,
            upper_text((string)($existingUser['firefighter_certificate_number']??'')) ?: null,
            (int)$existingUser['id']
        ]);
    }

    $aphCols = table_columns($pdo, 'aph_records');
    if (!in_array('deleted_at', $aphCols, true)) $pdo->exec("ALTER TABLE aph_records ADD COLUMN deleted_at TEXT");
    if (!in_array('deleted_by', $aphCols, true)) $pdo->exec("ALTER TABLE aph_records ADD COLUMN deleted_by INTEGER");
    if (!in_array('delete_reason', $aphCols, true)) $pdo->exec("ALTER TABLE aph_records ADD COLUMN delete_reason TEXT");

    // Registros antigos: usa o nome atual como nome de farda temporário.
    $st = $pdo->query("SELECT id,name,COALESCE(war_name,'') AS war_name FROM users");
    foreach ($st as $u) {
        if (trim((string)$u['war_name']) === '') {
            $parts = preg_split('/\s+/', trim((string)$u['name'])) ?: [];
            $fallback = end($parts) ?: (string)$u['name'];
            $up = $pdo->prepare("UPDATE users SET war_name=? WHERE id=?");
            $up->execute([$fallback, (int)$u['id']]);
        }
    }
    recalculate_all_bc_names($pdo);

    $occCols = table_columns($pdo, 'occurrences');
    if (!in_array('nature', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN nature TEXT");
    if (!in_array('source', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN source TEXT NOT NULL DEFAULT 'INTERNA'");
    if (!in_array('requester_name', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN requester_name TEXT");
    if (!in_array('requester_phone', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN requester_phone TEXT");
    if (!in_array('requester_relation', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN requester_relation TEXT");
    if (!in_array('patient_name_hint', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN patient_name_hint TEXT");
    if (!in_array('assigned_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN assigned_at TEXT");
    if (!in_array('dispatched_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN dispatched_at TEXT");
    if (!in_array('en_route_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN en_route_at TEXT");
    if (!in_array('on_scene_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN on_scene_at TEXT");
    if (!in_array('care_started_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN care_started_at TEXT");
    if (!in_array('returning_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN returning_at TEXT");
    if (!in_array('closed_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN closed_at TEXT");
    if (!in_array('vehicle_id', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN vehicle_id INTEGER");
    if (!in_array('occurrence_level', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN occurrence_level TEXT NOT NULL DEFAULT 'NAO_CLASSIFICADO'");
    if (!in_array('requested_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN requested_at TEXT");
    if (!in_array('requester_gps_accuracy', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN requester_gps_accuracy REAL");
    if (!in_array('central_acknowledged_at', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN central_acknowledged_at TEXT");
    if (!in_array('central_acknowledged_by', $occCols, true)) $pdo->exec("ALTER TABLE occurrences ADD COLUMN central_acknowledged_by INTEGER");
    $pdo->exec("UPDATE occurrences SET requested_at=created_at WHERE requested_at IS NULL OR requested_at=''");



    $pdo->exec("
        CREATE TABLE IF NOT EXISTS occurrence_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            occurrence_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(occurrence_id) REFERENCES occurrences(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS team_presence (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            team TEXT,
            lat REAL,
            lng REAL,
            accuracy REAL,
            last_seen TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS vehicles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prefix TEXT NOT NULL UNIQUE,
            description TEXT NOT NULL,
            plate TEXT,
            team_id INTEGER,
            status TEXT NOT NULL DEFAULT 'DISPONIVEL',
            active INTEGER NOT NULL DEFAULT 1,
            notes TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(team_id) REFERENCES teams(id)
        );

        CREATE TABLE IF NOT EXISTS vehicle_checklists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            vehicle_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            odometer TEXT,
            fuel_level TEXT,
            checklist_json TEXT NOT NULL DEFAULT '{}',
            notes TEXT,
            status TEXT NOT NULL DEFAULT 'OK',
            created_at TEXT NOT NULL,
            FOREIGN KEY(vehicle_id) REFERENCES vehicles(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            category TEXT,
            quantity REAL NOT NULL DEFAULT 0,
            minimum_quantity REAL NOT NULL DEFAULT 0,
            unit TEXT NOT NULL DEFAULT 'UN',
            active INTEGER NOT NULL DEFAULT 1,
            notes TEXT,
            updated_by INTEGER,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(updated_by) REFERENCES users(id)
        );

        CREATE INDEX IF NOT EXISTS idx_occ_messages_occ ON occurrence_messages(occurrence_id,id);
        CREATE INDEX IF NOT EXISTS idx_presence_team ON team_presence(team,last_seen);
        CREATE INDEX IF NOT EXISTS idx_vehicle_status ON vehicles(active,status);
        CREATE INDEX IF NOT EXISTS idx_vehicle_checklist ON vehicle_checklists(vehicle_id,created_at);
        CREATE INDEX IF NOT EXISTS idx_materials_active ON materials(active,category,name);

        CREATE TABLE IF NOT EXISTS password_recovery_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            registration_number TEXT,
            success INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        );

        CREATE INDEX IF NOT EXISTS idx_password_recovery_user ON password_recovery_events(user_id,created_at);

        CREATE TABLE IF NOT EXISTS device_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            device_label TEXT,
            user_agent TEXT,
            ip_hash TEXT,
            created_at TEXT NOT NULL,
            last_seen TEXT NOT NULL,
            revoked_at TEXT,
            revoked_by INTEGER,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(revoked_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS security_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT,
            entity_id TEXT,
            success INTEGER NOT NULL DEFAULT 1,
            ip_hash TEXT,
            device_id INTEGER,
            details TEXT,
            prev_hash TEXT,
            event_hash TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(device_id) REFERENCES device_sessions(id)
        );

        CREATE TABLE IF NOT EXISTS homologation_checks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            check_key TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'PENDENTE',
            notes TEXT,
            checked_by INTEGER,
            checked_at TEXT,
            sort_order INTEGER NOT NULL DEFAULT 100,
            FOREIGN KEY(checked_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS cloud_backup_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider TEXT NOT NULL DEFAULT 'GOOGLE_DRIVE',
            account_email TEXT,
            local_file TEXT,
            remote_file_id TEXT,
            remote_name TEXT,
            status TEXT NOT NULL,
            details TEXT,
            created_at TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_device_user ON device_sessions(user_id,last_seen);
        CREATE INDEX IF NOT EXISTS idx_security_audit_created ON security_audit(created_at);
        CREATE INDEX IF NOT EXISTS idx_homologation_status ON homologation_checks(status,category,sort_order);
    ");

    $adminAuditCols = table_columns($pdo, 'admin_audit');
    if (!in_array('ip_hash', $adminAuditCols, true)) $pdo->exec("ALTER TABLE admin_audit ADD COLUMN ip_hash TEXT");
    if (!in_array('user_agent', $adminAuditCols, true)) $pdo->exec("ALTER TABLE admin_audit ADD COLUMN user_agent TEXT");
    if (!in_array('prev_hash', $adminAuditCols, true)) $pdo->exec("ALTER TABLE admin_audit ADD COLUMN prev_hash TEXT");
    if (!in_array('event_hash', $adminAuditCols, true)) $pdo->exec("ALTER TABLE admin_audit ADD COLUMN event_hash TEXT");

    $recoveryCols = table_columns($pdo, 'password_recovery_events');
    if (!in_array('ip_hash', $recoveryCols, true)) $pdo->exec("ALTER TABLE password_recovery_events ADD COLUMN ip_hash TEXT");
    if (!in_array('user_agent', $recoveryCols, true)) $pdo->exec("ALTER TABLE password_recovery_events ADD COLUMN user_agent TEXT");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_recovery_ip ON password_recovery_events(ip_hash,created_at)");

    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS trg_security_audit_no_update
        BEFORE UPDATE ON security_audit
        BEGIN SELECT RAISE(ABORT,'AUDITORIA DE SEGURANÇA IMUTÁVEL'); END;

        CREATE TRIGGER IF NOT EXISTS trg_security_audit_no_delete
        BEFORE DELETE ON security_audit
        BEGIN SELECT RAISE(ABORT,'AUDITORIA DE SEGURANÇA IMUTÁVEL'); END;

        CREATE TRIGGER IF NOT EXISTS trg_admin_audit_no_update
        BEFORE UPDATE ON admin_audit
        BEGIN SELECT RAISE(ABORT,'AUDITORIA ADMINISTRATIVA IMUTÁVEL'); END;

        CREATE TRIGGER IF NOT EXISTS trg_admin_audit_no_delete
        BEFORE DELETE ON admin_audit
        BEGIN SELECT RAISE(ABORT,'AUDITORIA ADMINISTRATIVA IMUTÁVEL'); END;
    ");

    ensure_user_registration_numbers($pdo);
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_user_registration_number ON users(registration_number)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_user_email_lower ON users(lower(email)) WHERE email IS NOT NULL AND email<>''");

    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('central_base_address','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('system_name','GUACAS',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('whatsapp_occurrence','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('whatsapp_complaints','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('dashboard_refresh_seconds','3',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('gps_enabled','1',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('session_idle_minutes','20',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('backup_enabled','1',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('backup_keep_days','30',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('admin_2fa_required','0',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('privacy_contact','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('privacy_retention','DEFINIR PELA ORGANIZAÇÃO',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('system_public_url','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_enabled','0',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_client_id','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_client_secret_enc','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_refresh_token_enc','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_email','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_folder_id','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_connected_at','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_auto_backup','1',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_last_upload_at','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_last_upload_file','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('google_drive_last_error','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('cloud_key_exported_at','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('sync_cloud_enabled','0',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('sync_cloud_auto_backup','1',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('sync_cloud_folder','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('sync_cloud_email','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('sync_cloud_last_copy_at','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('sync_cloud_last_copy_file','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('sync_cloud_last_error','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('sync_cloud_easy_mode','1',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('production_mode','0',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('production_hostname','',?)")->execute([now_iso()]);
    $pdo->prepare("INSERT OR IGNORE INTO system_settings(setting_key,setting_value,updated_at) VALUES('production_activated_at','',?)")->execute([now_iso()]);

    if ((int)$pdo->query("SELECT COUNT(*) FROM homologation_checks")->fetchColumn() === 0) {
        $checks = [
            ['LOGIN_ADMIN','Login e permissões dos Administradores Gerais','SEGURANÇA',10],
            ['TWO_FACTOR','Autenticação em duas etapas dos Admins','SEGURANÇA',20],
            ['PASSWORD_RECOVERY','Recuperação de senha por cadastro + CPF + nascimento','SEGURANÇA',30],
            ['SESSION_TIMEOUT','Bloqueio por inatividade','SEGURANÇA',40],
            ['DEVICE_REVOKE','Revogação de sessão/dispositivo de navegador','SEGURANÇA',50],
            ['BACKUP_CREATE','Criação manual e automática de backup','BACKUP',60],
            ['BACKUP_RESTORE','Restauração testada em cópia controlada','BACKUP',70],
            ['PUBLIC_REQUEST','Solicitação pública rápida e alerta na Central','OPERAÇÃO',80],
            ['PUBLIC_ALARM','Alarme repetitivo e ciência da Central','OPERAÇÃO',90],
            ['GPS_REQUESTER','GPS do solicitante e rota até ocorrência','OPERAÇÃO',100],
            ['FIELD_OFFLINE','Campo offline + sincronização ao reconectar','OPERAÇÃO',110],
            ['MULTI_PATIENT','Mais de um paciente/APAH na mesma ocorrência','APH',120],
            ['APH_SIGNATURE','Assinatura manual e cadastrada da Ficha APH','APH',130],
            ['APH_REPORT','Relatório automático + observação extra','APH',140],
            ['CARD_3X4','Carteirinha 3x4 e dados do bombeiro','CADASTROS',150],
            ['TEAM_RULES','Regras de equipe e auditoria de alterações','CADASTROS',160],
            ['VEHICLE_CHECK','Viaturas e checklist operacional','OPERAÇÃO',170],
            ['MATERIAL_STOCK','Materiais e estoque mínimo','OPERAÇÃO',180],
            ['PRIVACY','Aviso de privacidade e tratamento de dados','LGPD',190],
            ['FULL_FLOW','Simulação completa do chamado até encerramento','HOMOLOGAÇÃO',200],
        ];
        $ins=$pdo->prepare("INSERT OR IGNORE INTO homologation_checks(check_key,title,category,sort_order) VALUES(?,?,?,?)");
        foreach($checks as $c) $ins->execute($c);
    }

    if ((int)$pdo->query("SELECT COUNT(*) FROM occurrence_catalog")->fetchColumn() === 0) {
        $catalog = [
            ['ATENDIMENTO PRÉ-HOSPITALAR','Mal súbito',10],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Síncope / desmaio',20],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Dor torácica',30],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Dispneia / dificuldade respiratória',40],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Crise convulsiva',50],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Alteração glicêmica / hipoglicemia',60],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Reação alérgica / anafilaxia',70],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Suspeita de AVC',80],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Intoxicação / envenenamento',90],
            ['ATENDIMENTO PRÉ-HOSPITALAR','Urgência obstétrica / trabalho de parto',100],
            ['TRAUMA','Queda',110],
            ['TRAUMA','Acidente de trânsito',120],
            ['TRAUMA','Atropelamento',130],
            ['TRAUMA','Ferimento / corte',140],
            ['TRAUMA','Trauma contuso',150],
            ['TRAUMA','Suspeita de fratura / luxação',160],
            ['TRAUMA','Hemorragia',170],
            ['TRAUMA','Queimadura',180],
            ['TRAUMA','Trauma esportivo',190],
            ['EMERGÊNCIA CRÍTICA','Parada cardiorrespiratória (PCR)',200],
            ['EMERGÊNCIA CRÍTICA','Engasgo / OVACE',210],
            ['EMERGÊNCIA CRÍTICA','Afogamento',220],
            ['EMERGÊNCIA CRÍTICA','Choque elétrico',230],
            ['INCÊNDIO','Princípio de incêndio',240],
            ['INCÊNDIO','Incêndio em edificação',250],
            ['INCÊNDIO','Incêndio em veículo',260],
            ['INCÊNDIO','Incêndio em vegetação',270],
            ['INCÊNDIO','Vazamento / ocorrência com GLP ou gás',280],
            ['SALVAMENTO E APOIO','Salvamento em altura',290],
            ['SALVAMENTO E APOIO','Pessoa presa / confinada',300],
            ['SALVAMENTO E APOIO','Acidente com animal peçonhento',310],
            ['SALVAMENTO E APOIO','Alagamento / enchente',320],
            ['SALVAMENTO E APOIO','Apoio preventivo em evento',330],
            ['SALVAMENTO E APOIO','Outros',999],
        ];
        $ins = $pdo->prepare("INSERT OR IGNORE INTO occurrence_catalog(nature,type,sort_order) VALUES(?,?,?)");
        foreach ($catalog as $c) $ins->execute($c);
    }
}

function now_iso(): string {
    return date('Y-m-d\TH:i:sP');
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function json_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clear_auth_session(): void {
    unset(
        $_SESSION['uid'], $_SESSION['csrf'], $_SESSION['login_at'],
        $_SESSION['last_human_activity'], $_SESSION['device_session_id'],
        $_SESSION['pre_2fa_uid'], $_SESSION['pre_2fa_expires'],
        $_SESSION['pre_2fa_attempts']
    );
}

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;

    $pdo=db();
    $stmt = $pdo->prepare("SELECT id,name,war_name,bc_name,username,email,role,team,active,password_hash,COALESCE(financial_status,'REGULAR') AS financial_status,COALESCE(blood_type,'NÃO SABE') AS blood_type,registration_number,firefighter_certificate_number,photo_path,card_issued_at,card_updated_at,card_updated_by,registered_signature_path,registered_signature_updated_at,COALESCE(two_factor_enabled,0) AS two_factor_enabled,two_factor_secret,two_factor_recovery_hashes,two_factor_enabled_at FROM users WHERE id=? AND active=1 AND deleted_at IS NULL");
    $stmt->execute([$_SESSION['uid']]);
    $u = $stmt->fetch();
    if(!$u){ clear_auth_session(); return null; }

    $idleMinutes=max(5,min(240,(int)system_setting('session_idle_minutes','20')));
    $last=(int)($_SESSION['last_human_activity']??time());
    if(time()-$last > $idleMinutes*60){
        security_audit($pdo,(int)$u['id'],'SESSION_IDLE_TIMEOUT','SESSION',session_id(),true,'Sessão encerrada por inatividade.');
        clear_auth_session();
        return null;
    }

    $deviceId=(int)($_SESSION['device_session_id']??0);
    if($deviceId>0){
        $ds=$pdo->prepare("SELECT revoked_at FROM device_sessions WHERE id=? AND user_id=?");
        $ds->execute([$deviceId,(int)$u['id']]);
        $revoked=$ds->fetchColumn();
        if($revoked){
            security_audit($pdo,(int)$u['id'],'DEVICE_SESSION_REVOKED_BLOCK','DEVICE',(string)$deviceId,false,'Sessão de navegador revogada.');
            clear_auth_session();
            return null;
        }
        if(!isset($_SESSION['device_seen_touch']) || time()-(int)$_SESSION['device_seen_touch']>60){
            $pdo->prepare("UPDATE device_sessions SET last_seen=? WHERE id=?")->execute([now_iso(),$deviceId]);
            $_SESSION['device_seen_touch']=time();
        }
    }

    return $u;
}

function require_user(array $roles = []): array {
    $u = current_user();
    if (!$u) {
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) json_response(['ok'=>false,'error'=>'Sessão expirada ou dispositivo revogado'], 401);
        header('Location: login.php?expired=1');
        exit;
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    if(($u['role']??'')==='ADMIN' && system_setting('admin_2fa_required','0')==='1' && empty($u['two_factor_enabled'])){
        $script=basename((string)($_SERVER['SCRIPT_NAME']??''));
        $allowed=['seguranca.php','logout.php','login_2fa.php'];
        if(!in_array($script,$allowed,true)){
            header('Location: seguranca.php?setup2fa=1');
            exit;
        }
    }
    return $u;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function require_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) json_response(['ok'=>false,'error'=>'CSRF inválido'], 419);
}

function protocol_new(): string {
    $pdo = db();
    $year = date('Y');
    $prefix = "OC-$year-";
    $stmt = $pdo->prepare("SELECT protocol FROM occurrences WHERE protocol LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $n = 1;
    if ($last && preg_match('/(\d+)$/', (string)$last, $m)) $n = ((int)$m[1]) + 1;
    return $prefix . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

function aph_code_new(PDO $pdo): string {
    $year = date('Y');
    $prefix = "APH-$year-";
    $stmt = $pdo->prepare("SELECT code FROM aph_records WHERE code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $n = 1;
    if ($last && preg_match('/(\d+)$/', (string)$last, $m)) $n = ((int)$m[1]) + 1;
    return $prefix . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

function admin_count(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='ADMIN' AND active=1 AND deleted_at IS NULL")->fetchColumn();
}

function normalize_name(string $s): string {
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return mb_strtoupper($s, 'UTF-8');
}

function first_name(string $fullName): string {
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    return $parts[0] ?? trim($fullName);
}

function second_name_initial(string $fullName): string {
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    foreach (array_slice($parts, 1) as $p) {
        $n = normalize_name($p);
        if (!in_array($n, ['DE','DA','DO','DAS','DOS','E'], true) && $n !== '') return mb_substr($n,0,1,'UTF-8');
    }
    return '';
}

function recalculate_all_bc_names(PDO $pdo): void {
    $rows = $pdo->query("SELECT id,name,COALESCE(war_name,'') war_name FROM users ORDER BY id")->fetchAll();
    $groups = [];
    foreach ($rows as $r) {
        $war = normalize_name((string)$r['war_name']);
        if ($war === '') $war = 'SEM FARDA';
        $groups[$war][] = $r;
    }

    $up = $pdo->prepare("UPDATE users SET bc_name=? WHERE id=?");
    foreach ($groups as $war => $members) {
        if (count($members) === 1) {
            $up->execute(["BC $war", (int)$members[0]['id']]);
            continue;
        }

        $used = [];
        foreach ($members as $m) {
            $first = normalize_name(first_name((string)$m['name']));
            $initial = mb_substr($first,0,1,'UTF-8');
            $candidate = "BC $initial. $war";

            if (isset($used[$candidate])) {
                // Usa o menor prefixo possível do primeiro nome.
                $found = false;
                for ($len=2; $len<=mb_strlen($first,'UTF-8'); $len++) {
                    $prefix = mb_substr($first,0,$len,'UTF-8');
                    $test = "BC $prefix. $war";
                    if (!isset($used[$test])) {
                        $candidate = $test; $found = true; break;
                    }
                }
                if (!$found || isset($used[$candidate])) {
                    $second = second_name_initial((string)$m['name']);
                    $candidate = "BC $first" . ($second ? " $second." : '') . " $war";
                }
                if (isset($used[$candidate])) $candidate .= " " . (int)$m['id'];
            }

            $used[$candidate] = true;
            $up->execute([$candidate, (int)$m['id']]);
        }
    }
}


function user_photo_absolute_path(?string $photoPath): ?string {
    $photoPath = trim((string)$photoPath);
    if ($photoPath === '') return null;
    $name = basename($photoPath);
    $full = USER_PHOTO_DIR . DIRECTORY_SEPARATOR . $name;
    return is_file($full) ? $full : null;
}

function user_photo_mime(?string $photoPath): string {
    $full = user_photo_absolute_path($photoPath);
    if (!$full) return 'image/jpeg';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($full);
    return in_array($mime, ['image/jpeg','image/png','image/webp'], true) ? $mime : 'image/jpeg';
}

function store_user_photo(array $file): string {
    if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Selecione uma foto 3x4.');
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível receber a foto.');
    }
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('A foto deve ter no máximo 5 MB.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Upload de foto inválido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
    if ($ext === '') {
        throw new RuntimeException('Use foto JPG, PNG ou WEBP.');
    }

    if (!is_dir(USER_PHOTO_DIR) && !mkdir(USER_PHOTO_DIR, 0775, true) && !is_dir(USER_PHOTO_DIR)) {
        throw new RuntimeException('Não foi possível preparar a pasta de fotos.');
    }

    $name = 'bc_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = USER_PHOTO_DIR . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Não foi possível salvar a foto.');
    }
    @chmod($dest, 0640);
    return $name;
}

function delete_user_photo_file(?string $photoPath): void {
    $full = user_photo_absolute_path($photoPath);
    if ($full) @unlink($full);
}

function store_user_photo_data_url(string $dataUrl): string {
    $dataUrl = trim($dataUrl);
    if ($dataUrl === '') {
        throw new RuntimeException('Nenhuma foto da câmera foi capturada.');
    }

    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $dataUrl, $m)) {
        throw new RuntimeException('Formato da foto capturada inválido.');
    }

    $binary = base64_decode($m[2], true);
    if ($binary === false || strlen($binary) < 100) {
        throw new RuntimeException('Não foi possível processar a foto capturada.');
    }
    if (strlen($binary) > 5 * 1024 * 1024) {
        throw new RuntimeException('A foto capturada deve ter no máximo 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->buffer($binary);
    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
    if ($ext === '') {
        throw new RuntimeException('A imagem capturada não é JPG, PNG ou WEBP válido.');
    }

    if (!is_dir(USER_PHOTO_DIR) && !mkdir(USER_PHOTO_DIR, 0775, true) && !is_dir(USER_PHOTO_DIR)) {
        throw new RuntimeException('Não foi possível preparar a pasta de fotos.');
    }

    $name = 'bc_cam_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = USER_PHOTO_DIR . DIRECTORY_SEPARATOR . $name;

    if (file_put_contents($dest, $binary, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível salvar a foto capturada.');
    }
    @chmod($dest, 0640);
    return $name;
}

function receive_user_photo_from_form(string $fileField='photo_3x4', string $cameraField='webcam_photo_data'): ?string {
    $cameraData = trim((string)($_POST[$cameraField] ?? ''));
    if ($cameraData !== '') {
        return store_user_photo_data_url($cameraData);
    }

    if (isset($_FILES[$fileField]) && (int)($_FILES[$fileField]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        return store_user_photo($_FILES[$fileField]);
    }

    return null;
}


function issue_user_card(PDO $pdo, int $userId, int $adminId): void {
    $now = now_iso();
    $st = $pdo->prepare("
        UPDATE users
           SET card_issued_at=COALESCE(card_issued_at,?),
               card_updated_at=?,
               card_updated_by=?
         WHERE id=? AND deleted_at IS NULL
    ");
    $st->execute([$now,$now,$adminId,$userId]);
}

function issue_all_active_cards(PDO $pdo, int $adminId): int {
    $now = now_iso();
    $st = $pdo->prepare("
        UPDATE users
           SET card_issued_at=COALESCE(card_issued_at,?),
               card_updated_at=?,
               card_updated_by=?
         WHERE deleted_at IS NULL AND active=1
    ");
    $st->execute([$now,$now,$adminId]);
    return $st->rowCount();
}


function normalize_cpf(?string $value): string {
    return preg_replace('/\D+/', '', (string)$value) ?: '';
}

function valid_cpf(?string $value): bool {
    $cpf = normalize_cpf($value);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += ((int)$cpf[$i]) * (($t + 1) - $i);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int)$cpf[$t] !== $digit) return false;
    }
    return true;
}

function store_cpf_hash(string $cpf): string {
    $normalized = normalize_cpf($cpf);
    if (!valid_cpf($normalized)) {
        throw new RuntimeException('CPF inválido.');
    }
    return password_hash($normalized, PASSWORD_DEFAULT);
}

function cpf_last4(string $cpf): string {
    $normalized = normalize_cpf($cpf);
    return strlen($normalized) === 11 ? substr($normalized, -4) : '';
}

function cpf_masked_from_last4(?string $last4): string {
    $last4 = preg_replace('/\D+/', '', (string)$last4) ?: '';
    return strlen($last4) === 4 ? '***.***.***-' . substr($last4, 2, 2) . ' (final ' . $last4 . ')' : 'NÃO CADASTRADO';
}

function valid_birth_date(?string $value): bool {
    $value = trim((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) return false;
    $today = new DateTimeImmutable('today');
    $min = $today->modify('-100 years');
    $max = $today->modify('-14 years');
    return $d >= $min && $d <= $max;
}

function password_recovery_available(array $user): bool {
    return !empty($user['cpf_hash']) && !empty($user['birth_date']) && !empty($user['registration_number']);
}

function upper_text(?string $value): string {
    return mb_strtoupper(trim((string)$value), 'UTF-8');
}

function lower_email(?string $value): string {
    return mb_strtolower(trim((string)$value), 'UTF-8');
}

function normalize_record_text(array $data): array {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = normalize_record_text($value);
            continue;
        }
        if (!is_string($value)) continue;
        $k = mb_strtolower((string)$key, 'UTF-8');
        if (str_contains($k, 'email')) $data[$key] = lower_email($value);
        elseif (str_contains($k, 'password') || str_contains($k, 'csrf') || str_contains($k, 'uuid')) $data[$key] = $value;
        else $data[$key] = upper_text($value);
    }
    return $data;
}

function occurrence_level_options(): array {
    return ['NAO_CLASSIFICADO','N1_CRITICO','N2_URGENTE','N3_PRIORITARIO','N4_BAIXA_PRIORIDADE'];
}

function occurrence_level_label(string $level): string {
    return match($level) {
        'N1_CRITICO' => 'NÍVEL 1 — CRÍTICO',
        'N2_URGENTE' => 'NÍVEL 2 — URGENTE',
        'N3_PRIORITARIO' => 'NÍVEL 3 — PRIORITÁRIO',
        'N4_BAIXA_PRIORIDADE' => 'NÍVEL 4 — BAIXA PRIORIDADE',
        default => 'NÃO CLASSIFICADO — CENTRAL DEFINE',
    };
}

function occurrence_level_short(string $level): string {
    return match($level) {
        'N1_CRITICO' => 'N1 CRÍTICO',
        'N2_URGENTE' => 'N2 URGENTE',
        'N3_PRIORITARIO' => 'N3 PRIORITÁRIO',
        'N4_BAIXA_PRIORIDADE' => 'N4 BAIXA',
        default => 'NÃO CLASSIFICADO',
    };
}

function occurrence_level_priority(string $level): string {
    return match($level) {
        'N1_CRITICO' => 'CRITICA',
        'N2_URGENTE' => 'ALTA',
        'N4_BAIXA_PRIORIDADE' => 'BAIXA',
        default => 'MEDIA',
    };
}

function registered_signature_absolute_path(?string $signaturePath): ?string {
    $signaturePath = trim((string)$signaturePath);
    if ($signaturePath === '') return null;
    $name = basename($signaturePath);
    $full = USER_SIGNATURE_DIR . DIRECTORY_SEPARATOR . $name;
    return is_file($full) ? $full : null;
}

function save_registered_signature_data(string $dataUrl): string {
    if (!preg_match('#^data:image/png;base64,(.+)$#', $dataUrl, $m)) {
        throw new RuntimeException('ASSINATURA INVÁLIDA.');
    }
    $raw = base64_decode($m[1], true);
    if ($raw === false || strlen($raw) < 200 || strlen($raw) > 2_000_000) {
        throw new RuntimeException('ASSINATURA INVÁLIDA OU MUITO GRANDE.');
    }
    if (!is_dir(USER_SIGNATURE_DIR) && !mkdir(USER_SIGNATURE_DIR, 0775, true) && !is_dir(USER_SIGNATURE_DIR)) {
        throw new RuntimeException('NÃO FOI POSSÍVEL PREPARAR A PASTA DE ASSINATURAS.');
    }
    $name = 'bc_sig_' . bin2hex(random_bytes(16)) . '.png';
    $dest = USER_SIGNATURE_DIR . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($dest, $raw, LOCK_EX) === false) {
        throw new RuntimeException('NÃO FOI POSSÍVEL SALVAR A ASSINATURA.');
    }
    @chmod($dest, 0640);
    return $name;
}

function delete_registered_signature_file(?string $signaturePath): void {
    $full = registered_signature_absolute_path($signaturePath);
    if ($full) @unlink($full);
}

function blood_type_options(): array {
    return ['NÃO SABE','A+','A-','B+','B-','AB+','AB-','O+','O-'];
}

function ensure_user_registration_numbers(PDO $pdo): void {
    $rows=$pdo->query("SELECT id,created_at,COALESCE(registration_number,'') AS registration_number FROM users ORDER BY id")->fetchAll();
    $up=$pdo->prepare("UPDATE users SET registration_number=? WHERE id=?");
    foreach($rows as $r){
        if(trim((string)$r['registration_number'])!=='') continue;
        $year=preg_match('/^(\\d{4})/',(string)$r['created_at'],$m)?$m[1]:date('Y');
        $number='GUA-'.$year.'-'.str_pad((string)(int)$r['id'],6,'0',STR_PAD_LEFT);
        $up->execute([$number,(int)$r['id']]);
    }
}

function user_registration_number(PDO $pdo, int $userId): string {
    ensure_user_registration_numbers($pdo);
    $st=$pdo->prepare("SELECT registration_number FROM users WHERE id=?");
    $st->execute([$userId]);
    return (string)($st->fetchColumn() ?: '');
}

function role_label(string $role): string {
    return match($role) {
        'ADMIN' => 'ADMIN GERAL',
        'BASE' => 'BASE',
        'CAMPO' => 'CAMPO',
        'STAFF' => 'STAFF',
        default => $role,
    };
}

function canonicalize(mixed $value): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map('canonicalize', $value);
    ksort($value);
    foreach ($value as $k=>$v) $value[$k] = canonicalize($v);
    return $value;
}

function aph_content_hash(array $data): string {
    return hash('sha256', json_encode(canonicalize($data), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

function aph_audit(PDO $pdo, int $aphId, string $action, ?int $userId, string $details=''): void {
    $st = $pdo->prepare("INSERT INTO aph_audit(aph_id,action,user_id,details,created_at) VALUES(?,?,?,?,?)");
    $st->execute([$aphId,$action,$userId,$details,now_iso()]);
}

function aph_can_access(array $user, array $record): bool {
    if ($user['role'] !== 'CAMPO') return true;
    if (empty($record['occurrence_id'])) {
        return (int)($record['created_by'] ?? 0) === (int)($user['id'] ?? 0);
    }
    $st = db()->prepare("SELECT * FROM occurrences WHERE id=?");
    $st->execute([(int)$record['occurrence_id']]);
    $occ = $st->fetch();
    return $occ ? occurrence_mutation_allowed($user,$occ) : false;
}

function load_aph(int $id): ?array {
    $st = db()->prepare("SELECT * FROM aph_records WHERE id=?");
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function is_admin_general(array $user): bool {
    return ($user['role'] ?? '') === 'ADMIN';
}

function system_setting(string $key, string $default=''): string {
    $st = db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key=?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function app_display_name(): string {
    $name = trim(system_setting('system_name', APP_NAME));
    return $name !== '' ? $name : APP_NAME;
}

function update_system_setting(string $key, string $value, int $userId): void {
    $st = db()->prepare("INSERT INTO system_settings(setting_key,setting_value,updated_by,updated_at) VALUES(?,?,?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value,updated_by=excluded.updated_by,updated_at=excluded.updated_at");
    $st->execute([$key,$value,$userId,now_iso()]);
}

function current_app_base_url(): string {
    $configured = trim(system_setting('system_public_url',''));
    if ($configured !== '') return rtrim($configured,'/');

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:]+\])(?::\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = str_replace('\\','/',dirname($script));
    if ($dir === '/' || $dir === '.') $dir = '';
    return $scheme . '://' . $host . rtrim($dir,'/');
}

function app_absolute_url(string $file=''): string {
    return current_app_base_url() . '/' . ltrim($file,'/');
}

function cloud_setting(PDO $pdo, string $key, string $default=''): string {
    $st=$pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key=?");
    $st->execute([$key]);
    $v=$st->fetchColumn();
    return $v===false?$default:(string)$v;
}

function cloud_setting_write(PDO $pdo, string $key, string $value, ?int $userId=null): void {
    $st=$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,updated_by,updated_at) VALUES(?,?,?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value,updated_by=excluded.updated_by,updated_at=excluded.updated_at");
    $st->execute([$key,$value,$userId,now_iso()]);
}


function normalize_whatsapp_number(string $value): string {
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits === '') return '';
    // Se foi informado DDD + número brasileiro sem código do país, acrescenta 55.
    if ((strlen($digits) === 10 || strlen($digits) === 11) && !str_starts_with($digits, '55')) $digits = '55' . $digits;
    return $digits;
}

function whatsapp_url(string $settingKey, string $message=''): string {
    $number = normalize_whatsapp_number(system_setting($settingKey, ''));
    if ($number === '') return '';
    $url = 'https://wa.me/' . $number;
    if ($message !== '') $url .= '?text=' . rawurlencode($message);
    return $url;
}

function financial_status_label(string $status): string {
    return $status === 'INADIMPLENTE' ? 'INADIMPLENTE' : 'REGULAR';
}

function active_teams(PDO $pdo): array {
    return $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id=t.id AND tm.active=1) AS member_count FROM teams t WHERE t.active=1 ORDER BY t.name")->fetchAll();
}

function team_member_ids(PDO $pdo, int $teamId): array {
    $st=$pdo->prepare("SELECT user_id FROM team_members WHERE team_id=? AND active=1 ORDER BY user_id");
    $st->execute([$teamId]);
    return array_map('intval', array_column($st->fetchAll(), 'user_id'));
}

function team_snapshot(PDO $pdo, int $teamId): array {
    $st=$pdo->prepare("SELECT id,name,code,active,notes FROM teams WHERE id=?");
    $st->execute([$teamId]);
    $team=$st->fetch() ?: [];
    $team['member_ids']=team_member_ids($pdo,$teamId);
    return $team;
}

function sync_users_team_labels(PDO $pdo): void {
    $pdo->exec("UPDATE users SET team=NULL");
    $rows=$pdo->query("SELECT tm.user_id,t.name FROM team_members tm JOIN teams t ON t.id=tm.team_id WHERE tm.active=1 AND t.active=1")->fetchAll();
    $up=$pdo->prepare("UPDATE users SET team=? WHERE id=?");
    foreach($rows as $r) $up->execute([$r['name'],(int)$r['user_id']]);
}

function occurrence_catalog_grouped(PDO $pdo): array {
    $rows=$pdo->query("SELECT id,nature,type,sort_order FROM occurrence_catalog WHERE active=1 ORDER BY nature,sort_order,type")->fetchAll();
    $out=[];
    foreach($rows as $r) $out[$r['nature']][]=$r['type'];
    return $out;
}

function client_ip(): string {
    $raw=(string)($_SERVER['REMOTE_ADDR']??'LOCAL');
    return $raw!==''?$raw:'LOCAL';
}

function app_secret_key(): string {
    if(is_file(APP_SECRET_FILE)){
        $raw=file_get_contents(APP_SECRET_FILE);
        if(is_string($raw) && strlen($raw)>=32) return substr($raw,0,32);
    }
    $key=random_bytes(32);
    if(!is_dir(dirname(APP_SECRET_FILE))) mkdir(dirname(APP_SECRET_FILE),0775,true);
    file_put_contents(APP_SECRET_FILE,$key,LOCK_EX);
    @chmod(APP_SECRET_FILE,0600);
    return $key;
}

function privacy_hash(string $value): string {
    return hash_hmac('sha256',$value,app_secret_key());
}

function admin_audit(PDO $pdo, int $adminId, string $action, string $entityType, ?string $entityId=null, string $justification='', string $details=''): void {
    $created=now_iso();
    $prev=(string)($pdo->query("SELECT event_hash FROM admin_audit WHERE event_hash IS NOT NULL AND event_hash<>'' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '');
    $ip=privacy_hash(client_ip());
    $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);
    $payload=implode('|',[$prev,$adminId,$action,$entityType,(string)$entityId,$justification,$details,$created,$ip]);
    $hash=hash_hmac('sha256',$payload,app_secret_key());
    $st=$pdo->prepare("INSERT INTO admin_audit(admin_user_id,action,entity_type,entity_id,justification,details,created_at,ip_hash,user_agent,prev_hash,event_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([$adminId,$action,$entityType,$entityId,$justification,$details,$created,$ip,$ua,$prev?:null,$hash]);
}

function security_audit(PDO $pdo, ?int $userId, string $action, ?string $entityType=null, ?string $entityId=null, bool $success=true, string $details=''): void {
    $created=now_iso();
    $prev=(string)($pdo->query("SELECT event_hash FROM security_audit ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '');
    $ip=privacy_hash(client_ip());
    $deviceId=(int)($_SESSION['device_session_id']??0);
    $payload=implode('|',[$prev,(string)$userId,$action,(string)$entityType,(string)$entityId,$success?'1':'0',$ip,(string)$deviceId,$details,$created]);
    $hash=hash_hmac('sha256',$payload,app_secret_key());
    $st=$pdo->prepare("INSERT INTO security_audit(user_id,action,entity_type,entity_id,success,ip_hash,device_id,details,prev_hash,event_hash,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([$userId,$action,$entityType,$entityId,$success?1:0,$ip,$deviceId?:null,$details,$prev?:null,$hash,$created]);
}

function verify_audit_chain(PDO $pdo, string $table): bool {
    if(!in_array($table,['security_audit','admin_audit'],true)) return false;
    $rows=$pdo->query("SELECT * FROM $table WHERE event_hash IS NOT NULL AND event_hash<>'' ORDER BY id")->fetchAll();
    $prev='';
    foreach($rows as $r){
        if((string)($r['prev_hash']??'')!==$prev) return false;
        if($table==='security_audit'){
            $payload=implode('|',[$prev,(string)$r['user_id'],$r['action'],(string)$r['entity_type'],(string)$r['entity_id'],((int)$r['success']===1?'1':'0'),(string)$r['ip_hash'],(string)($r['device_id']??0),(string)$r['details'],$r['created_at']]);
        }else{
            $payload=implode('|',[$prev,(string)$r['admin_user_id'],$r['action'],$r['entity_type'],(string)$r['entity_id'],(string)$r['justification'],(string)$r['details'],$r['created_at'],(string)$r['ip_hash']]);
        }
        $expected=hash_hmac('sha256',$payload,app_secret_key());
        if(!hash_equals((string)$r['event_hash'],$expected)) return false;
        $prev=(string)$r['event_hash'];
    }
    return true;
}



function base32_encode_bytes(string $data): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits='';
    foreach(str_split($data) as $c) $bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);
    $out='';
    foreach(str_split($bits,5) as $chunk){
        if(strlen($chunk)<5) $chunk=str_pad($chunk,5,'0',STR_PAD_RIGHT);
        $out.=$alphabet[bindec($chunk)];
    }
    return $out;
}

function base32_decode_string(string $value): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $value=strtoupper(preg_replace('/[^A-Z2-7]/','',$value)??'');
    $bits='';
    foreach(str_split($value) as $c){
        $pos=strpos($alphabet,$c);
        if($pos===false) continue;
        $bits.=str_pad(decbin($pos),5,'0',STR_PAD_LEFT);
    }
    $out='';
    foreach(str_split($bits,8) as $chunk){
        if(strlen($chunk)===8) $out.=chr(bindec($chunk));
    }
    return $out;
}

function totp_secret_new(): string {
    return base32_encode_bytes(random_bytes(20));
}

function totp_code(string $secret, ?int $timestamp=null): string {
    $timestamp=$timestamp??time();
    $counter=intdiv($timestamp,30);
    $bin=pack('N2',0,$counter);
    $hash=hash_hmac('sha1',$bin,base32_decode_string($secret),true);
    $offset=ord($hash[19]) & 0x0f;
    $num=((ord($hash[$offset]) & 0x7f)<<24)
        |((ord($hash[$offset+1]) & 0xff)<<16)
        |((ord($hash[$offset+2]) & 0xff)<<8)
        |(ord($hash[$offset+3]) & 0xff);
    return str_pad((string)($num%1000000),6,'0',STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code): bool {
    $code=preg_replace('/\D+/','',$code)??'';
    if(strlen($code)!==6) return false;
    $now=time();
    for($i=-1;$i<=1;$i++){
        if(hash_equals(totp_code($secret,$now+$i*30),$code)) return true;
    }
    return false;
}

function encrypt_private_value(string $plain): string {
    if(!function_exists('openssl_encrypt')) throw new RuntimeException('Extensão OpenSSL necessária para proteger o segredo 2FA.');
    $iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($plain,'aes-256-gcm',app_secret_key(),OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false) throw new RuntimeException('Falha ao proteger segredo.');
    return base64_encode($iv.$tag.$cipher);
}

function decrypt_private_value(?string $encoded): string {
    if(!$encoded || !function_exists('openssl_decrypt')) return '';
    $raw=base64_decode($encoded,true);
    if($raw===false || strlen($raw)<29) return '';
    $iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);
    $plain=openssl_decrypt($cipher,'aes-256-gcm',app_secret_key(),OPENSSL_RAW_DATA,$iv,$tag);
    return is_string($plain)?$plain:'';
}

function recovery_codes_new(int $count=8): array {
    $codes=[];
    for($i=0;$i<$count;$i++){
        $raw=strtoupper(bin2hex(random_bytes(5)));
        $codes[]=substr($raw,0,5).'-'.substr($raw,5,5);
    }
    return $codes;
}

function recovery_hashes(array $codes): array {
    return array_map(fn($c)=>password_hash(strtoupper(trim((string)$c)),PASSWORD_DEFAULT),$codes);
}

function verify_two_factor_input(PDO $pdo, array $u, string $input, bool $consumeRecovery=true): bool {
    $input=strtoupper(trim($input));
    if(empty($u['two_factor_enabled'])) return true;

    $secret=decrypt_private_value((string)($u['two_factor_secret']??''));
    if($secret!=='' && totp_verify($secret,$input)) return true;

    $hashes=json_decode((string)($u['two_factor_recovery_hashes']??'[]'),true);
    if(!is_array($hashes)) $hashes=[];
    foreach($hashes as $idx=>$hash){
        if(password_verify($input,(string)$hash)){
            if($consumeRecovery){
                unset($hashes[$idx]);
                $pdo->prepare("UPDATE users SET two_factor_recovery_hashes=? WHERE id=?")
                    ->execute([json_encode(array_values($hashes)),(int)$u['id']]);
            }
            return true;
        }
    }
    return false;
}

function device_cookie_token(): ?string {
    $token=(string)($_COOKIE['GUACASDEVICE']??'');
    return preg_match('/^[a-f0-9]{64}$/',$token)?$token:null;
}

function create_or_touch_device_session(PDO $pdo, int $userId): int {
    $token=device_cookie_token();
    $now=now_iso();
    $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??'NAVEGADOR'),0,500);
    $label=preg_replace('/\s+/',' ',substr($ua,0,90)) ?: 'NAVEGADOR';
    $ip=privacy_hash(client_ip());

    if($token){
        $hash=hash('sha256',$token);
        $st=$pdo->prepare("SELECT id,revoked_at FROM device_sessions WHERE token_hash=? AND user_id=?");
        $st->execute([$hash,$userId]);
        $row=$st->fetch();
        if($row && empty($row['revoked_at'])){
            $pdo->prepare("UPDATE device_sessions SET last_seen=?,user_agent=?,ip_hash=? WHERE id=?")->execute([$now,$ua,$ip,(int)$row['id']]);
            return (int)$row['id'];
        }
    }

    $token=bin2hex(random_bytes(32));
    if(PHP_SAPI!=='cli' && !headers_sent()){
        $secure=(!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS'])!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
        setcookie('GUACASDEVICE',$token,[
            'expires'=>time()+31536000,
            'path'=>'/',
            'secure'=>$secure,
            'httponly'=>true,
            'samesite'=>'Lax'
        ]);
    }
    $hash=hash('sha256',$token);
    $st=$pdo->prepare("INSERT INTO device_sessions(user_id,token_hash,device_label,user_agent,ip_hash,created_at,last_seen) VALUES(?,?,?,?,?,?,?)");
    $st->execute([$userId,$hash,$label,$ua,$ip,$now,$now]);
    return (int)$pdo->lastInsertId();
}

function complete_login(PDO $pdo, array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid']=(int)$u['id'];
    $_SESSION['login_at']=time();
    $_SESSION['last_human_activity']=time();
    $_SESSION['device_session_id']=create_or_touch_device_session($pdo,(int)$u['id']);
    unset($_SESSION['pre_2fa_uid'],$_SESSION['pre_2fa_expires'],$_SESSION['pre_2fa_attempts']);
    csrf_token();
    security_audit($pdo,(int)$u['id'],'LOGIN_SUCCESS','SESSION',session_id(),true,'Login concluído.');
}

function login_rate_limited(PDO $pdo): bool {
    $ip=privacy_hash(client_ip());
    $since=date('Y-m-d\TH:i:sP',time()-15*60);
    $st=$pdo->prepare("SELECT COUNT(*) FROM security_audit WHERE action='LOGIN_FAILURE' AND success=0 AND ip_hash=? AND created_at>=?");
    $st->execute([$ip,$since]);
    return (int)$st->fetchColumn()>=8;
}

function password_recovery_rate_limited(PDO $pdo): bool {
    $ip=privacy_hash(client_ip());
    $since=date('Y-m-d\TH:i:sP',time()-15*60);
    $st=$pdo->prepare("SELECT COUNT(*) FROM password_recovery_events WHERE success=0 AND ip_hash=? AND created_at>=?");
    $st->execute([$ip,$since]);
    return (int)$st->fetchColumn()>=5;
}

function public_request_rate_limited(PDO $pdo): bool {
    $ip=privacy_hash(client_ip());
    $since=date('Y-m-d\TH:i:sP',time()-10*60);
    $st=$pdo->prepare("SELECT COUNT(*) FROM security_audit WHERE action='PUBLIC_OCCURRENCE_CREATED' AND success=1 AND ip_hash=? AND created_at>=?");
    $st->execute([$ip,$since]);
    return (int)$st->fetchColumn()>=5;
}

function sensitive_admin_auth(PDO $pdo, array $admin, string $password, string $twoFactor=''): bool {
    if(!password_verify($password,(string)$admin['password_hash'])) return false;
    if(!empty($admin['two_factor_enabled'])){
        return verify_two_factor_input($pdo,$admin,$twoFactor,false);
    }
    return true;
}

function sqlite_file_valid(string $path): bool {
    if(!is_file($path) || filesize($path)<100) return false;
    $fh=fopen($path,'rb');
    if(!$fh) return false;
    $header=fread($fh,16);
    fclose($fh);
    return $header==="SQLite format 3\x00";
}

function apply_pending_restore_before_database_open(): void {
    if(!is_file(RESTORE_PENDING_FILE)) return;
    if(!sqlite_file_valid(RESTORE_PENDING_FILE)){
        @unlink(RESTORE_PENDING_FILE);
        return;
    }
    if(is_file(DB_PATH)){
        if(!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR,0775,true);
        $emergency=BACKUP_DIR.'/pre_restore_'.date('Ymd_His').'.sqlite';
        @copy(DB_PATH,$emergency);
    }
    @unlink(DB_PATH.'-wal');@unlink(DB_PATH.'-shm');
    @copy(RESTORE_PENDING_FILE,DB_PATH);
    @unlink(RESTORE_PENDING_FILE);
}

function backup_file_name(string $label='manual'): string {
    $label=preg_replace('/[^a-z0-9_-]/i','_',strtolower($label)) ?: 'backup';
    return 'guacas_'.$label.'_'.date('Ymd_His').'.sqlite';
}

function create_database_backup(PDO $pdo, string $label='manual'): string {
    if(!is_dir(BACKUP_DIR) && !mkdir(BACKUP_DIR,0775,true) && !is_dir(BACKUP_DIR)){
        throw new RuntimeException('Não foi possível criar a pasta de backup.');
    }
    $name=backup_file_name($label);
    $path=BACKUP_DIR.'/'.$name;

    try{
        $safe=str_replace("'","''",$path);
        $pdo->exec("PRAGMA wal_checkpoint(FULL)");
        $pdo->exec("VACUUM INTO '$safe'");
    }catch(Throwable $e){
        $pdo->exec("PRAGMA wal_checkpoint(FULL)");
        if(!copy(DB_PATH,$path)) throw new RuntimeException('Falha ao copiar o banco para backup.');
    }

    if(!sqlite_file_valid($path)) throw new RuntimeException('Backup criado não passou na validação básica.');
    file_put_contents($path.'.sha256',hash_file('sha256',$path));
    @chmod($path,0640);
    return $path;
}

function cleanup_old_backups(int $days): void {
    $days=max(7,min(3650,$days));
    $cut=time()-$days*86400;
    foreach(glob(BACKUP_DIR.'/guacas_auto_*.sqlite')?:[] as $file){
        if(filemtime($file)<$cut){ @unlink($file); @unlink($file.'.sha256'); }
    }
}

function maybe_create_automatic_backup(PDO $pdo): void {
    try{
        $enabled=(string)$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='backup_enabled'")->fetchColumn();
        if($enabled!=='1') return;
        $today=date('Ymd');
        $existing=glob(BACKUP_DIR.'/guacas_auto_'.$today.'_*.sqlite')?:[];
        $created=null;
        if(!$existing) $created=create_database_backup($pdo,'auto_'.$today);

        if($created
            && cloud_setting($pdo,'sync_cloud_auto_backup','1')==='1'
            && sync_cloud_ready($pdo)){
            try{ sync_cloud_copy_encrypted_backup($pdo,$created); }
            catch(Throwable $cloudError){
                cloud_setting_write($pdo,'sync_cloud_last_error',substr($cloudError->getMessage(),0,1000),null);
            }
        }

        $days=(int)($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='backup_keep_days'")->fetchColumn() ?: 30);
        cleanup_old_backups($days);
    }catch(Throwable $e){
        // O sistema continua funcionando mesmo se o backup automático falhar.
    }
}

function backup_inventory(): array {
    $rows=[];
    foreach(glob(BACKUP_DIR.'/*.sqlite')?:[] as $path){
        if(basename($path)==='restore_pending.sqlite') continue;
        $rows[]=[
            'name'=>basename($path),
            'size'=>filesize($path),
            'mtime'=>filemtime($path),
            'sha256'=>is_file($path.'.sha256')?trim((string)file_get_contents($path.'.sha256')):hash_file('sha256',$path),
        ];
    }
    usort($rows,fn($a,$b)=>$b['mtime']<=>$a['mtime']);
    return $rows;
}



/* ==========================================================
   NUVEM SIMPLES — PASTA SINCRONIZADA
   Compatível com Google Drive para computador, OneDrive,
   Dropbox ou qualquer pasta local sincronizada por outro app.
   ========================================================== */


function simple_cloud_folder(): string {
    if(!is_dir(SIMPLE_CLOUD_DIR)) @mkdir(SIMPLE_CLOUD_DIR,0775,true);
    return SIMPLE_CLOUD_DIR;
}

function enable_simple_cloud(PDO $pdo, ?int $adminId=null, string $email=''): string {
    $folder=simple_cloud_folder();
    if(!is_dir($folder) && !mkdir($folder,0775,true) && !is_dir($folder)){
        throw new RuntimeException('Não foi possível criar a pasta interna de backup.');
    }
    if(!is_writable($folder)){
        throw new RuntimeException('A pasta interna de backup não está gravável pelo Apache.');
    }
    cloud_setting_write($pdo,'sync_cloud_folder',$folder,$adminId);
    cloud_setting_write($pdo,'sync_cloud_email',lower_email($email),$adminId);
    cloud_setting_write($pdo,'sync_cloud_enabled','1',$adminId);
    cloud_setting_write($pdo,'sync_cloud_easy_mode','1',$adminId);
    cloud_setting_write($pdo,'sync_cloud_last_error','',$adminId);
    return $folder;
}

function simple_cloud_relative_display(): string {
    return 'data'.DIRECTORY_SEPARATOR.'cloud_sync';
}

function normalize_sync_folder_path(string $path): string {
    $path=trim($path," \t\n\r\0\x0B\"'");
    if($path==='') return '';
    if(PHP_OS_FAMILY==='Windows'){
        $path=str_replace('/','\\',$path);
        if(preg_match('/^[A-Za-z]:\\\\?$/',$path)) return rtrim($path,'\\').'\\';
        return rtrim($path,'\\');
    }
    return rtrim($path,'/');
}

function sync_folder_is_absolute(string $path): bool {
    if($path==='') return false;
    if(PHP_OS_FAMILY==='Windows'){
        return (bool)preg_match('/^[A-Za-z]:\\\\/',$path) || str_starts_with($path,'\\\\');
    }
    return str_starts_with($path,'/');
}

function sync_folder_prepare(string $path): string {
    $path=normalize_sync_folder_path($path);
    if(!sync_folder_is_absolute($path)) throw new RuntimeException('Informe o caminho completo da pasta sincronizada.');
    if(!is_dir($path)){
        $parent=dirname($path);
        if(!is_dir($parent) || !is_writable($parent)){
            throw new RuntimeException('A pasta não existe e o sistema não tem permissão para criá-la.');
        }
        if(!mkdir($path,0775,true) && !is_dir($path)){
            throw new RuntimeException('Não foi possível criar a pasta de backup.');
        }
    }
    if(!is_writable($path)) throw new RuntimeException('A pasta existe, mas o Apache/PHP não tem permissão para gravar nela.');
    return $path;
}

function sync_cloud_folder(PDO $pdo): string {
    return normalize_sync_folder_path(cloud_setting($pdo,'sync_cloud_folder',''));
}

function sync_cloud_ready(PDO $pdo): bool {
    if(cloud_setting($pdo,'sync_cloud_enabled','0')!=='1') return false;
    $path=sync_cloud_folder($pdo);
    return $path!=='' && is_dir($path) && is_writable($path);
}

function detect_common_sync_folders(): array {
    $candidates=[];
    $add=function(string $path,string $label) use (&$candidates){
        $path=normalize_sync_folder_path($path);
        if($path!=='' && is_dir($path)){
            $candidates[$path]=[
                'path'=>$path,
                'label'=>$label,
                'writable'=>is_writable($path),
            ];
        }
    };

    $home=(string)(getenv('USERPROFILE') ?: getenv('HOME') ?: '');
    if($home!==''){
        foreach([
            'Google Drive'=>'Google Drive',
            'Meu Drive'=>'Meu Drive',
            'My Drive'=>'My Drive',
            'OneDrive'=>'OneDrive',
            'Dropbox'=>'Dropbox',
        ] as $dir=>$label){
            $add($home.DIRECTORY_SEPARATOR.$dir,$label.' no usuário atual');
        }
    }

    if(PHP_OS_FAMILY==='Windows'){
        foreach(range('C','Z') as $letter){
            $root=$letter.':\\';
            if(!is_dir($root)) continue;
            foreach([
                'Meu Drive'=>'Google Drive — Meu Drive',
                'My Drive'=>'Google Drive — My Drive',
                'Google Drive'=>'Google Drive',
            ] as $dir=>$label){
                $add($root.$dir,$label.' ('.$letter.':)');
            }
        }
    }
    return array_values($candidates);
}

function sync_cloud_list_files(PDO $pdo, int $limit=100): array {
    $folder=sync_cloud_folder($pdo);
    if($folder===''||!is_dir($folder)) return [];
    $rows=[];
    foreach(glob($folder.DIRECTORY_SEPARATOR.'*.guacasenc') ?: [] as $file){
        $rows[]=[
            'name'=>basename($file),
            'path'=>$file,
            'size'=>(int)filesize($file),
            'mtime'=>(int)filemtime($file),
            'sha256'=>is_file($file.'.sha256')?trim((string)file_get_contents($file.'.sha256')):hash_file('sha256',$file),
        ];
    }
    usort($rows,fn($a,$b)=>$b['mtime']<=>$a['mtime']);
    return array_slice($rows,0,max(1,min(500,$limit)));
}

function sync_cloud_copy_encrypted_backup(PDO $pdo, string $sqlitePath): array {
    if(!is_file($sqlitePath)) throw new RuntimeException('Backup local não encontrado.');
    $folder=sync_folder_prepare(sync_cloud_folder($pdo));
    $encrypted=cloud_encrypt_backup_file($sqlitePath);

    try{
        $name=basename($sqlitePath).'.guacasenc';
        $dest=$folder.DIRECTORY_SEPARATOR.$name;
        $part=$dest.'.part';

        if(!copy($encrypted,$part)) throw new RuntimeException('Não foi possível copiar o backup criptografado para a pasta sincronizada.');
        if(is_file($dest)) @unlink($dest);
        if(!rename($part,$dest)){
            @unlink($part);
            throw new RuntimeException('Não foi possível finalizar a cópia do backup.');
        }

        $sha=hash_file('sha256',$dest);
        file_put_contents($dest.'.sha256',$sha,LOCK_EX);

        $email=lower_email(cloud_setting($pdo,'sync_cloud_email',''));
        $now=now_iso();
        $pdo->prepare("INSERT INTO cloud_backup_log(provider,account_email,local_file,remote_name,status,details,created_at) VALUES('SYNC_FOLDER',?,?,?,?,?,?)")
            ->execute([$email?:null,basename($sqlitePath),$name,'OK','BACKUP CRIPTOGRAFADO COPIADO PARA PASTA SINCRONIZADA: '.$folder,$now]);

        cloud_setting_write($pdo,'sync_cloud_last_copy_at',$now,null);
        cloud_setting_write($pdo,'sync_cloud_last_copy_file',$name,null);
        cloud_setting_write($pdo,'sync_cloud_last_error','',null);

        return ['name'=>$name,'path'=>$dest,'sha256'=>$sha];
    }catch(Throwable $e){
        $email=lower_email(cloud_setting($pdo,'sync_cloud_email',''));
        $pdo->prepare("INSERT INTO cloud_backup_log(provider,account_email,local_file,status,details,created_at) VALUES('SYNC_FOLDER',?,?,'ERRO',?,?)")
            ->execute([$email?:null,basename($sqlitePath),substr($e->getMessage(),0,1000),now_iso()]);
        cloud_setting_write($pdo,'sync_cloud_last_error',substr($e->getMessage(),0,1000),null);
        throw $e;
    }finally{
        @unlink($encrypted);
    }
}

function sync_cloud_restore_to_pending(PDO $pdo, string $fileName, ?string $recoveryKeyHex=null): string {
    $folder=sync_cloud_folder($pdo);
    if($folder===''||!is_dir($folder)) throw new RuntimeException('Pasta sincronizada não configurada.');
    $fileName=basename($fileName);
    if(!str_ends_with(strtolower($fileName),'.guacasenc')) throw new RuntimeException('Arquivo de backup inválido.');
    $path=$folder.DIRECTORY_SEPARATOR.$fileName;
    if(!is_file($path)) throw new RuntimeException('Arquivo não encontrado na pasta sincronizada.');

    $decrypted=cloud_decrypt_backup_file($path,$recoveryKeyHex);
    try{
        if(!sqlite_file_valid($decrypted)) throw new RuntimeException('O backup descriptografado não passou na validação SQLite.');
        if(!copy($decrypted,RESTORE_PENDING_FILE)) throw new RuntimeException('Não foi possível preparar a restauração.');
        return RESTORE_PENDING_FILE;
    }finally{
        @unlink($decrypted);
    }
}

/* ==========================================================
   GOOGLE DRIVE — BACKUP EM NUVEM
   O Drive é usado como armazenamento de BACKUP criptografado,
   nunca como banco de dados transacional ao vivo.
   ========================================================== */

function cloud_curl_available(): bool {
    return function_exists('curl_init');
}

function google_http_request(string $url, string $method='GET', array $headers=[], ?string $body=null, int $timeout=15): array {
    if(!cloud_curl_available()) throw new RuntimeException('A extensão PHP cURL é necessária para conectar ao Google Drive.');
    $ch=curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_HEADER=>false,
    ]);
    if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    $raw=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    if($raw===false) throw new RuntimeException('Falha de comunicação com o Google: '.$err);
    return ['status'=>$status,'body'=>(string)$raw];
}

function google_oauth_client_id_valid(string $clientId): bool {
    $clientId=trim($clientId);
    return $clientId!=='' && str_ends_with($clientId,'.apps.googleusercontent.com')
        && (bool)preg_match('/^[A-Za-z0-9._-]+\.apps\.googleusercontent\.com$/',$clientId);
}

function google_drive_client_credentials(PDO $pdo): array {
    $clientId=trim(cloud_setting($pdo,'google_drive_client_id',''));
    $secretEnc=cloud_setting($pdo,'google_drive_client_secret_enc','');
    $clientSecret=decrypt_private_value($secretEnc);
    return [$clientId,$clientSecret];
}

function google_drive_refresh_token(PDO $pdo): string {
    return decrypt_private_value(cloud_setting($pdo,'google_drive_refresh_token_enc',''));
}

function google_drive_connected(PDO $pdo): bool {
    [$cid,$secret]=google_drive_client_credentials($pdo);
    return cloud_setting($pdo,'google_drive_enabled','0')==='1'
        && $cid!=='' && $secret!=='' && google_drive_refresh_token($pdo)!=='';
}

function google_drive_access_token(PDO $pdo): string {
    [$clientId,$clientSecret]=google_drive_client_credentials($pdo);
    $refresh=google_drive_refresh_token($pdo);
    if($clientId===''||$clientSecret===''||$refresh==='') throw new RuntimeException('Conta Google Drive não conectada.');
    if(!google_oauth_client_id_valid($clientId)) throw new RuntimeException('Client ID OAuth inválido. Use o ID criado pelo Google, terminando em .apps.googleusercontent.com.');

    $body=http_build_query([
        'client_id'=>$clientId,
        'client_secret'=>$clientSecret,
        'refresh_token'=>$refresh,
        'grant_type'=>'refresh_token',
    ]);
    $r=google_http_request(
        'https://oauth2.googleapis.com/token',
        'POST',
        ['Content-Type: application/x-www-form-urlencoded'],
        $body,
        15
    );
    $j=json_decode($r['body'],true);
    if($r['status']<200||$r['status']>=300||!is_array($j)||empty($j['access_token'])){
        $msg=is_array($j)?(string)($j['error_description']??$j['error']??'Token não renovado'):'Token não renovado';
        throw new RuntimeException('Google OAuth: '.$msg);
    }
    return (string)$j['access_token'];
}

function google_drive_about(PDO $pdo): array {
    $token=google_drive_access_token($pdo);
    $url='https://www.googleapis.com/drive/v3/about?fields='.rawurlencode('user(displayName,emailAddress),storageQuota(limit,usage)');
    $r=google_http_request($url,'GET',['Authorization: Bearer '.$token],null,15);
    $j=json_decode($r['body'],true);
    if($r['status']<200||$r['status']>=300||!is_array($j)){
        throw new RuntimeException('Não foi possível consultar a conta Google Drive.');
    }
    return $j;
}

function google_drive_create_backup_folder(PDO $pdo, string $accessToken): string {
    $metadata=[
        'name'=>'GUACAS - BACKUPS CRIPTOGRAFADOS',
        'mimeType'=>'application/vnd.google-apps.folder',
        'description'=>'Pasta criada pelo sistema GUACAS para backups criptografados.',
        'appProperties'=>['guacas'=>'backup-folder','version'=>'1'],
    ];
    $r=google_http_request(
        'https://www.googleapis.com/drive/v3/files?fields=id,name',
        'POST',
        ['Authorization: Bearer '.$accessToken,'Content-Type: application/json; charset=UTF-8'],
        json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        20
    );
    $j=json_decode($r['body'],true);
    if($r['status']<200||$r['status']>=300||empty($j['id'])){
        throw new RuntimeException('Não foi possível criar a pasta GUACAS no Google Drive.');
    }
    return (string)$j['id'];
}

function cloud_backup_key(): string {
    if(is_file(CLOUD_BACKUP_KEY_FILE)){
        $raw=file_get_contents(CLOUD_BACKUP_KEY_FILE);
        if(is_string($raw)&&strlen($raw)>=32) return substr($raw,0,32);
    }
    if(!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL é necessário para criptografar backups da nuvem.');
    $key=random_bytes(32);
    file_put_contents(CLOUD_BACKUP_KEY_FILE,$key,LOCK_EX);
    @chmod(CLOUD_BACKUP_KEY_FILE,0600);
    return $key;
}

function cloud_backup_recovery_key_hex(): string {
    return strtoupper(bin2hex(cloud_backup_key()));
}

function cloud_encrypt_backup_file(string $sqlitePath): string {
    if(!is_file($sqlitePath)) throw new RuntimeException('Backup local não encontrado.');
    if(!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL é necessário para criptografar o backup.');
    $plain=file_get_contents($sqlitePath);
    if($plain===false) throw new RuntimeException('Não foi possível ler o backup.');
    $iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($plain,'aes-256-gcm',cloud_backup_key(),OPENSSL_RAW_DATA,$iv,$tag,'GUACAS-CLOUD-BACKUP-V1');
    if($cipher===false) throw new RuntimeException('Falha ao criptografar backup.');
    $out=$sqlitePath.'.guacasenc';
    $payload="GUACASENC1".$iv.$tag.$cipher;
    if(file_put_contents($out,$payload,LOCK_EX)===false) throw new RuntimeException('Falha ao preparar backup criptografado.');
    @chmod($out,0640);
    return $out;
}

function cloud_decrypt_backup_file(string $encryptedPath, ?string $recoveryHex=null): string {
    if(!is_file($encryptedPath)) throw new RuntimeException('Arquivo criptografado não encontrado.');
    $raw=file_get_contents($encryptedPath);
    if($raw===false||strlen($raw)<40||substr($raw,0,10)!=='GUACASENC1') throw new RuntimeException('Arquivo GUACAS criptografado inválido.');
    $key=$recoveryHex!==null&&trim($recoveryHex)!=='' ? hex2bin(preg_replace('/\s+/','',trim($recoveryHex))) : cloud_backup_key();
    if(!is_string($key)||strlen($key)!==32) throw new RuntimeException('Chave de recuperação inválida.');
    $iv=substr($raw,10,12);$tag=substr($raw,22,16);$cipher=substr($raw,38);
    $plain=openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'GUACAS-CLOUD-BACKUP-V1');
    if($plain===false||substr($plain,0,16)!=="SQLite format 3\x00") throw new RuntimeException('Não foi possível descriptografar/validar o backup.');
    $out=BACKUP_DIR.'/decrypted_restore_'.date('Ymd_His').'.sqlite';
    file_put_contents($out,$plain,LOCK_EX);
    return $out;
}

function google_drive_upload_encrypted_backup(PDO $pdo, string $sqlitePath): array {
    if(!google_drive_connected($pdo)) throw new RuntimeException('Google Drive não conectado.');
    $token=google_drive_access_token($pdo);
    $folderId=trim(cloud_setting($pdo,'google_drive_folder_id',''));
    if($folderId===''){
        $folderId=google_drive_create_backup_folder($pdo,$token);
        cloud_setting_write($pdo,'google_drive_folder_id',$folderId,null);
    }

    $encPath=cloud_encrypt_backup_file($sqlitePath);
    try{
        $remoteName=basename($sqlitePath).'.guacasenc';
        $metadata=[
            'name'=>$remoteName,
            'parents'=>[$folderId],
            'description'=>'Backup criptografado do sistema GUACAS. Requer a chave de recuperação GUACAS.',
            'appProperties'=>[
                'guacasBackup'=>'1',
                'sha256'=>hash_file('sha256',$encPath),
                'source'=>basename($sqlitePath),
            ],
        ];
        $boundary='guacas_'.bin2hex(random_bytes(12));
        $fileData=file_get_contents($encPath);
        if($fileData===false) throw new RuntimeException('Não foi possível ler o backup criptografado.');
        $body="--$boundary\r\n".
              "Content-Type: application/json; charset=UTF-8\r\n\r\n".
              json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\r\n".
              "--$boundary\r\n".
              "Content-Type: application/octet-stream\r\n\r\n".
              $fileData."\r\n".
              "--$boundary--\r\n";

        $r=google_http_request(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,size,createdTime',
            'POST',
            ['Authorization: Bearer '.$token,'Content-Type: multipart/related; boundary='.$boundary],
            $body,
            45
        );
        $j=json_decode($r['body'],true);
        if($r['status']<200||$r['status']>=300||empty($j['id'])){
            $detail=is_array($j)?json_encode($j,JSON_UNESCAPED_UNICODE):substr($r['body'],0,500);
            throw new RuntimeException('Falha no upload para Google Drive. '.$detail);
        }

        $email=cloud_setting($pdo,'google_drive_email','');
        $pdo->prepare("INSERT INTO cloud_backup_log(provider,account_email,local_file,remote_file_id,remote_name,status,details,created_at) VALUES('GOOGLE_DRIVE',?,?,?,?, 'OK',?,?)")
            ->execute([$email,basename($sqlitePath),(string)$j['id'],(string)$j['name'],'BACKUP CRIPTOGRAFADO COM AES-256-GCM',now_iso()]);
        cloud_setting_write($pdo,'google_drive_last_upload_at',now_iso(),null);
        cloud_setting_write($pdo,'google_drive_last_upload_file',(string)$j['name'],null);
        cloud_setting_write($pdo,'google_drive_last_error','',null);
        return $j;
    }catch(Throwable $e){
        $email=cloud_setting($pdo,'google_drive_email','');
        $pdo->prepare("INSERT INTO cloud_backup_log(provider,account_email,local_file,status,details,created_at) VALUES('GOOGLE_DRIVE',?,?,'ERRO',?,?)")
            ->execute([$email,basename($sqlitePath),substr($e->getMessage(),0,1000),now_iso()]);
        cloud_setting_write($pdo,'google_drive_last_error',substr($e->getMessage(),0,1000),null);
        throw $e;
    }finally{
        @unlink($encPath);
    }
}

function google_drive_list_app_backups(PDO $pdo, int $limit=50): array {
    if(!google_drive_connected($pdo)) return [];
    $folderId=trim(cloud_setting($pdo,'google_drive_folder_id',''));
    if($folderId==='') return [];
    $token=google_drive_access_token($pdo);
    $q="'".str_replace("'","\\'",$folderId)."' in parents and trashed=false";
    $url='https://www.googleapis.com/drive/v3/files?'.http_build_query([
        'q'=>$q,
        'pageSize'=>max(1,min(100,$limit)),
        'orderBy'=>'createdTime desc',
        'fields'=>'files(id,name,size,createdTime,modifiedTime,webViewLink)',
    ]);
    $r=google_http_request($url,'GET',['Authorization: Bearer '.$token],null,20);
    $j=json_decode($r['body'],true);
    if($r['status']<200||$r['status']>=300||!is_array($j)) throw new RuntimeException('Não foi possível listar backups do Google Drive.');
    return is_array($j['files']??null)?$j['files']:[];
}

function google_drive_download_file(PDO $pdo, string $fileId, string $dest): void {
    $token=google_drive_access_token($pdo);
    $url='https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?alt=media';
    $r=google_http_request($url,'GET',['Authorization: Bearer '.$token],null,60);
    if($r['status']<200||$r['status']>=300) throw new RuntimeException('Não foi possível baixar o backup do Google Drive.');
    if(file_put_contents($dest,$r['body'],LOCK_EX)===false) throw new RuntimeException('Não foi possível salvar o backup baixado.');
}

function google_drive_disconnect(PDO $pdo, ?int $adminId=null, bool $revoke=true): void {
    $refresh=google_drive_refresh_token($pdo);
    if($revoke&&$refresh!==''&&cloud_curl_available()){
        try{
            google_http_request(
                'https://oauth2.googleapis.com/revoke?token='.rawurlencode($refresh),
                'POST',
                ['Content-Type: application/x-www-form-urlencoded'],
                '',
                10
            );
        }catch(Throwable $e){}
    }
    foreach([
        'google_drive_enabled'=>'0',
        'google_drive_refresh_token_enc'=>'',
        'google_drive_email'=>'',
        'google_drive_folder_id'=>'',
        'google_drive_connected_at'=>'',
        'google_drive_last_error'=>'',
    ] as $k=>$v) cloud_setting_write($pdo,$k,$v,$adminId);
}


function status_timestamp_column(string $status): ?string {
    return match($status) {
        'DESPACHADA' => 'dispatched_at',
        'A_CAMINHO' => 'en_route_at',
        'NO_LOCAL' => 'on_scene_at',
        'EM_ATENDIMENTO' => 'care_started_at',
        'RETORNANDO' => 'returning_at',
        'ENCERRADA' => 'closed_at',
        default => null,
    };
}

function occurrence_status_label(string $status): string {
    return match($status) {
        'SOLICITADA' => 'SOLICITADA',
        'ABERTA' => 'ABERTA',
        'DESPACHADA' => 'DESPACHADA',
        'A_CAMINHO' => 'A CAMINHO',
        'NO_LOCAL' => 'NO LOCAL',
        'EM_ATENDIMENTO' => 'EM ATENDIMENTO',
        'RETORNANDO' => 'RETORNANDO',
        'ENCERRADA' => 'ENCERRADA',
        default => str_replace('_',' ',$status),
    };
}

function occurrence_access_allowed(array $user, array $occ): bool {
    if (($user['role'] ?? '') !== 'CAMPO') return true;
    $userTeam = trim((string)($user['team'] ?? ''));
    $occTeam = trim((string)($occ['team'] ?? ''));
    return $occTeam === '' || ($userTeam !== '' && $occTeam === $userTeam);
}

function occurrence_mutation_allowed(array $user, array $occ): bool {
    if (($user['role'] ?? '') !== 'CAMPO') return true;
    $userTeam = trim((string)($user['team'] ?? ''));
    $occTeam = trim((string)($occ['team'] ?? ''));
    return $userTeam !== '' && $occTeam !== '' && $occTeam === $userTeam;
}

function set_vehicle_status_if_available(PDO $pdo, ?int $vehicleId, string $status): void {
    if (!$vehicleId) return;
    $st=$pdo->prepare("UPDATE vehicles SET status=?,updated_at=? WHERE id=?");
    $st->execute([$status,now_iso(),$vehicleId]);
}

function release_vehicle_if_unused(PDO $pdo, ?int $vehicleId, int $closingOccurrenceId): void {
    if (!$vehicleId) return;
    $st=$pdo->prepare("SELECT COUNT(*) FROM occurrences WHERE vehicle_id=? AND id<>? AND status<>'ENCERRADA'");
    $st->execute([$vehicleId,$closingOccurrenceId]);
    if ((int)$st->fetchColumn() === 0) set_vehicle_status_if_available($pdo,$vehicleId,'DISPONIVEL');
}

function active_vehicles(PDO $pdo): array {
    return $pdo->query("
        SELECT v.*,t.name AS team_name
          FROM vehicles v
          LEFT JOIN teams t ON t.id=v.team_id
         WHERE v.active=1
         ORDER BY CASE v.status WHEN 'DISPONIVEL' THEN 0 WHEN 'EM_USO' THEN 1 ELSE 2 END,v.prefix
    ")->fetchAll();
}

function team_operational_rows(PDO $pdo): array {
    $teams=active_teams($pdo);
    foreach($teams as &$t){
        $st=$pdo->prepare("SELECT protocol,status,type,id FROM occurrences WHERE team=? AND status<>'ENCERRADA' ORDER BY id DESC LIMIT 1");
        $st->execute([$t['name']]);
        $occ=$st->fetch();
        $t['operational_status']=$occ?'EMPENHADA':'DISPONIVEL';
        $t['occurrence']=$occ ?: null;

        $p=$pdo->prepare("
            SELECT tp.lat,tp.lng,tp.accuracy,tp.last_seen,u.bc_name,u.name
              FROM team_presence tp
              JOIN users u ON u.id=tp.user_id
             WHERE tp.team=?
             ORDER BY tp.last_seen DESC LIMIT 1
        ");
        $p->execute([$t['name']]);
        $t['presence']=$p->fetch() ?: null;
    }
    unset($t);
    return $teams;
}


function acknowledge_public_occurrence(PDO $pdo, int $occurrenceId, int $userId): bool {
    $st=$pdo->prepare("SELECT id,source,status,central_acknowledged_at FROM occurrences WHERE id=?");
    $st->execute([$occurrenceId]);
    $o=$st->fetch();
    if(!$o || ($o['source']??'')!=='PUBLICO' || !empty($o['central_acknowledged_at'])) return false;

    $now=now_iso();
    $up=$pdo->prepare("UPDATE occurrences SET central_acknowledged_at=?,central_acknowledged_by=?,updated_at=? WHERE id=? AND central_acknowledged_at IS NULL");
    $up->execute([$now,$userId,$now,$occurrenceId]);
    if($up->rowCount()>0){
        $ev=$pdo->prepare("INSERT INTO occurrence_events(occurrence_id,event_type,old_status,new_status,note,user_id,created_at) SELECT id,'CIENCIA_CENTRAL',status,status,'SOLICITAÇÃO PÚBLICA VISUALIZADA PELA CENTRAL',?,? FROM occurrences WHERE id=?");
        $ev->execute([$userId,$now,$occurrenceId]);
        return true;
    }
    return false;
}

function occurrence_patient_count(PDO $pdo, int $occurrenceId): int {
    $st=$pdo->prepare("SELECT COUNT(*) FROM aph_records WHERE occurrence_id=? AND deleted_at IS NULL");
    $st->execute([$occurrenceId]);
    return (int)$st->fetchColumn();
}

function occurrence_message_count(PDO $pdo, int $occurrenceId): int {
    $st=$pdo->prepare("SELECT COUNT(*) FROM occurrence_messages WHERE occurrence_id=?");
    $st->execute([$occurrenceId]);
    return (int)$st->fetchColumn();
}

function minutes_between(?string $start, ?string $end): ?float {
    if(!$start || !$end) return null;
    $a=strtotime($start);$b=strtotime($end);
    if($a===false||$b===false||$b<$a) return null;
    return round(($b-$a)/60,1);
}

db();
