<?php
/**
 * Gestion de l'authentification et des sessions
 */

session_start();

// Vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Vérifier si l'utilisateur est un employé
function isEmploye() {
    return isLoggedIn() && $_SESSION['user_role'] === 'employe';
}

// Vérifier si l'utilisateur est un manager
function isManager() {
    return isLoggedIn() && $_SESSION['user_role'] === 'manager';
}

// Rediriger vers la page de login si non connecté
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../public/login.php');
        exit();
    }
}

// Rediriger vers la page de login si n'est pas un employé
function requireEmploye() {
    requireLogin();
    if (!isEmploye()) {
        header('Location: ../public/login.php');
        exit();
    }
}

// Rediriger vers la page de login si n'est pas un manager
function requireManager() {
    requireLogin();
    if (!isManager()) {
        header('Location: ../public/login.php');
        exit();
    }
}

// Obtenir les informations de l'utilisateur connecté
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, nom, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>

