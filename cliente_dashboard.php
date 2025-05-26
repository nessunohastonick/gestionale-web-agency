<?php
require_once 'config.php';
verificaCliente();

$message = '';
$error = '';

// Gestione reinvio credenziali
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reinvia_credenziali'])) {
    $servizio_id = (int)$_POST['servizio_id'];
    
    try {
        // Verifica che il servizio appartenga al cliente e abbia già credenziali inviate
        $stmt = $pdo->prepare("
            SELECT s.nome_servizio, s.dominio, u.email, u.nome, u.cognome
            FROM servizi s
            JOIN users u ON s.user_id = u.id
            JOIN storico_chiusure sc ON s.id = sc.servizio_id
            WHERE s.id = ? AND s.user_id = ? AND sc.stato = 'credenziali_inviate'
        ");
        $stmt->execute([$servizio_id, $_SESSION['user_id']]);
        $servizio_info = $stmt->fetch();
        
        if ($servizio_info) {
            // Recupero credenziali
            $stmt = $pdo->prepare("SELECT * FROM credenziali WHERE servizio_id = ?");
            $stmt->execute([$servizio_id]);
            $credenziali = $stmt->fetchAll();
            
            if (!empty($credenziali)) {
                // Invio email con credenziali
                $emailBody = "<h2>Reinvio Credenziali - {$servizio_info['nome_servizio']}</h2>";
                $emailBody .= "<p>Gentile {$servizio_info['nome']} {$servizio_info['cognome']},</p>";
                $emailBody .= "<p>Come richiesto, di seguito trovi nuovamente le credenziali per il servizio <strong>{$servizio_info['nome_servizio']}</strong>";
                if ($servizio_info['dominio']) {
                    $emailBody .= " ({$servizio_info['dominio']})";
                }
                $emailBody .= ":</p>";
                
                foreach ($credenziali as $cred) {
                    $emailBody .= "<div style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
                    $emailBody .= "<h3>" . ucfirst($cred['tipo_credenziale']) . "</h3>";
                    $emailBody .= "<p><strong>Username:</strong> {$cred['username']}</p>";
                    $emailBody .= "<p><strong>Password:</strong> {$cred['password']}</p>";
                    if ($cred['server']) {
                        $emailBody .= "<p><strong>Server:</strong> {$cred['server']}</p>";
                    }
                    if ($cred['porta']) {
                        $emailBody .= "<p><strong>Porta:</strong> {$cred['porta']}</p>";
                    }
                    if ($cred['note']) {
                        $emailBody .= "<p><strong>Note:</strong> {$cred['note']}</p>";
                    }
                    $emailBody .= "</div>";
                }
                
                $emailBody .= "<p><em>Questo è un reinvio delle credenziali già precedentemente inviate.</em></p>";
                $emailBody .= "<p>Cordiali saluti,<br>" . SITE_NAME . "</p>";
                
                if (inviaEmail($servizio_info['email'], "Reinvio Credenziali - {$servizio_info['nome_servizio']}", $emailBody)) {
                    $message = 'Credenziali reinviate con successo alla tua email.';
                } else {
                    $error = 'Errore durante il reinvio. Riprova o contatta l\'assistenza.';
                }
            } else {
                $error = 'Nessuna credenziale trovata per questo servizio.';
            }
        } else {
            $error = 'Servizio non trovato o non autorizzato.';
        }
    } catch (Exception $e) {
        $error = 'Errore durante il reinvio delle credenziali.';
    }
}

