<?php
require_once 'config.php';
verificaAdmin();

$servizio_id = isset($_GET['servizio_id']) ? (int)$_GET['servizio_id'] : 0;

if ($servizio_id <= 0) {
    echo '<p>ID servizio non valido.</p>';
    exit;
}

try {
    // Recupero credenziali
    $stmt = $pdo->prepare("SELECT * FROM credenziali WHERE servizio_id = ? ORDER BY tipo_credenziale");
    $stmt->execute([$servizio_id]);
    $credenziali = $stmt->fetchAll();
    
    if (empty($credenziali)) {
        echo '<p>Nessuna credenziale configurata per questo servizio.</p>';
        echo '<p><em>Utilizza il tab "Aggiungi Credenziale" per configurare le credenziali.</em></p>';
    } else {
        echo '<div class="credentials-grid">';
        foreach ($credenziali as $cred) {
            echo '<div class="credential-card">';
            echo '<div class="credential-type cred-' . $cred['tipo_credenziale'] . '">';
            echo ucfirst($cred['tipo_credenziale']);
            echo '</div>';
            
            echo '<div class="credential-info">';
            echo '<strong>Username:</strong> ' . e($cred['username']);
            echo '</div>';
            
            echo '<div class="credential-info">';
            echo '<strong>Password:</strong> ' . e($cred['password']);
            echo '</div>';
            
            if ($cred['server']) {
                echo '<div class="credential-info">';
                echo '<strong>Server:</strong> ' . e($cred['server']);
                echo '</div>';
            }
            
            if ($cred['porta']) {
                echo '<div class="credential-info">';
                echo '<strong>Porta:</strong> ' . e($cred['porta']);
                echo '</div>';
            }
            
            if ($cred['note']) {
                echo '<div class="credential-info">';
                echo '<strong>Note:</strong> ' . e($cred['note']);
                echo '</div>';
            }
            
            echo '<div class="credential-info" style="font-size: 0.8em; color: #666;">';
            echo '<strong>Creata:</strong> ' . formatDate($cred['data_creazione']);
            echo '</div>';
            
            echo '<div class="actions-group">';
            echo '<button class="btn btn-sm" onclick="editCredential(' . htmlspecialchars(json_encode($cred)) . ')">Modifica</button>';
            echo '<button class="btn btn-danger btn-sm" onclick="deleteCredential(' . $cred['id'] . ')">Elimina</button>';
            echo '</div>';
            
            echo '</div>';
        }
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<p>Errore nel caricamento delle credenziali.</p>';
}
?>