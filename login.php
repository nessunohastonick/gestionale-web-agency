<?php
require_once 'config.php';

$error = '';
$success = '';

// Gestione logout
if (isset($_GET['logout'])) {
    session_destroy();
    $success = 'Logout effettuato con successo.';
}

// Gestione timeout
if (isset($_GET['timeout'])) {
    $error = 'Sessione scaduta. Effettua nuovamente il login.';
}

// Gestione login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password.';
    } else {
        try {
            // Controllo tentativi di login (semplificato)
            $stmt = $pdo->prepare("SELECT id, username, email, nome, cognome, tipo FROM users WHERE username = ? AND password = MD5(?) AND attivo = 1");
            $stmt->execute([$username, $password]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['tipo'];
                $_SESSION['nome_completo'] = $user['nome'] . ' ' . $user['cognome'];
                $_SESSION['last_activity'] = time();
                
                // Aggiorna ultimo accesso
                $stmt = $pdo->prepare("UPDATE users SET ultimo_accesso = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Redirect basato sul tipo utente
                if ($user['tipo'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: cliente_dashboard.php');
                }
                exit;
            } else {
                $error = 'Credenziali non valide.';
            }
        } catch (Exception $e) {
            $error = 'Errore durante il login. Riprova.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background-color: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Accedi al tuo account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo e($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required value="<?php echo e($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Accedi</button>
        </form>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tutti i diritti riservati.</p>
        </div>
    </div>
</body>
</html>