<?php
/**
 * Dashboard Manager
 * Affiche les statistiques des demandes de l'équipe
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
requireManager();

$managerId = $_SESSION['user_id'];

// Récupérer les employés de l'équipe
$stmt = $pdo->prepare("SELECT id, nom FROM users WHERE manager_id = ?");
$stmt->execute([$managerId]);
$equipe = $stmt->fetchAll();
$equipeIds = array_column($equipe, 'id');

// Statistiques
$stats = [
    'en_attente' => 0,
    'validees' => 0,
    'rejetees' => 0
];

// Top Dépenses par employé (pour le graphique)
$top_spenders_labels = [];
$top_spenders_data = [];

if (!empty($equipeIds)) {
    $placeholders = str_repeat('?,', count($equipeIds) - 1) . '?';

    // Compter les demandes en attente (soumis)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM demande_frais 
        WHERE user_id IN ($placeholders) 
        AND statut = 'soumis'
    ");
    $stmt->execute($equipeIds);
    $stats['en_attente'] = $stmt->fetch()['count'];

    // Compter les demandes validées (par manager ou +)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM demande_frais 
        WHERE user_id IN ($placeholders) 
        AND statut IN ('valide_manager', 'valide_admin', 'rembourse')
    ");
    $stmt->execute($equipeIds);
    $stats['validees'] = $stmt->fetch()['count'];

    // Compter les demandes rejetées
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM demande_frais 
        WHERE user_id IN ($placeholders) 
        AND statut IN ('rejete_manager', 'rejete_admin')
    ");
    $stmt->execute($equipeIds);
    $stats['rejetees'] = $stmt->fetch()['count'];

    // Dernières demandes en attente
    $stmt = $pdo->prepare("
        SELECT d.*, u.nom as employe_nom,
               COALESCE(SUM(df.montant), 0) as montant_total
        FROM demande_frais d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN details_frais df ON d.id = df.demande_id
        WHERE d.user_id IN ($placeholders)
        AND d.statut = 'soumis'
        GROUP BY d.id
        ORDER BY d.date_soumission DESC
        LIMIT 5
    ");
    $stmt->execute($equipeIds);
    $demandes_attente = $stmt->fetchAll();

    // Stats par employé (Bar Chart)
    $stmt = $pdo->prepare("
        SELECT u.nom, SUM(df.montant) as total 
        FROM demande_frais d 
        JOIN users u ON d.user_id = u.id 
        JOIN details_frais df ON d.id = df.demande_id 
        WHERE u.manager_id = ? 
        AND d.statut IN ('valide_manager', 'valide_admin', 'rembourse') 
        GROUP BY u.id 
        ORDER BY total DESC 
        LIMIT 5
    ");
    $stmt->execute([$managerId]);
    $spenders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($spenders as $s) {
        $top_spenders_labels[] = $s['nom'];
        $top_spenders_data[] = $s['total'];
    }

} else {
    $demandes_attente = [];
}

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-speedometer2"></i> Dashboard Manager</h1>
    <a href="demandes.php" class="btn btn-primary">
        <i class="bi bi-list-check"></i> Voir toutes les demandes
    </a>
</div>

<!-- Statistiques Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card stat-card border-warning shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-0">En attente de validation</h6>
                        <h2 class="mb-0 text-warning"><?php echo $stats['en_attente']; ?></h2>
                    </div>
                    <div class="text-warning opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card stat-card border-success shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-0">Validées / Remboursées</h6>
                        <h2 class="mb-0 text-success"><?php echo $stats['validees']; ?></h2>
                    </div>
                    <div class="text-success opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card stat-card border-danger shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-0">Rejetées</h6>
                        <h2 class="mb-0 text-danger"><?php echo $stats['rejetees']; ?></h2>
                    </div>
                    <div class="text-danger opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphs Section -->
<div class="row mb-4">
    <!-- Top Expenses -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-bar-chart"></i> Top dépenses par employé (Validées)</h5>
            </div>
            <div class="card-body">
                <canvas id="teamExpensesChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <!-- Status Distribution -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-pie-chart"></i> État des demandes de l'équipe</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="teamStatusChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Informations équipe -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-people"></i> Mon équipe</h5>
    </div>
    <div class="card-body">
        <?php if (empty($equipe)): ?>
            <p class="text-muted mb-0">Aucun employé dans votre équipe pour le moment.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($equipe as $employe): ?>
                    <div class="col-md-3 mb-2">
                        <div class="d-flex align-items-center p-2 border rounded hover-bg-light">
                            <i class="bi bi-person-circle me-3 text-secondary" style="font-size: 2rem;"></i>
                            <span class="fw-bold"><?php echo htmlspecialchars($employe['nom']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Demandes en attente -->
<div class="card shadow-sm">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Demandes en attente de validation</h5>
    </div>
    <div class="card-body">
        <?php if (empty($demandes_attente)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucune demande en attente pour le moment.
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
                            <th>Montant total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandes_attente as $demande): ?>
                            <tr>
                                <td>#<?php echo $demande['id']; ?></td>
                                <td><?php echo htmlspecialchars($demande['employe_nom']); ?></td>
                                <td><?php echo htmlspecialchars(substr($demande['objectif_mission'], 0, 50)); ?>...</td>
                                <td><?php echo date('d/m/Y', strtotime($demande['date_mission'])); ?></td>
                                <td><strong><?php echo number_format($demande['montant_total'], 2, ',', ' '); ?> DH</strong>
                                </td>
                                <td>
                                    <a href="demandes.php?action=voir&id=<?php echo $demande['id']; ?>"
                                        class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> Examiner
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <a href="demandes.php?statut=soumis" class="btn btn-outline-primary">Voir toutes les demandes en attente</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        Chart.defaults.font.family = "'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif";
        Chart.defaults.color = '#6c757d';

        // 1. Dépenses équipe (Bar)
        const ctxTeamExp = document.getElementById('teamExpensesChart').getContext('2d');
        new Chart(ctxTeamExp, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($top_spenders_labels); ?>,
                datasets: [{
                    label: 'Total dépenses validées (DH)',
                    data: <?php echo json_encode($top_spenders_data); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.6)', // Success color
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // Horizontal bars for better name readability
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) { return value + ' DH'; }
                        }
                    }
                }
            }
        });

        // 2. Status équipe (Pie)
        const ctxTeamStatus = document.getElementById('teamStatusChart').getContext('2d');
        new Chart(ctxTeamStatus, {
            type: 'pie',
            data: {
                labels: ['En attente', 'Validées', 'Rejetées'],
                datasets: [{
                    data: [<?php echo $stats['en_attente']; ?>, <?php echo $stats['validees']; ?>, <?php echo $stats['rejetees']; ?>],
                    backgroundColor: ['#ffc107', '#198754', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
                }
            }
        });
    });
</script>

<?php include __DIR__ . '/footer.php'; ?>