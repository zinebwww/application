<?php
/**
 * Gestion des catégories de frais
 * Liste, ajout, modification, suppression
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php';
require_once __DIR__ . '/../../../includes/notifications.php';

$admin = getAdminInfo();
$currentUser = $admin;
$currentPage = basename($_SERVER['PHP_SELF']);

include __DIR__ . '/header.php';

$message = '';
$messageType = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $nom = trim($_POST['nom'] ?? '');
            if (empty($nom)) {
                throw new Exception("Le nom de la catégorie est obligatoire.");
            }

            // Vérifier si la catégorie existe déjà
            $stmt = $pdo->prepare("SELECT id FROM categories_frais WHERE nom = ?");
            $stmt->execute([$nom]);
            if ($stmt->fetch()) {
                throw new Exception("Cette catégorie existe déjà.");
            }

            $stmt = $pdo->prepare("INSERT INTO categories_frais (nom) VALUES (?)");
            $stmt->execute([$nom]);

            $message = "Catégorie ajoutée avec succès !";
            $messageType = "success";

        } elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $nom = trim($_POST['nom'] ?? '');

            if ($id <= 0 || empty($nom)) {
                throw new Exception("Données invalides.");
            }

            // Vérifier si une autre catégorie a le même nom
            $stmt = $pdo->prepare("SELECT id FROM categories_frais WHERE nom = ? AND id != ?");
            $stmt->execute([$nom, $id]);
            if ($stmt->fetch()) {
                throw new Exception("Cette catégorie existe déjà.");
            }

            $stmt = $pdo->prepare("UPDATE categories_frais SET nom = ? WHERE id = ?");
            $stmt->execute([$nom, $id]);

            $message = "Catégorie modifiée avec succès !";
            $messageType = "success";

        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception("ID invalide.");
            }

            // Vérifier si la catégorie est utilisée
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM details_frais WHERE categorie_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch()['count'];

            if ($count > 0) {
                throw new Exception("Impossible de supprimer cette catégorie car elle est utilisée dans " . $count . " demande(s).");
            }

            $stmt = $pdo->prepare("DELETE FROM categories_frais WHERE id = ?");
            $stmt->execute([$id]);

            $message = "Catégorie supprimée avec succès !";
            $messageType = "success";
        }

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Récupérer toutes les catégories
$stmt = $pdo->query("
    SELECT c.*, COUNT(df.id) as usage_count
    FROM categories_frais c
    LEFT JOIN details_frais df ON c.id = df.categorie_id
    GROUP BY c.id
    ORDER BY c.nom
");
$categories = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-tags"></i> Gestion des catégories de frais</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">
        <i class="bi bi-plus-circle"></i> Ajouter une catégorie
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des catégories (<?php echo count($categories); ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Utilisations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Aucune catégorie trouvée</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $categorie): ?>
                            <tr>
                                <td>#<?php echo $categorie['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($categorie['nom']); ?></strong></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $categorie['usage_count']; ?> demande(s)</span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalEdit<?php echo $categorie['id']; ?>">
                                        <i class="bi bi-pencil"></i> Modifier
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                        data-bs-target="#modalDelete<?php echo $categorie['id']; ?>">
                                        <i class="bi bi-trash"></i> Supprimer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Ajouter -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une catégorie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="nom_add" class="form-label">Nom de la catégorie <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom_add" name="nom" required autofocus>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals Modifier -->
<?php foreach ($categories as $categorie): ?>
    <div class="modal fade" id="modalEdit<?php echo $categorie['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $categorie['id']; ?>">
                        <div class="mb-3">
                            <label for="nom_edit_<?php echo $categorie['id']; ?>" class="form-label">Nom de la catégorie
                                <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom_edit_<?php echo $categorie['id']; ?>" name="nom"
                                value="<?php echo htmlspecialchars($categorie['nom']); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Modals Supprimer -->
<?php foreach ($categories as $categorie): ?>
    <?php if ($categorie['usage_count'] == 0): ?>
        <div class="modal fade" id="modalDelete<?php echo $categorie['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Supprimer la catégorie</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $categorie['id']; ?>">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Êtes-vous sûr de vouloir supprimer la catégorie
                                <strong><?php echo htmlspecialchars($categorie['nom']); ?></strong> ?
                                Cette action est irréversible.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php include __DIR__ . '/footer.php'; ?>