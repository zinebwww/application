<?php
/**
 * Authentification et sécurité pour l'espace admin
 * Empêche l'accès si l'utilisateur n'est pas admin
 */

session_start();

// Vérifier si l'utilisateur est connecté et est admin
function requireAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: ../public/login.php');
        exit();
    }
    
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ../public/login.php');
        exit();
    }
}

// Obtenir les informations de l'admin connecté
function getAdminInfo() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'nom' => $_SESSION['user_nom'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role']
    ];
}

// Appeler requireAdmin() automatiquement
requireAdmin();
?>

