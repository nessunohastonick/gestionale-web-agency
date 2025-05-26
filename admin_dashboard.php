<?php
require_once 'config.php';
verificaAdmin();

$message = '';
$error = '';

// Gestione completamento richiesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['completa_richiesta'])) {
    $richiesta_id = (int)$_POST['richiesta_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE storico_chiusure SET stato = 'completata' WHERE id = ? AND stato = 'credenziali_inviate'");
        $stmt->execute([$richiesta_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = "Richiesta marcata come completata.";
        } else {
            $error = "Richiesta non trovata o non in stato valido.";
        }
    } catch (Exception $e) {
        $error = "Errore durante l'aggiornamento.";
    }
}

// Recupero statistiche
try {
    $stmt = $pdo->query("SELECT * FROM vista_statistiche");
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = ['clienti_attivi' => 0, 'servizi_attivi' => 0, 'richieste_pending' => 0, 'chiusure_ultimo_mese' => 0];
}

// Recupero richieste pendenti - solo quelle con credenziali inviate per completamento
try {
    $stmt = $pdo->query("
        SELECT sc.*, s.nome_servizio, s.tipo_servizio, s.dominio, u.nome, u.cognome, u.email
        FROM storico_chiusure sc
        JOIN servizi s ON sc.servizio_id = s.id
        JOIN users u ON sc.user_id = u.id
        WHERE sc.stato = 'credenziali_inviate'
        ORDER BY sc.data_richiesta ASC
    ");
    $richieste = $stmt->fetchAll();
} catch (Exception $e) {
    $richieste = [];
}

// Recupero clienti attivi
try {
    $stmt = $pdo->query("
        SELECT u.*, COUNT(s.id) as servizi_count
        FROM users u
        LEFT JOIN servizi s ON u.id = s.user_id AND s.attivo = 1
        WHERE u.tipo = 'cliente' AND u.attivo = 1
        GROUP BY u.id
        ORDER BY u.nome, u.cognome
    ");
    $clienti = $stmt->fetchAll();
} catch (Exception $e) {
    $clienti = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Dashboard Admin</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
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
        
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            transition: transform 0.2s;
            margin: 0.25rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-richiesta {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-credenziali_inviate {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-completata {
            background-color: #d4edda;
            color: #155724;
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
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .tabs {
                flex-wrap: wrap;
            }
            
            .tab {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1><?php echo SITE_NAME; ?> - Admin</h1>
                <p>Benvenuto, <?php echo e($_SESSION['nome_completo']); ?></p>
            </div>
            <div class="nav-menu">
                <a href="gestione_clienti.php">Gestisci Clienti</a>
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
        
        <!-- Statistiche -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['clienti_attivi']; ?></div>
                <div class="stat-label">Clienti Attivi</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['servizi_attivi']; ?></div>
                <div class="stat-label">Servizi Attivi</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['richieste_pending']; ?></div>
                <div class="stat-label">Richieste Pendenti</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['chiusure_ultimo_mese']; ?></div>
                <div class="stat-label">Chiusure Ultimo Mese</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="section-card">
            <div class="tabs">
                <div class="tab active" onclick="showTab('richieste')">Richieste Chiusure</div>
                <div class="tab" onclick="showTab('clienti')">Riepilogo Clienti</div>
            </div>
            
            <!-- Tab Richieste -->
            <div id="richieste" class="tab-content active">
                <div class="section-title">Richieste di Chiusura - Credenziali Inviate</div>
                <p style="margin-bottom: 1rem; color: #666; font-style: italic;">
                    Le richieste vengono processate automaticamente. Qui puoi marcare come "completate" quelle già gestite.
                </p>
                
                <?php if (empty($richieste)): ?>
                    <p>Nessuna richiesta da completare.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Servizio</th>
                                    <th>Tipo</th>
                                    <th>Data Richiesta</th>
                                    <th>Credenziali Inviate</th>
                                    <th>Note</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($richieste as $richiesta): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($richiesta['nome'] . ' ' . $richiesta['cognome']); ?></strong><br>
                                            <small><?php echo e($richiesta['email']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo e($richiesta['nome_servizio']); ?></strong>
                                            <?php if ($richiesta['dominio']): ?>
                                                <br><small><?php echo e($richiesta['dominio']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="service-type <?php echo $richiesta['tipo_servizio']; ?>">
                                                <?php echo $richiesta['tipo_servizio'] === 'hosting_standard' ? 'Hosting' : 'Server'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($richiesta['data_richiesta']); ?></td>
                                        <td><?php echo formatDate($richiesta['data_invio_credenziali']); ?></td>
                                        <td>
                                            <?php if ($richiesta['note_cliente']): ?>
                                                <small><?php echo e(substr($richiesta['note_cliente'], 0, 50)) . (strlen($richiesta['note_cliente']) > 50 ? '...' : ''); ?></small>
                                            <?php else: ?>
                                                <small>-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="richiesta_id" value="<?php echo $richiesta['id']; ?>">
                                                <button type="submit" name="completa_richiesta" class="btn btn-warning btn-sm" onclick="return confirm('Marca come completata?')">
                                                    Completa
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Clienti -->
            <div id="clienti" class="tab-content">
                <div class="section-title">Riepilogo Clienti</div>
                
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
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Servizi Attivi</th>
                                    <th>Registrato</th>
                                    <th>Ultimo Accesso</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clienti as $cliente): ?>
                                    <tr>
                                        <td><strong><?php echo e($cliente['nome'] . ' ' . $cliente['cognome']); ?></strong></td>
                                        <td><?php echo e($cliente['email']); ?></td>
                                        <td>
                                            <span class="status-badge" style="background: #e3f2fd; color: #1976d2;">
                                                <?php echo $cliente['servizi_count']; ?> servizi
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($cliente['data_creazione'])); ?></td>
                                        <td>
                                            <?php echo $cliente['ultimo_accesso'] ? formatDate($cliente['ultimo_accesso']) : 'Mai'; ?>
                                        </td>
                                        <td>
                                            <a href="gestione_servizi.php?cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-sm">
                                                Gestisci Servizi
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
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
        
        // Auto-refresh ogni 30 secondi per le richieste pendenti
        setInterval(function() {
            if (document.getElementById('richieste').classList.contains('active')) {
                // Solo se siamo nel tab richieste
                const pendingRequests = document.querySelectorAll('.status-richiesta').length;
                if (pendingRequests > 0) {
                    // Ricarica solo se ci sono richieste pendenti
                    window.location.reload();
                }
            }
        }, 30000);
    </script>
</body>
</html>
                