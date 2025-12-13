<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
requireEmploye();

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Récupérer les données fraîches de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$managerName = '';
if (!empty($user['manager_id'])) {
    $stmtMgr = $pdo->prepare("SELECT nom FROM users WHERE id = ?");
    $stmtMgr->execute([$user['manager_id']]);
    $mgr = $stmtMgr->fetch();
    if ($mgr) {
        $managerName = $mgr['nom'];
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation basique
    if (empty($email)) {
        $message = "L'email est obligatoire.";
        $messageType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format d'email invalide.";
        $messageType = "danger";
    } elseif (!empty($password) && $password !== $password_confirm) {
        $message = "Les mots de passe ne correspondent pas.";
        $messageType = "danger";
    } else {
        try {
            // Vérifier si l'email existe déjà pour un autre utilisateur
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                throw new Exception("Cet email est déjà utilisé par un autre compte.");
            }

            if (!empty($password)) {
                // Mise à jour avec mot de passe (hashé)
                // Note : On suppose l'utilisation de password_hash standard
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET email = ?, mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$email, $hashed_password, $userId]);
            } else {
                // Mise à jour email seulement
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$email, $userId]);
            }

            // Mettre à jour la session
            $_SESSION['user_email'] = $email;

            $message = "Profil mis à jour avec succès.";
            $messageType = "success";

            // Rafraîchir les données affichées
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

        } catch (Exception $e) {
            $message = "Erreur : " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

include __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-person-circle"></i> Mon Profil</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informations personnelles</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Informations en lecture seule -->
                    <div class="mb-3">
                        <label class="form-label text-muted">Nom complet</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['nom']); ?>"
                            disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted">Rôle</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                    </div>

                    <?php if ($managerName): ?>
                    <div class="mb-3">
                        <label class="form-label text-muted">Manager</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($managerName); ?>" disabled>
                    </div>
                    <?php endif; ?>

                    <hr class="my-4">
                    <h6 class="mb-3 text-primary">Modifier mes identifiants</h6>

                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Laisser vide pour ne pas changer">
                    </div>

                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                            placeholder="Répéter le nouveau mot de passe">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>