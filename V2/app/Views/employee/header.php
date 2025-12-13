<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../includes/notifications.php';
requireEmploye();

$currentUser = getCurrentUser();

// Fonction utilitaire pour les badges de statut
if (!function_exists('getStatutBadge')) {
    function getStatutBadge($statut)
    {
        $badges = [
            'soumis' => 'bg-secondary',
            'valide_manager' => 'bg-info',
            'rejete_manager' => 'bg-danger',
            'valide_admin' => 'bg-success',
            'rejete_admin' => 'bg-danger',
            'rembourse' => 'bg-primary'
        ];

        $labels = [
            'soumis' => 'Soumis',
            'valide_manager' => 'Validé Manager',
            'rejete_manager' => 'Rejeté Manager',
            'valide_admin' => 'Validé Admin',
            'rejete_admin' => 'Rejeté Admin',
            'rembourse' => 'Remboursé'
        ];

        $badge = $badges[$statut] ?? 'bg-secondary';
        $label = $labels[$statut] ?? $statut;

        return "<span class='badge $badge'>$label</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Employé - Gestion des Frais de Déplacement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../../public/css/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Vérification de sécurité : si l'onglet a été fermé (sessionStorage vide), on déconnecte
        if (!sessionStorage.getItem('isLoggedIn')) {
            window.location.href = '../../../public/logout.php';
        }
    </script>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-wallet2"></i> Frais de Déplacement
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php include __DIR__ . '/../../../includes/notification_widget.php'; ?>
                    <li class="nav-item">
                        <a class="nav-link text-white me-3 d-flex align-items-center" href="profil.php">
                            <i class="bi bi-person-circle me-2"></i>
                            <span class="fw-semibold"><?php echo htmlspecialchars($currentUser['nom']); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white d-flex align-items-center" href="../../../public/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar p-3">
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"
                            href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'nouvelle_demande.php' ? 'active' : ''; ?>"
                            href="nouvelle_demande.php">
                            <i class="bi bi-plus-circle"></i> Nouvelle demande
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'mes_demandes.php' ? 'active' : ''; ?>"
                            href="mes_demandes.php">
                            <i class="bi bi-list-ul"></i> Mes demandes
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>"
                            href="calendar.php">
                            <i class="bi bi-calendar-event"></i> Calendrier
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'historique.php' ? 'active' : ''; ?>"
                            href="historique.php">
                            <i class="bi bi-clock-history"></i> Historique remboursements
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>"
                            href="profil.php">
                            <i class="bi bi-person"></i> Mon Profil
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Contenu principal -->
            <main class="col-md-9 col-lg-10 p-4 fade-in">