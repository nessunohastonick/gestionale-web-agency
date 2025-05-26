<?php
require_once 'config.php';
verificaAdmin();

$message = '';
$error = '';

// Gestione operazioni CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $nome = trim($_POST['nome'] ?? '');
            $cognome = trim($_POST['cognome'] ?? '');
            
            if (empty($username) || empty($password) || empty($email) || empty($nome) || empty($cognome)) {
                $error = 'Tutti i campi sono obbligatori.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email non valida.';
            } else {
                try {
                    // Controllo username duplicato
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Username già esistente.';
                    } else {
                        // Controllo email duplicata
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        
                        if ($stmt->fetch()) {
                            $error = 'Email già registrata.';
                        } else {
                            // Inserimento nuovo cliente
                            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, nome, cognome, tipo) VALUES (?, MD5(?), ?, ?, ?, 'cliente')");
                            $stmt->execute([$username, $password, $email, $nome, $cognome]);
                            $message = 'Cliente creato con successo.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Errore durante la creazione del cliente.';
                }
            }
            break;
            
        case 'update':
            $user_id = (int)$_POST['user_id'];
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $nome = trim($_POST['nome'] ?? '');
            $cognome = trim($_POST['cognome'] ?? '');
            $new_password = trim($_POST['new_password'] ?? '');
            
            if (empty($username) || empty($email) || empty($nome) || empty($cognome)) {
                $error = 'Tutti i campi sono obbligatori.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email non valida.';
            } else {
                try {
                    // Controllo duplicati escludendo l'utente corrente
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $user_id]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Username già esistente.';
                    } else {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $user_id]);
                        
                        if ($stmt->fetch()) {
                            $error = 'Email già registrata.';
                        } else {
                            // Aggiornamento
                            if (!empty($new_password)) {
                                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, nome = ?, cognome = ?, password = MD5(?) WHERE id = ? AND tipo = 'cliente'");
                                $stmt->execute([$username, $email, $nome, $cognome, $new_password, $user_id]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, nome = ?, cognome = ? WHERE id = ? AND tipo = 'cliente'");
                                $stmt->execute([$username, $email, $nome, $cognome, $user_id]);
                            }
                            $message = 'Cliente aggiornato con successo.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Errore durante l\'aggiornamento del cliente.';
                }
            }
            break;
            
        case 'toggle_status':
            $user_id = (int)$_POST['user_id'];
            try {
                $stmt = $pdo->prepare("UPDATE users SET attivo = IF(attivo = 1, 0, 1) WHERE id = ? AND tipo = 'cliente'");
                $stmt->execute([$user_id]);
                $message = 'Stato cliente aggiornato.';
            } catch (Exception $e) {
                $error = 'Errore durante l\'aggiornamento dello stato.';
            }
            break;
            
        case 'delete':
            $user_id = (int)$_POST['user_id'];
            try {
                // Controllo se ha servizi attivi
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM servizi WHERE user_id = ? AND attivo = 1");
                $stmt->execute([$user_id]);
                $servizi_attivi = $stmt->fetchColumn();
                
                if ($servizi_attivi > 0) {
                    $error = 'Impossibile eliminare: il cliente ha servizi attivi.';
                } else {
                    // Eliminazione (CASCADE eliminerà anche servizi e credenziali)
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND tipo = 'cliente'");
                    $stmt->execute([$user_id]);
                    $message = 'Cliente eliminato con successo.';
                }
            } catch (Exception $e) {
                $error = 'Errore durante l\'eliminazione del cliente.';
            }
            break;
    }
}

