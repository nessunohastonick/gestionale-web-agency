<?php
require_once 'config.php';
verificaAdmin();

$message = '';
$error = '';
$cliente_selezionato = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;

// Gestione operazioni CRUD servizi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_servizio':
            $user_id = (int)$_POST['user_id'];
            $nome_servizio = trim($_POST['nome_servizio'] ?? '');
            $tipo_servizio = $_POST['tipo_servizio'] ?? '';
            $dominio = trim($_POST['dominio'] ?? '');
            $descrizione = trim($_POST['descrizione'] ?? '');
            $data_scadenza = $_POST['data_scadenza'] ?? null;
            
            if (empty($nome_servizio) || empty($tipo_servizio) || $user_id <= 0) {
                $error = 'Nome servizio, tipo e cliente sono obbligatori.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO servizi (user_id, nome_servizio, tipo_servizio, dominio, descrizione, data_scadenza) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $nome_servizio, $tipo_servizio, $dominio ?: null, $descrizione ?: null, $data_scadenza ?: null]);
                    $servizio_id = $pdo->lastInsertId();
                    
                    // Redirect per aggiungere credenziali
                    header("Location: gestione_servizi.php?servizio_id=$servizio_id&add_credentials=1");
                    exit;
                } catch (Exception $e) {
                    $error = 'Errore durante la creazione del servizio.';
                }
            }
            break;
            
        case 'update_servizio':
            $servizio_id = (int)$_POST['servizio_id'];
            $nome_servizio = trim($_POST['nome_servizio'] ?? '');
            $tipo_servizio = $_POST['tipo_servizio'] ?? '';
            $dominio = trim($_POST['dominio'] ?? '');
            $descrizione = trim($_POST['descrizione'] ?? '');
            $data_scadenza = $_POST['data_scadenza'] ?? null;
            
            if (empty($nome_servizio) || empty($tipo_servizio)) {
                $error = 'Nome servizio e tipo sono obbligatori.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE servizi SET nome_servizio = ?, tipo_servizio = ?, dominio = ?, descrizione = ?, data_scadenza = ? WHERE id = ?");
                    $stmt->execute([$nome_servizio, $tipo_servizio, $dominio ?: null, $descrizione ?: null, $data_scadenza ?: null, $servizio_id]);
                    $message = 'Servizio aggiornato con successo.';
                } catch (Exception $e) {
                    $error = 'Errore durante l\'aggiornamento del servizio.';
                }
            }
            break;
            
        case 'toggle_servizio':
            $servizio_id = (int)$_POST['servizio_id'];
            try {
                $stmt = $pdo->prepare("UPDATE servizi SET attivo = IF(attivo = 1, 0, 1) WHERE id = ?");
                $stmt->execute([$servizio_id]);
                $message = 'Stato servizio aggiornato.';
            } catch (Exception $e) {
                $error = 'Errore durante l\'aggiornamento dello stato.';
            }
            break;
            
        case 'delete_servizio':
            $servizio_id = (int)$_POST['servizio_id'];
            try {
                // Controllo se ha richieste di chiusura
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM storico_chiusure WHERE servizio_id = ?");
                $stmt->execute([$servizio_id]);
                $chiusure = $stmt->fetchColumn();
                
                if ($chiusure > 0) {
                    $error = 'Impossibile eliminare: esistono richieste di chiusura associate.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM servizi WHERE id = ?");
                    $stmt->execute([$servizio_id]);
                    $message = 'Servizio eliminato con successo.';
                }
            } catch (Exception $e) {
                $error = 'Errore durante l\'eliminazione del servizio.';
            }
            break;
            
        case 'create_credential':
            $servizio_id = (int)$_POST['servizio_id'];
            $tipo_credenziale = $_POST['tipo_credenziale'] ?? '';
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $server = trim($_POST['server'] ?? '');
            $porta = !empty($_POST['porta']) ? (int)$_POST['porta'] : null;
            $note = trim($_POST['note'] ?? '');
            
            if (empty($tipo_credenziale) || empty($username) || empty($password)) {
                $error = 'Tipo, username e password sono obbligatori.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO credenziali (servizio_id, tipo_credenziale, username, password, server, porta, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$servizio_id, $tipo_credenziale, $username, $password, $server ?: null, $porta, $note ?: null]);
                    $message = 'Credenziale aggiunta con successo.';
                } catch (Exception $e) {
                    $error = 'Errore durante l\'aggiunta della credenziale.';
                }
            }
            break;
            
        case 'update_credential':
            $credential_id = (int)$_POST['credential_id'];
            $tipo_credenziale = $_POST['tipo_credenziale'] ?? '';
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $server = trim($_POST['server'] ?? '');
            $porta = !empty($_POST['porta']) ? (int)$_POST['porta'] : null;
            $note = trim($_POST['note'] ?? '');
            
            if (empty($tipo_credenziale) || empty($username) || empty($password)) {
                $error = 'Tipo, username e password sono obbligatori.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE credenziali SET tipo_credenziale = ?, username = ?, password = ?, server = ?, porta = ?, note = ? WHERE id = ?");
                    $stmt->execute([$tipo_credenziale, $username, $password, $server ?: null, $porta, $note ?: null, $credential_id]);
                    $message = 'Credenziale aggiornata con successo.';
                } catch (Exception $e) {
                    $error = 'Errore durante l\'aggiornamento della credenziale.';
                }
            }
            break;
            
        case 'delete_credential':
            $credential_id = (int)$_POST['credential_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM credenziali WHERE id = ?");
                $stmt->execute([$credential_id]);
                $message = 'Credenziale eliminata con successo.';
            } catch (Exception $e) {
                $error = 'Errore durante l\'eliminazione della credenziale.';
            }
            break;
    }
}

