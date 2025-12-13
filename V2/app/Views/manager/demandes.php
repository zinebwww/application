<?php
/**
 * Gestion des demandes de l'équipe
 * Liste + Filtres + Détails + Validation/Rejet (tout regroupé)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../includes/notifications.php';
require_once __DIR__ . '/../../../services/send_mail.php';
requireManager();

$managerId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'liste';
$demandeId = intval($_GET['id'] ?? 0);
$message = '';
$messageType = '';

// Récupérer les employés de l'équipe
$stmt = $pdo->prepare("SELECT id, nom FROM users WHERE manager_id = ? ORDER BY nom");
$stmt->execute([$managerId]);
$equipe = $stmt->fetchAll();
$equipeIds = array_column($equipe, 'id');

// Fonction pour obtenir le badge Bootstrap selon le statut
function getStatutBadge($statut)
{
    $badges = [
        'soumis' => 'bg-secondary',
        'valide_manager' => 'bg-success',
        'rejete_manager' => 'bg-danger',
        'valide_admin' => 'bg-info',
        'rejete_admin' => 'bg-danger',
        'rembourse' => 'bg-primary'
    ];

    $labels = [
        'soumis' => 'En attente',
        'valide_manager' => 'Validé',
        'rejete_manager' => 'Rejeté',
        'valide_admin' => 'Validé Admin',
        'rejete_admin' => 'Rejeté Admin',
        'rembourse' => 'Remboursé'
    ];

    $badge = $badges[$statut] ?? 'bg-secondary';
    $label = $labels[$statut] ?? $statut;

    return "<span class='badge $badge'>$label</span>";
}

// Traitement de la validation/rejet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_validation'])) {
    try {
        $pdo->beginTransaction();

        $demandeId = intval($_POST['demande_id'] ?? 0);
        $action_validation = $_POST['action_validation'] ?? '';
        $commentaire = trim($_POST['commentaire'] ?? '');

        if ($demandeId <= 0 || !in_array($action_validation, ['valider', 'rejeter'])) {
            throw new Exception("Action invalide.");
        }

        // Vérifier que la demande appartient à un employé de l'équipe
        $stmt = $pdo->prepare("
            SELECT d.*, u.nom as employe_nom
            FROM demande_frais d
            JOIN users u ON d.user_id = u.id
            WHERE d.id = ? AND u.manager_id = ?
        ");
        $stmt->execute([$demandeId, $managerId]);
        $demande = $stmt->fetch();

        if (!$demande) {
            throw new Exception("Demande introuvable ou vous n'avez pas les droits.");
        }

        if ($demande['statut'] !== 'soumis') {
            throw new Exception("Cette demande a déjà été traitée.");
        }

        // Déterminer le nouveau statut
        $nouveau_statut = ($action_validation === 'valider') ? 'valide_manager' : 'rejete_manager';
        $commentaire_auto = ($action_validation === 'valider')
            ? 'Demande validée par le manager'
            : 'Demande rejetée par le manager';

        // Mettre à jour le statut
        $stmt = $pdo->prepare("UPDATE demande_frais SET statut = ? WHERE id = ?");
        $stmt->execute([$nouveau_statut, $demandeId]);

        // Ajouter dans l'historique
        $stmt = $pdo->prepare("
            INSERT INTO historique_statuts (demande_id, statut, user_id, commentaire)
            VALUES (?, ?, ?, ?)
        ");
        $commentaire_final = !empty($commentaire) ? $commentaire : $commentaire_auto;
        $stmt->execute([$demandeId, $nouveau_statut, $managerId, $commentaire_final]);

        // Récupérer les informations de l'employé pour la notification et l'email
        $stmt = $pdo->prepare("
            SELECT d.user_id, u.nom, u.email, d.objectif_mission 
            FROM demande_frais d 
            JOIN users u ON d.user_id = u.id 
            WHERE d.id = ?
        ");
        $stmt->execute([$demandeId]);
        $demandeInfo = $stmt->fetch();
        $employeId = $demandeInfo['user_id'];
        $employeNom = $demandeInfo['nom'];
        $employeEmail = $demandeInfo['email'];
        $objectifMission = $demandeInfo['objectif_mission'];

        // Créer une notification pour l'employé et envoyer un email
        if ($action_validation === 'valider') {
            createNotification(
                $employeId,
                'success',
                "Votre demande de frais #{$demandeId} a été validée par votre manager.",
                "../../../app/Views/employee/mes_demandes.php?action=voir&id={$demandeId}",
                false // Pas d'email via notifications (on utilise PHPMailer)
            );

            // Envoyer un email de validation à l'employé
            sendManagerValidationEmail(
                $employeEmail,
                $employeNom,
                $demandeId,
                true, // Approuvé
                $commentaire
            );

            // Créer une notification pour tous les admins et leur envoyer un email
            $stmt_admins = $pdo->prepare("SELECT id, nom, email FROM users WHERE role = 'admin'");
            $stmt_admins->execute();
            $admins = $stmt_admins->fetchAll();

            foreach ($admins as $admin) {
                createNotification(
                    $admin['id'],
                    'info',
                    "Nouvelle demande #{$demandeId} validée par le manager et en attente de traitement.",
                    "../../../app/Views/admin/details_demande.php?id={$demandeId}",
                    false // Pas d'email pour les notifications admin
                );

                // Envoyer un email de notification à l'admin
                sendAdminNotificationEmail(
                    $admin['email'],
                    $admin['nom'],
                    $demandeId,
                    $employeNom,
                    $objectifMission
                );
            }
        } else {
            createNotification(
                $employeId,
                'danger',
                "Votre demande de frais #{$demandeId} a été rejetée par votre manager." . (!empty($commentaire) ? " Commentaire : {$commentaire}" : ""),
                "../../../app/Views/employee/mes_demandes.php?action=voir&id={$demandeId}",
                false // Pas d'email via notifications (on utilise PHPMailer)
            );

            // Envoyer un email de rejet à l'employé
            sendManagerValidationEmail(
                $employeEmail,
                $employeNom,
                $demandeId,
                false, // Rejeté
                $commentaire
            );
        }

        $pdo->commit();

        // Redirection vers les détails
        header("Location: demandes.php?action=voir&id=$demandeId&success=1");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Affichage des détails d'une demande
if ($action === 'voir' && $demandeId > 0) {
    if (empty($equipeIds)) {
        header('Location: demandes.php');
        exit();
    }

    $placeholders = str_repeat('?,', count($equipeIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT d.*, u.nom as employe_nom, u.email as employe_email
        FROM demande_frais d
        JOIN users u ON d.user_id = u.id
        WHERE d.id = ? AND d.user_id IN ($placeholders)
    ");
    $params = array_merge([$demandeId], $equipeIds);
    $stmt->execute($params);
    $demande = $stmt->fetch();

    if (!$demande) {
        header('Location: demandes.php');
        exit();
    }

    // Récupérer les détails de frais
    $stmt = $pdo->prepare("
        SELECT df.*, cf.nom as categorie_nom
        FROM details_frais df
        JOIN categories_frais cf ON df.categorie_id = cf.id
        WHERE df.demande_id = ?
        ORDER BY df.date_depense
    ");
    $stmt->execute([$demandeId]);
    $details = $stmt->fetchAll();

    // Calculer le montant total
    $montant_total = 0;
    foreach ($details as $detail) {
        $montant_total += $detail['montant'];
    }

    // Récupérer l'historique des statuts
    $stmt = $pdo->prepare("
        SELECT h.*, u.nom as user_nom, u.role as user_role
        FROM historique_statuts h
        JOIN users u ON h.user_id = u.id
        WHERE h.demande_id = ?
        ORDER BY h.date_changement ASC
    ");
    $stmt->execute([$demandeId]);
    $historique = $stmt->fetchAll();

    include __DIR__ . '/header.php';
    ?>
        <!-- PDF Generation Scripts -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
        <script>
            console.log("Loading PDF scripts...");
            window.demandeData = {
                id: <?php echo json_encode($demande['id']); ?>,
                employe: <?php echo json_encode($demande['employe_nom'] ?? 'Employé'); ?>,
                objectif: <?php echo json_encode($demande['objectif_mission']); ?>,
                lieu: <?php echo json_encode($demande['lieu_mission']); ?>,
                date_mission: <?php echo json_encode(date('d/m/Y', strtotime($demande['date_mission']))); ?>,
                statut: <?php echo json_encode($demande['statut']); ?>,
                montant_total: <?php echo json_encode(number_format($montant_total, 2, ',', ' ')); ?>,
                details: [
                    <?php foreach ($details as $detail): ?> {
                                categorie: <?php echo json_encode($detail['categorie_nom']); ?>,
                                date: <?php echo json_encode(date('d/m/Y', strtotime($detail['date_depense']))); ?>,
                                montant: <?php echo json_encode(number_format($detail['montant'], 2, ',', ' ')); ?>,
                                description: <?php echo json_encode($detail['description']); ?>,
                                transport: <?php echo json_encode($detail['moyen_transport'] ?? '-'); ?>
                            },
                    <?php endforeach; ?>
                ]
            };

            // Define globally
            window.generatePDF = async function() {
                try {
                    if (!window.jspdf) {
                        alert("Bibliothèque PDF non chargée. Vérifiez votre connexion internet.");
                        return;
                    }
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();
                
                    // En-tête
                    doc.setFontSize(22);
                    doc.setTextColor(40);
                    doc.text("Note de Frais", 14, 20);

                    doc.setFontSize(10);
                    doc.text("Entreprise : Ma Société", 14, 30); 
                    doc.text("Date d'export : " + new Date().toLocaleDateString(), 140, 30);

                    // Ligne de séparation
                    doc.setLineWidth(0.5);
                    doc.line(14, 35, 196, 35);

                    // Informations Employé & Mission
                    doc.setFontSize(14);
                    doc.setTextColor(0);
                    doc.text("Informations Générales", 14, 45);

                    doc.setFontSize(10);
                    doc.text(`Employé : ${window.demandeData.employe}`, 14, 55);
                    doc.text(`Demande N° : #${window.demandeData.id}`, 14, 60);
                    doc.text(`Objectif : ${window.demandeData.objectif}`, 14, 65);
                    doc.text(`Lieu : ${window.demandeData.lieu}`, 14, 70);
                
                    doc.text(`Date mission : ${window.demandeData.date_mission}`, 120, 55);
                    doc.text(`Statut : ${window.demandeData.statut.toUpperCase()}`, 120, 60);

                    // Tableau des frais
                    const tableColumn = ["Date", "Catégorie", "Transport", "Description / Trajet", "Montant (DH)"];
                    const tableRows = [];

                    window.demandeData.details.forEach(detail => {
                        let desc = detail.description || '';
                        if(desc.length > 60) desc = desc.substring(0, 60) + '...';
                    
                        const row = [
                            detail.date,
                            detail.categorie,
                            detail.transport,
                            desc,
                            detail.montant
                        ];
                        tableRows.push(row);
                    });

                    doc.autoTable({
                        head: [tableColumn],
                        body: tableRows,
                        startY: 80,
                        theme: 'striped',
                        headStyles: { fillColor: [66, 133, 244] },
                        styles: { fontSize: 9, cellPadding: 3, overflow: 'linebreak' },
                        columnStyles: { 3: { cellWidth: 80 } }
                    });

                    // Total
                    const finalY = doc.lastAutoTable.finalY + 15;
                    doc.setFontSize(12);
                    doc.setFont(undefined, 'bold');
                    doc.text(`Montant Total Validé : ${window.demandeData.montant_total} DH`, 130, finalY);

                    // Pied de page
                    const pageCount = doc.internal.getNumberOfPages();
                    for(let i = 1; i <= pageCount; i++) {
                        doc.setPage(i);
                        doc.setFontSize(8);
                        doc.setFont(undefined, 'normal');
                        doc.text("Document généré automatiquement - Gestion des Frais de Déplacement", 14, 285);
                        doc.text('Page ' + i + ' sur ' + pageCount, 180, 285);
                    }

                    doc.save(`Demande_Frais_${window.demandeData.id}.pdf`);
                } catch (error) {
                    console.error("PDF Generation Error:", error);
                    alert("Erreur lors de la génération du PDF. Consultez la console.");
                }
            };
            console.log("PDF function registered");
        </script>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-eye"></i> Détails de la demande #<?php echo $demande['id']; ?></h1>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-danger" onclick="generatePDF()">
                    <i class="bi bi-file-earmark-pdf"></i> Exporter PDF
                </button>
                <a href="demandes.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Retour à la liste
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Opération effectuée avec succès !
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
        <?php endif; ?>

        <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
        <?php endif; ?>

        <!-- Informations générales -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informations de la mission</h5>
                <?php echo getStatutBadge($demande['statut']); ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Employé :</strong><br>
                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($demande['employe_nom']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($demande['employe_email']); ?></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Date de soumission :</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($demande['date_soumission'])); ?>
                    </div>
                    <div class="col-md-12 mb-3">
                        <strong>Objectif de la mission :</strong><br>
                        <?php echo nl2br(htmlspecialchars($demande['objectif_mission'])); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Lieu :</strong><br>
                        <?php echo htmlspecialchars($demande['lieu_mission']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Date mission :</strong><br>
                        <?php echo date('d/m/Y', strtotime($demande['date_mission'])); ?>
                    </div>
                    <?php if ($demande['justificatif_principal']): ?>
                            <div class="col-md-12 mb-3">
                                <strong>Justificatif principal :</strong><br>
                                <a href="../../../public/uploads/<?php echo htmlspecialchars($demande['justificatif_principal']); ?>"
                                    target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-earmark"></i> Voir le fichier
                                </a>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Détails des frais -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Détails des frais</h5>
            </div>
            <div class="card-body">
                <?php if (empty($details)): ?>
                        <p class="text-muted">Aucun détail de frais enregistré.</p>
                <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Description</th>
                                        <th>Justificatif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($details as $detail): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($detail['categorie_nom']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($detail['date_depense'])); ?></td>
                                                <td><strong><?php echo number_format($detail['montant'], 2, ',', ' '); ?> DH</strong></td>
                                                <td><?php echo htmlspecialchars($detail['description'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if (!empty($detail['point_depart']) && !empty($detail['point_arrivee'])):
                                                        // Utiliser les coordonnées précises si disponibles, sinon l'adresse
                                                        $start = (!empty($detail['lat_depart']) && !empty($detail['lng_depart']))
                                                            ? $detail['lat_depart'] . ',' . $detail['lng_depart']
                                                            : $detail['point_depart'];

                                                        $end = (!empty($detail['lat_arrivee']) && !empty($detail['lng_arrivee']))
                                                            ? $detail['lat_arrivee'] . ',' . $detail['lng_arrivee']
                                                            : $detail['point_arrivee'];
                                                        ?>
                                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                                onclick='openMapModal(<?php echo htmlspecialchars(json_encode($start), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($end), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (float) $detail['montant']; ?>)'>
                                                                <i class="bi bi-map"></i> Voir itinéraire
                                                            </button>
                                                    <?php elseif ($detail['justificatif']): ?>
                                                            <a href="../../../public/uploads/<?php echo htmlspecialchars($detail['justificatif']); ?>"
                                                                target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-file-earmark"></i> Voir
                                                            </a>
                                                    <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <th colspan="2">Total</th>
                                        <th><?php echo number_format($montant_total, 2, ',', ' '); ?> DH</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historique des statuts -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historique</h5>
            </div>
            <div class="card-body">
                <?php if (empty($historique)): ?>
                        <p class="text-muted">Aucun historique disponible.</p>
                <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($historique as $hist): ?>
                                    <div class="d-flex mb-3">
                                        <div class="flex-shrink-0">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                                                style="width: 40px; height: 40px;">
                                                <i class="bi bi-check"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <strong><?php echo getStatutBadge($hist['statut']); ?></strong>
                                                            <p class="mb-1 mt-2">
                                                                <?php echo htmlspecialchars($hist['commentaire'] ?? 'Aucun commentaire'); ?>
                                                            </p>
                                                            <small class="text-muted">
                                                                Par <?php echo htmlspecialchars($hist['user_nom']); ?>
                                                                (<?php echo htmlspecialchars($hist['user_role']); ?>)
                                                            </small>
                                                        </div>
                                                        <small
                                                            class="text-muted"><?php echo date('d/m/Y H:i', strtotime($hist['date_changement'])); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            <?php endforeach; ?>
                        </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions de validation/rejet -->
        <?php if ($demande['statut'] === 'soumis'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-check-circle"></i> Action de validation</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="validationForm">
                            <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">

                            <div class="mb-3">
                                <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                                <textarea class="form-control" id="commentaire" name="commentaire" rows="3"
                                    placeholder="Ajouter un commentaire sur votre décision..."></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="demandes.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                                <div>
                                    <button type="submit" name="action_validation" value="rejeter" class="btn btn-danger"
                                        onclick="return confirm('Êtes-vous sûr de vouloir rejeter cette demande ?');">
                                        <i class="bi bi-x-circle"></i> Rejeter
                                    </button>
                                    <button type="submit" name="action_validation" value="valider" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Valider
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
        <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Cette demande a déjà été traitée. Statut actuel :
                    <?php echo getStatutBadge($demande['statut']); ?>
                </div>
        <?php endif; ?>

        <!-- Modal Map -->
        <div class="modal fade" id="mapModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Justification du trajet</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="mapContainer" style="height: 400px; margin-bottom: 1rem;"></div>
                        <div class="row mt-3 map-info-section">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Distance calculée (aller)</label>
                                <div class="alert alert-primary mb-0 py-2">
                                    <i class="bi bi-arrow-right"></i>
                                    <span id="modal_distance_aller">0</span> km
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Distance aller-retour</label>
                                <div class="alert alert-info mb-0 py-2">
                                    <i class="bi bi-arrow-left-right"></i>
                                    <span id="modal_distance_ar">0</span> km
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Montant calculé (DH)</label>
                                <div class="alert alert-success mb-0 py-2">
                                    <i class="bi bi-currency-dollar"></i>
                                    <span id="modal_montant">0.00</span> DH
                                </div>
                            </div>
                        </div>
                        <p class="text-muted small mt-3 mb-0" id="modal_distance_summary">
                            <i class="bi bi-info-circle"></i>
                            Distance calculée : <strong>0 km</strong>
                            (aller-retour : <strong>0 km</strong>)
                            – Montant estimé : <strong>0.00 DH</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaflet JS + Routing Machine + Geocoder + Map Modal Script -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
        <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
        <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
        <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
        <script src="../../../public/js/map-modal.js"></script>
        <style>
            .leaflet-control-geocoder {
                z-index: 1000;
            }
        </style>

        <?php
        include __DIR__ . '/footer.php';
        exit();
}

// Liste des demandes avec filtres
if (empty($equipeIds)) {
    include __DIR__ . '/header.php';
    ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> Aucun employé dans votre équipe pour le moment.
        </div>
        <?php
        include __DIR__ . '/footer.php';
        exit();
}

// Récupération des filtres
$filtre_employe = isset($_GET['employe']) ? intval($_GET['employe']) : 0;
$filtre_statut = $_GET['statut'] ?? '';
$filtre_date_debut = $_GET['date_debut'] ?? '';
$filtre_date_fin = $_GET['date_fin'] ?? '';

// Construction de la requête
$placeholders = str_repeat('?,', count($equipeIds) - 1) . '?';
$sql = "
    SELECT d.*, u.nom as employe_nom,
           COALESCE(SUM(df.montant), 0) as montant_total
    FROM demande_frais d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN details_frais df ON d.id = df.demande_id
    WHERE d.user_id IN ($placeholders)
";
$params = $equipeIds;

// Application des filtres
if ($filtre_employe > 0 && in_array($filtre_employe, $equipeIds)) {
    $sql .= " AND d.user_id = ?";
    $params[] = $filtre_employe;
}

if (!empty($filtre_statut)) {
    $sql .= " AND d.statut = ?";
    $params[] = $filtre_statut;
}

if (!empty($filtre_date_debut)) {
    $sql .= " AND d.date_mission >= ?";
    $params[] = $filtre_date_debut;
}

if (!empty($filtre_date_fin)) {
    $sql .= " AND d.date_mission <= ?";
    $params[] = $filtre_date_fin;
}

$sql .= " GROUP BY d.id ORDER BY d.date_soumission DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-list-check"></i> Demandes de l'équipe</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Dashboard
    </a>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtres</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="employe" class="form-label">Employé</label>
                <select class="form-select" id="employe" name="employe">
                    <option value="">Tous</option>
                    <?php foreach ($equipe as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $filtre_employe == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['nom']); ?>
                            </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="">Tous</option>
                    <option value="soumis" <?php echo $filtre_statut === 'soumis' ? 'selected' : ''; ?>>En attente
                    </option>
                    <option value="valide_manager" <?php echo $filtre_statut === 'valide_manager' ? 'selected' : ''; ?>>
                        Validées</option>
                    <option value="rejete_manager" <?php echo $filtre_statut === 'rejete_manager' ? 'selected' : ''; ?>>
                        Rejetées</option>
                </select>
            </div>

            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut"
                    value="<?php echo htmlspecialchars($filtre_date_debut); ?>">
            </div>

            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin"
                    value="<?php echo htmlspecialchars($filtre_date_fin); ?>">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrer
                </button>
                <a href="demandes.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des demandes -->
<div class="card">
    <div class="card-body">
        <?php if (empty($demandes)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Aucune demande trouvée avec ces critères.
                </div>
        <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employé</th>
                                <th>Objectif</th>
                                <th>Date mission</th>
                                <th>Montant total</th>
                                <th>Statut</th>
                                <th>Date soumission</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($demandes as $demande): ?>
                                    <tr>
                                        <td>#<?php echo $demande['id']; ?></td>
                                        <td><?php echo htmlspecialchars($demande['employe_nom']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($demande['objectif_mission'], 0, 50)); ?><?php echo strlen($demande['objectif_mission']) > 50 ? '...' : ''; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($demande['date_mission'])); ?></td>
                                        <td><strong><?php echo number_format($demande['montant_total'], 2, ',', ' '); ?> DH</strong>
                                        </td>
                                        <td><?php echo getStatutBadge($demande['statut']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($demande['date_soumission'])); ?></td>
                                        <td>
                                            <a href="demandes.php?action=voir&id=<?php echo $demande['id']; ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Voir
                                            </a>
                                        </td>
                                    </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Map -->
<div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Justification du trajet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="mapContainer" style="height: 400px; margin-bottom: 1rem;"></div>
                <div class="row mt-3 map-info-section">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Distance calculée (aller)</label>
                        <div class="alert alert-primary mb-0 py-2">
                            <i class="bi bi-arrow-right"></i>
                            <span id="modal_distance_aller">0</span> km
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Distance aller-retour</label>
                        <div class="alert alert-info mb-0 py-2">
                            <i class="bi bi-arrow-left-right"></i>
                            <span id="modal_distance_ar">0</span> km
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Montant calculé (DH)</label>
                        <div class="alert alert-success mb-0 py-2">
                            <i class="bi bi-currency-dollar"></i>
                            <span id="modal_montant">0.00</span> DH
                        </div>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0" id="modal_distance_summary">
                    <i class="bi bi-info-circle"></i>
                    Distance calculée : <strong>0 km</strong>
                    (aller-retour : <strong>0 km</strong>)
                    – Montant estimé : <strong>0.00 DH</strong>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS + Routing Machine + Map Modal Script -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script src="../../../public/js/map-modal.js"></script>

<?php include __DIR__ . '/footer.php'; ?>