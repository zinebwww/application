<?php
/**
 * Gestion des demandes de frais
 * Voir toutes les demandes et filtrer par statut
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php';
require_once __DIR__ . '/../../../includes/notifications.php';

$admin = getAdminInfo();
$currentUser = $admin;
$currentPage = basename($_SERVER['PHP_SELF']);

include __DIR__ . '/header.php';

$message = '';
$messageType = '';

if (isset($_GET['success'])) {
    $message = 'Opération effectuée avec succès !';
    $messageType = 'success';
}

// Récupération des filtres
$filtre_statut = $_GET['statut'] ?? '';
$filtre_employe = isset($_GET['employe']) ? intval($_GET['employe']) : 0;
$filtre_date_debut = $_GET['date_debut'] ?? '';
$filtre_date_fin = $_GET['date_fin'] ?? '';

// Construction de la requête
// L'Admin voit UNIQUEMENT les demandes validées par le manager (statut = 'valide_manager')
// Sauf si un filtre spécifique est appliqué
$sql = "
    SELECT d.*, u.nom as employe_nom, u.email as employe_email,
           COALESCE(SUM(df.montant), 0) as montant_total
    FROM demande_frais d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN details_frais df ON d.id = df.demande_id
    WHERE 1=1
";
$params = [];

// Par défaut, filtrer uniquement les demandes validées par le manager
// Sauf si un filtre de statut est explicitement demandé
if (empty($filtre_statut)) {
    $sql .= " AND d.statut = 'valide_manager'";
}

// Application des filtres
if (!empty($filtre_statut)) {
    // Mapper les statuts pour l'admin
    $statutMap = [
        'soumis' => 'soumis',
        'en_attente_manager' => 'soumis',
        'valide_manager' => 'valide_manager',
        'rejete_manager' => 'rejete_manager',
        'en_cours' => 'valide_manager',
        'approuvee' => 'rembourse',
        'rejetee' => 'rejete_admin'
    ];
    
    if (isset($statutMap[$filtre_statut])) {
        $sql .= " AND d.statut = ?";
        $params[] = $statutMap[$filtre_statut];
    } elseif (in_array($filtre_statut, ['soumis', 'valide_manager', 'rejete_manager', 'valide_admin', 'rejete_admin', 'rembourse'])) {
        $sql .= " AND d.statut = ?";
        $params[] = $filtre_statut;
    }
}

if ($filtre_employe > 0) {
    $sql .= " AND d.user_id = ?";
    $params[] = $filtre_employe;
}

if (!empty($filtre_date_debut)) {
    $sql .= " AND d.date_mission >= ?";
    $params[] = $filtre_date_debut;
}

if (!empty($filtre_date_fin)) {
    $sql .= " AND d.date_mission <= ?";
    $params[] = $filtre_date_fin;
}

$sql .= " GROUP BY d.id ORDER BY d.date_soumission DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll();

// Récupérer la liste des employés pour le filtre
$stmt = $pdo->query("SELECT id, nom FROM users WHERE role = 'employe' ORDER BY nom");
$employes = $stmt->fetchAll();

// Fonction pour obtenir le badge du statut (vue Admin)
function getStatutBadge($statut) {
    $badges = [
        'valide_manager' => 'bg-warning',
        'valide_admin' => 'bg-warning',
        'rejete_admin' => 'bg-danger',
        'rembourse' => 'bg-success'
    ];
    $labels = [
        'valide_manager' => 'En cours (traitement interne)',
        'valide_admin' => 'En cours (traitement interne)',
        'rejete_admin' => 'Rejetée (refusée définitivement)',
        'rembourse' => 'Approuvée (remboursée)'
    ];
    $badge = $badges[$statut] ?? 'bg-secondary';
    $label = $labels[$statut] ?? $statut;
    return "<span class='badge $badge'>$label</span>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-file-earmark-text"></i> Gestion des demandes</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtres</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="">Tous</option>
                    <option value="en_cours" <?php echo $filtre_statut === 'en_cours' ? 'selected' : ''; ?>>En cours (traitement interne)</option>
                    <option value="approuvee" <?php echo $filtre_statut === 'approuvee' ? 'selected' : ''; ?>>Approuvée (remboursée)</option>
                    <option value="rejetee" <?php echo $filtre_statut === 'rejetee' ? 'selected' : ''; ?>>Rejetée (refusée définitivement)</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="employe" class="form-label">Employé</label>
                <select class="form-select" id="employe" name="employe">
                    <option value="">Tous</option>
                    <?php foreach ($employes as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $filtre_employe == $emp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo htmlspecialchars($filtre_date_debut); ?>">
            </div>
            
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo htmlspecialchars($filtre_date_fin); ?>">
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrer
                </button>
                <a href="demandes.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des demandes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des demandes (<?php echo count($demandes); ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th class="w-5">ID</th>
                        <th class="w-15">Employé</th>
                        <th class="w-20 text-truncate" style="max-width: 200px;">Objectif</th>
                        <th class="w-15 text-truncate" style="max-width: 150px;">Lieu</th>
                        <th class="w-10">Date mission</th>
                        <th class="w-10">Montant</th>
                        <th class="w-10">Statut</th>
                        <th class="w-10">Date soumission</th>
                        <th class="w-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($demandes)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">Aucune demande trouvée</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($demandes as $demande): ?>
                            <tr>
                                <td>#<?php echo $demande['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($demande['employe_nom']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($demande['employe_email']); ?></small>
                                </td>
                                <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($demande['objectif_mission']); ?>">
                                    <?php echo htmlspecialchars(substr($demande['objectif_mission'], 0, 40)); ?><?php echo strlen($demande['objectif_mission']) > 40 ? '...' : ''; ?>
                                </td>
                                <td class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($demande['lieu_mission']); ?>">
                                    <?php echo htmlspecialchars($demande['lieu_mission']); ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($demande['date_mission'])); ?></td>
                                <td><strong><?php echo number_format($demande['montant_total'], 2, ',', ' '); ?> DH</strong></td>
                                <td><?php echo getStatutBadge($demande['statut']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($demande['date_soumission'])); ?></td>
                                <td>
                                    <a href="details_demande.php?id=<?php echo $demande['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir les détails">
                                        <i class="bi bi-eye"></i> Voir
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalStatut<?php echo $demande['id']; ?>" title="Modifier le statut">
                                        <i class="bi bi-pencil"></i> Statut
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals pour modifier le statut -->
<?php foreach ($demandes as $demande): ?>
<div class="modal fade" id="modalStatut<?php echo $demande['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le statut - Demande #<?php echo $demande['id']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formStatut<?php echo $demande['id']; ?>">
                <div class="modal-body">
                    <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="statut_<?php echo $demande['id']; ?>" class="form-label">Nouveau statut <span class="text-danger">*</span></label>
                        <select class="form-select" id="statut_<?php echo $demande['id']; ?>" name="statut" required>
                            <option value="">Sélectionner...</option>
                            <option value="valide_manager" <?php echo in_array($demande['statut'], ['valide_manager', 'valide_admin']) ? 'selected' : ''; ?>>En cours (traitement interne)</option>
                            <option value="rembourse" <?php echo $demande['statut'] === 'rembourse' ? 'selected' : ''; ?>>Approuvée (remboursée)</option>
                            <option value="rejete_admin" <?php echo $demande['statut'] === 'rejete_admin' ? 'selected' : ''; ?>>Rejetée (refusée définitivement)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire_<?php echo $demande['id']; ?>" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaire_<?php echo $demande['id']; ?>" name="commentaire" rows="3" placeholder="Ajouter un commentaire sur ce changement de statut..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <small><i class="bi bi-info-circle"></i> Le statut actuel est : <?php echo getStatutBadge($demande['statut']); ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
// Gestion de la soumission des formulaires de statut
<?php foreach ($demandes as $demande): ?>
document.getElementById('formStatut<?php echo $demande['id']; ?>').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update_statut.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Fermer le modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalStatut<?php echo $demande['id']; ?>'));
            modal.hide();
            
            // Afficher un message de succès
            alert('Statut mis à jour avec succès !');
            
            // Recharger la page pour voir les changements
            window.location.reload();
        } else {
            alert('Erreur : ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Une erreur est survenue');
    });
});
<?php endforeach; ?>
</script>

<?php include __DIR__ . '/footer.php'; ?>

