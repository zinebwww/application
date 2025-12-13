<?php
/**
 * Mise à jour du statut d'une demande (traitement AJAX)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php';
require_once __DIR__ . '/../../../includes/notifications.php';
require_once __DIR__ . '/../../../services/send_mail.php';

ob_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$demandeId = intval($_POST['demande_id'] ?? 0);
$nouveauStatut = $_POST['statut'] ?? '';
$commentaire = trim($_POST['commentaire'] ?? '');

if ($demandeId <= 0 || empty($nouveauStatut)) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

// Statuts autorisés pour l'Admin (uniquement les 3 statuts possibles)
$statutsAutorises = ['valide_manager', 'rejete_admin', 'rembourse'];

if (!in_array($nouveauStatut, $statutsAutorises)) {
    echo json_encode(['success' => false, 'message' => 'Statut invalide']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Vérifier que la demande existe
    $stmt = $pdo->prepare("SELECT id, statut FROM demande_frais WHERE id = ?");
    $stmt->execute([$demandeId]);
    $demande = $stmt->fetch();

    if (!$demande) {
        throw new Exception("Demande introuvable");
    }

    // V\u00e9rifier que la demande n'a pas d\u00e9j\u00e0 \u00e9t\u00e9 trait\u00e9e de mani\u00e8re d\u00e9finitive
    if (in_array($demande['statut'], ['rejete_admin', 'rembourse'])) {
        throw new Exception("Cette demande a d\u00e9j\u00e0 \u00e9t\u00e9 trait\u00e9e de mani\u00e8re d\u00e9finitive et ne peut plus \u00eatre modifi\u00e9e.");
    }

    // Mettre à jour le statut
    $stmt = $pdo->prepare("UPDATE demande_frais SET statut = ? WHERE id = ?");
    $stmt->execute([$nouveauStatut, $demandeId]);

    // Ajouter dans l'historique
    $commentaireFinal = !empty($commentaire) ? $commentaire : "Statut modifié par l'administrateur";
    $stmt = $pdo->prepare("
        INSERT INTO historique_statuts (demande_id, statut, user_id, commentaire)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$demandeId, $nouveauStatut, $_SESSION['user_id'], $commentaireFinal]);

    // Récupérer les informations de l'employé pour la notification et l'email
    $stmt = $pdo->prepare("
        SELECT d.user_id, u.nom, u.email 
        FROM demande_frais d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.id = ?
    ");
    $stmt->execute([$demandeId]);
    $demandeInfo = $stmt->fetch();
    $employeId = $demandeInfo['user_id'];
    $employeNom = $demandeInfo['nom'];
    $employeEmail = $demandeInfo['email'];

    // Créer une notification pour l'employé selon le nouveau statut et envoyer un email
    if ($nouveauStatut === 'rembourse') {
        createNotification(
            $employeId,
            'success',
            "Votre demande de frais #{$demandeId} a été approuvée et remboursée.",
            "../mes_demandes.php?action=voir&id={$demandeId}",
            false // Pas d'email via notifications (on utilise PHPMailer)
        );

        // Envoyer un email d'approbation (dans un try/catch pour ne pas bloquer)
        try {
            sendAdminDecisionEmail(
                $employeEmail,
                $employeNom,
                $demandeId,
                true, // Approuvé
                $commentaire
            );
        } catch (Exception $e) {
            error_log("Erreur envoi email: " . $e->getMessage());
        }

    } elseif ($nouveauStatut === 'rejete_admin') {
        createNotification(
            $employeId,
            'danger',
            "Votre demande de frais #{$demandeId} a été rejetée définitivement." . (!empty($commentaire) ? " Commentaire : {$commentaire}" : ""),
            "../mes_demandes.php?action=voir&id={$demandeId}",
            false // Pas d'email via notifications (on utilise PHPMailer)
        );

        // Envoyer un email de rejet (dans un try/catch)
        try {
            sendAdminDecisionEmail(
                $employeEmail,
                $employeNom,
                $demandeId,
                false, // Rejeté
                $commentaire
            );
        } catch (Exception $e) {
            error_log("Erreur envoi email: " . $e->getMessage());
        }

    } elseif ($nouveauStatut === 'valide_manager') {
        createNotification(
            $employeId,
            'info',
            "Votre demande de frais #{$demandeId} est en cours de traitement par l'administration.",
            "../mes_demandes.php?action=voir&id={$demandeId}",
            false // Pas d'email pour "en cours"
        );
    }

    $pdo->commit();

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Statut mis à jour avec succès',
        'statut' => $nouveauStatut
    ]);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    exit();
}
?>