// Gestione richiesta chiusura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['richiedi_chiusura'])) {
    $servizio_id = (int)$_POST['servizio_id'];
    $conferma_responsabilita = isset($_POST['conferma_responsabilita']);
    $note_cliente = trim($_POST['note_cliente'] ?? '');
    
    if (!$conferma_responsabilita) {
        $error = 'Devi confermare la dichiarazione di responsabilità per procedere.';
    } else {
        try {
            // Verifica che il servizio appartenga al cliente
            $stmt = $pdo->prepare("SELECT id, nome_servizio FROM servizi WHERE id = ? AND user_id = ? AND attivo = 1");
            $stmt->execute([$servizio_id, $_SESSION['user_id']]);
            $servizio = $stmt->fetch();
            
            if (!$servizio) {
                $error = 'Servizio non trovato o non autorizzato.';
            } else {
                // Controllo se esiste già una richiesta pendente
                $stmt = $pdo->prepare("SELECT id FROM storico_chiusure WHERE servizio_id = ? AND stato = 'richiesta'");
                $stmt->execute([$servizio_id]);
                
                if ($stmt->fetch()) {
                    $error = 'Esiste già una richiesta di chiusura pendente per questo servizio.';
                } else {
                    // Inserimento richiesta
                    $stmt = $pdo->prepare("INSERT INTO storico_chiusure (servizio_id, user_id, ip_richiesta, note_cliente) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$servizio_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $note_cliente]);
                    
                    // INVIO AUTOMATICO CREDENZIALI
                    try {
                        // Recupero credenziali del servizio
                        $stmt = $pdo->prepare("SELECT * FROM credenziali WHERE servizio_id = ?");
                        $stmt->execute([$servizio_id]);
                        $credenziali = $stmt->fetchAll();
                        
                        // Recupero dettagli cliente
                        $stmt = $pdo->prepare("
                            SELECT u.email, u.nome, u.cognome, s.nome_servizio, s.dominio
                            FROM users u
                            JOIN servizi s ON s.user_id = u.id
                            WHERE u.id = ? AND s.id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $servizio_id]);
                        $dettagli = $stmt->fetch();
                        
                        if ($dettagli && !empty($credenziali)) {
                            // Creazione email con credenziali
                            $emailBody = "<h2>Credenziali per il servizio: {$dettagli['nome_servizio']}</h2>";
                            $emailBody .= "<p>Gentile {$dettagli['nome']} {$dettagli['cognome']},</p>";
                            $emailBody .= "<p>Come richiesto, di seguito trovi le credenziali per il servizio <strong>{$dettagli['nome_servizio']}</strong>";
                            if ($dettagli['dominio']) {
                                $emailBody .= " ({$dettagli['dominio']})";
                            }
                            $emailBody .= ":</p>";
                            
                            foreach ($credenziali as $cred) {
                                $emailBody .= "<div style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
                                $emailBody .= "<h3>" . ucfirst($cred['tipo_credenziale']) . "</h3>";
                                $emailBody .= "<p><strong>Username:</strong> {$cred['username']}</p>";
                                $emailBody .= "<p><strong>Password:</strong> {$cred['password']}</p>";
                                if ($cred['server']) {
                                    $emailBody .= "<p><strong>Server:</strong> {$cred['server']}</p>";
                                }
                                if ($cred['porta']) {
                                    $emailBody .= "<p><strong>Porta:</strong> {$cred['porta']}</p>";
                                }
                                if ($cred['note']) {
                                    $emailBody .= "<p><strong>Note:</strong> {$cred['note']}</p>";
                                }
                                $emailBody .= "</div>";
                            }
                            
                            $emailBody .= "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ffc107;'>";
                            $emailBody .= "<h3>⚠️ Importante</h3>";
                            $emailBody .= "<p>Ti ricordiamo che:</p>";
                            $emailBody .= "<ul>";
                            $emailBody .= "<li>Da questo momento la gestione tecnica del servizio è completamente a tuo carico</li>";
                            $emailBody .= "<li>Conserva queste credenziali in modo sicuro</li>";
                            $emailBody .= "<li>Il nostro supporto tecnico per questo servizio è terminato</li>";
                            $emailBody .= "</ul>";
                            $emailBody .= "</div>";
                            
                            $emailBody .= "<p>Cordiali saluti,<br>" . SITE_NAME . "</p>";
                            
                            // Invio email
                            if (inviaEmail($dettagli['email'], "Credenziali servizio: {$dettagli['nome_servizio']}", $emailBody)) {
                                // Aggiornamento stato a credenziali_inviate
                                $stmt = $pdo->prepare("UPDATE storico_chiusure SET stato = 'credenziali_inviate', data_invio_credenziali = NOW() WHERE servizio_id = ? AND user_id = ? ORDER BY data_richiesta DESC LIMIT 1");
                                $stmt->execute([$servizio_id, $_SESSION['user_id']]);
                                
                                $message = 'Richiesta di chiusura processata. Le credenziali sono state inviate alla tua email.';
                            } else {
                                $message = 'Richiesta di chiusura registrata. Le credenziali verranno inviate appena possibile.';
                            }
                        } else {
                            $message = 'Richiesta di chiusura registrata. Le credenziali verranno configurate e inviate appena possibile.';
                        }
                    } catch (Exception $e) {
                        $message = 'Richiesta di chiusura registrata. Le credenziali verranno inviate appena possibile.';
                    }
                    
                    // Invio email di notifica all'admin
                    $emailBodyAdmin = "
                    <h2>Nuova Richiesta di Chiusura Contratto</h2>
                    <p><strong>Cliente:</strong> {$_SESSION['nome_completo']}</p>
                    <p><strong>Servizio:</strong> {$servizio['nome_servizio']}</p>
                    <p><strong>Data Richiesta:</strong> " . date('d/m/Y H:i') . "</p>
                    <p><strong>Note Cliente:</strong> " . e($note_cliente) . "</p>
                    <p><strong>Credenziali:</strong> Inviate automaticamente</p>
                    <p><a href='" . SITE_URL . "/admin_dashboard.php'>Accedi al pannello di controllo</a></p>
                    ";
                    
                    inviaEmail(ADMIN_EMAIL, 'Nuova Richiesta Chiusura - ' . $servizio['nome_servizio'], $emailBodyAdmin);
                }
            }
        } catch (Exception $e) {
            $error = 'Errore durante l\'invio della richiesta. Riprova.';
        }
    }
}

