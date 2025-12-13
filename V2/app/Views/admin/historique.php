<?php
/**
 * Historique des demandes traitées (Admin)
 * Affiche les demandes validées, remboursées ou rejetées par l'administrateur
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php';
require_once __DIR__ . '/../../../includes/notifications.php';

$admin = getAdminInfo();
$currentUser = $admin;
$currentPage = basename($_SERVER['PHP_SELF']);

// Fonction pour obtenir le badge du statut (vue Admin)
function getStatutBadge($statut)
{
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

// Récupérer l'historique des demandes traitées (Rejetées de manière définitive ou Remboursées)
// On peut aussi inclure 'valide_admin' si on considère que c'est en cours de traitement par l'admin
$stmt = $pdo->prepare("
    SELECT d.*, u.nom as employe_nom, u.email as employe_email,
           COALESCE(SUM(df.montant), 0) as montant_total
    FROM demande_frais d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN details_frais df ON d.id = df.demande_id
    WHERE d.statut IN ('rejete_admin', 'rembourse', 'valide_admin')
    GROUP BY d.id
    ORDER BY d.date_soumission DESC
");
$stmt->execute();
$historique = $stmt->fetchAll();

// Calculer les statistiques
$total_rembourse = 0;
$nb_rembourses = 0;
$nb_rejetees = 0;
$nb_en_cours = 0;

foreach ($historique as $h) {
    if ($h['statut'] === 'rembourse') {
        $total_rembourse += $h['montant_total'];
        $nb_rembourses++;
    } elseif ($h['statut'] === 'rejete_admin') {
        $nb_rejetees++;
    } elseif ($h['statut'] === 'valide_admin') {
        $nb_en_cours++;
    }
}

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-clock-history"></i> Historique des traitements</h1>
    <a href="demandes.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Retour aux demandes
    </a>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-0">Total remboursé</h6>
                        <h2 class="mb-0 text-success"><?php echo number_format($total_rembourse, 2, ',', ' '); ?> DH
                        </h2>
                    </div>
                    <div class="text-success" style="font-size: 2rem;">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-0">Remboursées</h6>
                        <h2 class="mb-0 text-primary"><?php echo $nb_rembourses; ?></h2>
                    </div>
                    <div class="text-primary" style="font-size: 2rem;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card border-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-0">Rejetées</h6>
                        <h2 class="mb-0 text-danger"><?php echo $nb_rejetees; ?></h2>
                    </div>
                    <div class="text-danger" style="font-size: 2rem;">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>

<!-- Liste de l'historique -->
<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Liste des demandes traitées</h5>
        <span class="badge bg-white text-primary"><?php echo count($historique); ?> demandes</span>
    </div>
    <div class="card-body">
        <?php if (empty($historique)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucun historique disponible.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employé</th>
                            <th>Objectif</th>
                            <th>Date mission</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Date soumission</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historique as $demande): ?>
                            <tr>
                                <td>#<?php echo $demande['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($demande['employe_nom']); ?></h6>
                                            <small
                                                class="text-muted"><?php echo htmlspecialchars($demande['employe_email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 150px;"
                                        title="<?php echo htmlspecialchars($demande['objectif_mission']); ?>">
                                        <?php echo htmlspecialchars($demande['objectif_mission']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($demande['date_mission'])); ?></td>
                                <td><strong><?php echo number_format($demande['montant_total'], 2, ',', ' '); ?> DH</strong>
                                </td>
                                <td><?php echo getStatutBadge($demande['statut']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($demande['date_soumission'])); ?></td>
                                <td>
                                    <a href="details_demande.php?id=<?php echo $demande['id']; ?>"
                                        class="btn btn-sm btn-outline-primary" title="Voir les détails">
                                        <i class="bi bi-eye"></i>
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

<?php include __DIR__ . '/footer.php'; ?>