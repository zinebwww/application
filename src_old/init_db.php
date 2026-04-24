<?php
// Initialisation de la base de données SQLite
$data_dir = __DIR__ . '/../data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0777, true);
}

$db = new PDO('sqlite:' . $data_dir . '/absences.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Création des tables
$db->exec("
    CREATE TABLE IF NOT EXISTS employes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        prenom TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        departement TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS absences (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employe_id INTEGER NOT NULL,
        date_debut DATE NOT NULL,
        date_fin DATE NOT NULL,
        motif TEXT NOT NULL,
        statut TEXT DEFAULT 'En attente',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employe_id) REFERENCES employes(id)
    );
");

// Insertion des données de test
$db->exec("
    INSERT OR IGNORE INTO employes (id, nom, prenom, email, departement) VALUES
    (1, 'WARIT', 'Zineb', 'zineb@example.com', 'IT'),
    (2, 'ALAMI', 'Ahmed', 'ahmed@example.com', 'RH'),
    (3, 'BENNANI', 'Sara', 'sara@example.com', 'Finance'),
    (4, 'CHADLI', 'Omar', 'omar@example.com', 'Marketing'),
    (5, 'NAJIB', 'Leila', 'leila@example.com', 'IT');

    INSERT OR IGNORE INTO absences (employe_id, date_debut, date_fin, motif, statut) VALUES
    (1, '2026-04-20', '2026-04-22', 'Congé maladie', 'Approuvé'),
    (2, '2026-04-25', '2026-04-26', 'Congé personnel', 'En attente'),
    (3, '2026-05-01', '2026-05-05', 'Vacances', 'Approuvé'),
    (4, '2026-04-28', '2026-04-29', 'Formation', 'En attente');
");

echo "✅ Base de données initialisée avec succès!\n";
?>
