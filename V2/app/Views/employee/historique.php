<?php
/**
 * Historique des remboursements
 * Affiche les demandes validées et remboursées par l'administrateur
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
requireEmploye();

$userId = $_SESSION['user_id'];

// Fonction pour obtenir le badge Bootstrap selon le statut
// La fonction getStatutBadge est désormais incluse via header.php

// Récupérer les demandes validées et remboursées
$stmt = $pdo->prepare("
    SELECT d.*, 
           COALESCE(SUM(df.montant), 0) as montant_total
    FROM demande_frais d
    LEFT JOIN details_frais df ON d.id = df.demande_id
    WHERE d.user_id = ? 
    AND d.statut IN ('valide_admin', 'rembourse')
    GROUP BY d.id
    ORDER BY d.date_soumission DESC
");
$stmt->execute([$userId]);
$remboursements = $stmt->fetchAll();

// Calculer les statistiques
$total_rembourse = 0;
$nb_rembourses = 0;
$nb_validees = 0;

foreach ($remboursements as $remb) {
    if ($remb['statut'] === 'rembourse') {
        $total_rembourse += $remb['montant_total'];
        $nb_rembourses++;
    } else {
        $nb_validees++;
    }
}

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-clock-history"></i> Historique des remboursements</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Retour
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
                    <div class="text-success" style="font-size: 2.5rem;">
                        <i class="bi bi-wallet2"></i>
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
                        <h6 class="text-muted mb-0">Demandes remboursées</h6>
                        <h2 class="mb-0 text-primary"><?php echo $nb_rembourses; ?></h2>
                    </div>
                    <div class="text-primary" style="font-size: 2.5rem;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-0">En attente de remboursement</h6>
                        <h2 class="mb-0 text-info"><?php echo $nb_validees; ?></h2>
                    </div>
                    <div class="text-info" style="font-size: 2.5rem;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des remboursements -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Demandes validées et remboursées</h5>
    </div>
    <div class="card-body">
        <?php if (empty($remboursements)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucune demande remboursée pour le moment.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Objectif</th>
                            <th>Lieu</th>
                            <th>Date mission</th>
                            <th>Montant total</th>
                            <th>Statut</th>
                            <th>Date soumission</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($remboursements as $remb): ?>
                            <tr class="<?php echo $remb['statut'] === 'rembourse' ? 'table-success' : ''; ?>">
                                <td>#<?php echo $remb['id']; ?></td>
                                <td><?php echo htmlspecialchars(substr($remb['objectif_mission'], 0, 50)); ?><?php echo strlen($remb['objectif_mission']) > 50 ? '...' : ''; ?>
                                </td>
                                <td><?php echo htmlspecialchars($remb['lieu_mission']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($remb['date_mission'])); ?></td>
                                <td><strong><?php echo number_format($remb['montant_total'], 2, ',', ' '); ?> DH</strong></td>
                                <td><?php echo getStatutBadge($remb['statut']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($remb['date_soumission'])); ?></td>
                                <td>
                                    <a href="mes_demandes.php?action=voir&id=<?php echo $remb['id']; ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Voir détails
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="4">Total remboursé</th>
                            <th><?php echo number_format($total_rembourse, 2, ',', ' '); ?> DH</th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>