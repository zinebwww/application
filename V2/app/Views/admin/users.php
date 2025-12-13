<?php
/**
 * Liste des utilisateurs
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php';
require_once __DIR__ . '/../../../includes/notifications.php';

$admin = getAdminInfo();
$currentUser = $admin;
$currentPage = basename($_SERVER['PHP_SELF']);

$message = '';
$messageType = '';

if (isset($_GET['success'])) {
    $message = 'Opération effectuée avec succès !';
    $messageType = 'success';
}


// Récupérer les managers pour le select (nécessaire pour le formulaire)
$stmt = $pdo->query("SELECT id, nom FROM users WHERE role = 'manager' ORDER BY nom");
$managers = $stmt->fetchAll();

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    try {
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;

        // Validation
        if (empty($nom) || empty($email) || empty($password) || empty($role)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email invalide.");
        }

        if (!in_array($role, ['admin', 'manager', 'employe'])) {
            throw new Exception("Rôle invalide.");
        }

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Cet email est déjà utilisé.");
        }

        // Si c'est un employé, vérifier que le manager existe
        if ($role === 'employe' && $manager_id) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'manager'");
            $stmt->execute([$manager_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Manager invalide.");
            }
        } else {
            $manager_id = null;
        }

        // Hasher le mot de passe
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insérer l'utilisateur
        $stmt = $pdo->prepare("
            INSERT INTO users (nom, email, mot_de_passe, role, manager_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nom, $email, $password_hash, $role, $manager_id]);

        // Redirection pour éviter la resoumission
        header('Location: users.php?success=1');
        exit();

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

include __DIR__ . '/header.php';

// Récupérer tous les utilisateurs avec leurs managers
$stmt = $pdo->query("
    SELECT u.*, m.nom as manager_nom
    FROM users u
    LEFT JOIN users m ON u.manager_id = m.id
    ORDER BY u.role, u.nom
");
$users = $stmt->fetchAll();

// Fonction pour obtenir le badge du rôle
function getRoleBadge($role)
{
    $badges = [
        'admin' => 'bg-danger',
        'manager' => 'bg-primary',
        'employe' => 'bg-success'
    ];
    $labels = [
        'admin' => 'Admin',
        'manager' => 'Manager',
        'employe' => 'Employé'
    ];
    $badge = $badges[$role] ?? 'bg-secondary';
    $label = $labels[$role] ?? $role;
    return "<span class='badge $badge'>$label</span>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-people"></i> Gestion des utilisateurs</h1>

    <button type="button" class="btn btn-primary" onclick="toggleUserForm()">
        <i class="bi bi-plus-circle"></i> Ajouter un utilisateur
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4" id="userFormCard" style="display: none;">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bi bi-person-plus"></i> Nouvel utilisateur</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nom" class="form-label">Nom complet <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nom" name="nom" required
                        value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <small class="text-muted">Minimum 6 caractères</small>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role" required onchange="toggleManagerField()">
                        <option value="">Sélectionner...</option>
                        <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>
                            Administrateur</option>
                        <option value="manager" <?php echo (($_POST['role'] ?? '') === 'manager') ? 'selected' : ''; ?>>
                            Manager</option>
                        <option value="employe" <?php echo (($_POST['role'] ?? '') === 'employe') ? 'selected' : ''; ?>>
                            Employé</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3" id="manager_field" style="display: none;">
                    <label for="manager_id" class="form-label">Manager</label>
                    <select class="form-select" id="manager_id" name="manager_id">
                        <option value="">Aucun</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?php echo $manager['id']; ?>" <?php echo (($_POST['manager_id'] ?? '') == $manager['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($manager['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Obligatoire pour les employés</small>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-secondary me-2" onclick="toggleUserForm()">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Créer l'utilisateur
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des utilisateurs (<?php echo count($users); ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Manager</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Aucun utilisateur trouvé</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['nom']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo getRoleBadge($user['role']); ?></td>
                                <td>
                                    <?php if ($user['manager_nom']): ?>
                                        <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($user['manager_nom']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Modifier
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="delete_user.php?id=<?php echo $user['id']; ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                            <i class="bi bi-trash"></i> Supprimer
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<script>
    function toggleUserForm() {
        const formCard = document.getElementById('userFormCard');
        if (formCard.style.display === 'none') {
            formCard.style.display = 'block';
        } else {
            formCard.style.display = 'none';
        }
    }

    function toggleManagerField() {
        const role = document.getElementById('role').value;
        const managerField = document.getElementById('manager_field');
        const managerSelect = document.getElementById('manager_id');

        if (role === 'employe') {
            managerField.style.display = 'block';
            managerSelect.required = true;
        } else {
            managerField.style.display = 'none';
            managerSelect.required = false;
            managerSelect.value = '';
        }
    }

    // Appeler au chargement si nécessaire (en cas de réaffichage après erreur)
    document.addEventListener('DOMContentLoaded', toggleManagerField);
</script>