// Recupero servizi attivi
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               sc.stato as stato_chiusura,
               sc.data_invio_credenziali,
               (SELECT COUNT(*) FROM storico_chiusure sc2 WHERE sc2.servizio_id = s.id AND sc2.stato IN ('richiesta', 'credenziali_inviate')) as richiesta_pendente
        FROM servizi s 
        LEFT JOIN storico_chiusure sc ON s.id = sc.servizio_id AND sc.id = (
            SELECT MAX(id) FROM storico_chiusure WHERE servizio_id = s.id
        )
        WHERE s.user_id = ? AND s.attivo = 1 
        ORDER BY s.data_creazione DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $servizi = $stmt->fetchAll();
} catch (Exception $e) {
    $servizi = [];
    $error = 'Errore nel caricamento dei servizi.';
}

// Recupero storico chiusure
try {
    $stmt = $pdo->prepare("
        SELECT sc.*, s.nome_servizio 
        FROM storico_chiusure sc
        JOIN servizi s ON sc.servizio_id = s.id
        WHERE sc.user_id = ?
        ORDER BY sc.data_richiesta DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $storico = $stmt->fetchAll();
} catch (Exception $e) {
    $storico = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Dashboard Cliente</title>
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
        
        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }
        
        .welcome-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .service-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .service-card:hover {
            transform: translateY(-2px);
        }
        
        .service-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .hosting-standard {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .server-dedicato {
            background-color: #f3e5f5;
            color: #7b1fa2;
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
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
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
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .checkbox-group input {
            width: auto;
            margin-right: 0.5rem;
            margin-top: 0.25rem;
        }
        
        .history-table {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
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
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Benvenuto, <?php echo e($_SESSION['nome_completo']); ?></p>
            </div>
            <div>
                <a href="login.php?logout=1" class="btn btn-sm">Logout</a>
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
        
        <div class="welcome-card">
            <h2>I Tuoi Servizi</h2>
            <p>Gestisci i tuoi servizi attivi e richiedi la chiusura dei contratti quando necessario.</p>
        </div>
        
        <?php if (empty($servizi)): ?>
            <div class="welcome-card">
                <h3>Nessun servizio attivo</h3>
                <p>Al momento non hai servizi attivi associati al tuo account.</p>
            </div>
        <?php else: ?>
            <div class="services-grid">
                <?php foreach ($servizi as $servizio): ?>
                    <div class="service-card">
                        <div class="service-type <?php echo $servizio['tipo_servizio']; ?>">
                            <?php echo $servizio['tipo_servizio'] === 'hosting_standard' ? 'Hosting Standard' : 'Server Dedicato'; ?>
                        </div>
                        
                        <h3><?php echo e($servizio['nome_servizio']); ?></h3>
                        
                        <?php if ($servizio['dominio']): ?>
                            <p><strong>Dominio:</strong> <?php echo e($servizio['dominio']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($servizio['descrizione']): ?>
                            <p><strong>Descrizione:</strong> <?php echo e($servizio['descrizione']); ?></p>
                        <?php endif; ?>
                        
                        <p><strong>Attivo dal:</strong> <?php echo date('d/m/Y', strtotime($servizio['data_creazione'])); ?></p>
                        
                        <?php if ($servizio['data_scadenza']): ?>
                            <p><strong>Scadenza:</strong> <?php echo date('d/m/Y', strtotime($servizio['data_scadenza'])); ?></p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 1rem;">
                            <?php if ($servizio['richiesta_pendente'] > 0): ?>
                                <?php if ($servizio['stato_chiusura'] === 'credenziali_inviate' || $servizio['data_invio_credenziali']): ?>
                                    <button class="btn btn-warning btn-sm" onclick="reinviaCredenziali(<?php echo $servizio['id']; ?>, '<?php echo e($servizio['nome_servizio']); ?>')">
                                        Contratto Chiuso - Reinvia Credenziali
                                    </button>
                                    <br><small style="color: #666; margin-top: 0.5rem; display: block;">
                                        Credenziali inviate il <?php echo formatDate($servizio['data_invio_credenziali']); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="status-badge status-richiesta">Richiesta Chiusura in Elaborazione</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-danger btn-sm" onclick="apriModalChiusura(<?php echo $servizio['id']; ?>, '<?php echo e($servizio['nome_servizio']); ?>')">
                                    Richiedi Chiusura Contratto
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($storico)): ?>
            <div class="history-table">
                <h3>Storico Chiusure</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Servizio</th>
                            <th>Data Richiesta</th>
                            <th>Stato</th>
                            <th>Data Invio Credenziali</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($storico as $record): ?>
                            <tr>
                                <td><?php echo e($record['nome_servizio']); ?></td>
                                <td><?php echo formatDate($record['data_richiesta']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['stato']; ?>">
                                        <?php 
                                        switch($record['stato']) {
                                            case 'richiesta': echo 'In Attesa'; break;
                                            case 'credenziali_inviate': echo 'Credenziali Inviate'; break;
                                            case 'completata': echo 'Completata'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $record['data_invio_credenziali'] ? formatDate($record['data_invio_credenziali']) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal Richiesta Chiusura -->
    <div id="modalChiusura" class="modal">
        <div class="modal-content">
            <span class="close" onclick="chiudiModal()">&times;</span>
            <h2>Richiesta Chiusura Contratto</h2>
            <p id="nomeServizio" style="margin-bottom: 1.5rem; font-weight: bold;"></p>
            
            <form method="POST">
                <input type="hidden" id="servizio_id" name="servizio_id" value="">
                <input type="hidden" name="richiedi_chiusura" value="1">
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="conferma_responsabilita" name="conferma_responsabilita" required>
                        <label for="conferma_responsabilita">
                            <strong>Confermo che:</strong> Da questo momento ogni problematica tecnica relativa al sito/servizio non sarà più di competenza del fornitore. Mi assumo la piena responsabilità della gestione tecnica e delle credenziali che riceverò.
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="note_cliente">Note aggiuntive (opzionale):</label>
                    <textarea id="note_cliente" name="note_cliente" rows="4" placeholder="Inserisci eventuali note o richieste particolari..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="chiudiModal()" style="background: #6c757d;">Annulla</button>
                    <button type="submit" class="btn btn-danger">Conferma Richiesta</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function apriModalChiusura(servicioId, nomeServizio) {
            document.getElementById('servizio_id').value = servicioId;
            document.getElementById('nomeServizio').textContent = 'Servizio: ' + nomeServizio;
            document.getElementById('modalChiusura').style.display = 'block';
        }
        
        function chiudiModal() {
            document.getElementById('modalChiusura').style.display = 'none';
            // Reset form
            document.getElementById('conferma_responsabilita').checked = false;
            document.getElementById('note_cliente').value = '';
        }
        
        function reinviaCredenziali(servizioId, nomeServizio) {
            if (confirm('Vuoi richiedere il reinvio delle credenziali per il servizio: ' + nomeServizio + '?')) {
                // Crea form per reinvio
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="reinvia_credenziali" value="1">
                    <input type="hidden" name="servizio_id" value="${servizioId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Chiudi modal cliccando fuori
        window.onclick = function(event) {
            var modal = document.getElementById('modalChiusura');
            if (event.target == modal) {
                chiudiModal();
            }
        }
    </script>
</body>
</html>