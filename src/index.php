<?php
session_start();
$data_dir = __DIR__ . '/../data';
if (!is_dir($data_dir)) { @mkdir($data_dir, 0777, true); }
$db_file = $data_dir . '/absences.db';
$db = new PDO('sqlite:' . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$db->exec("CREATE TABLE IF NOT EXISTS employes (id INTEGER PRIMARY KEY AUTOINCREMENT, nom TEXT, prenom TEXT, email TEXT UNIQUE, departement TEXT);
           CREATE TABLE IF NOT EXISTS absences (id INTEGER PRIMARY KEY AUTOINCREMENT, employe_id INTEGER, date_debut DATE, date_fin DATE, motif TEXT, statut TEXT DEFAULT 'En attente', created_at DATETIME DEFAULT CURRENT_TIMESTAMP);");

if ($db->query("SELECT COUNT(*) FROM employes")->fetchColumn() == 0) {
    $db->exec("INSERT INTO employes (nom, prenom, email, departement) VALUES ('WARIT', 'Zineb', 'zineb@devsecops.com', 'IT Security'), ('ALAMI', 'Ahmed', 'ahmed@devsecops.com', 'RH'), ('BENNANI', 'Sara', 'sara@finance.com', 'Finance');");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'ajouter') {
        $stmt = $db->prepare("INSERT INTO absences (employe_id, date_debut, date_fin, motif) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['employe_id'], $_POST['date_debut'], $_POST['date_fin'], $_POST['motif']]);
    } elseif ($_POST['action'] === 'approuver') {
        $db->prepare("UPDATE absences SET statut = 'Approuvé' WHERE id = ?")->execute([$_POST['absence_id']]);
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

$employes = $db->query("SELECT * FROM employes ORDER BY prenom")->fetchAll(PDO::FETCH_ASSOC);
$absences = $db->query("SELECT a.*, e.nom, e.prenom FROM absences a JOIN employes e ON a.employe_id = e.id ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$stats = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN statut='Approuvé' THEN 1 ELSE 0 END) as ok FROM absences")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><title>DevSecOps | Gestion des Absences</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-box { background: #667eea; color: white; padding: 15px; border-radius: 10px; flex: 1; text-align: center; }
        h1 { color: #4a5568; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border-bottom: 1px solid #edf2f7; text-align: left; }
        th { background: #f8fafc; color: #718096; text-transform: uppercase; font-size: 0.8rem; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge-wait { background: #fef3c7; color: #92400e; }
        .badge-ok { background: #d1fae5; color: #065f46; }
        button { background: #667eea; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; transition: 0.2s; }
        button:hover { background: #5a67d8; }
        .form-group { display: flex; gap: 10px; flex-wrap: wrap; }
        input, select { padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🏢 Système de Gestion des Absences</h1>
    <p style="text-align: center; color: #718096;">Infrastructure : k3d Multi-Cluster | Sécurité : Sonar & Trivy</p>
    
    <div class="stats">
        <div class="stat-box"><h3>Demandes</h3><div style="font-size:1.5rem"><?= $stats['total'] ?></div></div>
        <div class="stat-box" style="background:#10b981"><h3>Approuvées</h3><div style="font-size:1.5rem"><?= (int)$stats['ok'] ?></div></div>
    </div>

    <div class="card">
        <h2>➕ Nouvelle Demande</h2>
        <form method="POST" class="form-group">
            <input type="hidden" name="action" value="ajouter">
            <select name="employe_id" required>
                <option value="">Choisir l'employé...</option>
                <?php foreach($employes as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= $e['prenom'] ?> <?= $e['nom'] ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_debut" required>
            <input type="date" name="date_fin" required>
            <input type="text" name="motif" placeholder="Motif" required>
            <button type="submit">Envoyer</button>
        </form>
    </div>

    <div class="card">
        <h2> Historique des Absences</h2>
        <table>
            <tr><th>Employé</th><th>Dates</th><th>Motif</th><th>Statut</th><th>Action</th></tr>
            <?php foreach($absences as $a): ?>
            <tr>
                <td><strong><?= $a['prenom'] ?> <?= $a['nom'] ?></strong></td>
                <td><?= $a['date_debut'] ?> au <?= $a['date_fin'] ?></td>
                <td><?= htmlspecialchars($a['motif']) ?></td>
                <td><span class="badge <?= $a['statut']=='Approuvé'?'badge-ok':'badge-wait' ?>"><?= $a['statut'] ?></span></td>
                <td>
                    <?php if($a['statut']=='En attente'): ?>
                        <form method="POST"><input type="hidden" name="action" value="approuver"><input type="hidden" name="absence_id" value="<?= $a['id'] ?>"><button type="submit">Approuver</button></form>
                    <?php else: echo "-"; endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>
