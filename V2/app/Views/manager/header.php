<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../includes/notifications.php';
requireManager();

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Manager - Gestion des Frais de Déplacement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../../public/css/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-briefcase"></i> Espace Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php include __DIR__ . '/../../../includes/notification_widget.php'; ?>
                    <li class="nav-item">
                        <a class="nav-link text-white me-3" href="profil.php">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($currentUser['nom']); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../../../public/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Déconnexion
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
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'demandes.php' ? 'active' : ''; ?>"
                            href="demandes.php">
                            <i class="bi bi-list-check"></i> Demandes de l'équipe
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'map.php' ? 'active' : ''; ?>"
                            href="map.php">
                            <i class="bi bi-globe-europe-africa"></i> Carte Stratégique
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>"
                            href="calendar.php">
                            <i class="bi bi-calendar-check"></i> Calendrier Équipe
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