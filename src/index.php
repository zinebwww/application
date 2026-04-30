<?php
session_start();

/**
 * CONFIGURATION DE LA BASE DE DONNÉES
 * Dans Docker, /var/www/html est le dossier du code (src)
 * Le dossier /var/www/data est utilisé pour la base de données
 */
$data_dir = __DIR__ . '/../data';
$db_file = $data_dir . '/absences.db';

try {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initialisation des tables
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

    // Ajout de données de test si la table est vide
    $count = $db->query("SELECT COUNT(*) FROM employes")->fetchColumn();
    if ($count == 0) {
        $db->exec("
            INSERT INTO employes (nom, prenom, email, departement) VALUES
            ('WARIT', 'Zineb', 'zineb@devsecops.com', 'IT Security'),
            ('ALAMI', 'Ahmed', 'ahmed@devsecops.com', 'RH'),
            ('BENNANI', 'Sara', 'sara@devsecops.com', 'Finance'),
            ('CHADLI', 'Omar', 'omar@devsecops.com', 'Marketing'),
            ('NAJIB', 'Leila', 'leila@devsecops.com', 'IT Operations');
        ");
    }
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

/**
 * GESTION DES ACTIONS (POST)
 */
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'ajouter':
                if (!empty($_POST['employe_id']) && !empty($_POST['date_debut']) && !empty($_POST['date_fin']) && !empty($_POST['motif'])) {
                    $stmt = $db->prepare("INSERT INTO absences (employe_id, date_debut, date_fin, motif) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_POST['employe_id'], $_POST['date_debut'], $_POST['date_fin'], $_POST['motif']]);
                    $_SESSION['flash'] = "✅ Demande ajoutée avec succès !";
                }
                break;
            case 'approuver':
                $stmt = $db->prepare("UPDATE absences SET statut = 'Approuvé' WHERE id = ?");
                $stmt->execute([$_POST['absence_id']]);
                $_SESSION['flash'] = "✅ Demande approuvée !";
                break;
            case 'rejeter':
                $stmt = $db->prepare("UPDATE absences SET statut = 'Rejeté' WHERE id = ?");
                $stmt->execute([$_POST['absence_id']]);
                $_SESSION['flash'] = "❌ Demande rejetée !";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = "❌ Erreur : " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_SESSION['flash'])) {
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// Récupération des données pour l'affichage
$employes = $db->query("SELECT * FROM employes ORDER BY departement, nom")->fetchAll(PDO::FETCH_ASSOC);
$absences = $db->query("
    SELECT a.*, e.nom, e.prenom, e.departement 
    FROM absences a 
    JOIN employes e ON a.employe_id = e.id 
    ORDER BY a.created_at DESC
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
    <title>DevSecOps | Gestion des Absences</title>
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #28a745; --danger: #dc3545; --warning: #ffc107; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 5px solid var(--primary); }
        .header h1 { color: var(--primary); font-size: 1.8rem; margin-bottom: 5px; }
        .header p { color: #666; font-size: 0.9rem; }
        .message { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid var(--success); font-weight: bold; animation: fadeIn 0.5s; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 0.8rem; text-transform: uppercase; color: #888; margin-bottom: 10px; }
        .stat-card .number { font-size: 2rem; font-weight: bold; color: var(--primary); }
        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 25px; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card h2 { font-size: 1.2rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: var(--secondary); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-size: 0.9rem; font-weight: 600; color: #555; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; }
        button { width: 100%; padding: 12px; border: none; border-radius: 8px; background: var(--primary); color: white; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background: var(--secondary); transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; background: #f8f9fa; border-bottom: 2px solid #eee; font-size: 0.85rem; color: #666; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .badge-success { background: #e6fffa; color: #00875a; }
        .badge-warning { background: #fffaf0; color: #975a16; }
        .badge-danger { background: #fff5f5; color: #e53e3e; }
        .btn-action { width: auto; padding: 5px 10px; display: inline-block; margin-right: 5px; font-size: 0.8rem; }
        .footer { text-align: center; margin-top: 40px; color: #666; font-size: 0.8rem; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🏢 Système de Gestion des Absences</h1>
        <p>Pipeline DevSecOps : Jenkins | SonarQube | Trivy | Kubernetes (k3d)</p>
    </div>

    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card"><h3>Total Demandes</h3><div class="number"><?= $stats['total'] ?></div></div>
        <div class="stat-card"><h3>Approuvées</h3><div class="number" style="color:var(--success)"><?= (int)$stats['approuve'] ?></div></div>
        <div class="stat-card"><h3>En attente</h3><div class="number" style="color:var(--warning)"><?= (int)$stats['en_attente'] ?></div></div>
        <div class="stat-card"><h3>Rejetées</h3><div class="number" style="color:var(--danger)"><?= (int)$stats['rejete'] ?></div></div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>➕ Nouvelle demande</h2>
            <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div class="form-group">
                    <label>Employé</label>
                    <select name="employe_id" required>
                        <option value="">-- Choisir un employé --</option>
                        <?php foreach ($employes as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?> (<?= htmlspecialchars($e['departement']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date de début</label>
                    <input type="date" name="date_debut" required>
                </div>
                <div class="form-group">
                    <label>Date de fin</label>
                    <input type="date" name="date_fin" required>
                </div>
                <div class="form-group">
                    <label>Motif de l'absence</label>
                    <textarea name="motif" rows="3" placeholder="Ex: Congé annuel, Maladie..." required></textarea>
                </div>
                <button type="submit">Soumettre la demande</button>
            </form>
        </div>

        <div class="card">
            <h2>📋 Historique des demandes</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Dates</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($absences)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #999; padding: 30px;">Aucune demande enregistrée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($absences as $a): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></strong><br>
                                    <small style="color:#888"><?= htmlspecialchars($a['departement']) ?></small>
                                </td>
                                <td><small><?= date('d/m/Y', strtotime($a['date_debut'])) ?> al <?= date('d/m/Y', strtotime($a['date_fin'])) ?></small></td>
                                <td><?= htmlspecialchars($a['motif']) ?></td>
                                <td>
                                    <?php 
                                        $class = $a['statut'] === 'Approuvé' ? 'badge-success' : ($a['statut'] === 'En attente' ? 'badge-warning' : 'badge-danger');
                                    ?>
                                    <span class="badge <?= $class ?>"><?= $a['statut'] ?></span>
                                </td>
                                <td>
                                    <?php if ($a['statut'] === 'En attente'): ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="approuver">
                                            <input type="hidden" name="absence_id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn-action btn-success" style="width:auto; padding: 2px 8px;">✓</button>
                                        </form>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="rejeter">
                                            <input type="hidden" name="absence_id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn-action btn-danger" style="width:auto; padding: 2px 8px; background:var(--danger)">✗</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#ccc">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2026 DevSecOps Project - Déploiement Haute Disponibilité (Multi-Cluster)</p>
    </div>
</div>
</body>
</html>
