<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

function init_db(): void {
    $db = get_db();
    $db->exec("
        CREATE TABLE IF NOT EXISTS tests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            answers TEXT NOT NULL,
            total INTEGER NOT NULL,
            created_by INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            deadline TEXT,
            is_active INTEGER DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            full_name TEXT NOT NULL,
            user_answers TEXT NOT NULL,
            correct INTEGER NOT NULL,
            wrong INTEGER NOT NULL,
            score REAL NOT NULL,
            submitted_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY,
            full_name TEXT,
            username TEXT,
            registered_at TEXT
        );
        CREATE TABLE IF NOT EXISTS states (
            user_id INTEGER PRIMARY KEY,
            state TEXT,
            data TEXT
        );
    ");
}

// ── STATES ────────────────────────────────────────────────────────────────────
function get_state(int $uid): array {
    $db = get_db();
    $row = $db->prepare("SELECT state, data FROM states WHERE user_id=?")->execute([$uid]) ? 
           $db->query("SELECT state, data FROM states WHERE user_id=$uid")->fetch() : null;
    $st = $db->prepare("SELECT state, data FROM states WHERE user_id=?");
    $st->execute([$uid]);
    $row = $st->fetch();
    if (!$row) return ['state' => null, 'data' => []];
    return ['state' => $row['state'], 'data' => json_decode($row['data'] ?? '{}', true)];
}

function set_state(int $uid, ?string $state, array $data = []): void {
    $db = get_db();
    $db->prepare("INSERT OR REPLACE INTO states (user_id, state, data) VALUES (?,?,?)")
       ->execute([$uid, $state, json_encode($data)]);
}

function clear_state(int $uid): void {
    get_db()->prepare("DELETE FROM states WHERE user_id=?")->execute([$uid]);
}

// ── USERS ─────────────────────────────────────────────────────────────────────
function register_user(int $uid, string $full_name, string $username = ''): void {
    $db = get_db();
    $now = date('Y-m-d H:i');
    $db->prepare("INSERT OR IGNORE INTO users (user_id, full_name, username, registered_at) VALUES (?,?,?,?)")
       ->execute([$uid, $full_name, $username, $now]);
}

function get_all_users(): array {
    $st = get_db()->query("SELECT user_id FROM users");
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

function get_user_count(): int {
    return (int) get_db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

// ── TESTS ─────────────────────────────────────────────────────────────────────
function add_test(string $title, string $answers, int $created_by, ?string $deadline = null): int {
    $db = get_db();
    $now = date('Y-m-d H:i');
    $db->prepare("INSERT INTO tests (title, answers, total, created_by, created_at, deadline) VALUES (?,?,?,?,?,?)")
       ->execute([$title, strtoupper($answers), strlen($answers), $created_by, $now, $deadline]);
    return (int) $db->lastInsertId();
}

function get_active_tests(): array {
    return get_db()->query("SELECT * FROM tests WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
}

function get_test(int $id): ?array {
    $st = get_db()->prepare("SELECT * FROM tests WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function deactivate_test(int $id): void {
    get_db()->prepare("UPDATE tests SET is_active=0 WHERE id=?")->execute([$id]);
}

function delete_test(int $id): void {
    $db = get_db();
    $db->prepare("DELETE FROM tests WHERE id=?")->execute([$id]);
    $db->prepare("DELETE FROM results WHERE test_id=?")->execute([$id]);
}

function get_all_tests(): array {
    return get_db()->query("SELECT * FROM tests ORDER BY created_at DESC")->fetchAll();
}

// ── RESULTS ───────────────────────────────────────────────────────────────────
function already_submitted(int $test_id, int $user_id): bool {
    $st = get_db()->prepare("SELECT id FROM results WHERE test_id=? AND user_id=?");
    $st->execute([$test_id, $user_id]);
    return (bool) $st->fetch();
}

function save_result(int $test_id, int $user_id, string $full_name, string $user_answers, int $correct, int $wrong, float $score): void {
    $now = date('Y-m-d H:i');
    get_db()->prepare("INSERT INTO results (test_id, user_id, full_name, user_answers, correct, wrong, score, submitted_at) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$test_id, $user_id, $full_name, strtoupper($user_answers), $correct, $wrong, $score, $now]);
}

function get_results_by_test(int $test_id): array {
    $st = get_db()->prepare("SELECT * FROM results WHERE test_id=? ORDER BY score DESC");
    $st->execute([$test_id]);
    return $st->fetchAll();
}

function get_top10(int $test_id): array {
    $st = get_db()->prepare("SELECT * FROM results WHERE test_id=? ORDER BY score DESC LIMIT 10");
    $st->execute([$test_id]);
    return $st->fetchAll();
}

function get_user_results(int $user_id): array {
    $st = get_db()->prepare("
        SELECT r.*, t.title FROM results r 
        JOIN tests t ON r.test_id = t.id 
        WHERE r.user_id=? ORDER BY r.submitted_at DESC
    ");
    $st->execute([$user_id]);
    return $st->fetchAll();
}

function get_stats(): array {
    $db = get_db();
    return [
        'users'   => (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'tests'   => (int) $db->query("SELECT COUNT(*) FROM tests")->fetchColumn(),
        'results' => (int) $db->query("SELECT COUNT(*) FROM results")->fetchColumn(),
        'avg'     => round((float) $db->query("SELECT AVG(score) FROM results")->fetchColumn(), 1),
    ];
}
