<?php
session_start();

// Connexion à la base de données
$data_dir = __DIR__ . '/../data';
$db = new PDO('sqlite:' . $data_dir . '/absences.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'ajouter':
            if (!empty($_POST['employe_id']) && !empty($_POST['date_debut']) && !empty($_POST['date_fin']) && !empty($_POST['motif'])) {
                $stmt = $db->prepare("INSERT INTO absences (employe_id, date_debut, date_fin, motif) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_POST['employe_id'], $_POST['date_debut'], $_POST['date_fin'], $_POST['motif']]);
                $message = "✅ Demande d'absence soumise avec succès!";
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
    <title>Gestion des Absences - DevSecOps</title>
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }
        .header h1 { color: #667eea; margin-bottom: 10px; font-size: 2.5em; }
        .header p { color: #666; }
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
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { color: #666; font-size: 0.9em; margin-bottom: 10px; text-transform: uppercase; }
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
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
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
            transition: transform 0.3s;
        }
        button:hover { transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            color: #555;
            font-weight: 600;
        }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f8f9fa; }
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
        .action-buttons { display: flex; gap: 5px; }
        .btn-sm { padding: 5px 10px; font-size: 0.85em; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .footer {
            text-align: center;
            color: white;
            margin-top: 30px;
            padding: 20px;
        }
        @media (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 Système de Gestion des Absences</h1>
            <p>Pipeline DevSecOps - Jenkins | Docker | Kubernetes</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <h3>📊 Total Demandes</h3>
                <div class="number"><?= $stats['total'] ?></div>
            </div>
            <div class="stat-card">
                <h3>✅ Approuvées</h3>
                <div class="number" style="color: #28a745;"><?= $stats['approuve'] ?></div>
            </div>
            <div class="stat-card">
                <h3>⏳ En Attente</h3>
                <div class="number" style="color: #ffc107;"><?= $stats['en_attente'] ?></div>
            </div>
            <div class="stat-card">
                <h3>❌ Rejetées</h3>
                <div class="number" style="color: #dc3545;"><?= $stats['rejete'] ?></div>
            </div>
        </div>
        
        <div class="card">
            <h2>➕ Nouvelle Demande d'Absence</h2>
            <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div class="form-grid">
                    <div class="form-group">
                        <label>👤 Employé</label>
                        <select name="employe_id" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($employes as $emp): ?>
                                <option value="<?= $emp['id'] ?>">
                                    <?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']) ?> (<?= htmlspecialchars($emp['departement']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>📅 Date Début</label>
                        <input type="date" name="date_debut" required>
                    </div>
                    <div class="form-group">
                        <label>📅 Date Fin</label>
                        <input type="date" name="date_fin" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>📝 Motif</label>
                    <textarea name="motif" rows="3" required placeholder="Décrivez la raison de l'absence..."></textarea>
                </div>
                <button type="submit">Soumettre la Demande</button>
            </form>
        </div>
        
        <div class="card">
            <h2>📋 Liste des Demandes d'Absence</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <table>
                            <th>Employé</th>
                            <th>Département</th>
                            <th>Date Début</th>
                            <th>Date Fin</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($absences)): ?>
                            <tr><td colspan="7" style="text-align: center;">Aucune demande d'absence trouvée</td></tr>
                        <?php else: ?>
                            <?php foreach ($absences as $abs): ?>
                                <tr>
                                    <td><?= htmlspecialchars($abs['prenom'] . ' ' . $abs['nom']) ?></td>
                                    <td><?= htmlspecialchars($abs['departement']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($abs['date_debut'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($abs['date_fin'])) ?></td>
                                    <td><?= htmlspecialchars(substr($abs['motif'], 0, 50)) . (strlen($abs['motif']) > 50 ? '...' : '') ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = match($abs['statut']) {
                                            'Approuvé' => 'badge-success',
                                            'En attente' => 'badge-warning',
                                            'Rejeté' => 'badge-danger',
                                            default => 'badge-warning'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($abs['statut']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($abs['statut'] === 'En attente'): ?>
                                            <div class="action-buttons">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="approuver">
                                                    <input type="hidden" name="absence_id" value="<?= $abs['id'] ?>">
                                                    <button type="submit" class="btn-sm btn-success" title="Approuver">✓</button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="rejeter">
                                                    <input type="hidden" name="absence_id" value="<?= $abs['id'] ?>">
                                                    <button type="submit" class="btn-sm btn-danger" title="Rejeter">✗</button>
                                                </form>
                                            </div>
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
            <p>🚀 Déployé via Pipeline DevSecOps | Jenkins → SonarQube → Trivy → Docker → Kubernetes</p>
        </div>
    </div>
</body>
</html>
