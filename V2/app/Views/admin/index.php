<?php
/**
 * Dashboard Administrateur
 * Statistiques globales de l'application
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php';
require_once __DIR__ . '/../../../includes/notifications.php';

$admin = getAdminInfo();
$currentUser = $admin;
$currentPage = basename($_SERVER['PHP_SELF']);

include __DIR__ . '/header.php';

// Statistiques utilisateurs
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'employe'");
$total_employes = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'manager'");
$total_managers = $stmt->fetch()['total'];

// Statistiques demandes
$stmt = $pdo->query("SELECT COUNT(*) as total FROM demande_frais WHERE statut IN ('valide_manager', 'valide_admin')");
$demandes_attente = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM demande_frais WHERE statut = 'rembourse'");
$demandes_approuvees = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM demande_frais WHERE statut = 'rejete_admin'");
$demandes_rejetees = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM demande_frais WHERE statut IN ('valide_manager', 'valide_admin', 'rejete_admin', 'rembourse')");
$total_demandes = $stmt->fetch()['total'];

// Evolution des dépenses (6 derniers mois)
$stmt = $pdo->query("
    SELECT DATE_FORMAT(d.date_soumission, '%Y-%m') as mois, SUM(df.montant) as total 
    FROM demande_frais d
    JOIN details_frais df ON d.id = df.demande_id
    WHERE d.statut = 'rembourse'
    GROUP BY mois 
    ORDER BY mois ASC 
    LIMIT 6
");
$evolution_depenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mois_labels = [];
$mois_montants = [];
foreach ($evolution_depenses as $row) {
    $mois_labels[] = date('M Y', strtotime($row['mois'] . '-01'));
    $mois_montants[] = $row['total'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-speedometer2"></i> Dashboard Administrateur</h1>
</div>

<!-- KPI Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stat-card border-primary shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Utilisateurs</h6>
                        <h2 class="mb-0 text-primary"><?php echo $total_users; ?></h2>
                    </div>
                    <div class="text-primary opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card border-warning shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">En traitement</h6>
                        <h2 class="mb-0 text-warning"><?php echo $demandes_attente; ?></h2>
                    </div>
                    <div class="text-warning opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card border-success shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Remboursées</h6>
                        <h2 class="mb-0 text-success"><?php echo $demandes_approuvees; ?></h2>
                    </div>
                    <div class="text-success opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card border-danger shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Rejetées</h6>
                        <h2 class="mb-0 text-danger"><?php echo $demandes_rejetees; ?></h2>
                    </div>
                    <div class="text-danger opacity-25" style="font-size: 2.5rem;">
                        <i class="bi bi-x-circle-fill"></i>
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
                <h5 class="card-title mb-0"><i class="bi bi-graph-up"></i> Évolution des dépenses remboursées</h5>
            </div>
            <div class="card-body">
                <canvas id="expensesChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <!-- Demandes Breakdown -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-pie-chart"></i> État des demandes</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="statusChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Users Breakdown -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-people"></i> Répartition des utilisateurs</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="usersChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Configuration commune
        Chart.defaults.font.family = "'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif";
        Chart.defaults.color = '#6c757d';

        // 1. Évolution des dépenses (Bar Chart)
        const ctxExpenses = document.getElementById('expensesChart').getContext('2d');
        new Chart(ctxExpenses, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($mois_labels); ?>,
                datasets: [{
                    label: 'Montant remboursé (DH)',
                    data: <?php echo json_encode($mois_montants); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.6)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1,
                    borderRadius: 5,
                    barPercentage: 0.6
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
                            callback: function (value) {
                                return value + ' DH';
                            }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 2. État des demandes (Doughnut)
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['En traitement', 'Remboursées', 'Rejetées'],
                datasets: [{
                    data: [<?php echo $demandes_attente; ?>, <?php echo $demandes_approuvees; ?>, <?php echo $demandes_rejetees; ?>],
                    backgroundColor: [
                        '#ffc107', // Warning
                        '#198754', // Success
                        '#dc3545'  // Danger
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
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

        // 3. Répartition Utilisateurs (Pie)
        const ctxUsers = document.getElementById('usersChart').getContext('2d');
        new Chart(ctxUsers, {
            type: 'pie',
            data: {
                labels: ['Employés', 'Managers'],
                datasets: [{
                    data: [<?php echo $total_employes; ?>, <?php echo $total_managers; ?>],
                    backgroundColor: [
                        '#0d6efd', // Primary
                        '#0dcaf0'  // Info
                    ],
                    borderWidth: 1
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
                }
            }
        });
    });
</script>

<?php include __DIR__ . '/footer.php'; ?>