<?php
/**
 * Supprimer un utilisateur
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php';
require_once __DIR__ . '/../../../includes/notifications.php';

$admin = getAdminInfo();
$currentUser = $admin;
$currentPage = basename($_SERVER['PHP_SELF']);

$userId = intval($_GET['id'] ?? 0);

if ($userId <= 0) {
    header('Location: users.php');
    exit();
}

// Empêcher la suppression de soi-même
if ($userId == $_SESSION['user_id']) {
    header('Location: users.php');
    exit();
}

// Récupérer l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit();
}

// Vérifier s'il y a des demandes associées
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM demande_frais WHERE user_id = ?");
$stmt->execute([$userId]);
$hasDemandes = $stmt->fetch()['count'] > 0;

// Vérifier s'il y a des employés liés (si c'est un manager)
if ($user['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE manager_id = ?");
    $stmt->execute([$userId]);
    $hasEmployees = $stmt->fetch()['count'] > 0;
} else {
    $hasEmployees = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        // Validation stricte
        if ($userId == $_SESSION['user_id']) {
            throw new Exception("Vous ne pouvez pas supprimer votre propre compte.");
        }

        // Si l'utilisateur a des demandes, on ne peut pas le supprimer
        if ($hasDemandes) {
            throw new Exception("Impossible de supprimer cet utilisateur car il a des demandes associées.");
        }

        // Si c'est un manager avec des employés, on ne peut pas le supprimer
        if ($hasEmployees) {
            throw new Exception("Impossible de supprimer ce manager car il a des employés associés. Veuillez d'abord réassigner ses employés.");
        }

        // Supprimer l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        // Redirection propre
        header('Location: users.php?success=1');
        exit();

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-trash"></i> Supprimer l'utilisateur</h1>
    <a href="users.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if ($hasDemandes || $hasEmployees): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <?php if ($hasDemandes): ?>
                    Cet utilisateur a des demandes associées. Vous ne pouvez pas le supprimer.
                <?php elseif ($hasEmployees): ?>
                    Ce manager a des employés associés. Vous ne pouvez pas le supprimer.
                <?php endif; ?>
            </div>
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                Êtes-vous sûr de vouloir supprimer l'utilisateur
                <strong><?php echo htmlspecialchars($user['nom']); ?></strong> ?
                Cette action est irréversible.
            </div>

            <div class="mb-3">
                <strong>Nom :</strong> <?php echo htmlspecialchars($user['nom']); ?><br>
                <strong>Email :</strong> <?php echo htmlspecialchars($user['email']); ?><br>
                <strong>Rôle :</strong> <?php echo htmlspecialchars($user['role']); ?>
            </div>

            <form method="POST">
                <input type="hidden" name="confirm" value="1">
                <div class="d-flex justify-content-between">
                    <a href="users.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Confirmer la suppression
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>