// Recupero lista clienti per dropdown
try {
    $stmt = $pdo->query("SELECT id, nome, cognome, email FROM users WHERE tipo = 'cliente' AND attivo = 1 ORDER BY nome, cognome");
    $clienti = $stmt->fetchAll();
} catch (Exception $e) {
    $clienti = [];
}

// Recupero servizi (filtrati per cliente se selezionato)
try {
    if ($cliente_selezionato > 0) {
        $stmt = $pdo->prepare("
            SELECT s.*, u.nome, u.cognome, u.email,
                   COUNT(c.id) as credenziali_count
            FROM servizi s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN credenziali c ON s.id = c.servizio_id
            WHERE s.user_id = ?
            GROUP BY s.id
            ORDER BY s.attivo DESC, s.data_creazione DESC
        ");
        $stmt->execute([$cliente_selezionato]);
    } else {
        $stmt = $pdo->query("
            SELECT s.*, u.nome, u.cognome, u.email,
                   COUNT(c.id) as credenziali_count
            FROM servizi s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN credenziali c ON s.id = c.servizio_id
            GROUP BY s.id
            ORDER BY s.attivo DESC, s.data_creazione DESC
        ");
    }
    $servizi = $stmt->fetchAll();
} catch (Exception $e) {
    $servizi = [];
    $error = 'Errore nel caricamento dei servizi.';
}

// Se richiesto, recupero credenziali per un servizio specifico
$servizio_credenziali = [];
if (isset($_GET['servizio_id'])) {
    $servizio_id = (int)$_GET['servizio_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM credenziali WHERE servizio_id = ? ORDER BY tipo_credenziale");
        $stmt->execute([$servizio_id]);
        $servizio_credenziali = $stmt->fetchAll();
    } catch (Exception $e) {
        $servizio_credenziali = [];
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Gestione Servizi</title>
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
        
        .filters-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            align-items: end;
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
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
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
        
        .service-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .hosting-standard {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .server-dedicato {
            background-color: #f3e5f5;
            color: #7b1fa2;
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
            margin: 2% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
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
        
        .credentials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .credential-card {
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            padding: 1rem;
            background-color: #f8f9fa;
        }
        
        .credential-type {
            font-weight: bold;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .cred-cpanel {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .cred-webmail {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .cred-ssh {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .credential-info {
            margin-bottom: 0.5rem;
        }
        
        .credential-info strong {
            display: inline-block;
            width: 80px;
            font-size: 0.9rem;
        }
        
        .actions-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e1e1e1;
            margin-bottom: 1rem;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-bottom-color: #667eea;
            background-color: #f8f9fa;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .credentials-grid {
                grid-template-columns: 1fr;
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
                <h1><?php echo SITE_NAME; ?> - Gestione Servizi</h1>
            </div>
            <div class="nav-menu">
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="gestione_clienti.php">Gestisci Clienti</a>
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
        
        <!-- Filtri -->
        <div class="filters-card">
            <h3>Filtri</h3>
            <div class="filters-grid">
                <div class="form-group">
                    <label for="cliente_filter">Filtra per Cliente</label>
                    <select id="cliente_filter" onchange="filterByCliente(this.value)">
                        <option value="0">Tutti i clienti</option>
                        <?php foreach ($clienti as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" <?php echo $cliente_selezionato == $cliente['id'] ? 'selected' : ''; ?>>
                                <?php echo e($cliente['nome'] . ' ' . $cliente['cognome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button class="btn" onclick="openModal('create_servizio')">Nuovo Servizio</button>
                </div>
            </div>
        </div>
        
        <!-- Lista Servizi -->
        <div class="section-card">
            <div class="section-title">
                <span>Servizi (<?php echo count($servizi); ?>)</span>
            </div>
            
            <?php if (empty($servizi)): ?>
                <p>Nessun servizio trovato.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" id="serviziTable">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Servizio</th>
                                <th>Tipo</th>
                                <th>Dominio</th>
                                <th>Credenziali</th>
                                <th>Stato</th>
                                <th>Creato</th>
                                <th>Scadenza</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servizi as $servizio): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($servizio['nome'] . ' ' . $servizio['cognome']); ?></strong><br>
                                        <small><?php echo e($servizio['email']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo e($servizio['nome_servizio']); ?></strong>
                                        <?php if ($servizio['descrizione']): ?>
                                            <br><small><?php echo e(substr($servizio['descrizione'], 0, 50)) . (strlen($servizio['descrizione']) > 50 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="service-type <?php echo $servizio['tipo_servizio']; ?>">
                                            <?php echo $servizio['tipo_servizio'] === 'hosting_standard' ? 'Hosting' : 'Server'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo e($servizio['dominio']) ?: '-'; ?></td>
                                    <td>
                                        <span class="status-badge" style="background: #e3f2fd; color: #1976d2;">
                                            <?php echo $servizio['credenziali_count']; ?> credenziali
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $servizio['attivo'] ? 'attivo' : 'inattivo'; ?>">
                                            <?php echo $servizio['attivo'] ? 'Attivo' : 'Inattivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($servizio['data_creazione'])); ?></td>
                                    <td>
                                        <?php echo $servizio['data_scadenza'] ? date('d/m/Y', strtotime($servizio['data_scadenza'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <div class="actions-group">
                                            <button class="btn btn-info btn-sm" onclick="viewCredentials(<?php echo $servizio['id']; ?>, '<?php echo e($servizio['nome_servizio']); ?>')">
                                                Credenziali
                                            </button>
                                            <button class="btn btn-sm" onclick="editServizio(<?php echo htmlspecialchars(json_encode($servizio)); ?>)">
                                                Modifica
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_servizio">
                                                <input type="hidden" name="servizio_id" value="<?php echo $servizio['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Confermi il cambio di stato?')">
                                                    <?php echo $servizio['attivo'] ? 'Disattiva' : 'Attiva'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_servizio">
                                                <input type="hidden" name="servizio_id" value="<?php echo $servizio['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('ATTENZIONE: Questa azione eliminerà definitivamente il servizio e tutte le sue credenziali. Confermi?')">
                                                    Elimina
                                                </button>
                                            </form>
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
    
    <!-- Modal Nuovo/Modifica Servizio -->
    <div id="servizioModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('servizioModal')">&times;</span>
            <h2 id="servizioModalTitle">Nuovo Servizio</h2>
            
            <form method="POST" id="servizioForm">
                <input type="hidden" name="action" id="servizioAction" value="create_servizio">
                <input type="hidden" name="servizio_id" id="servizioId" value="">
                
                <div class="form-group">
                    <label for="user_id">Cliente *</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Seleziona cliente</option>
                        <?php foreach ($clienti as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>">
                                <?php echo e($cliente['nome'] . ' ' . $cliente['cognome'] . ' (' . $cliente['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="nome_servizio">Nome Servizio *</label>
                    <input type="text" id="nome_servizio" name="nome_servizio" required>
                </div>
                
                <div class="form-group">
                    <label for="tipo_servizio">Tipo Servizio *</label>
                    <select id="tipo_servizio" name="tipo_servizio" required>
                        <option value="">Seleziona tipo</option>
                        <option value="hosting_standard">Hosting Standard</option>
                        <option value="server_dedicato">Server Dedicato</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="dominio">Dominio</label>
                    <input type="text" id="dominio" name="dominio" placeholder="es. example.com">
                </div>
                
                <div class="form-group">
                    <label for="descrizione">Descrizione</label>
                    <textarea id="descrizione" name="descrizione" rows="3" placeholder="Descrizione del servizio..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="data_scadenza">Data Scadenza</label>
                    <input type="date" id="data_scadenza" name="data_scadenza">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn" onclick="closeModal('servizioModal')" style="background: #6c757d;">Annulla</button>
                    <button type="submit" class="btn" id="servizioSubmitBtn">Crea Servizio</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Credenziali -->
    <div id="credenzialiModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('credenzialiModal')">&times;</span>
            <h2 id="credenzialiModalTitle">Gestione Credenziali</h2>
            
            <div class="tabs">
                <div class="tab active" onclick="showTab('lista_credenziali')">Lista Credenziali</div>
                <div class="tab" onclick="showTab('nuova_credenziale')">Aggiungi Credenziale</div>
            </div>
            
            <!-- Tab Lista Credenziali -->
            <div id="lista_credenziali" class="tab-content active">
                <div id="credenzialiContent">
                    <!-- Contenuto caricato dinamicamente -->
                </div>
            </div>
            
            <!-- Tab Nuova Credenziale -->
            <div id="nuova_credenziale" class="tab-content">
                <form method="POST" id="credenzialForm">
                    <input type="hidden" name="action" id="credenzialAction" value="create_credential">
                    <input type="hidden" name="servizio_id" id="credServizioId" value="">
                    <input type="hidden" name="credential_id" id="credentialId" value="">
                    
                    <div class="form-group">
                        <label for="tipo_credenziale">Tipo Credenziale *</label>
                        <select id="tipo_credenziale" name="tipo_credenziale" required>
                            <option value="">Seleziona tipo</option>
                            <option value="cpanel">cPanel</option>
                            <option value="webmail">Webmail</option>
                            <option value="ssh">SSH</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cred_username">Username *</label>
                        <input type="text" id="cred_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cred_password">Password *</label>
                        <input type="text" id="cred_password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cred_server">Server</label>
                        <input type="text" id="cred_server" name="server" placeholder="es. server1.hosting.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="cred_porta">Porta</label>
                        <input type="number" id="cred_porta" name="porta" placeholder="es. 22, 587, 993">
                    </div>
                    
                    <div class="form-group">
                        <label for="cred_note">Note</label>
                        <textarea id="cred_note" name="note" rows="3" placeholder="Note aggiuntive..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn" onclick="resetCredentialForm()" style="background: #6c757d;">Reset</button>
                        <button type="submit" class="btn" id="credenzialSubmitBtn">Aggiungi Credenziale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function filterByCliente(clienteId) {
            const url = new URL(window.location);
            if (clienteId > 0) {
                url.searchParams.set('cliente_id', clienteId);
            } else {
                url.searchParams.delete('cliente_id');
            }
            window.location = url;
        }
        
        function openModal(action) {
            if (action === 'create_servizio') {
                const modal = document.getElementById('servizioModal');
                const form = document.getElementById('servizioForm');
                const title = document.getElementById('servizioModalTitle');
                const submitBtn = document.getElementById('servizioSubmitBtn');
                
                // Reset form
                form.reset();
                document.getElementById('servizioAction').value = 'create_servizio';
                document.getElementById('servizioId').value = '';
                
                title.textContent = 'Nuovo Servizio';
                submitBtn.textContent = 'Crea Servizio';
                
                // Pre-seleziona cliente se filtrato
                const clienteFilter = document.getElementById('cliente_filter');
                if (clienteFilter.value > 0) {
                    document.getElementById('user_id').value = clienteFilter.value;
                }
                
                modal.style.display = 'block';
            }
        }
        
        function editServizio(servizio) {
            const modal = document.getElementById('servizioModal');
            const form = document.getElementById('servizioForm');
            const title = document.getElementById('servizioModalTitle');
            const submitBtn = document.getElementById('servizioSubmitBtn');
            
            // Popolamento form
            document.getElementById('servizioAction').value = 'update_servizio';
            document.getElementById('servizioId').value = servizio.id;
            document.getElementById('user_id').value = servizio.user_id;
            document.getElementById('nome_servizio').value = servizio.nome_servizio;
            document.getElementById('tipo_servizio').value = servizio.tipo_servizio;
            document.getElementById('dominio').value = servizio.dominio || '';
            document.getElementById('descrizione').value = servizio.descrizione || '';
            document.getElementById('data_scadenza').value = servizio.data_scadenza || '';
            
            title.textContent = 'Modifica Servizio';
            submitBtn.textContent = 'Aggiorna Servizio';
            
            modal.style.display = 'block';
        }
        
        function viewCredentials(servizioId, nomeServizio) {
            const modal = document.getElementById('credenzialiModal');
            const title = document.getElementById('credenzialiModalTitle');
            
            title.textContent = 'Credenziali - ' + nomeServizio;
            document.getElementById('credServizioId').value = servizioId;
            
            // Carica credenziali
            loadCredentials(servizioId);
            
            modal.style.display = 'block';
        }
        
        function loadCredentials(servizioId) {
            const content = document.getElementById('credenzialiContent');
            content.innerHTML = '<p>Caricamento...</p>';
            
            fetch(`get_credentials.php?servizio_id=${servizioId}`)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(error => {
                    content.innerHTML = '<p>Errore nel caricamento delle credenziali.</p>';
                });
        }
        
        function editCredential(credential) {
            // Passa al tab di modifica
            showTab('nuova_credenziale');
            
            // Popolamento form
            document.getElementById('credenzialAction').value = 'update_credential';
            document.getElementById('credentialId').value = credential.id;
            document.getElementById('tipo_credenziale').value = credential.tipo_credenziale;
            document.getElementById('cred_username').value = credential.username;
            document.getElementById('cred_password').value = credential.password;
            document.getElementById('cred_server').value = credential.server || '';
            document.getElementById('cred_porta').value = credential.porta || '';
            document.getElementById('cred_note').value = credential.note || '';
            
            document.getElementById('credenzialSubmitBtn').textContent = 'Aggiorna Credenziale';
        }
        
        function deleteCredential(credentialId) {
            if (confirm('Confermi l\'eliminazione di questa credenziale?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_credential">
                    <input type="hidden" name="credential_id" value="${credentialId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resetCredentialForm() {
            const form = document.getElementById('credenzialForm');
            form.reset();
            document.getElementById('credenzialAction').value = 'create_credential';
            document.getElementById('credentialId').value = '';
            document.getElementById('credenzialSubmitBtn').textContent = 'Aggiungi Credenziale';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'credenzialiModal') {
                resetCredentialForm();
                showTab('lista_credenziali');
            }
        }
        
        function showTab(tabName) {
            // Nascondi tutti i contenuti
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Rimuovi classe active da tutti i tab
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Mostra il contenuto selezionato
            document.getElementById(tabName).classList.add('active');
            
            // Attiva il tab selezionato
            event.target.classList.add('active');
        }
        
        // Chiudi modal cliccando fuori
        window.onclick = function(event) {
            const servizioModal = document.getElementById('servizioModal');
            const credenzialiModal = document.getElementById('credenzialiModal');
            
            if (event.target == servizioModal) {
                closeModal('servizioModal');
            }
            if (event.target == credenzialiModal) {
                closeModal('credenzialiModal');
            }
        }
        
        // Auto-apertura modal credenziali se richiesto
        <?php if (isset($_GET['add_credentials']) && isset($_GET['servizio_id'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                viewCredentials(<?php echo (int)$_GET['servizio_id']; ?>, 'Nuovo Servizio');
                showTab('nuova_credenziale');
            });
        <?php endif; ?>
    </script>
</body>
</html>