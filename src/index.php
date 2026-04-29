<?php
session_start();

$data_dir = __DIR__ . '/../data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0777, true);
}

$db_file = $data_dir . '/absences.db';
$db = new PDO('sqlite:' . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

// Insertion des données de test si la table est vide
$count = $db->query("SELECT COUNT(*) FROM employes")->fetchColumn();
if ($count == 0) {
    $db->exec("
        INSERT INTO employes (nom, prenom, email, departement) VALUES
        ('WARIT', 'Zineb', 'zineb@example.com', 'IT'),
        ('ALAMI', 'Ahmed', 'ahmed@example.com', 'RH'),
        ('BENNANI', 'Sara', 'sara@example.com', 'Finance'),
        ('CHADLI', 'Omar', 'omar@example.com', 'Marketing'),
        ('NAJIB', 'Leila', 'leila@example.com', 'IT');
    ");
}

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'ajouter':
            if (!empty($_POST['employe_id']) && !empty($_POST['date_debut']) && !empty($_POST['date_fin']) && !empty($_POST['motif'])) {
                $stmt = $db->prepare("INSERT INTO absences (employe_id, date_debut, date_fin, motif) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_POST['employe_id'], $_POST['date_debut'], $_POST['date_fin'], $_POST['motif']]);
                $message = "✅ Demande ajoutée avec succès!";
            } else {
                $message = "❌ Tous les champs sont obligatoires!";
            }
            break;
        case 'approuver':
            $stmt = $db->prepare("UPDATE absences SET statut = 'Approuvé' WHERE id = ?");
            $stmt->execute([$_POST['absence_id']]);
            $message = "✅ Demande approuvée!";
            break;
        case 'rejeter':
            $stmt = $db->prepare("UPDATE absences SET statut = 'Rejeté' WHERE id = ?");
            $stmt->execute([$_POST['absence_id']]);
            $message = "❌ Demande rejetée!";
            break;
    }
    // Rediriger pour éviter la resoumission du formulaire
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Récupération des données
$employes = $db->query("SELECT * FROM employes ORDER BY departement, nom")->fetchAll(PDO::FETCH_ASSOC);
$absences = $db->query("
    SELECT a.*, e.nom, e.prenom, e.departement 
    FROM absences a 
    JOIN employes e ON a.employe_id = e.id 
    ORDER BY a.date_debut DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'Approuvé' THEN 1 ELSE 0 END) as approuve,
        SUM(CASE WHEN statut = 'En attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'Rejeté' THEN 1 ELSE 0 END) as rejete
    FROM absences
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Absences</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header h1 { color: #667eea; margin-bottom: 10px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card .number { font-size: 3em; font-weight: bold; color: #667eea; }
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
        }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .btn-sm { padding: 5px 10px; font-size: 0.85em; margin: 0 2px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .footer { text-align: center; color: white; margin-top: 30px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🏢 Gestion des Absences</h1>
        <p>Déployé via Jenkins + k3d | Pipeline DevSecOps</p>
    </div>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card"><h3>Total</h3><div class="number"><?= $stats['total'] ?></div></div>
        <div class="stat-card"><h3>Approuvées</h3><div class="number" style="color:#28a745"><?= $stats['approuve'] ?></div></div>
        <div class="stat-card"><h3>En attente</h3><div class="number" style="color:#ffc107"><?= $stats['en_attente'] ?></div></div>
        <div class="stat-card"><h3>Rejetées</h3><div class="number" style="color:#dc3545"><?= $stats['rejete'] ?></div></div>
    </div>

    <div class="card">
        <h2>➕ Nouvelle demande</h2>
        <form method="POST">
            <input type="hidden" name="action" value="ajouter">
            <div class="form-group">
                <label>Employé</label>
                <select name="employe_id" required>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($employes as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= $e['prenom'] . ' ' . $e['nom'] ?> (<?= $e['departement'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date début</label>
                <input type="date" name="date_debut" required>
            </div>
            <div class="form-group">
                <label>Date fin</label>
                <input type="date" name="date_fin" required>
            </div>
            <div class="form-group">
                <label>Motif</label>
                <textarea name="motif" rows="3" required></textarea>
            </div>
            <button type="submit">Soumettre</button>
        </form>
    </div>

    <div class="card">
        <h2>📋 Liste des demandes</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Département</th>
                        <th>Dates</th>
                        <th>Motif</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absences)): ?>
                        <tr><td colspan="6" style="text-align: center;">Aucune demande d'absence trouvée</td></tr>
                    <?php else: ?>
                        <?php foreach ($absences as $a): ?>
                        <tr>
                            <td><?= $a['prenom'] . ' ' . $a['nom'] ?></td>
                            <td><?= $a['departement'] ?></td>
                            <td><?= date('d/m/Y', strtotime($a['date_debut'])) ?> → <?= date('d/m/Y', strtotime($a['date_fin'])) ?></td>
                            <td><?= htmlspecialchars(substr($a['motif'], 0, 40)) ?></td>
                            <td>
                                <span class="badge <?= $a['statut'] === 'Approuvé' ? 'badge-success' : ($a['statut'] === 'En attente' ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= $a['statut'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($a['statut'] === 'En attente'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="approuver">
                                        <input type="hidden" name="absence_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn-sm btn-success">✓</button>
                                    </form>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="rejeter">
                                        <input type="hidden" name="absence_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn-sm btn-danger">✗</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        <p>Jenkins → SonarQube → Trivy → Docker → k3d (Kubernetes)</p>
    </div>
</div>
</body>
</html>
