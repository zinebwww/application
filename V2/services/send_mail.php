<?php
/**
 * Configuration et envoi d'emails avec PHPMailer
 * Utilise PHPMailer depuis le dossier vendor
 */

// Inclure les fichiers PHPMailer
require_once __DIR__ . '/../vendor/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../vendor/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Configuration SMTP Gmail
 * IMPORTANT : Modifiez ces valeurs avec vos propres identifiants Gmail
 */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'wzineb000005@gmail.com'); // À MODIFIER
define('SMTP_PASSWORD', 'nfzb tvaw qbaj wlms'); // À MODIFIER
define('SMTP_FROM_EMAIL', 'wzineb000005@gmail.com'); // À MODIFIER
define('SMTP_FROM_NAME', 'Gestion Frais de Déplacement');

/**
 * Fonction principale pour envoyer un email
 * 
 * @param string $to Email du destinataire
 * @param string $subject Sujet de l'email
 * @param string $message Corps du message (HTML accepté)
 * @param string $toName Nom du destinataire (optionnel)
 * @return bool True si envoyé avec succès, False sinon
 */
function sendEmail($to, $subject, $message, $toName = '')
{
    $mail = new PHPMailer(true);

    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Option pour contourner le problème de certificat SSL en local
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Configuration de l'encodage
        $mail->CharSet = 'UTF-8';

        // Expéditeur
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Destinataire
        $mail->addAddress($to, $toName);

        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        // Version texte alternative (optionnelle)
        $mail->AltBody = strip_tags($message);

        // Envoyer l'email
        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log l'erreur (vous pouvez personnaliser cette partie)
        error_log("Erreur envoi email : {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Templates d'emails prédéfinis
 */

/**
 * Email de confirmation de soumission de demande
 */
function sendDemandeSubmissionEmail($to, $toName, $demandeId, $objectifMission)
{
    $subject = "Confirmation de soumission - Demande de frais #{$demandeId}";

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .highlight { background-color: #e7f3ff; padding: 10px; border-left: 4px solid #007bff; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🎯 Demande de frais soumise avec succès</h2>
        </div>
        <div class='content'>
            <p>Bonjour <strong>{$toName}</strong>,</p>
            
            <p>Votre demande de frais de déplacement a été soumise avec succès et est en cours de traitement.</p>
            
            <div class='highlight'>
                <strong>Détails de la demande :</strong><br>
                • Numéro : #{$demandeId}<br>
                • Objectif : {$objectifMission}<br>
                • Statut : En attente de validation par votre manager
            </div>
            
            <p>Vous recevrez un email de notification dès que votre manager aura traité votre demande.</p>
            
            <p>Cordialement,<br>L'équipe Gestion des Frais</p>
        </div>
        <div class='footer'>
            Cet email a été envoyé automatiquement. Merci de ne pas y répondre.
        </div>
    </body>
    </html>";

    return sendEmail($to, $subject, $message, $toName);
}

/**
 * Email de validation par le manager
 */
function sendManagerValidationEmail($to, $toName, $demandeId, $isApproved, $commentaire = '')
{
    $status = $isApproved ? 'validée' : 'rejetée';
    $statusIcon = $isApproved ? '✅' : '❌';
    $statusColor = $isApproved ? '#28a745' : '#dc3545';

    $subject = "Demande #{$demandeId} {$status} par votre manager";

    $commentaireHtml = '';
    if (!empty($commentaire)) {
        $commentaireHtml = "<div style='background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 15px 0;'>
            <strong>Commentaire du manager :</strong><br>
            " . nl2br(htmlspecialchars($commentaire)) . "
        </div>";
    }

    $nextStep = $isApproved ?
        "Votre demande va maintenant être traitée par l'administration pour approbation finale et remboursement." :
        "Vous pouvez soumettre une nouvelle demande si nécessaire.";

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: {$statusColor}; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .highlight { background-color: #e7f3ff; padding: 10px; border-left: 4px solid: {$statusColor}; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>{$statusIcon} Demande {$status} par votre manager</h2>
        </div>
        <div class='content'>
            <p>Bonjour <strong>{$toName}</strong>,</p>
            
            <p>Votre demande de frais #{$demandeId} a été <strong>{$status}</strong> par votre manager.</p>
            
            {$commentaireHtml}
            
            <div class='highlight'>
                <strong>Prochaine étape :</strong><br>
                {$nextStep}
            </div>
            
            <p>Cordialement,<br>L'équipe Gestion des Frais</p>
        </div>
        <div class='footer'>
            Cet email a été envoyé automatiquement. Merci de ne pas y répondre.
        </div>
    </body>
    </html>";

    return sendEmail($to, $subject, $message, $toName);
}

/**
 * Email de décision finale de l'admin
 */
function sendAdminDecisionEmail($to, $toName, $demandeId, $isApproved, $commentaire = '')
{
    $status = $isApproved ? 'approuvée et remboursée' : 'rejetée définitivement';
    $statusIcon = $isApproved ? '💰' : '❌';
    $statusColor = $isApproved ? '#28a745' : '#dc3545';

    $subject = "Décision finale - Demande #{$demandeId} {$status}";

    $commentaireHtml = '';
    if (!empty($commentaire)) {
        $commentaireHtml = "<div style='background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 15px 0;'>
            <strong>Commentaire de l'administration :</strong><br>
            " . nl2br(htmlspecialchars($commentaire)) . "
        </div>";
    }

    $finalMessage = $isApproved ?
        "Le remboursement sera traité dans les prochains jours ouvrables." :
        "Cette décision est définitive. Vous pouvez soumettre une nouvelle demande si nécessaire.";

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: {$statusColor}; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .highlight { background-color: #e7f3ff; padding: 10px; border-left: 4px solid {$statusColor}; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>{$statusIcon} Décision finale de l'administration</h2>
        </div>
        <div class='content'>
            <p>Bonjour <strong>{$toName}</strong>,</p>
            
            <p>Votre demande de frais #{$demandeId} a été <strong>{$status}</strong> par l'administration.</p>
            
            {$commentaireHtml}
            
            <div class='highlight'>
                <strong>Information importante :</strong><br>
                {$finalMessage}
            </div>
            
            <p>Cordialement,<br>L'équipe Gestion des Frais</p>
        </div>
        <div class='footer'>
            Cet email a été envoyé automatiquement. Merci de ne pas y répondre.
        </div>
    </body>
    </html>";

    return sendEmail($to, $subject, $message, $toName);
}

/**
 * Email de notification pour les admins (nouvelles demandes)
 */
function sendAdminNotificationEmail($to, $toName, $demandeId, $employeNom, $objectifMission)
{
    $subject = "Nouvelle demande à traiter - #{$demandeId}";

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .highlight { background-color: #d1ecf1; padding: 10px; border-left: 4px solid #17a2b8; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>📋 Nouvelle demande validée par le manager</h2>
        </div>
        <div class='content'>
            <p>Bonjour <strong>{$toName}</strong>,</p>
            
            <p>Une nouvelle demande de frais a été validée par le manager et nécessite votre traitement.</p>
            
            <div class='highlight'>
                <strong>Détails de la demande :</strong><br>
                • Numéro : #{$demandeId}<br>
                • Employé : {$employeNom}<br>
                • Objectif : {$objectifMission}<br>
                • Statut : En attente de traitement administratif
            </div>
            
            <p>Veuillez vous connecter à l'espace administrateur pour traiter cette demande.</p>
            
            <p>Cordialement,<br>Système de Gestion des Frais</p>
        </div>
        <div class='footer'>
            Cet email a été envoyé automatiquement. Merci de ne pas y répondre.
        </div>
    </body>
    </html>";

    return sendEmail($to, $subject, $message, $toName);
}
/**
 * Email de réinitialisation de mot de passe (Code OTP)
 */
function sendPasswordResetEmail($to, $code)
{
    $subject = "Réinitialisation de votre mot de passe - Code de vérification";

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #6c757d; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .code-box { background-color: #e9ecef; border: 2px dashed #6c757d; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🔐 Réinitialisation de mot de passe</h2>
        </div>
        <div class='content'>
            <p>Bonjour,</p>
            
            <p>Vous avez demandé la réinitialisation de votre mot de passe. Veuillez utiliser le code ci-dessous pour vérifier votre identité :</p>
            
            <div class='code-box'>
                {$code}
            </div>
            
            <p>Ce code est valable pour une durée limitée (15 minutes).</p>
            <p>Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email en toute sécurité.</p>
            
            <p>Cordialement,<br>L'équipe Gestion des Frais</p>
        </div>
        <div class='footer'>
            Cet email a été envoyé automatiquement. Merci de ne pas y répondre.
        </div>
    </body>
    </html>";

    return sendEmail($to, $subject, $message);
}
?>