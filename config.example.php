<?php
// ESEMPIO DI CONFIGURAZIONE - COPIA QUESTO FILE COME config.php E MODIFICA I PARAMETRI

// Configurazione Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestionale_web');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');

// Configurazione Email SMTP
define('SMTP_HOST', 'smtp.tuodominio.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@tuodominio.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_FROM_EMAIL', 'noreply@tuodominio.com');
define('SMTP_FROM_NAME', 'Sistema Gestionale');

// Configurazioni Generali
define('SITE_URL', 'https://tuodominio.com/gestionale');
define('SITE_NAME', 'Gestionale Servizi');
define('ADMIN_EMAIL', 'admin@tuodominio.com');

// Configurazioni Sicurezza
define('SESSION_TIMEOUT', 3600); // 1 ora in secondi
define('MAX_LOGIN_ATTEMPTS', 5);

// Timezone
date_default_timezone_set('Europe/Rome');

// Avvio sessione
session_start();

// Connessione Database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Errore connessione database: " . $e->getMessage());
}

// Funzione per verificare login
function verificaLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        header('Location: login.php');
        exit;
    }
    
    // Controllo timeout sessione
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
}

// Funzione per verificare admin
function verificaAdmin() {
    verificaLogin();
    if ($_SESSION['user_type'] !== 'admin') {
        header('Location: cliente_dashboard.php');
        exit;
    }
}

// Funzione per verificare cliente
function verificaCliente() {
    verificaLogin();
    if ($_SESSION['user_type'] !== 'cliente') {
        header('Location: admin_dashboard.php');
        exit;
    }
}

// Funzione per log di sicurezza
function logSecurity($action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO security_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Log error silently
    }
}

// Funzione per escape HTML
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Funzione per formattare date
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Funzione per inviare email
function inviaEmail($to, $subject, $body, $isHTML = true) {
    $headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
        'Reply-To: ' . SMTP_FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    if ($isHTML) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}
?>
