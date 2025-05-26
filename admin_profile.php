<?php
require_once 'config.php';
verificaAdmin();

$message = '';
$error = '';

// Gestione aggiornamento profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $nome = trim($_POST['nome'] ?? '');
        $cognome = trim($_POST['cognome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        if (empty($nome) || empty($cognome) || empty($email)) {
            $error = 'Nome, cognome e email sono obbligatori.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email non valida.';
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $error = 'Le password non coincidono.';
        } elseif (!empty($new_password) && strlen($new_password) < 6) {
            $error = 'La password deve essere di almeno 6 caratteri.';
        } else {
            try {
                // Controllo email duplicata (escludendo l'utente corrente)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $error = 'Email già registrata da un altro utente.';
                } else {
                    // Aggiornamento profilo
                    if (!empty($new_password)) {
                        $stmt = $pdo->prepare("UPDATE users SET nome = ?, cognome = ?, email = ?, password = MD5(?) WHERE id = ?");
                        $stmt->execute([$nome, $cognome, $email, $new_password, $_SESSION['user_id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET nome = ?, cognome = ?, email = ? WHERE id = ?");
                        $stmt->execute([$nome, $cognome, $email, $_SESSION['user_id']]);
                    }
                    
                    // Aggiorna sessione
                    $_SESSION['nome_completo'] = $nome . ' ' . $cognome;
                    
                    $message = 'Profilo aggiornato con successo.';
                }
            } catch (Exception $e) {
                $error = 'Errore durante l\'aggiornamento del profilo.';
            }
        }
    }
}

// Recupero dati utente corrente
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
} catch (Exception $e) {
    $error = 'Errore nel caricamento del profilo.';
    $admin = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Profilo Amministratore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-menu {
            display: flex;
            gap: 1rem;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .nav-menu a:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .main-content {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 20px;
        }
        
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .password-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .password-section h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .info-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1><?php echo SITE_NAME; ?> - Profilo Admin</h1>
            </div>
            <div class="nav-menu">
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="gestione_clienti.php">Gestisci Clienti</a>
                <a href="gestione_servizi.php">Gestisci Servizi</a>
                <a href="login.php?logout=1">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($admin['nome'], 0, 1) . substr($admin['cognome'], 0, 1)); ?>
                </div>
                <h2><?php echo e($admin['nome'] . ' ' . $admin['cognome']); ?></h2>
                <p style="color: #666;">Amministratore Sistema</p>
                <p style="color: #999; font-size: 0.9rem;">
                    Ultimo accesso: <?php echo $admin['ultimo_accesso'] ? formatDate($admin['ultimo_accesso']) : 'Mai'; ?>
                </p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome *</label>
                        <input type="text" id="nome" name="nome" required value="<?php echo e($admin['nome']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="cognome">Cognome *</label>
                        <input type="text" id="cognome" name="cognome" required value="<?php echo e($admin['cognome']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" value="<?php echo e($admin['username']); ?>" disabled>
                    <small style="color: #666;">Il username non può essere modificato</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required value="<?php echo e($admin['email']); ?>">
                </div>
                
                <div class="password-section">
                    <h3>Modifica Password</h3>
                    <p style="color: #666; margin-bottom: 1rem;">Lascia vuoto per mantenere la password attuale</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Nuova Password</label>
                            <input type="password" id="new_password" name="new_password" minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Conferma Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="6">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="admin_dashboard.php" class="btn btn-secondary">Annulla</a>
                    <button type="submit" class="btn">Aggiorna Profilo</button>
                </div>
            </form>
        </div>
        
        <?php
        // Recupero statistiche rapide per il profilo
        try {
            $stmt = $pdo->query("SELECT * FROM vista_statistiche");
            $stats = $stmt->fetch();
        } catch (Exception $e) {
            $stats = ['clienti_attivi' => 0, 'servizi_attivi' => 0, 'richieste_pending' => 0, 'chiusure_ultimo_mese' => 0];
        }
        ?>
        
        <div class="info-grid">
            <div class="info-card">
                <div class="info-number"><?php echo $stats['clienti_attivi']; ?></div>
                <div class="info-label">Clienti Attivi</div>
            </div>
            <div class="info-card">
                <div class="info-number"><?php echo $stats['servizi_attivi']; ?></div>
                <div class="info-label">Servizi Attivi</div>
            </div>
            <div class="info-card">
                <div class="info-number"><?php echo $stats['richieste_pending']; ?></div>
                <div class="info-label">Richieste Pendenti</div>
            </div>
            <div class="info-card">
                <div class="info-number"><?php echo $stats['chiusure_ultimo_mese']; ?></div>
                <div class="info-label">Chiusure Ultimo Mese</div>
            </div>
        </div>
    </div>
    
    <script>
        // Verifica corrispondenza password
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== '' && confirmPassword !== '') {
                if (newPassword !== confirmPassword) {
                    this.setCustomValidity('Le password non coincidono');
                } else {
                    this.setCustomValidity('');
                }
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('new_password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value !== '') {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>