// Recupero lista clienti
try {
    $stmt = $pdo->query("
        SELECT u.*, 
               COUNT(s.id) as servizi_totali,
               COUNT(CASE WHEN s.attivo = 1 THEN s.id END) as servizi_attivi
        FROM users u
        LEFT JOIN servizi s ON u.id = s.user_id
        WHERE u.tipo = 'cliente'
        GROUP BY u.id
        ORDER BY u.attivo DESC, u.nome, u.cognome
    ");
    $clienti = $stmt->fetchAll();
} catch (Exception $e) {
    $clienti = [];
    $error = 'Errore nel caricamento dei clienti.';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Gestione Clienti</title>
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
            max-width: 1400px;
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 20px;
        }
        
        .section-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-attivo {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inattivo {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
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
        
        .search-box {
            margin-bottom: 1rem;
        }
        
        .search-box input {
            width: 100%;
            max-width: 300px;
            padding: 0.75rem;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .actions-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            
            .section-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .actions-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1><?php echo SITE_NAME; ?> - Gestione Clienti</h1>
            </div>
            <div class="nav-menu">
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="gestione_servizi.php">Gestisci Servizi</a>
                <a href="admin_profile.php">Profilo</a>
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
        
        <div class="section-card">
            <div class="section-title">
                <span>Gestione Clienti (<?php echo count($clienti); ?>)</span>
                <button class="btn" onclick="openModal('create')">Nuovo Cliente</button>
            </div>
            
            <div class="search-box">
                <input type="text" id="searchClienti" placeholder="Cerca clienti..." onkeyup="filterTable('clientiTable', this.value)">
            </div>
            
            <?php if (empty($clienti)): ?>
                <p>Nessun cliente registrato.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" id="clientiTable">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Servizi</th>
                                <th>Stato</th>
                                <th>Registrato</th>
                                <th>Ultimo Accesso</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clienti as $cliente): ?>
                                <tr>
                                    <td><strong><?php echo e($cliente['nome'] . ' ' . $cliente['cognome']); ?></strong></td>
                                    <td><?php echo e($cliente['username']); ?></td>
                                    <td><?php echo e($cliente['email']); ?></td>
                                    <td>
                                        <span class="status-badge" style="background: #e3f2fd; color: #1976d2;">
                                            <?php echo $cliente['servizi_attivi']; ?>/<?php echo $cliente['servizi_totali']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $cliente['attivo'] ? 'attivo' : 'inattivo'; ?>">
                                            <?php echo $cliente['attivo'] ? 'Attivo' : 'Inattivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($cliente['data_creazione'])); ?></td>
                                    <td>
                                        <?php echo $cliente['ultimo_accesso'] ? formatDate($cliente['ultimo_accesso']) : 'Mai'; ?>
                                    </td>
                                    <td>
                                        <div class="actions-group">
                                            <button class="btn btn-sm" onclick="editCliente(<?php echo htmlspecialchars(json_encode($cliente)); ?>)">
                                                Modifica
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $cliente['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Confermi il cambio di stato?')">
                                                    <?php echo $cliente['attivo'] ? 'Disattiva' : 'Attiva'; ?>
                                                </button>
                                            </form>
                                            <?php if ($cliente['servizi_attivi'] == 0): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $cliente['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('ATTENZIONE: Questa azione eliminerà definitivamente il cliente e tutti i suoi dati. Confermi?')">
                                                        Elimina
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Nuovo/Modifica Cliente -->
    <div id="clienteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Nuovo Cliente</h2>
            
            <form method="POST" id="clienteForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="user_id" id="userId" value="">
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="nome">Nome *</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label for="cognome">Cognome *</label>
                    <input type="text" id="cognome" name="cognome" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password" id="passwordLabel">Password *</label>
                    <input type="password" id="password" name="password">
                    <input type="password" id="new_password" name="new_password" style="display: none;">
                    <small id="passwordHelp">Lascia vuoto per mantenere la password attuale</small>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn" onclick="closeModal()" style="background: #6c757d;">Annulla</button>
                    <button type="submit" class="btn" id="submitBtn">Crea Cliente</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(action) {
            const modal = document.getElementById('clienteModal');
            const form = document.getElementById('clienteForm');
            const title = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            const passwordField = document.getElementById('password');
            const newPasswordField = document.getElementById('new_password');
            const passwordLabel = document.getElementById('passwordLabel');
            const passwordHelp = document.getElementById('passwordHelp');
            
            // Reset form
            form.reset();
            document.getElementById('formAction').value = action;
            document.getElementById('userId').value = '';
            
            if (action === 'create') {
                title.textContent = 'Nuovo Cliente';
                submitBtn.textContent = 'Crea Cliente';
                passwordField.style.display = 'block';
                newPasswordField.style.display = 'none';
                passwordField.required = true;
                passwordLabel.textContent = 'Password *';
                passwordHelp.style.display = 'none';
            }
            
            modal.style.display = 'block';
        }
        
        function editCliente(cliente) {
            const modal = document.getElementById('clienteModal');
            const form = document.getElementById('clienteForm');
            const title = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            const passwordField = document.getElementById('password');
            const newPasswordField = document.getElementById('new_password');
            const passwordLabel = document.getElementById('passwordLabel');
            const passwordHelp = document.getElementById('passwordHelp');
            
            // Popolamento form
            document.getElementById('formAction').value = 'update';
            document.getElementById('userId').value = cliente.id;
            document.getElementById('username').value = cliente.username;
            document.getElementById('nome').value = cliente.nome;
            document.getElementById('cognome').value = cliente.cognome;
            document.getElementById('email').value = cliente.email;
            
            title.textContent = 'Modifica Cliente';
            submitBtn.textContent = 'Aggiorna Cliente';
            passwordField.style.display = 'none';
            newPasswordField.style.display = 'block';
            newPasswordField.required = false;
            passwordLabel.textContent = 'Nuova Password';
            passwordHelp.style.display = 'block';
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('clienteModal').style.display = 'none';
        }
        
        function filterTable(tableId, searchValue) {
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    const cell = cells[j];
                    if (cell.textContent.toLowerCase().indexOf(searchValue.toLowerCase()) > -1) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        }
        
        // Chiudi modal cliccando fuori
        window.onclick = function(event) {
            const modal = document.getElementById('clienteModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>