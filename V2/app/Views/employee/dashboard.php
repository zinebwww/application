<?php
/**
 * Dashboard Employé
 * Affiche les statistiques des demandes de l'employé
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
requireEmploye();

$userId = $_SESSION['user_id'];

// Statistiques des demandes
$stats = [
    'en_cours' => 0,
    'validees' => 0,
    'rejetees' => 0,
    'remboursees' => 0
];

// Compter les demandes en cours (soumis, valide_manager, valide_admin)
$stmt = $pdo->prepare("
    SELECT statut, COUNT(*) as count 
    FROM demande_frais 
    WHERE user_id = ? 
    GROUP BY statut
");
$stmt->execute([$userId]);
$results = $stmt->fetchAll();

foreach ($results as $row) {
    switch ($row['statut']) {
        case 'soumis':
        case 'valide_manager':
        case 'valide_admin':
            $stats['en_cours'] += $row['count'];
            break;
        case 'rembourse':
            $stats['remboursees'] += $row['count'];
            break;
        case 'rejete_manager':
        case 'rejete_admin':
            $stats['rejetees'] += $row['count'];
            break;
    }
}

// Les demandes validées incluent celles remboursées
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM demande_frais 
    WHERE user_id = ? 
    AND statut IN ('valide_admin', 'rembourse')
");
$stmt->execute([$userId]);
$stats['validees'] = $stmt->fetch()['count'];

// Historique Dépenses (30 derniers jours avec transactions)
$stmt = $pdo->prepare("
    SELECT DATE(d.date_soumission) as jour, SUM(df.montant) as total 
    FROM demande_frais d
    JOIN details_frais df ON d.id = df.demande_id
    WHERE d.user_id = ? AND d.statut = 'rembourse'
    GROUP BY jour 
    ORDER BY jour ASC 
    LIMIT 30
");
$stmt->execute([$userId]);
$history_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$jour_labels = [];
$jour_montants = [];
foreach ($history_results as $row) {
    $jour_labels[] = date('d/m/Y', strtotime($row['jour']));
    $jour_montants[] = $row['total'];
}


// Dernières demandes
$stmt = $pdo->prepare("
    SELECT d.*, 
           COALESCE(SUM(df.montant), 0) as montant_total
    FROM demande_frais d
    LEFT JOIN details_frais df ON d.id = df.demande_id
    WHERE d.user_id = ?
    GROUP BY d.id
    ORDER BY d.date_soumission DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$dernieres_demandes = $stmt->fetchAll();

// Fonction pour obtenir le badge Bootstrap selon le statut
// La fonction getStatutBadge est désormais incluse via header.php

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0"><i class="bi bi-speedometer2"></i> Dashboard</h1>
    <a href="nouvelle_demande.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Créer une nouvelle demande
    </a>
</div>

<!-- Statistiques (KPIs) -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card border-primary h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">En cours</h6>
                        <h2 class="text-primary mb-0"><?php echo $stats['en_cours']; ?></h2>
                    </div>
                    <div class="text-primary opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card stat-card border-success h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Validées</h6>
                        <h2 class="text-success mb-0"><?php echo $stats['validees']; ?></h2>
                    </div>
                    <div class="text-success opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card stat-card border-danger h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Rejetées</h6>
                        <h2 class="text-danger mb-0"><?php echo $stats['rejetees']; ?></h2>
                    </div>
                    <div class="text-danger opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card stat-card border-info h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Remboursées</h6>
                        <h2 class="text-info mb-0"><?php echo $stats['remboursees']; ?></h2>
                    </div>
                    <div class="text-info opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row mb-4">
    <!-- Evolution Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-graph-up"></i> Mes dépenses remboursées</h5>
            </div>
            <div class="card-body">
                <canvas id="myHistoryChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <!-- Status Chart -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-pie-chart"></i> État de mes demandes</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="myStatusChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Dernières demandes -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Dernières demandes</h5>
    </div>
    <div class="card-body">
        <?php if (empty($dernieres_demandes)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucune demande pour le moment.
                <a href="nouvelle_demande.php" class="alert-link">Créer votre première demande</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Objectif</th>
                            <th>Date mission</th>
                            <th>Montant total</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dernieres_demandes as $demande): ?>
                            <tr>
                                <td>#<?php echo $demande['id']; ?></td>
                                <td><?php echo htmlspecialchars(substr($demande['objectif_mission'], 0, 50)); ?>...</td>
                                <td><?php echo date('d/m/Y', strtotime($demande['date_mission'])); ?></td>
                                <td><strong><?php echo number_format($demande['montant_total'], 2, ',', ' '); ?> DH</strong>
                                </td>
                                <td><?php echo getStatutBadge($demande['statut']); ?></td>
                                <td>
                                    <a href="mes_demandes.php?action=voir&id=<?php echo $demande['id']; ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Voir
                                    </a>
                                    <?php if (in_array($demande['statut'], ['soumis', 'rejete_manager', 'rejete_admin'])): ?>
                                        <a href="mes_demandes.php?action=modifier&id=<?php echo $demande['id']; ?>"
                                            class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i> Modifier
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <a href="mes_demandes.php" class="btn btn-outline-primary">Voir toutes mes demandes</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        Chart.defaults.font.family = "'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif";
        Chart.defaults.color = '#6c757d';

        // 1. Évolution (Line Chart)
        const ctxHistory = document.getElementById('myHistoryChart').getContext('2d');
        new Chart(ctxHistory, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($jour_labels); ?>,
                datasets: [{
                    label: 'Montant remboursé (DH)',
                    data: <?php echo json_encode($jour_montants); ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#0d6efd',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y.toLocaleString('fr-MA', { style: 'currency', currency: 'MAD' });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4] },
                        ticks: {
                            callback: function (value) { return value + ' DH'; }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. État des demandes (Doughnut)
        const ctxStatus = document.getElementById('myStatusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['En cours', 'Remboursées', 'Rejetées'],
                datasets: [{
                    data: [<?php echo $stats['en_cours']; ?>, <?php echo $stats['remboursees']; ?>, <?php echo $stats['rejetees']; ?>],
                    backgroundColor: [
                        '#ffc107',
                        '#0dcaf0',
                        '#dc3545'
                    ],
                    hoverOffset: 4,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 20 }
                    }
                },
                cutout: '70%'
            }
        });
    });
</script>

<?php include __DIR__ . '/footer.php'; ?>