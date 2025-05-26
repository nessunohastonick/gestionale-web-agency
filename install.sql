-- Gestionale Web - Database Setup
-- Eseguire questo script per creare il database e le tabelle

CREATE DATABASE IF NOT EXISTS gestionale_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestionale_web;

-- Tabella utenti (clienti e admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(32) NOT NULL, -- MD5 hash
    email VARCHAR(100) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    tipo ENUM('cliente', 'admin') DEFAULT 'cliente',
    attivo TINYINT(1) DEFAULT 1,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso TIMESTAMP NULL
);

-- Tabella servizi
CREATE TABLE servizi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nome_servizio VARCHAR(200) NOT NULL,
    tipo_servizio ENUM('hosting_standard', 'server_dedicato') NOT NULL,
    dominio VARCHAR(100),
    descrizione TEXT,
    attivo TINYINT(1) DEFAULT 1,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_scadenza DATE NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabella credenziali
CREATE TABLE credenziali (
    id INT AUTO_INCREMENT PRIMARY KEY,
    servizio_id INT NOT NULL,
    tipo_credenziale ENUM('cpanel', 'webmail', 'ssh') NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(200) NOT NULL,
    server VARCHAR(100),
    porta INT DEFAULT NULL,
    note TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (servizio_id) REFERENCES servizi(id) ON DELETE CASCADE
);

-- Tabella storico chiusure
CREATE TABLE storico_chiusure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    servizio_id INT NOT NULL,
    user_id INT NOT NULL,
    data_richiesta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_invio_credenziali TIMESTAMP NULL,
    stato ENUM('richiesta', 'credenziali_inviate', 'completata') DEFAULT 'richiesta',
    ip_richiesta VARCHAR(45),
    note_cliente TEXT,
    FOREIGN KEY (servizio_id) REFERENCES servizi(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Inserimento admin di default
INSERT INTO users (username, password, email, nome, cognome, tipo) 
VALUES ('admin', MD5('admin123'), 'admin@tuodominio.com', 'Amministratore', 'Sistema', 'admin');

-- Inserimento cliente di esempio
INSERT INTO users (username, password, email, nome, cognome, tipo) 
VALUES ('cliente1', MD5('password123'), 'cliente@example.com', 'Mario', 'Rossi', 'cliente');

-- Inserimento servizio di esempio
INSERT INTO servizi (user_id, nome_servizio, tipo_servizio, dominio, descrizione) 
VALUES (2, 'Hosting Sito Web', 'hosting_standard', 'example.com', 'Hosting condiviso con cPanel');

-- Inserimento credenziali di esempio
INSERT INTO credenziali (servizio_id, tipo_credenziale, username, password, server) 
VALUES 
(1, 'cpanel', 'example_cpanel', 'password_cpanel_123', 'server1.hosting.com'),
(1, 'webmail', 'postmaster@example.com', 'webmail_password_123', 'mail.example.com');

-- Indici per ottimizzazione
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_tipo ON users(tipo);
CREATE INDEX idx_servizi_user ON servizi(user_id);
CREATE INDEX idx_servizi_attivo ON servizi(attivo);
CREATE INDEX idx_credenziali_servizio ON credenziali(servizio_id);
CREATE INDEX idx_storico_servizio ON storico_chiusure(servizio_id);
CREATE INDEX idx_storico_user ON storico_chiusure(user_id);

-- Vista per statistiche admin
CREATE VIEW vista_statistiche AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE tipo = 'cliente' AND attivo = 1) as clienti_attivi,
    (SELECT COUNT(*) FROM servizi WHERE attivo = 1) as servizi_attivi,
    (SELECT COUNT(*) FROM storico_chiusure WHERE stato = 'richiesta') as richieste_pending,
    (SELECT COUNT(*) FROM storico_chiusure WHERE data_richiesta >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as chiusure_ultimo_mese;