<?php
/**
 * Suivi et détails des demandes de frais de l'employé
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
requireEmploye();

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'liste';
$demandeId = intval($_GET['id'] ?? 0);

// Fonction pour obtenir le badge Bootstrap selon le statut
// La fonction getStatutBadge est désormais incluse via header.php

// Traitement de la modification (si demandée)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'modifier') {
    try {
        $pdo->beginTransaction();

        // Vérifier que la demande appartient à l'utilisateur et peut être modifiée
        $stmt = $pdo->prepare("SELECT id, statut FROM demande_frais WHERE id = ? AND user_id = ?");
        $stmt->execute([$demandeId, $userId]);
        $demande = $stmt->fetch();

        if (!$demande) {
            throw new Exception("Demande introuvable.");
        }

        if (!in_array($demande['statut'], ['soumis', 'rejete_manager', 'rejete_admin'])) {
            throw new Exception("Cette demande ne peut plus être modifiée.");
        }

        // Mettre à jour la demande
        $objectif_mission = trim($_POST['objectif_mission'] ?? '');
        $lieu_mission = trim($_POST['lieu_mission'] ?? '');
        $date_mission = $_POST['date_mission'] ?? '';

        if (empty($objectif_mission) || empty($lieu_mission) || empty($date_mission)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        $stmt = $pdo->prepare("
            UPDATE demande_frais 
            SET objectif_mission = ?, lieu_mission = ?, date_mission = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$objectif_mission, $lieu_mission, $date_mission, $demandeId, $userId]);

        // Upload du justificatif principal
        if (isset($_FILES['justificatif_principal']) && $_FILES['justificatif_principal']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['justificatif_principal'];
            if ($file['size'] <= UPLOAD_MAX_SIZE) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ALLOWED_EXTENSIONS)) {
                    $filename = uniqid('justif_principal_', true) . '.' . $ext;
                    $filepath = UPLOAD_DIR . $filename;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Supprimer l'ancien fichier
                        if (!empty($demande['justificatif_principal']) && file_exists(UPLOAD_DIR . $demande['justificatif_principal'])) {
                            @unlink(UPLOAD_DIR . $demande['justificatif_principal']);
                        }

                        $stmt = $pdo->prepare("UPDATE demande_frais SET justificatif_principal = ? WHERE id = ?");
                        $stmt->execute([$filename, $demandeId]);
                    }
                }
            }
        }

        // Mettre à jour les détails existants ou en créer de nouveaux
        if (isset($_POST['details']) && is_array($_POST['details'])) {
            // Supprimer les anciens détails
            $stmt = $pdo->prepare("DELETE FROM details_frais WHERE demande_id = ?");
            $stmt->execute([$demandeId]);

            // Ajouter les nouveaux détails
            require_once __DIR__ . '/../../../services/CurrencyConverter.php';
            foreach ($_POST['details'] as $index => $detail) {
                $categorie_id = intval($detail['categorie_id'] ?? 0);
                $date_depense = $detail['date_depense'] ?? '';
                $montant_source = floatval($detail['montant'] ?? 0);
                // Récupérer aussi le montant depuis le champ caché montant_mad si disponible
                $montant_mad_hidden = floatval($detail['montant_mad'] ?? 0);
                $currency = strtoupper(trim($detail['currency'] ?? 'MAD'));
                $description = trim($detail['description'] ?? '');

                // Récupérer les points de départ et d'arrivée - essayer plusieurs sources
                $point_depart = '';
                $point_arrivee = '';

                // Essayer d'abord depuis les champs cachés (plus fiable car toujours présents)
                if (isset($detail['point_depart_hidden']) && !empty(trim($detail['point_depart_hidden']))) {
                    $point_depart = trim($detail['point_depart_hidden']);
                } elseif (isset($detail['point_depart']) && !empty(trim($detail['point_depart']))) {
                    $point_depart = trim($detail['point_depart']);
                }

                if (isset($detail['point_arrivee_hidden']) && !empty(trim($detail['point_arrivee_hidden']))) {
                    $point_arrivee = trim($detail['point_arrivee_hidden']);
                } elseif (isset($detail['point_arrivee']) && !empty(trim($detail['point_arrivee']))) {
                    $point_arrivee = trim($detail['point_arrivee']);
                }

                // Récupérer le moyen de transport
                $moyen_transport = !empty($detail['moyen_transport']) ? trim($detail['moyen_transport']) : null;

                // Convertir les chaînes vides en NULL pour la base de données
                $point_depart = !empty($point_depart) ? $point_depart : null;
                $point_arrivee = !empty($point_arrivee) ? $point_arrivee : null;

                // Récupérer les coordonnées GPS
                $lat_depart = !empty($detail['lat_depart']) ? floatval($detail['lat_depart']) : null;
                $lng_depart = !empty($detail['lng_depart']) ? floatval($detail['lng_depart']) : null;
                $lat_arrivee = !empty($detail['lat_arrivee']) ? floatval($detail['lat_arrivee']) : null;
                $lng_arrivee = !empty($detail['lng_arrivee']) ? floatval($detail['lng_arrivee']) : null;

                // Gestion du justificatif : conserver l'ancien si pas de nouveau fichier
                $justificatif = null;
                $fileKey = 'detail_justif_' . $index;
                $oldJustificatif = $detail['old_justificatif'] ?? null;

                // Si un nouveau fichier est uploadé
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$fileKey];

                    // Vérifier la taille
                    if ($file['size'] <= UPLOAD_MAX_SIZE) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ALLOWED_EXTENSIONS)) {
                            $filename = uniqid('detail_', true) . '.' . $ext;
                            $filepath = UPLOAD_DIR . $filename;

                            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                                // Supprimer l'ancien fichier si on le remplace
                                if (!empty($oldJustificatif) && file_exists(UPLOAD_DIR . $oldJustificatif)) {
                                    @unlink(UPLOAD_DIR . $oldJustificatif);
                                }
                                $justificatif = $filename;
                            }
                        }
                    }
                } else {
                    // Pas de nouveau fichier : conserver l'ancien si existant (passé via champ caché)
                    if (!empty($oldJustificatif)) {
                        $justificatif = $oldJustificatif;
                    }
                }

                // Convertir le montant en MAD
                // Si montant_mad est disponible (pour les transports calculés automatiquement), l'utiliser en priorité
                if ($montant_mad_hidden > 0) {
                    $montant = $montant_mad_hidden;
                } else {
                    $montant = $montant_source;
                    if ($currency !== 'MAD' && $montant_source > 0) {
                        try {
                            $montant = \Services\CurrencyConverter::convertToMAD($montant_source, $currency);
                        } catch (Exception $e) {
                            // En cas d'erreur, utiliser le montant source
                            $montant = $montant_source;
                        }
                    }
                }

                // Vérifier si c'est un transport avec points de départ/arrivée (montant peut être calculé automatiquement)
                $isTransportWithPoints = !empty($point_depart) || !empty($point_arrivee);

                // Condition de sauvegarde : 
                // - catégorie et date obligatoires
                // - montant > 0 OU transport avec points (le montant sera calculé/rempli automatiquement)
                if ($categorie_id > 0 && !empty($date_depense) && ($montant > 0 || $isTransportWithPoints)) {
                    // Vérification : Justificatif obligatoire pour les frais NON "voiture" (pas de points)
                    if (!$isTransportWithPoints && empty($justificatif)) {
                        throw new Exception("Le justificatif est obligatoire pour le détail des frais (Date: " . $date_depense . ").");
                    }

                    // Préparer la requête
                    $stmt = $pdo->prepare("
                        INSERT INTO details_frais (
                            demande_id, categorie_id, moyen_transport, date_depense, 
                            montant, currency, description, 
                            point_depart, point_arrivee, 
                            lat_depart, lng_depart, lat_arrivee, lng_arrivee,
                            justificatif
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    // Exécuter avec les valeurs
                    $stmt->execute([
                        $demandeId,
                        $categorie_id,
                        $moyen_transport,  // Peut être NULL
                        $date_depense,
                        $montant,
                        $currency,  // Devise
                        $description,
                        $point_depart,  // Nom du lieu (peut être NULL)
                        $point_arrivee, // Nom du lieu (peut être NULL)
                        $lat_depart,
                        $lng_depart,
                        $lat_arrivee,
                        $lng_arrivee,
                        $justificatif   // Peut être NULL
                    ]);
                }
            }
        }

        // Notification au manager
        if (!function_exists('createNotification')) {
            require_once __DIR__ . '/../../../includes/notifications.php';
        }

        $stmt = $pdo->prepare("SELECT manager_id, nom FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();

        if ($currentUser && $currentUser['manager_id']) {
            $messageNotif = "L'employé " . $currentUser['nom'] . " a modifié la demande #" . $demandeId . ", veuillez re-vérifier.";
            createNotification(
                $currentUser['manager_id'],
                'warning',
                $messageNotif,
                "demandes.php?action=voir&id=$demandeId"
            );
        }

        $pdo->commit();
        header("Location: mes_demandes.php?action=voir&id=$demandeId&success=1");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Affichage des détails d'une demande
if ($action === 'voir' && $demandeId > 0) {
    $stmt = $pdo->prepare("
        SELECT d.*, u.nom as user_nom
        FROM demande_frais d
        JOIN users u ON d.user_id = u.id
        WHERE d.id = ? AND d.user_id = ?
    ");
    $stmt->execute([$demandeId, $userId]);
    $demande = $stmt->fetch();

    if (!$demande) {
        header('Location: mes_demandes.php');
        exit();
    }

    // Récupérer les détails de frais avec toutes les colonnes
    $stmt = $pdo->prepare("
        SELECT df.*, cf.nom as categorie_nom
        FROM details_frais df
        JOIN categories_frais cf ON df.categorie_id = cf.id
        WHERE df.demande_id = ?
        ORDER BY df.date_depense
    ");
    $stmt->execute([$demandeId]);
    $details = $stmt->fetchAll();

    // Si les colonnes n'existent pas encore, les valeurs seront NULL (gestion rétrocompatible)

    // Calculer le montant total
    $montant_total = 0;
    foreach ($details as $detail) {
        $montant_total += $detail['montant'];
    }

    // Récupérer l'historique des statuts
    $stmt = $pdo->prepare("
        SELECT h.*, u.nom as user_nom
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
            employe: <?php echo json_encode($demande['user_nom'] ?? 'Employé'); ?>,
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
        window.generatePDF = async function () {
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
                    if (desc.length > 60) desc = desc.substring(0, 60) + '...';

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
                for (let i = 1; i <= pageCount; i++) {
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

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h1 class="mb-0"><i class="bi bi-eye"></i> Détails de la demande #<?php echo $demande['id']; ?></h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger" onclick="generatePDF()">
                <i class="bi bi-file-earmark-pdf"></i> Exporter PDF
            </button>
            <a href="mes_demandes.php" class="btn btn-outline-secondary">
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

    <!-- Informations générales -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Informations de la mission</h5>
            <?php echo getStatutBadge($demande['statut']); ?>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <strong>Objectif :</strong><br>
                    <?php echo nl2br(htmlspecialchars($demande['objectif_mission'])); ?>
                </div>
                <div class="col-md-3 mb-3">
                    <strong>Lieu :</strong><br>
                    <?php echo htmlspecialchars($demande['lieu_mission']); ?>
                </div>
                <div class="col-md-3 mb-3">
                    <strong>Date mission :</strong><br>
                    <?php echo date('d/m/Y', strtotime($demande['date_mission'])); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Date de soumission :</strong><br>
                    <?php echo date('d/m/Y H:i', strtotime($demande['date_soumission'])); ?>
                </div>
                <?php if ($demande['justificatif_principal']): ?>
                    <div class="col-md-6 mb-3">
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
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i> Détails des frais</h5>
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
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Historique</h5>
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
                                                <small class="text-muted">Par
                                                    <?php echo htmlspecialchars($hist['user_nom']); ?></small>
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

    <!-- Bouton modifier (si possible) -->
    <?php if (in_array($demande['statut'], ['soumis', 'rejete_manager', 'rejete_admin'])): ?>
        <div class="text-center mb-4">
            <a href="mes_demandes.php?action=modifier&id=<?php echo $demande['id']; ?>" class="btn btn-warning">
                <i class="bi bi-pencil"></i> Modifier la demande
            </a>
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

    <?php
    include __DIR__ . '/footer.php';
    exit();
}

// Affichage du formulaire de modification
if ($action === 'modifier' && $demandeId > 0) {
    $stmt = $pdo->prepare("
        SELECT d.*
        FROM demande_frais d
        WHERE d.id = ? AND d.user_id = ?
    ");
    $stmt->execute([$demandeId, $userId]);
    $demande = $stmt->fetch();

    if (!$demande || !in_array($demande['statut'], ['soumis', 'rejete_manager', 'rejete_admin'])) {
        header('Location: mes_demandes.php');
        exit();
    }

    // Récupérer les détails
    $stmt = $pdo->prepare("
        SELECT df.*, cf.nom as categorie_nom
        FROM details_frais df
        JOIN categories_frais cf ON df.categorie_id = cf.id
        WHERE df.demande_id = ?
        ORDER BY df.date_depense
    ");
    $stmt->execute([$demandeId]);
    $details = $stmt->fetchAll();

    // Récupérer les catégories
    $stmt = $pdo->query("SELECT id, nom FROM categories_frais ORDER BY nom");
    $categories = $stmt->fetchAll();

    include __DIR__ . '/header.php';
    ?>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <!-- Geocoding pour la recherche d'adresses -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.css" />

    <style>
        /* Style pour tous les conteneurs de carte */
        [id^="map_"] {
            z-index: 1;
            min-height: 400px;
            background-color: #f5f5f5;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }

        .leaflet-container {
            background-color: #f5f5f5 !important;
            border-radius: var(--border-radius);
        }

        .leaflet-routing-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            box-shadow: var(--shadow);
        }

        .leaflet-control-geocoder {
            border-radius: var(--border-radius);
        }

        [id^="distance_calculee_"],
        [id^="montant_calcule_"] {
            font-weight: 600;
            color: var(--primary-color);
            background-color: var(--bg-secondary);
        }

        .carte-section .card {
            border: 1px solid rgba(13, 110, 253, 0.2);
        }

        .carte-section .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h1 class="mb-0"><i class="bi bi-pencil"></i> Modifier la demande #<?php echo $demande['id']; ?></h1>
        <a href="mes_demandes.php?action=voir&id=<?php echo $demande['id']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Annuler
        </a>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="demandeForm">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Informations de la mission</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="objectif_mission" class="form-label">Objectif de la mission <span
                                class="text-danger">*</span></label>
                        <textarea class="form-control" id="objectif_mission" name="objectif_mission" rows="3"
                            required><?php echo htmlspecialchars($demande['objectif_mission']); ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="lieu_mission" class="form-label">Lieu de la mission <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lieu_mission" name="lieu_mission"
                            value="<?php echo htmlspecialchars($demande['lieu_mission']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="date_mission" class="form-label">Date de la mission <span
                                class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_mission" name="date_mission"
                            value="<?php echo htmlspecialchars($demande['date_mission']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="justificatif_principal" class="form-label">Justificatif Principal</label>
                        <?php if (!empty($demande['justificatif_principal'])): ?>
                            <div class="mb-2">
                                <span class="badge bg-info">Fichier actuel :
                                    <?php echo htmlspecialchars($demande['justificatif_principal']); ?></span>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="justificatif_principal" name="justificatif_principal"
                            accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Si nouveau fichier sélectionné, l'ancien sera remplacé.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body p-0">
                <div id="detailsContainer" class="p-3">
                    <?php foreach ($details as $index => $detail):
                        // Déterminer si c'est un transport voiture basé sur la description, la catégorie, ou les colonnes dédiées
                        $isTransport = stripos($detail['categorie_nom'], 'transport') !== false;
                        // Vérifier si c'est une voiture : soit dans la description, soit si on a des points de départ/arrivée
                        $hasPoints = !empty($detail['point_depart']) || !empty($detail['point_arrivee']);
                        $isVoiture = $isTransport && (
                            stripos($detail['description'] ?? '', 'Distance calculée') !== false ||
                            stripos($detail['description'] ?? '', 'voiture') !== false ||
                            $hasPoints
                        );

                        // Récupérer le moyen de transport depuis la BDD (colonne moyen_transport)
                        $moyenTransport = $detail['moyen_transport'] ?? '';

                        // Si pas dans la BDD, essayer d'extraire depuis la description (rétrocompatibilité)
                        if (empty($moyenTransport) && !empty($detail['description'])) {
                            $descLower = strtolower($detail['description']);
                            if (stripos($descLower, 'train') !== false) {
                                $moyenTransport = 'train';
                            } elseif (stripos($descLower, 'avion') !== false) {
                                $moyenTransport = 'avion';
                            } elseif ($isVoiture || stripos($descLower, 'voiture') !== false) {
                                $moyenTransport = 'voiture';
                            } elseif (stripos($descLower, 'autre') !== false) {
                                $moyenTransport = 'autre';
                            }
                        }
                        // Si toujours pas trouvé mais c'est un transport avec points, par défaut "voiture"
                        if (empty($moyenTransport) && $isTransport && $hasPoints) {
                            $moyenTransport = 'voiture';
                        }

                        // Récupérer la devise depuis la BDD
                        $currency = $detail['currency'] ?? 'MAD';

                        // IMPORTANT : Les coordonnées lat/lng ne sont PAS stockées en BDD
                        // Elles seront recalculées côté JS via reverse geocoding depuis point_depart/point_arrivee
                        $latDepart = null;
                        $lngDepart = null;
                        $latArrivee = null;
                        $lngArrivee = null;

                        // Extraire les informations de distance depuis la description pour pré-remplir les champs
                        $distanceKm = '';
                        $distanceKmAr = '';
                        // Récupérer les points depuis les colonnes dédiées si disponibles, sinon depuis la description
                        $pointDepart = '';
                        $pointArrivee = '';

                        // Priorité 1 : Utiliser les colonnes dédiées si elles existent
                        if (isset($detail['point_depart']) && !empty($detail['point_depart'])) {
                            $pointDepart = trim($detail['point_depart']);
                        }
                        if (isset($detail['point_arrivee']) && !empty($detail['point_arrivee'])) {
                            $pointArrivee = trim($detail['point_arrivee']);
                        }

                        // Extraire les distances depuis la description
                        if (!empty($detail['description'])) {
                            if (preg_match('/Distance calculée\s*:\s*([\d.]+)\s*km/i', $detail['description'], $matches)) {
                                $distanceKm = $matches[1];
                            }
                            if (preg_match('/aller-retour\s*:\s*([\d.]+)\s*km/i', $detail['description'], $matches)) {
                                $distanceKmAr = $matches[1];
                            }
                        }

                        // Priorité 2 : Fallback sur la description si les colonnes sont vides
                        if (empty($pointDepart) && empty($pointArrivee) && $isVoiture && !empty($detail['description'])) {
                            // Essayer d'extraire les points de départ/arrivée si présents dans la description
                            // Format: "Départ : [adresse]" et "Arrivée : [adresse]" sur des lignes séparées
                            $lines = explode("\n", $detail['description']);
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (preg_match('/^Départ\s*:\s*(.+)$/i', $line, $matches)) {
                                    $pointDepart = trim($matches[1]);
                                }
                                if (preg_match('/^Arrivée\s*:\s*(.+)$/i', $line, $matches)) {
                                    $pointArrivee = trim($matches[1]);
                                }
                            }
                        }
                        ?>
                        <div class="card mb-3 detail-item" data-index="<?php echo $index; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Frais #<?php echo $index + 1; ?></h6>
                                    <button type="button" class="btn btn-sm btn-danger removeDetail">
                                        <i class="bi bi-trash"></i> Supprimer
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Type de dépense <span class="text-danger">*</span></label>
                                        <select class="form-select categorie-select"
                                            name="details[<?php echo $index; ?>][categorie_id]"
                                            data-index="<?php echo $index; ?>" required>
                                            <option value="">Sélectionner...</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>"
                                                    data-nom="<?php echo htmlspecialchars(strtolower($cat['nom'])); ?>" <?php echo $cat['id'] == $detail['categorie_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control"
                                            name="details[<?php echo $index; ?>][date_depense]"
                                            value="<?php echo htmlspecialchars($detail['date_depense']); ?>" required>
                                    </div>

                                    <div class="col-md-3 mb-3 montant-container" data-index="<?php echo $index; ?>"
                                        style="<?php echo $isVoiture ? 'display: none;' : ''; ?>">
                                        <label class="form-label">Montant & devise <span class="text-danger">*</span></label>
                                        <div class="input-group mb-2">
                                            <input type="number" class="form-control montant-input"
                                                name="details[<?php echo $index; ?>][montant]" step="0.01" min="0"
                                                value="<?php echo htmlspecialchars($detail['montant']); ?>"
                                                data-index="<?php echo $index; ?>" <?php echo $isVoiture ? '' : 'required'; ?>>
                                            <select class="form-select currency-select"
                                                name="details[<?php echo $index; ?>][currency]"
                                                data-index="<?php echo $index; ?>">
                                                <option value="MAD" <?php echo ($currency === 'MAD') ? 'selected' : ''; ?>>MAD
                                                    (DH)</option>
                                                <option value="EUR" <?php echo ($currency === 'EUR') ? 'selected' : ''; ?>>EUR (€)
                                                </option>
                                                <option value="USD" <?php echo ($currency === 'USD') ? 'selected' : ''; ?>>USD ($)
                                                </option>
                                            </select>
                                        </div>
                                        <input type="text" class="form-control montant-mad-input"
                                            data-index="<?php echo $index; ?>" placeholder="Montant en MAD" readonly
                                            value="<?php echo number_format($detail['montant'], 2, '.', ' '); ?> MAD">
                                        <input type="hidden" class="montant-mad-hidden"
                                            name="details[<?php echo $index; ?>][montant_mad]"
                                            data-index="<?php echo $index; ?>" value="<?php echo $detail['montant']; ?>">
                                    </div>

                                    <div class="col-md-2 mb-3 justificatif-container" data-index="<?php echo $index; ?>"
                                        style="<?php echo $isVoiture ? 'display: none;' : ''; ?>">
                                        <label class="form-label">Justificatif <span class="text-danger">*</span></label>
                                        <?php if (!empty($detail['justificatif'])): ?>
                                            <div class="mb-2">
                                                <a href="../../../public/uploads/<?php echo htmlspecialchars($detail['justificatif']); ?>"
                                                    target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-download"></i> Télécharger
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control form-control-sm justificatif-input"
                                            name="detail_justif_<?php echo $index; ?>" accept=".pdf,.jpg,.jpeg,.png"
                                            data-index="<?php echo $index; ?>" <?php echo empty($detail['justificatif']) ? 'required' : ''; ?>>
                                        <input type="hidden" name="details[<?php echo $index; ?>][old_justificatif]"
                                            value="<?php echo htmlspecialchars($detail['justificatif'] ?? ''); ?>">
                                        <small
                                            class="text-muted"><?php echo !empty($detail['justificatif']) ? 'Laisser vide pour conserver le fichier actuel' : 'Fichier obligatoire'; ?></small>
                                    </div>

                                    <!-- Options de transport -->
                                    <div class="col-md-12 mb-3 transport-options" id="transport_options_<?php echo $index; ?>"
                                        style="<?php echo $isTransport ? 'display: block;' : 'display: none;'; ?>">
                                        <label class="form-label">Moyen de transport <span class="text-danger">*</span></label>
                                        <select class="form-select transport-select"
                                            name="details[<?php echo $index; ?>][moyen_transport]"
                                            data-index="<?php echo $index; ?>" <?php echo $isTransport ? 'required' : ''; ?>>
                                            <option value="">Sélectionner...</option>
                                            <option value="voiture" <?php echo ($moyenTransport === 'voiture') ? 'selected' : ''; ?>>Voiture</option>
                                            <option value="train" <?php echo ($moyenTransport === 'train') ? 'selected' : ''; ?>>
                                                Train</option>
                                            <option value="avion" <?php echo ($moyenTransport === 'avion') ? 'selected' : ''; ?>>
                                                Avion</option>
                                            <option value="autre" <?php echo ($moyenTransport === 'autre') ? 'selected' : ''; ?>>
                                                Autre</option>
                                        </select>
                                    </div>

                                    <!-- Section Carte Leaflet -->
                                    <div class="col-md-12 mb-3 carte-section" id="carte_section_<?php echo $index; ?>"
                                        style="<?php echo $isVoiture ? 'display: block;' : 'display: none;'; ?>">
                                        <div class="card border-primary">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Calcul de distance (Voiture)</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="alert alert-info carte-instructions"
                                                    id="carte_instructions_<?php echo $index; ?>">
                                                    <i class="bi bi-info-circle"></i> <strong>Mode :</strong> Sélectionnez le
                                                    <strong>point de départ</strong> (marqueur bleu) en cliquant sur la carte ou
                                                    en recherchant une adresse.
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-md-5">
                                                        <label class="form-label">Point de départ</label>
                                                        <input type="text" class="form-control point-depart"
                                                            id="point_depart_<?php echo $index; ?>"
                                                            name="details[<?php echo $index; ?>][point_depart]"
                                                            placeholder="Rechercher ou cliquer sur la carte"
                                                            data-default-placeholder="Rechercher ou cliquer sur la carte"
                                                            data-index="<?php echo $index; ?>"
                                                            value="<?php echo htmlspecialchars($pointDepart); ?>"
                                                            data-initial-value="<?php echo htmlspecialchars($pointDepart); ?>">
                                                        <!-- Champ caché pour garantir l'envoi même si la section est masquée -->
                                                        <input type="hidden"
                                                            name="details[<?php echo $index; ?>][point_depart_hidden]"
                                                            id="point_depart_hidden_<?php echo $index; ?>"
                                                            value="<?php echo htmlspecialchars($pointDepart); ?>">
                                                        <input type="hidden" name="details[<?php echo $index; ?>][lat_depart]"
                                                            id="lat_depart_<?php echo $index; ?>"
                                                            value="<?php echo $latDepart ? htmlspecialchars($latDepart) : ''; ?>">
                                                        <input type="hidden" name="details[<?php echo $index; ?>][lng_depart]"
                                                            id="lng_depart_<?php echo $index; ?>"
                                                            value="<?php echo $lngDepart ? htmlspecialchars($lngDepart) : ''; ?>">
                                                        <small class="text-muted point-helper"
                                                            id="point_depart_helper_<?php echo $index; ?>"
                                                            data-default-helper="Cliquez sur la carte ou recherchez une adresse"><?php echo $pointDepart ? 'Adresse initiale : ' . htmlspecialchars($pointDepart) : 'Cliquez sur la carte ou recherchez une adresse'; ?></small>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <label class="form-label">Point d'arrivée</label>
                                                        <input type="text" class="form-control point-arrivee"
                                                            id="point_arrivee_<?php echo $index; ?>"
                                                            name="details[<?php echo $index; ?>][point_arrivee]"
                                                            placeholder="Rechercher ou cliquer sur la carte"
                                                            data-default-placeholder="Rechercher ou cliquer sur la carte"
                                                            data-index="<?php echo $index; ?>"
                                                            value="<?php echo htmlspecialchars($pointArrivee); ?>"
                                                            data-initial-value="<?php echo htmlspecialchars($pointArrivee); ?>">
                                                        <!-- Champ caché pour garantir l'envoi même si la section est masquée -->
                                                        <input type="hidden"
                                                            name="details[<?php echo $index; ?>][point_arrivee_hidden]"
                                                            id="point_arrivee_hidden_<?php echo $index; ?>"
                                                            value="<?php echo htmlspecialchars($pointArrivee); ?>">
                                                        <input type="hidden" name="details[<?php echo $index; ?>][lat_arrivee]"
                                                            id="lat_arrivee_<?php echo $index; ?>"
                                                            value="<?php echo $latArrivee ? htmlspecialchars($latArrivee) : ''; ?>">
                                                        <input type="hidden" name="details[<?php echo $index; ?>][lng_arrivee]"
                                                            id="lng_arrivee_<?php echo $index; ?>"
                                                            value="<?php echo $lngArrivee ? htmlspecialchars($lngArrivee) : ''; ?>">
                                                        <small class="text-muted point-helper"
                                                            id="point_arrivee_helper_<?php echo $index; ?>"
                                                            data-default-helper="Cliquez sur la carte ou recherchez une adresse"><?php echo $pointArrivee ? 'Adresse initiale : ' . htmlspecialchars($pointArrivee) : 'Cliquez sur la carte ou recherchez une adresse'; ?></small>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">&nbsp;</label>
                                                        <button type="button"
                                                            class="btn btn-outline-danger w-100 reset-carte-btn"
                                                            data-index="<?php echo $index; ?>">
                                                            <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                                                        </button>
                                                    </div>
                                                </div>
                                                <div id="map_<?php echo $index; ?>"
                                                    style="height: 400px; width: 100%; border-radius: 5px;"></div>
                                                <div class="row mt-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Distance calculée (aller)</label>
                                                        <input type="text" class="form-control distance-calculee"
                                                            id="distance_calculee_<?php echo $index; ?>" readonly
                                                            placeholder="0 km" data-index="<?php echo $index; ?>"
                                                            value="<?php echo $distanceKm ? number_format($distanceKm, 2, '.', ' ') . ' km' : ''; ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Distance aller-retour</label>
                                                        <input type="text" class="form-control distance-ar-calculee"
                                                            id="distance_ar_calculee_<?php echo $index; ?>" readonly
                                                            placeholder="0 km" data-index="<?php echo $index; ?>"
                                                            value="<?php echo $distanceKmAr ? number_format($distanceKmAr, 2, '.', ' ') . ' km' : ''; ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Montant calculé (DH)</label>
                                                        <input type="text" class="form-control montant-calcule"
                                                            id="montant_calcule_<?php echo $index; ?>" readonly
                                                            placeholder="0.00 DH" data-index="<?php echo $index; ?>"
                                                            value="<?php echo $isVoiture ? number_format($detail['montant'], 2, '.', ' ') . ' DH' : ''; ?>">
                                                    </div>
                                                </div>
                                                <p class="text-muted small mt-2 mb-0"
                                                    id="distance_summary_<?php echo $index; ?>">
                                                    <?php if ($isVoiture && ($distanceKm || $distanceKmAr)): ?>
                                                        <!-- Texte distance supprimé à la demande -->
                                                    <?php elseif ($isVoiture): ?>
                                                        Montant actuel :
                                                        <?php echo number_format($detail['montant'], 2, ',', ' '); ?> DH (Utilisez
                                                        la carte pour recalculer)
                                                    <?php endif; ?>
                                                </p>
                                                <input type="hidden" class="distance-km" id="distance_km_<?php echo $index; ?>"
                                                    name="details[<?php echo $index; ?>][distance_km]"
                                                    value="<?php echo htmlspecialchars($distanceKm); ?>"
                                                    data-index="<?php echo $index; ?>">
                                                <input type="hidden" class="distance-km-ar"
                                                    id="distance_km_ar_<?php echo $index; ?>"
                                                    name="details[<?php echo $index; ?>][distance_km_ar]"
                                                    value="<?php echo htmlspecialchars($distanceKmAr); ?>"
                                                    data-index="<?php echo $index; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="details[<?php echo $index; ?>][description]"
                                            rows="2"><?php echo htmlspecialchars($detail['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i> Détails des frais</h5>
                <button type="button" class="btn btn-sm btn-outline-light" id="addDetail">
                    <i class="bi bi-plus"></i> Ajouter un frais
                </button>
            </div>
            <div class="card-body">
                <div class="text-muted text-center py-3" id="noDetails" style="display: none;">
                    Aucun frais ajouté. Cliquez sur "Ajouter un frais" pour commencer.
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="mes_demandes.php?action=voir&id=<?php echo $demande['id']; ?>" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> Annuler
            </a>
            <button type="button" class="btn btn-primary" onclick="submitDemandeForm()">
                <i class="bi bi-check-circle"></i> Enregistrer les modifications
            </button>
        </div>
    </form>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <!-- Geocoding pour la recherche d'adresses -->
    <script src="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.js"></script>
    <script src="../../../public/js/currency_converter.js"></script>

    <script>
        let detailIndex = <?php echo count($details); ?>;
        // Stockage des cartes et marqueurs par index
        const maps = {};
        const routingControls = {};
        const markers = {};
        const currentSelectionModes = {};
        // Tarif par kilomètre (configurable depuis config.php)
        const TARIF_PAR_KM = <?php echo defined('TARIF_PAR_KM') ? TARIF_PAR_KM : 1.2; ?>; // DH par km
        const ALLER_RETOUR_MULTIPLICATEUR = 2; // Aller + retour

        // Initialiser le convertisseur pour les détails existants
        document.addEventListener('DOMContentLoaded', function () {
            <?php
            // Réutiliser les mêmes variables extraites dans la boucle PHP principale
            // On doit recalculer pour chaque détail dans le contexte JavaScript
            foreach ($details as $index => $detail):
                $jsIsTransport = stripos($detail['categorie_nom'], 'transport') !== false;

                // Récupérer les points depuis les colonnes dédiées
                $jsPointDepart = '';
                $jsPointArrivee = '';
                $jsLatDepart = '';
                $jsLngDepart = '';
                $jsLatArrivee = '';
                $jsLngArrivee = '';
                if (isset($detail['point_depart']) && !empty($detail['point_depart'])) {
                    $jsPointDepart = trim($detail['point_depart']);
                }
                if (isset($detail['point_arrivee']) && !empty($detail['point_arrivee'])) {
                    $jsPointArrivee = trim($detail['point_arrivee']);
                }
                // Récupérer les coordonnées depuis la BDD si disponibles
                $jsLatDepart = !empty($detail['lat_depart']) ? floatval($detail['lat_depart']) : '';
                $jsLngDepart = !empty($detail['lng_depart']) ? floatval($detail['lng_depart']) : '';
                $jsLatArrivee = !empty($detail['lat_arrivee']) ? floatval($detail['lat_arrivee']) : '';
                $jsLngArrivee = !empty($detail['lng_arrivee']) ? floatval($detail['lng_arrivee']) : '';

                // Vérifier si c'est une voiture
                $jsHasPoints = !empty($jsPointDepart) || !empty($jsPointArrivee);
                $jsIsVoiture = $jsIsTransport && (
                    stripos($detail['description'] ?? '', 'Distance calculée') !== false ||
                    stripos($detail['description'] ?? '', 'voiture') !== false ||
                    $jsHasPoints
                );

                // Extraire les distances
                $jsDistanceKm = '';
                $jsDistanceKmAr = '';
                if (!empty($detail['description'])) {
                    if (preg_match('/Distance calculée\s*:\s*([\d.]+)\s*km/i', $detail['description'], $matches)) {
                        $jsDistanceKm = $matches[1];
                    }
                    if (preg_match('/aller-retour\s*:\s*([\d.]+)\s*km/i', $detail['description'], $matches)) {
                        $jsDistanceKmAr = $matches[1];
                    }
                }

                // Fallback sur la description si les colonnes sont vides
                if (empty($jsPointDepart) && empty($jsPointArrivee) && $jsIsVoiture && !empty($detail['description'])) {
                    $lines = explode("\n", $detail['description']);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (preg_match('/^Départ\s*:\s*(.+)$/i', $line, $matches)) {
                            $jsPointDepart = trim($matches[1]);
                        }
                        if (preg_match('/^Arrivée\s*:\s*(.+)$/i', $line, $matches)) {
                            $jsPointArrivee = trim($matches[1]);
                        }
                    }
                }
                ?>
                if (window.CurrencyConverterUI) {
                    CurrencyConverterUI.initForDetail(<?php echo $index; ?>);
                }
                initDetailEvents(<?php echo $index; ?>);

                // Initialiser la carte si c'est déjà une voiture
                <?php if ($jsIsVoiture): ?>
                    // S'assurer que la section carte est visible
                    const carteSection_<?php echo $index; ?> = document.getElementById('carte_section_<?php echo $index; ?>');
                    if (carteSection_<?php echo $index; ?>) {
                        carteSection_<?php echo $index; ?>.style.display = 'block';
                    }

                    // S'assurer que les champs de points sont visibles et pré-remplis
                    const pointDepartField_<?php echo $index; ?> = document.getElementById('point_depart_<?php echo $index; ?>');
                    const pointArriveeField_<?php echo $index; ?> = document.getElementById('point_arrivee_<?php echo $index; ?>');
                    if (pointDepartField_<?php echo $index; ?>) {
                        pointDepartField_<?php echo $index; ?>.value = <?php echo json_encode($jsPointDepart); ?>;
                    }
                    if (pointArriveeField_<?php echo $index; ?>) {
                        pointArriveeField_<?php echo $index; ?>.value = <?php echo json_encode($jsPointArrivee); ?>;
                    }

                    // Initialiser la carte avec les points existants après un délai pour s'assurer que la section est visible
                    setTimeout(function () {
                        // Vérifier à nouveau que la section est visible
                        if (carteSection_<?php echo $index; ?>) {
                            carteSection_<?php echo $index; ?>.style.display = 'block';
                        }
                        initMap(<?php echo $index; ?>, {
                            pointDepart: <?php echo json_encode($jsPointDepart); ?>,
                            pointArrivee: <?php echo json_encode($jsPointArrivee); ?>,
                            latDepart: <?php echo json_encode($jsLatDepart); ?>,
                            lngDepart: <?php echo json_encode($jsLngDepart); ?>,
                            latArrivee: <?php echo json_encode($jsLatArrivee); ?>,
                            lngArrivee: <?php echo json_encode($jsLngArrivee); ?>,
                            distanceKm: <?php echo json_encode($jsDistanceKm); ?>,
                            distanceKmAr: <?php echo json_encode($jsDistanceKmAr); ?>
                        });
                    }, 800);
                <?php endif; ?>

            <?php endforeach; ?>

            // Attacher l'event listener au bouton "Ajouter un frais" après le chargement du DOM
            const addDetailBtn = document.getElementById('addDetail');
            if (addDetailBtn) {
                // Supprimer les anciens event listeners si existants
                const newBtn = addDetailBtn.cloneNode(true);
                addDetailBtn.parentNode.replaceChild(newBtn, addDetailBtn);
                // Attacher le nouvel event listener
                document.getElementById('addDetail').addEventListener('click', function () {
                    addNewDetail();
                });
            }
        });

        // Fonctions utilitaires (formatage, etc.)
        function formatNumber(value) {
            const num = Number(value);
            if (Number.isNaN(num)) return '0';
            return parseFloat(num.toFixed(2)).toString();
        }

        function formatKmValue(value) {
            return `${formatNumber(value)} km`;
        }

        function formatMadCurrency(value) {
            return `${formatNumber(value)} DH`;
        }

        function setDistanceSummaryText(index, text = '') {
            const summary = document.getElementById(`distance_summary_${index}`);
            if (summary) summary.textContent = text;
        }

        function updateDistanceSummary(index, distanceKm, roundTripKm, amountMad, options = {}) {
            const estimationLabel = options.estimated ? ' (estimation)' : '';
            const text = `Distance calculée${estimationLabel} : ${formatKmValue(distanceKm)} (aller-retour : ${formatKmValue(roundTripKm)}) – Montant estimé : ${formatMadCurrency(amountMad)}`;
            setDistanceSummaryText(index, text);
        }

        function updateDescriptionWithDistance(index, distanceKm, roundTripKm, amountMad, options = {}) {
            const descriptionInput = document.querySelector(`textarea[name*="[${index}][description]"]`);
            if (!descriptionInput) return;

            // Récupérer les adresses depuis les champs
            const pointDepartField = document.getElementById(`point_depart_${index}`);
            const pointArriveeField = document.getElementById(`point_arrivee_${index}`);
            const pointDepart = pointDepartField ? pointDepartField.value.trim() : '';
            const pointArrivee = pointArriveeField ? pointArriveeField.value.trim() : '';

            const estimationLabel = options.estimated ? '[Estimation] ' : '';
            let infoLine = `${estimationLabel}Distance calculée : ${formatKmValue(distanceKm)} (aller-retour : ${formatKmValue(roundTripKm)}) – Montant estimé : ${formatMadCurrency(amountMad)}`;

            // Ajouter les adresses si disponibles
            if (pointDepart || pointArrivee) {
                infoLine += '\n';
                if (pointDepart) {
                    infoLine += `Départ : ${pointDepart}`;
                }
                if (pointArrivee) {
                    if (pointDepart) infoLine += '\n';
                    infoLine += `Arrivée : ${pointArrivee}`;
                }
            }

            const currentValue = descriptionInput.value.trim();
            const lines = currentValue.split('\n').map(line => line.trim()).filter(line => line);

            const cleanedLines = lines.filter(line =>
                !line.includes('Distance calculée :') &&
                !line.startsWith('[Estimation] Distance calculée :') &&
                !line.startsWith('Départ :') &&
                !line.startsWith('Arrivée :') &&
                !line.includes('Montant estimé :')
            );

            cleanedLines.push(infoLine);
            descriptionInput.value = cleanedLines.join('\n').trim();
        }

        function setPointFieldValue(fieldId, address, lat = null, lng = null) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            field.value = address;
            field.placeholder = address;
            field.dataset.lastAddress = address;
            if (lat !== null) field.dataset.lat = lat;
            if (lng !== null) field.dataset.lng = lng;

            // Extraire l'index depuis le fieldId ou le dataset
            let index = field.dataset.index || '';
            if (!index && fieldId) {
                const match = fieldId.match(/(?:point_depart_|point_arrivee_)(\d+)/);
                if (match) index = match[1];
            }

            // Mettre à jour aussi le champ caché correspondant
            const hiddenFieldId = fieldId.includes('point_depart_')
                ? fieldId.replace('point_depart_', 'point_depart_hidden_')
                : fieldId.replace('point_arrivee_', 'point_arrivee_hidden_');
            const hiddenField = document.getElementById(hiddenFieldId);
            if (hiddenField) {
                hiddenField.value = address;
            }

            // Mettre à jour les champs lat/lng
            const isDepart = fieldId.includes('point_depart_');
            const latFieldId = isDepart ? `lat_depart_${index}` : `lat_arrivee_${index}`;
            const lngFieldId = isDepart ? `lng_depart_${index}` : `lng_arrivee_${index}`;
            const latField = document.getElementById(latFieldId);
            const lngField = document.getElementById(lngFieldId);
            if (latField && lat !== null) latField.value = lat;
            if (lngField && lng !== null) lngField.value = lng;

            const helperId = fieldId.includes('point_depart_')
                ? fieldId.replace('point_depart_', 'point_depart_helper_')
                : fieldId.replace('point_arrivee_', 'point_arrivee_helper_');
            const helper = document.getElementById(helperId);
            if (helper) {
                helper.textContent = `Adresse sélectionnée : ${address}`;
                helper.classList.remove('text-muted');
                helper.classList.add('text-success');
            }
        }

        function resetPointField(field) {
            if (!field) return;
            field.value = '';
            if (field.dataset.defaultPlaceholder) {
                field.placeholder = field.dataset.defaultPlaceholder;
            } else {
                field.placeholder = '';
            }

            // Réinitialiser aussi le champ caché correspondant
            const hiddenFieldId = field.id.includes('point_depart_')
                ? field.id.replace('point_depart_', 'point_depart_hidden_')
                : field.id.replace('point_arrivee_', 'point_arrivee_hidden_');
            const hiddenField = document.getElementById(hiddenFieldId);
            if (hiddenField) {
                hiddenField.value = '';
            }

            const helperId = field.id.includes('point_depart_')
                ? field.id.replace('point_depart_', 'point_depart_helper_')
                : field.id.replace('point_arrivee_', 'point_arrivee_helper_');
            const helper = document.getElementById(helperId);
            if (helper) {
                const defaultText = helper.dataset.defaultHelper || 'Cliquez sur la carte ou recherchez une adresse';
                helper.textContent = defaultText;
                helper.classList.remove('text-success');
                helper.classList.add('text-muted');
            }
            delete field.dataset.lastAddress;
        }

        function applyMadAmountToDetail(index, amountMad) {
            const montantInput = document.querySelector(`input.montant-input[data-index="${index}"]`);
            const montantMadHidden = document.querySelector(`input.montant-mad-hidden[data-index="${index}"]`);
            const currencySelect = document.querySelector(`select.currency-select[data-index="${index}"]`);

            if (currencySelect) currencySelect.value = 'MAD';

            if (montantInput) {
                montantInput.value = amountMad;
                // S'assurer que le champ est toujours présent dans le formulaire même s'il est masqué
                // Les champs avec display:none sont normalement envoyés, mais on s'assure qu'il a une valeur
                if (window.CurrencyConverterUI) {
                    CurrencyConverterUI.triggerConversion(index);
                }
            }

            // Mettre à jour aussi le champ montant_mad caché (important pour la sauvegarde)
            if (montantMadHidden) {
                montantMadHidden.value = amountMad;
            }
        }

        function clearDistanceDisplays(index) {
            const distanceField = document.getElementById(`distance_calculee_${index}`);
            const distanceArField = document.getElementById(`distance_ar_calculee_${index}`);
            const montantField = document.getElementById(`montant_calcule_${index}`);
            const distanceKmField = document.getElementById(`distance_km_${index}`);
            const distanceKmArField = document.getElementById(`distance_km_ar_${index}`);
            const pointDepartField = document.getElementById(`point_depart_${index}`);
            const pointArriveeField = document.getElementById(`point_arrivee_${index}`);
            const montantInput = document.querySelector(`input.montant-input[data-index="${index}"]`);

            if (distanceField) distanceField.value = '';
            if (distanceArField) distanceArField.value = '';
            if (montantField) montantField.value = '';
            if (distanceKmField) distanceKmField.value = '';
            if (distanceKmArField) distanceKmArField.value = '';

            resetPointField(pointDepartField);
            resetPointField(pointArriveeField);

            if (montantInput) {
                montantInput.value = '';
                if (window.CurrencyConverterUI) {
                    CurrencyConverterUI.triggerConversion(index);
                }
            }

            // Nettoyer le texte de résumé de distance
            setDistanceSummaryText(index, '');
        }

        // Initialiser les événements pour un détail de frais
        function initDetailEvents(index) {
            // Détecter le changement de catégorie
            const categorieSelect = document.querySelector(`select[name="details[${index}][categorie_id]"][data-index="${index}"]`);
            if (categorieSelect) {
                categorieSelect.addEventListener('change', function () {
                    handleCategorieChange(index, this);
                });
            }

            // Détecter le changement de moyen de transport
            const transportSelect = document.querySelector(`select.transport-select[data-index="${index}"]`);
            if (transportSelect) {
                transportSelect.addEventListener('change', function () {
                    handleTransportChange(index, this.value);
                });
            }

            // Bouton de réinitialisation de la carte
            const resetBtn = document.querySelector(`button.reset-carte-btn[data-index="${index}"]`);
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    resetCartePoints(index);
                });
            }

            // Focus sur les champs de points pour changer le mode et permettre la modification
            const pointDepart = document.getElementById(`point_depart_${index}`);
            const pointArrivee = document.getElementById(`point_arrivee_${index}`);
            const pointDepartHidden = document.getElementById(`point_depart_hidden_${index}`);
            const pointArriveeHidden = document.getElementById(`point_arrivee_hidden_${index}`);

            // Fonction pour synchroniser le champ caché avec le champ visible
            function syncHiddenField(visibleField, hiddenField) {
                if (visibleField && hiddenField) {
                    hiddenField.value = visibleField.value || '';
                }
            }

            if (pointDepart) {
                pointDepart.addEventListener('focus', function () {
                    updateSelectionMode(index, 'depart');
                });

                // Synchroniser le champ caché à chaque modification
                pointDepart.addEventListener('input', function () {
                    syncHiddenField(pointDepart, pointDepartHidden);
                });

                // Permettre la recherche d'adresse depuis le champ
                pointDepart.addEventListener('blur', function () {
                    // Synchroniser avant le blur
                    syncHiddenField(pointDepart, pointDepartHidden);

                    const address = this.value.trim();
                    if (address && address !== this.dataset.lastAddress) {
                        // Si la carte existe, géocoder l'adresse et mettre à jour le marqueur
                        if (maps[index]) {
                            const geocoder = L.Control.Geocoder.nominatim({
                                serviceUrl: '/V22/V2/public/reverse_proxy.php/'
                            });
                            geocoder.geocode(address, function (results) {
                                if (results && results.length > 0 && maps[index]) {
                                    const result = results[0];
                                    const latlng = result.center;
                                    updatePointOnMap(index, 'depart', latlng, result.name);
                                    // La fonction updatePointOnMap va recalculer la route si nécessaire
                                }
                            });
                        }
                    }
                });
            }

            if (pointArrivee) {
                pointArrivee.addEventListener('focus', function () {
                    updateSelectionMode(index, 'arrivee');
                });

                // Synchroniser le champ caché à chaque modification
                pointArrivee.addEventListener('input', function () {
                    syncHiddenField(pointArrivee, pointArriveeHidden);
                });

                // Permettre la recherche d'adresse depuis le champ
                pointArrivee.addEventListener('blur', function () {
                    // Synchroniser avant le blur
                    syncHiddenField(pointArrivee, pointArriveeHidden);

                    const address = this.value.trim();
                    if (address && address !== this.dataset.lastAddress) {
                        // Si la carte existe, géocoder l'adresse et mettre à jour le marqueur
                        if (maps[index]) {
                            const geocoder = L.Control.Geocoder.nominatim({
                                serviceUrl: '/V22/V2/public/reverse_proxy.php/'
                            });
                            geocoder.geocode(address, function (results) {
                                if (results && results.length > 0 && maps[index]) {
                                    const result = results[0];
                                    const latlng = result.center;
                                    updatePointOnMap(index, 'arrivee', latlng, result.name);
                                    // La fonction updatePointOnMap va recalculer la route si nécessaire
                                }
                            });
                        }
                    }
                });
            }
        }

        function handleTransportChange(index, transportValue) {
            const carteSection = document.getElementById(`carte_section_${index}`);
            const montantContainer = document.querySelector(`div.montant-container[data-index="${index}"]`);
            const justificatifContainer = document.querySelector(`div.justificatif-container[data-index="${index}"]`);
            const montantInput = document.querySelector(`input.montant-input[data-index="${index}"]`);
            const justificatifInput = document.querySelector(`input.justificatif-input[data-index="${index}"]`);
            const descriptionInput = document.querySelector(`textarea[name*="[${index}][description]"]`);

            if (transportValue === 'voiture') {
                // Afficher la carte
                if (carteSection) {
                    carteSection.style.display = 'block';
                    // Initialiser la carte si elle n'existe pas encore
                    // Utiliser un délai pour s'assurer que le conteneur est visible et a une taille
                    setTimeout(function () {
                        if (!maps[index]) {
                            initMap(index);
                        } else {
                            // Si la carte existe déjà, forcer le recalcul de la taille
                            setTimeout(function () {
                                if (maps[index]) {
                                    maps[index].invalidateSize();
                                }
                            }, 100);
                        }
                    }, 200);
                }
                // Masquer les conteneurs montant et justificatif
                if (montantContainer) {
                    montantContainer.style.display = 'none';
                }
                if (justificatifContainer) {
                    justificatifContainer.style.display = 'none';
                }
                // Désactiver la validation du montant (sera rempli automatiquement)
                if (montantInput) {
                    montantInput.required = false;
                }
                // Désactiver la validation du justificatif
                if (justificatifInput) {
                    justificatifInput.required = false;
                }
            } else {
                // Masquer la carte
                if (carteSection) {
                    carteSection.style.display = 'none';
                    cleanupMap(index);
                }
                resetCartePoints(index);
                // Réafficher les conteneurs montant et justificatif
                if (montantContainer) {
                    montantContainer.style.display = 'block';
                }
                if (justificatifContainer) {
                    justificatifContainer.style.display = 'block';
                }
                // Réactiver la validation du montant
                if (montantInput) {
                    montantInput.required = true;
                    montantInput.value = '';
                }
                // Réactiver la validation du justificatif SEULEMENT si aucun fichier n'est déjà présent
                if (justificatifInput) {
                    // Vérifier si un lien de téléchargement existe dans le conteneur (indique un fichier existant)
                    const hasExistingFile = justificatifContainer.querySelector('a.btn-outline-primary') !== null;
                    justificatifInput.required = !hasExistingFile;
                }

                // Nettoyer la description pour supprimer les informations de distance
                if (descriptionInput) {
                    const currentValue = descriptionInput.value.trim();
                    const lines = currentValue.split('\n').map(line => line.trim()).filter(line => line);
                    const cleanedLines = lines.filter(line =>
                        !line.includes('Distance calculée :') &&
                        !line.startsWith('[Estimation] Distance calculée :') &&
                        !line.includes('Montant estimé :')
                    );
                    descriptionInput.value = cleanedLines.join('\n').trim();
                }

                // Nettoyer le texte de résumé de distance
                setDistanceSummaryText(index, '');

                // Nettoyer les champs de distance
                clearDistanceDisplays(index);

                if (window.CurrencyConverterUI) {
                    CurrencyConverterUI.triggerConversion(index);
                }
            }
        }

        function handleCategorieChange(index, selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const categorieNom = selectedOption ? selectedOption.getAttribute('data-nom') : '';
            const transportOptions = document.getElementById(`transport_options_${index}`);
            const carteSection = document.getElementById(`carte_section_${index}`);
            const montantContainer = document.querySelector(`div.montant-container[data-index="${index}"]`);
            const justificatifContainer = document.querySelector(`div.justificatif-container[data-index="${index}"]`);
            const montantInput = document.querySelector(`input.montant-input[data-index="${index}"]`);
            const justificatifInput = document.querySelector(`input.justificatif-input[data-index="${index}"]`);

            // Vérifier si c'est Transport
            if (categorieNom && categorieNom.includes('transport')) {
                // Afficher les options de transport
                if (transportOptions) {
                    transportOptions.style.display = 'block';
                    // Rendre le champ moyen de transport requis
                    const transportSelect = transportOptions.querySelector('select');
                    if (transportSelect) {
                        transportSelect.required = true;
                    }
                }
            } else {
                // Masquer les options de transport et la carte
                if (transportOptions) {
                    transportOptions.style.display = 'none';
                    const transportSelect = transportOptions.querySelector('select');
                    if (transportSelect) {
                        transportSelect.required = false;
                        transportSelect.value = '';
                    }
                }
                if (carteSection) {
                    carteSection.style.display = 'none';
                    cleanupMap(index);
                }
                resetCartePoints(index);
                // Réafficher les conteneurs montant et justificatif
                if (montantContainer) {
                    montantContainer.style.display = 'block';
                }
                if (justificatifContainer) {
                    justificatifContainer.style.display = 'block';
                }
                // Réactiver la validation du montant
                if (montantInput) {
                    montantInput.required = true;
                }
                // Réactiver la validation du justificatif SEULEMENT si aucun fichier n'est déjà présent
                if (justificatifInput) {
                    // Vérifier si un lien de téléchargement existe dans le conteneur (indique un fichier existant)
                    const hasExistingFile = justificatifContainer.querySelector('a.btn-outline-primary') !== null;
                    justificatifInput.required = !hasExistingFile;
                }

                // Nettoyer la description pour supprimer les informations de distance
                const descriptionInput = document.querySelector(`textarea[name*="[${index}][description]"]`);
                if (descriptionInput) {
                    const currentValue = descriptionInput.value.trim();
                    const lines = currentValue.split('\n').map(line => line.trim()).filter(line => line);
                    const cleanedLines = lines.filter(line =>
                        !line.includes('Distance calculée :') &&
                        !line.startsWith('[Estimation] Distance calculée :') &&
                        !line.startsWith('Départ :') &&
                        !line.startsWith('Arrivée :') &&
                        !line.includes('Montant estimé :')
                    );
                    descriptionInput.value = cleanedLines.join('\n').trim();
                }

                // Nettoyer le texte de résumé de distance
                setDistanceSummaryText(index, '');

                // Nettoyer les champs de distance
                clearDistanceDisplays(index);
            }
        }

        // Géocodage inverse (coordonnées -> adresse)
        function reverseGeocode(index, latlng, fieldId, callback, attempt = 1) {
            const lat = latlng.lat;
            const lon = latlng.lng;

            // Vérifier que lat et lon sont valides
            if (!lat || !lon || isNaN(lat) || isNaN(lon)) {
                console.error('Coordonnées invalides:', lat, lon);
                const offlineAddress = `Lieu sélectionné (offline) - ${lat.toFixed(6)}, ${lon.toFixed(6)}`;
                if (callback) callback(offlineAddress);
                else if (fieldId) setPointFieldValue(fieldId, offlineAddress, lat, lon);
                return;
            }

            // Utiliser AbortController pour le timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 secondes max

            // Appel à l'API via le proxy local pour éviter CORS et 403
            fetch(`/V22/V2/public/reverse_proxy.php?lat=${lat}&lon=${lon}`, {
                signal: controller.signal
            })
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    let readableAddress = '';

                    if (data && data.address) {
                        // Construire une adresse structurée lisible
                        const addr = data.address;
                        let addressParts = [];

                        // Ajouter la rue si disponible
                        if (addr.road || addr.street) {
                            addressParts.push(addr.road || addr.street);
                        }

                        // Ajouter le quartier/village
                        if (addr.suburb || addr.village || addr.neighbourhood) {
                            addressParts.push(addr.suburb || addr.village || addr.neighbourhood);
                        }

                        // Ajouter la ville
                        if (addr.city || addr.town || addr.municipality) {
                            addressParts.push(addr.city || addr.town || addr.municipality);
                        }

                        // Construire l'adresse lisible
                        if (addressParts.length > 0) {
                            readableAddress = addressParts.join(', ');
                        } else if (data.display_name) {
                            // Prendre les 3 premières parties de display_name pour un nom lisible
                            const displayParts = data.display_name.split(',').slice(0, 3).map(s => s.trim());
                            readableAddress = displayParts.join(', ');
                        }
                    } else if (data && data.display_name) {
                        // Fallback sur display_name si address n'est pas disponible
                        const displayParts = data.display_name.split(',').slice(0, 3).map(s => s.trim());
                        readableAddress = displayParts.join(', ');
                    }

                    // Si aucune adresse n'a été trouvée, utiliser un format avec coordonnées
                    if (!readableAddress) {
                        readableAddress = `Lieu sélectionné (${lat.toFixed(6)}, ${lon.toFixed(6)})`;
                    }

                    // Mettre à jour les champs avec l'adresse lisible et les coordonnées
                    if (callback) {
                        callback(readableAddress);
                    } else if (fieldId) {
                        setPointFieldValue(fieldId, readableAddress, lat, lon);
                    }
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    console.error('Erreur géocodage (tentative ' + attempt + '):', error);

                    // Si c'est une erreur d'annulation (timeout), on utilise directement le fallback
                    if (error.name === 'AbortError') {
                        const offlineAddress = `Lieu sélectionné (${lat.toFixed(6)}, ${lon.toFixed(6)})`;
                        if (callback) callback(offlineAddress);
                        else if (fieldId) setPointFieldValue(fieldId, offlineAddress, lat, lon);
                        return;
                    }

                    // Si on a une erreur réseau mais qu'on semble être en ligne, on réessaie (max 3 fois)
                    if (attempt < 3 && navigator.onLine) {
                        console.log('Nouvelle tentative de géocodage dans 1s...');
                        setTimeout(() => reverseGeocode(index, latlng, fieldId, callback, attempt + 1), 1000);
                        return;
                    }

                    // En cas d'erreur réseau (pas d'Internet), afficher "Lieu sélectionné (offline)" + coordonnées
                    const offlineAddress = `Lieu sélectionné (${lat.toFixed(6)}, ${lon.toFixed(6)})`;
                    if (callback) {
                        callback(offlineAddress);
                    } else if (fieldId) {
                        setPointFieldValue(fieldId, offlineAddress, lat, lon);
                    }
                });
        }

        // Initialiser la carte Leaflet pour un index spécifique
        function initMap(index, options = {}) {
            options = options || {};
            // Créer la carte centrée sur la France
            const mapElement = document.getElementById(`map_${index}`);
            if (!mapElement) {
                console.error(`Élément map_${index} non trouvé`);
                return;
            }

            // Vérifier que l'élément est visible et a une taille
            if (mapElement.offsetParent === null) {
                console.warn(`L'élément map_${index} n'est pas visible, attente...`);
                // Réessayer après un délai
                setTimeout(function () {
                    initMap(index);
                }, 300);
                return;
            }

            // Vérifier que l'élément a une hauteur
            if (mapElement.offsetHeight === 0) {
                console.warn(`L'élément map_${index} n'a pas de hauteur, attente...`);
                setTimeout(function () {
                    initMap(index);
                }, 300);
                return;
            }

            try {
                // Nettoyer la carte si elle existe déjà
                if (maps[index]) {
                    maps[index].remove();
                }

                maps[index] = L.map(`map_${index}`, {
                    zoomControl: true,
                    attributionControl: true
                }).setView([31.7917, -7.0926], 6); // Maroc

                markers[index] = { depart: null, arrivee: null };

                // Déterminer le mode initial selon les points déjà chargés
                const pointDepartField = document.getElementById(`point_depart_${index}`);
                const pointArriveeField = document.getElementById(`point_arrivee_${index}`);
                const hasDepart = pointDepartField && pointDepartField.value.trim();
                const hasArrivee = pointArriveeField && pointArriveeField.value.trim();

                // Si on a déjà les deux points, permettre la modification des deux
                // Sinon, commencer par le point manquant
                if (hasDepart && hasArrivee) {
                    currentSelectionModes[index] = 'both'; // Mode permettant de modifier les deux
                } else if (hasDepart) {
                    currentSelectionModes[index] = 'arrivee'; // On a le départ, on attend l'arrivée
                } else {
                    currentSelectionModes[index] = 'depart'; // On commence par le départ
                }

                const currentMap = maps[index];

                // Ajouter la couche de tuiles OpenStreetMap
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19,
                    tileSize: 256,
                    zoomOffset: 0
                }).addTo(currentMap);

                // Forcer le recalcul de la taille après un court délai
                setTimeout(function () {
                    if (currentMap) {
                        currentMap.invalidateSize();
                    }
                }, 200);

                // Ajouter le contrôleur de géocodage pour la recherche d'adresses
                const geocoder = L.Control.Geocoder.nominatim({
                    serviceUrl: '/V22/V2/public/reverse_proxy.php/'
                });
                L.Control.geocoder({
                    geocoder: geocoder,
                    defaultMarkGeocode: false,
                    position: 'topleft',
                    placeholder: 'Rechercher une adresse...'
                })
                    .on('markgeocode', function (e) {
                        const latlng = e.geocode.center;
                        const address = e.geocode.name;
                        const mode = currentSelectionModes[index];
                        // Déterminer quel point modifier selon le mode
                        let targetMode = mode;
                        if (mode === 'both') {
                            // En mode 'both', on modifie le point le plus proche
                            if (markers[index].depart && markers[index].arrivee) {
                                const distToDepart = latlng.distanceTo(markers[index].depart.getLatLng());
                                const distToArrivee = latlng.distanceTo(markers[index].arrivee.getLatLng());
                                targetMode = distToDepart < distToArrivee ? 'depart' : 'arrivee';
                            } else if (markers[index].depart) {
                                targetMode = 'arrivee';
                            } else if (markers[index].arrivee) {
                                targetMode = 'depart';
                            } else {
                                targetMode = 'depart';
                            }
                        }
                        handleMapClick(index, latlng, address, targetMode);
                        currentMap.setView(latlng, 16);
                    })
                    .addTo(currentMap);

                // Gestion du clic sur la carte : permet de modifier n'importe quel point
                currentMap.on('click', function (e) {
                    const mode = currentSelectionModes[index];

                    // Si on est en mode 'both', on détermine quel point modifier selon la proximité
                    let targetMode = mode;
                    if (mode === 'both') {
                        // Vérifier la proximité avec les marqueurs existants
                        if (markers[index].depart && markers[index].arrivee) {
                            const distToDepart = e.latlng.distanceTo(markers[index].depart.getLatLng());
                            const distToArrivee = e.latlng.distanceTo(markers[index].arrivee.getLatLng());
                            // Si on clique très près d'un marqueur, on le modifie
                            if (distToDepart < 100) { // 100 mètres
                                targetMode = 'depart';
                            } else if (distToArrivee < 100) {
                                targetMode = 'arrivee';
                            } else {
                                // Sinon, on modifie le point le plus proche
                                targetMode = distToDepart < distToArrivee ? 'depart' : 'arrivee';
                            }
                        } else if (markers[index].depart) {
                            targetMode = 'arrivee';
                        } else if (markers[index].arrivee) {
                            targetMode = 'depart';
                        } else {
                            targetMode = 'depart';
                        }
                    }

                    // Reverse geocoding avec Nominatim pour obtenir un nom de lieu lisible
                    reverseGeocode(index, e.latlng, null, function (address) {
                        handleMapClick(index, e.latlng, address, targetMode);
                    });
                });

                // Si on est en mode modification et qu'on a des points initiaux, essayer de les charger
                setTimeout(function () {
                    // Récupérer les valeurs depuis les champs si disponibles
                    const pointDepartField = document.getElementById(`point_depart_${index}`);
                    const pointArriveeField = document.getElementById(`point_arrivee_${index}`);
                    const latDepartField = document.getElementById(`lat_depart_${index}`);
                    const lngDepartField = document.getElementById(`lng_depart_${index}`);
                    const latArriveeField = document.getElementById(`lat_arrivee_${index}`);
                    const lngArriveeField = document.getElementById(`lng_arrivee_${index}`);

                    const pointDepartValue = (pointDepartField && pointDepartField.value.trim()) || options.pointDepart || '';
                    const pointArriveeValue = (pointArriveeField && pointArriveeField.value.trim()) || options.pointArrivee || '';
                    const latDepartValue = (latDepartField && latDepartField.value.trim()) || options.latDepart || '';
                    const lngDepartValue = (lngDepartField && lngDepartField.value.trim()) || options.lngDepart || '';
                    const latArriveeValue = (latArriveeField && latArriveeField.value.trim()) || options.latArrivee || '';
                    const lngArriveeValue = (lngArriveeField && lngArriveeField.value.trim()) || options.lngArrivee || '';

                    if (pointDepartValue || pointArriveeValue) {
                        loadInitialPoints(index, {
                            pointDepart: pointDepartValue,
                            pointArrivee: pointArriveeValue,
                            latDepart: latDepartValue,
                            lngDepart: lngDepartValue,
                            latArrivee: latArriveeValue,
                            lngArrivee: lngArriveeValue,
                            distanceKm: options.distanceKm || '',
                            distanceKmAr: options.distanceKmAr || ''
                        });
                    }
                }, 300);

            } catch (e) {
                console.error("Erreur lors de l'initialisation de la carte:", e);
            }
        }

        // Charger les points initiaux depuis les données existantes
        function loadInitialPoints(index, options) {
            if (!maps[index] || !options) return;

            const geocoder = L.Control.Geocoder.nominatim({
                serviceUrl: '/V22/V2/public/reverse_proxy.php/'
            });
            let departLoaded = false;
            let arriveeLoaded = false;

            // Fonction pour vérifier si on peut calculer l'itinéraire
            function checkAndCalculateRoute() {
                if (departLoaded && arriveeLoaded && markers[index].depart && markers[index].arrivee) {
                    // Ajuster la vue pour voir les deux marqueurs
                    const group = new L.featureGroup([markers[index].depart, markers[index].arrivee]);
                    maps[index].fitBounds(group.getBounds().pad(0.1));
                    // Calculer l'itinéraire
                    calculateRoute(index);
                }
            }

            // Charger le point de départ
            if (options.latDepart && options.lngDepart) {
                // Si on a les coordonnées, on les utilise directement (OFFLINE FIRST)
                const latlng = L.latLng(options.latDepart, options.lngDepart);
                let addressName = options.pointDepart || 'Point de départ';

                updatePointOnMap(index, 'depart', latlng, addressName);
                departLoaded = true;
                checkAndCalculateRoute();

                // Correction "Healeur": Si l'adresse sauvegardée indique "offline" mais qu'on a maintenant une connexion
                if (addressName.toLowerCase().includes('offline') || addressName.match(/^Lieu sélectionné/)) {
                    reverseGeocode(index, latlng, null, function (newAddress) {
                        if (newAddress && !newAddress.toLowerCase().includes('offline') && newAddress !== addressName) {
                            console.log('Adresse corrigée:', newAddress);
                            updatePointOnMap(index, 'depart', latlng, newAddress);
                        }
                    });
                }
            } else if (options.pointDepart && options.pointDepart.trim()) {
                // Sinon, on essaie de géocoder l'adresse
                geocoder.geocode(options.pointDepart, function (results) {
                    if (results && results.length > 0) {
                        const result = results[0];
                        const latlng = result.center;
                        const address = result.name || options.pointDepart;
                        updatePointOnMap(index, 'depart', latlng, address);
                        departLoaded = true;
                        checkAndCalculateRoute();
                    } else {
                        // Fallback
                        const pointDepartField = document.getElementById(`point_depart_${index}`);
                        if (pointDepartField) {
                            pointDepartField.value = options.pointDepart;
                            pointDepartField.dataset.lastAddress = options.pointDepart;
                        }
                        departLoaded = true;
                        checkAndCalculateRoute();
                    }
                });
            } else {
                departLoaded = true;
            }

            // Charger le point d'arrivée
            if (options.latArrivee && options.lngArrivee) {
                // Si on a les coordonnées, on les utilise directement (OFFLINE FIRST)
                const latlng = L.latLng(options.latArrivee, options.lngArrivee);
                let addressName = options.pointArrivee || 'Point d\'arrivée';

                updatePointOnMap(index, 'arrivee', latlng, addressName);
                arriveeLoaded = true;
                checkAndCalculateRoute();

                // Correction "Healeur": Si l'adresse sauvegardée indique "offline" mais qu'on a maintenant une connexion
                if (addressName.toLowerCase().includes('offline') || addressName.match(/^Lieu sélectionné/)) {
                    reverseGeocode(index, latlng, null, function (newAddress) {
                        if (newAddress && !newAddress.toLowerCase().includes('offline') && newAddress !== addressName) {
                            console.log('Adresse arrivée corrigée:', newAddress);
                            updatePointOnMap(index, 'arrivee', latlng, newAddress);
                        }
                    });
                }
            } else if (options.pointArrivee && options.pointArrivee.trim()) {
                // Sinon, on essaie de géocoder l'adresse
                geocoder.geocode(options.pointArrivee, function (results) {
                    if (results && results.length > 0) {
                        const result = results[0];
                        const latlng = result.center;
                        const addressName = result.name || options.pointArrivee;
                        updatePointOnMap(index, 'arrivee', latlng, addressName);
                        arriveeLoaded = true;
                        checkAndCalculateRoute();
                    } else {
                        // Fallback
                        const pointArriveeField = document.getElementById(`point_arrivee_${index}`);
                        if (pointArriveeField) {
                            pointArriveeField.value = options.pointArrivee;
                            pointArriveeField.dataset.lastAddress = options.pointArrivee;
                        }
                        arriveeLoaded = true;
                        checkAndCalculateRoute();
                    }
                });
            } else {
                arriveeLoaded = true;
            }

            // Si on a les distances mais pas les adresses, pré-remplir au moins les champs de distance
            if ((options.distanceKm || options.distanceKmAr) && !options.pointDepart && !options.pointArrivee) {
                const distanceField = document.getElementById(`distance_calculee_${index}`);
                const distanceArField = document.getElementById(`distance_ar_calculee_${index}`);
                const distanceKmField = document.getElementById(`distance_km_${index}`);
                const distanceKmArField = document.getElementById(`distance_km_ar_${index}`);

                if (options.distanceKm && distanceField) {
                    distanceField.value = formatKmValue(options.distanceKm);
                }
                if (options.distanceKmAr && distanceArField) {
                    distanceArField.value = formatKmValue(options.distanceKmAr);
                }
                if (options.distanceKm && distanceKmField) {
                    distanceKmField.value = options.distanceKm;
                }
                if (options.distanceKmAr && distanceKmArField) {
                    distanceKmArField.value = options.distanceKmAr;
                }
            }
        }

        // Fonction utilitaire pour mettre à jour un point sur la carte
        function updatePointOnMap(index, pointType, latlng, address) {
            if (!maps[index]) return;

            const iconColor = pointType === 'depart' ? 'blue' : 'red';
            const iconUrl = pointType === 'depart'
                ? 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png'
                : 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png';
            const popupText = pointType === 'depart' ? 'Point de départ (glissez pour déplacer)' : 'Point d\'arrivée (glissez pour déplacer)';
            const fieldId = pointType === 'depart' ? `point_depart_${index}` : `point_arrivee_${index}`;

            // Supprimer l'ancien marqueur s'il existe
            if (markers[index][pointType]) {
                maps[index].removeLayer(markers[index][pointType]);
            }

            // Créer le nouveau marqueur avec draggable activé
            markers[index][pointType] = L.marker(latlng, {
                draggable: true,
                icon: L.icon({
                    iconUrl: iconUrl,
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                })
            }).addTo(maps[index]).bindPopup(popupText);

            // Gérer le déplacement du marqueur (dragend)
            markers[index][pointType].on('dragend', function (e) {
                const newLatLng = e.target.getLatLng();
                // Reverse geocoding pour obtenir un nom de lieu lisible (jamais de coordonnées brutes)
                reverseGeocode(index, newLatLng, fieldId, function (newAddress) {
                    // Mettre à jour le champ avec la nouvelle adresse et les coordonnées (côté JS uniquement)
                    setPointFieldValue(fieldId, newAddress, newLatLng.lat, newLatLng.lng);
                    // Recalculer la route si on a les deux points
                    if (markers[index].depart && markers[index].arrivee) {
                        calculateRoute(index);
                    }
                });
            });

            // Mettre à jour le champ avec l'adresse et les coordonnées
            setPointFieldValue(fieldId, address, latlng.lat, latlng.lng);

            // Mettre à jour le mode de sélection
            if (markers[index].depart && markers[index].arrivee) {
                currentSelectionModes[index] = 'both';
                updateSelectionMode(index, 'both');
            } else if (pointType === 'depart') {
                currentSelectionModes[index] = 'arrivee';
                updateSelectionMode(index, 'arrivee');
            } else {
                currentSelectionModes[index] = 'depart';
                updateSelectionMode(index, 'depart');
            }

            // Calculer l'itinéraire si on a les deux points
            if (markers[index].depart && markers[index].arrivee) {
                // Ajuster la vue pour voir les deux marqueurs
                const group = new L.featureGroup([markers[index].depart, markers[index].arrivee]);
                maps[index].fitBounds(group.getBounds().pad(0.1));
                calculateRoute(index);
            } else {
                // Centrer sur le nouveau marqueur
                maps[index].setView(latlng, 15);
            }
        }

        function handleMapClick(index, latlng, address, targetMode = null) {
            const mode = targetMode || currentSelectionModes[index];

            // Si mode 'both' et pas de targetMode spécifique, déterminer quel point modifier
            if (mode === 'both' && !targetMode) {
                if (markers[index].depart && markers[index].arrivee) {
                    // Si on a les deux points, modifier le plus proche
                    const distToDepart = latlng.distanceTo(markers[index].depart.getLatLng());
                    const distToArrivee = latlng.distanceTo(markers[index].arrivee.getLatLng());
                    const closestType = distToDepart < distToArrivee ? 'depart' : 'arrivee';
                    updatePointOnMap(index, closestType, latlng, address);
                } else if (markers[index].depart) {
                    // Si on a seulement le départ, modifier l'arrivée
                    updatePointOnMap(index, 'arrivee', latlng, address);
                } else if (markers[index].arrivee) {
                    // Si on a seulement l'arrivée, modifier le départ
                    updatePointOnMap(index, 'depart', latlng, address);
                } else {
                    // Si on n'a aucun point, commencer par le départ
                    updatePointOnMap(index, 'depart', latlng, address);
                }
            } else if (mode === 'depart') {
                updatePointOnMap(index, 'depart', latlng, address);
            } else if (mode === 'arrivee') {
                updatePointOnMap(index, 'arrivee', latlng, address);
            }
        }

        function updateSelectionMode(index, mode) {
            currentSelectionModes[index] = mode;
            const instructions = document.getElementById(`carte_instructions_${index}`);
            if (instructions) {
                if (mode === 'depart') {
                    instructions.innerHTML = '<i class="bi bi-info-circle"></i> <strong>Mode :</strong> Sélectionnez le <strong>point de départ</strong> (marqueur bleu) en cliquant sur la carte ou en recherchant une adresse.';
                    instructions.className = 'alert alert-info carte-instructions';
                } else if (mode === 'arrivee') {
                    instructions.innerHTML = '<i class="bi bi-info-circle"></i> <strong>Mode :</strong> Sélectionnez le <strong>point d\'arrivée</strong> (marqueur rouge) en cliquant sur la carte ou en recherchant une adresse.';
                    instructions.className = 'alert alert-warning carte-instructions';
                } else if (mode === 'both') {
                    instructions.innerHTML = '<i class="bi bi-info-circle"></i> <strong>Mode :</strong> Vous pouvez modifier <strong>n\'importe quel point</strong> en cliquant sur la carte, en recherchant une adresse, ou en modifiant les champs. Cliquez sur un champ pour modifier le point correspondant.';
                    instructions.className = 'alert alert-success carte-instructions';
                }
            }
        }

        function calculateRoute(index) {
            if (!markers[index].depart || !markers[index].arrivee) return;

            // Supprimer l'ancien contrôle de routing s'il existe
            if (routingControls[index]) {
                maps[index].removeControl(routingControls[index]);
                routingControls[index] = null;
            }

            // Supprimer les anciennes polylines d'itinéraire
            maps[index].eachLayer(function (layer) {
                if (layer instanceof L.Polyline && layer.options && layer.options.isRoute) {
                    maps[index].removeLayer(layer);
                }
            });

            const waypoints = [
                markers[index].depart.getLatLng(),
                markers[index].arrivee.getLatLng()
            ];

            // Afficher un message de chargement
            const distanceField = document.getElementById(`distance_calculee_${index}`);
            const distanceArField = document.getElementById(`distance_ar_calculee_${index}`);
            const montantField = document.getElementById(`montant_calcule_${index}`);
            if (distanceField) distanceField.value = 'Calcul en cours...';
            if (distanceArField) distanceArField.value = 'Calcul en cours...';
            if (montantField) montantField.value = 'Calcul en cours...';

            routingControls[index] = L.Routing.control({
                waypoints: waypoints,
                routeWhileDragging: false,
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: true,
                show: false, // On cache le panneau mais on affiche la ligne
                router: L.Routing.osrmv1({
                    serviceUrl: '/V22/V2/public/osrm_proxy.php/route/v1',
                    profile: 'driving',
                    timeout: 10000
                }),
                lineOptions: {
                    styles: [{ color: '#3388ff', weight: 5, opacity: 0.7 }]
                },
                createMarker: function () { return null; } // On utilise nos propres marqueurs
            }).on('routesfound', function (e) {
                const routes = e.routes;
                if (routes && routes.length > 0) {
                    const route = routes[0];
                    const summary = route.summary;

                    // Distance en km
                    const distanceKm = parseFloat((summary.totalDistance / 1000).toFixed(2));
                    const distanceKmAr = parseFloat((distanceKm * 2).toFixed(2));

                    // Montant = distance aller-retour (km) × tarif par km (DH/km)
                    const montantMad = parseFloat((distanceKmAr * TARIF_PAR_KM).toFixed(2));

                    // Mise à jour des champs
                    if (distanceField) distanceField.value = formatKmValue(distanceKm);
                    if (distanceArField) distanceArField.value = formatKmValue(distanceKmAr);
                    if (montantField) montantField.value = formatMadCurrency(montantMad);

                    const distanceKmHidden = document.getElementById(`distance_km_${index}`);
                    const distanceKmArHidden = document.getElementById(`distance_km_ar_${index}`);
                    if (distanceKmHidden) distanceKmHidden.value = distanceKm;
                    if (distanceKmArHidden) distanceKmArHidden.value = distanceKmAr;

                    // Le routing control dessine automatiquement la route, pas besoin de le faire manuellement

                    applyMadAmountToDetail(index, montantMad);
                    // Mettre à jour la description avec les distances ET les adresses
                    updateDescriptionWithDistance(index, distanceKm, distanceKmAr, montantMad);
                }
            }).on('routingerror', function (e) {
                console.error('Erreur Routing OSRM:', e);
                // console.warn('Passage au calcul à vol d\'oiseau (fallback).');
                // Utiliser le calcul de distance de secours
                calculateDistanceFallback(index);
            }).addTo(maps[index]);
        }

        // Calcul de distance de secours (à vol d'oiseau avec coefficient)
        function calculateDistanceFallback(index) {
            if (!markers[index].depart || !markers[index].arrivee) return;

            const startLatLng = markers[index].depart.getLatLng();
            const endLatLng = markers[index].arrivee.getLatLng();

            // Calculer la distance à vol d'oiseau en km (formule de Haversine)
            const R = 6371; // Rayon de la Terre en km
            const dLat = (endLatLng.lat - startLatLng.lat) * Math.PI / 180;
            const dLon = (endLatLng.lng - startLatLng.lng) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(startLatLng.lat * Math.PI / 180) * Math.cos(endLatLng.lat * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            const distanceVolOiseau = R * c;

            // Appliquer un coefficient pour estimer la distance routière (1.3 = +30%)
            const distanceKm = parseFloat((distanceVolOiseau * 1.3).toFixed(2));
            const distanceKmAr = parseFloat((distanceKm * 2).toFixed(2));
            // Montant = distance aller-retour (km) × tarif par km (DH/km)
            const montantMad = parseFloat((distanceKmAr * TARIF_PAR_KM).toFixed(2));

            // Dessiner une ligne droite sur la carte (estimation) - SUPPRIMÉ
            /*
            const routeLine = L.polyline([startLatLng, endLatLng], {
                color: '#ff7800',
                weight: 3,
                opacity: 0.7,
                dashArray: '10, 10',
                isRoute: true
            }).addTo(maps[index]);
            */

            // Mise à jour des champs
            const distanceField = document.getElementById(`distance_calculee_${index}`);
            const distanceArField = document.getElementById(`distance_ar_calculee_${index}`);
            const montantField = document.getElementById(`montant_calcule_${index}`);
            if (distanceField) distanceField.value = formatKmValue(distanceKm) + ' (estimation)';
            if (distanceArField) distanceArField.value = formatKmValue(distanceKmAr) + ' (estimation)';
            if (montantField) montantField.value = formatMadCurrency(montantMad);

            const distanceKmHidden = document.getElementById(`distance_km_${index}`);
            const distanceKmArHidden = document.getElementById(`distance_km_ar_${index}`);
            if (distanceKmHidden) distanceKmHidden.value = distanceKm;
            if (distanceKmArHidden) distanceKmArHidden.value = distanceKmAr;

            applyMadAmountToDetail(index, montantMad);
            updateDescriptionWithDistance(index, distanceKm, distanceKmAr, montantMad, { estimated: true });
        }

        function resetCartePoints(index) {
            if (markers[index]) {
                if (markers[index].depart && maps[index]) {
                    maps[index].removeLayer(markers[index].depart);
                }
                if (markers[index].arrivee && maps[index]) {
                    maps[index].removeLayer(markers[index].arrivee);
                }
                markers[index].depart = null;
                markers[index].arrivee = null;
            }
            if (routingControls[index] && maps[index]) {
                maps[index].removeControl(routingControls[index]);
                routingControls[index] = null;
            }

            // Réinitialiser le mode de sélection
            currentSelectionModes[index] = 'depart';
            updateSelectionMode(index, 'depart');
            clearDistanceDisplays(index);
        }

        function cleanupMap(index) {
            if (maps[index]) {
                maps[index].remove();
                delete maps[index];
                delete markers[index];
                delete routingControls[index];
                delete currentSelectionModes[index];
            }
        }

        function addNewDetail() {
            const container = document.getElementById('detailsContainer');
            if (!container) {
                console.error('Container detailsContainer non trouvé');
                return;
            }

            const detailHtml = `
            <div class="card mb-3 detail-item" data-index="${detailIndex}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Frais #${detailIndex + 1}</h6>
                        <button type="button" class="btn btn-sm btn-danger removeDetail">
                            <i class="bi bi-trash"></i> Supprimer
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type de dépense <span class="text-danger">*</span></label>
                            <select class="form-select categorie-select" name="details[${detailIndex}][categorie_id]" data-index="${detailIndex}" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" data-nom="<?php echo htmlspecialchars(strtolower($cat['nom'])); ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="details[${detailIndex}][date_depense]" required>
                        </div>
                        <div class="col-md-3 mb-3 montant-container" data-index="${detailIndex}">
                            <label class="form-label">Montant & devise <span class="text-danger">*</span></label>
                            <div class="input-group mb-2">
                                <input type="number" class="form-control montant-input" name="details[${detailIndex}][montant]" step="0.01" min="0" data-index="${detailIndex}" placeholder="0.00" required>
                                <select class="form-select currency-select" name="details[${detailIndex}][currency]" data-index="${detailIndex}">
                                    <option value="EUR" selected>EUR (€)</option>
                                    <option value="USD">USD ($)</option>
                                    <option value="MAD">MAD (DH)</option>
                                </select>
                            </div>
                            <input type="text" class="form-control montant-mad-input" data-index="${detailIndex}" placeholder="Montant en MAD" readonly>
                            <input type="hidden" class="montant-mad-hidden" name="details[${detailIndex}][montant_mad]" data-index="${detailIndex}">
                            <small class="text-muted d-block mt-1">Conversion automatique en dirhams (MAD)</small>
                        </div>
                        <div class="col-md-2 mb-3 justificatif-container" data-index="${detailIndex}">
                            <label class="form-label">Justificatif <span class="text-danger">*</span></label>
                            <input type="file" class="form-control form-control-sm justificatif-input" name="detail_justif_${detailIndex}" accept=".pdf,.jpg,.jpeg,.png" data-index="${detailIndex}" required>
                            <input type="hidden" name="details[${detailIndex}][old_justificatif]" value="">
                        </div>
                        
                        <!-- Options de transport -->
                        <div class="col-md-12 mb-3 transport-options" id="transport_options_${detailIndex}" style="display: none;">
                            <label class="form-label">Moyen de transport <span class="text-danger">*</span></label>
                            <select class="form-select transport-select" name="details[${detailIndex}][moyen_transport]" data-index="${detailIndex}">
                                <option value="">Sélectionner...</option>
                                <option value="voiture">Voiture</option>
                                <option value="train">Train</option>
                                <option value="avion">Avion</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                        
                        <!-- Section Carte Leaflet -->
                        <div class="col-md-12 mb-3 carte-section" id="carte_section_${detailIndex}" style="display: none;">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Calcul de distance (Voiture)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info carte-instructions" id="carte_instructions_${detailIndex}">
                                        <i class="bi bi-info-circle"></i> <strong>Mode :</strong> Sélectionnez le <strong>point de départ</strong> (marqueur bleu) en cliquant sur la carte ou en recherchant une adresse.
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-5">
                                            <label class="form-label">Point de départ</label>
                                            <input type="text" class="form-control point-depart" id="point_depart_${detailIndex}" name="details[${detailIndex}][point_depart]" placeholder="Rechercher ou cliquer sur la carte" data-default-placeholder="Rechercher ou cliquer sur la carte" data-index="${detailIndex}">
                                            <!-- Champ caché pour garantir l'envoi même si la section est masquée -->
                                            <input type="hidden" name="details[${detailIndex}][point_depart_hidden]" id="point_depart_hidden_${detailIndex}" value="">
                                            <small class="text-muted point-helper" id="point_depart_helper_${detailIndex}" data-default-helper="Cliquez sur la carte ou recherchez une adresse">Cliquez sur la carte ou recherchez une adresse</small>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">Point d'arrivée</label>
                                            <input type="text" class="form-control point-arrivee" id="point_arrivee_${detailIndex}" name="details[${detailIndex}][point_arrivee]" placeholder="Rechercher ou cliquer sur la carte" data-default-placeholder="Rechercher ou cliquer sur la carte" data-index="${detailIndex}">
                                            <!-- Champ caché pour garantir l'envoi même si la section est masquée -->
                                            <input type="hidden" name="details[${detailIndex}][point_arrivee_hidden]" id="point_arrivee_hidden_${detailIndex}" value="">
                                            <small class="text-muted point-helper" id="point_arrivee_helper_${detailIndex}" data-default-helper="Cliquez sur la carte ou recherchez une adresse">Cliquez sur la carte ou recherchez une adresse</small>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-outline-danger w-100 reset-carte-btn" data-index="${detailIndex}">
                                                <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                                            </button>
                                        </div>
                                    </div>
                                    <div id="map_${detailIndex}" style="height: 400px; width: 100%; border-radius: 5px;"></div>
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Distance calculée (aller)</label>
                                            <input type="text" class="form-control distance-calculee" id="distance_calculee_${detailIndex}" readonly placeholder="0 km" data-index="${detailIndex}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Distance aller-retour</label>
                                            <input type="text" class="form-control distance-ar-calculee" id="distance_ar_calculee_${detailIndex}" readonly placeholder="0 km" data-index="${detailIndex}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Montant calculé (DH)</label>
                                            <input type="text" class="form-control montant-calcule" id="montant_calcule_${detailIndex}" readonly placeholder="0.00 DH" data-index="${detailIndex}">
                                        </div>
                                    </div>
                                    <p class="text-muted small mt-2 mb-0" id="distance_summary_${detailIndex}"></p>
                                    <input type="hidden" class="distance-km" id="distance_km_${detailIndex}" name="details[${detailIndex}][distance_km]" value="" data-index="${detailIndex}">
                                    <input type="hidden" class="distance-km-ar" id="distance_km_ar_${detailIndex}" name="details[${detailIndex}][distance_km_ar]" value="" data-index="${detailIndex}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="details[${detailIndex}][description]" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
            container.insertAdjacentHTML('beforeend', detailHtml);

            // Initialiser les événements pour ce nouveau détail
            initDetailEvents(detailIndex);
            if (window.CurrencyConverterUI) {
                CurrencyConverterUI.initForDetail(detailIndex);
            }

            detailIndex++;

            // Attacher les event listeners pour les boutons de suppression
            container.querySelectorAll('.removeDetail').forEach(btn => {
                btn.addEventListener('click', function () {
                    const item = this.closest('.detail-item');
                    const index = item.getAttribute('data-index');
                    cleanupMap(index);
                    item.remove();
                });
            });
        }

        // Attacher les event listeners pour les boutons de suppression existants
        document.querySelectorAll('.removeDetail').forEach(btn => {
            btn.addEventListener('click', function () {
                const item = this.closest('.detail-item');
                const index = item.getAttribute('data-index');
                cleanupMap(index);
                item.remove();
            });
        });

        function cleanDescription(index) {
            const descriptionInput = document.querySelector(`textarea[name*="[${index}][description]"]`);
            if (!descriptionInput) return;

            const currentValue = descriptionInput.value.trim();
            const lines = currentValue.split('\n').map(line => line.trim()).filter(line => line);

            // Supprimer toutes les lignes générées automatiquement
            const cleanedLines = lines.filter(line =>
                !line.includes('Distance calculée :') &&
                !line.startsWith('[Estimation] Distance calculée :') &&
                !line.includes('Montant estimé :') &&
                !line.startsWith('Départ :') &&
                !line.startsWith('Arrivée :')
            );

            descriptionInput.value = cleanedLines.join('\n').trim();
        }

        function submitDemandeForm() {
            const form = document.getElementById('demandeForm');
            if (!form) return;

            // 1. Synchronisation et validation des données (logique de l'ancien handler)
            const detailItems = document.querySelectorAll('.detail-item');
            detailItems.forEach(item => {
                const index = item.getAttribute('data-index');
                if (index !== null) {
                    // S'assurer que les champs cachés point_depart et point_arrivee sont à jour
                    const pointDepartField = document.getElementById(`point_depart_${index}`);
                    const pointArriveeField = document.getElementById(`point_arrivee_${index}`);
                    const pointDepartHidden = document.getElementById(`point_depart_hidden_${index}`);
                    const pointArriveeHidden = document.getElementById(`point_arrivee_hidden_${index}`);

                    if (pointDepartField && pointDepartHidden) {
                        pointDepartHidden.value = pointDepartField.value || '';
                    }
                    if (pointArriveeField && pointArriveeHidden) {
                        pointArriveeHidden.value = pointArriveeField.value || '';
                    }

                    // Pour les transports avec carte (visible)
                    const carteSection = document.getElementById(`carte_section_${index}`);
                    if (carteSection && carteSection.style.display !== 'none') {
                        const montantInput = document.querySelector(`input.montant-input[data-index="${index}"]`);
                        const montantMadHidden = document.querySelector(`input.montant-mad-hidden[data-index="${index}"]`);

                        // Si le montant input est vide/0, essayer de le remplir avec le calcul
                        if (montantInput && (!montantInput.value || parseFloat(montantInput.value) === 0)) {
                            const montantCalcule = document.getElementById(`montant_calcule_${index}`);
                            if (montantCalcule && montantCalcule.value && montantCalcule.value !== 'Calcul en cours...') {
                                const montantMatch = montantCalcule.value.match(/([\d.]+)/);
                                if (montantMatch) {
                                    const montant = parseFloat(montantMatch[1]);
                                    if (montant > 0) {
                                        montantInput.value = montant;
                                        if (montantMadHidden) {
                                            montantMadHidden.value = montant;
                                        }
                                    }
                                }
                            }
                        }

                        // S'assurer que le champ montant_mad est toujours rempli
                        if (montantMadHidden && (!montantMadHidden.value || parseFloat(montantMadHidden.value) === 0)) {
                            if (montantInput && montantInput.value) {
                                montantMadHidden.value = montantInput.value;
                            }
                        }
                    }
                }
            });

            // 2. Désactiver le 'required' sur TOUS les champs cachés ou dans des conteneurs cachés
            const allInputs = form.querySelectorAll('input, select, textarea');
            allInputs.forEach(input => {
                // Si l'input lui-même est hidden
                if (input.type === 'hidden') {
                    input.required = false;
                    return;
                }

                // Si l'input ou un de ses parents est caché (display: none)
                if (input.offsetParent === null) {
                    input.required = false;
                }
            });

            // 3. Validation manuelle des champs visibles et soumission
            if (form.checkValidity()) {
                form.submit();
            } else {
                form.reportValidity();
            }
        }
    </script>

    <!-- PDF Generation Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <script>
        window.demandeData = {
            id: <?php echo json_encode($demande['id']); ?>,
            employe: <?php echo json_encode($demande['user_nom'] ?? 'Employé'); ?>,
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

        async function generatePDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            // En-tête
            doc.setFontSize(22);
            doc.setTextColor(40);
            doc.text("Note de Frais", 14, 20);

            doc.setFontSize(10);
            doc.text("Entreprise : Ma Société", 14, 30); // Placeholder or real name if available
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
                // Nettoyage description pour le tableau
                let desc = detail.description;
                if (desc.length > 60) desc = desc.substring(0, 60) + '...';

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
                headStyles: {
                    fillColor: [66, 133, 244]
                }, // Bootstrap Primary Color roughly
                styles: {
                    fontSize: 9,
                    cellPadding: 3,
                    overflow: 'linebreak'
                },
                columnStyles: {
                    3: { cellWidth: 80 } // Largeur pour la description
                }
            });

            // Total
            const finalY = doc.lastAutoTable.finalY + 15;
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.text(`Montant Total Validé : ${window.demandeData.montant_total} DH`, 130, finalY);

            // Pied de page
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setFont(undefined, 'normal');
                doc.text("Document généré automatiquement - Gestion des Frais de Déplacement", 14, 285);
                doc.text('Page ' + i + ' sur ' + pageCount, 180, 285);
            }

            // Save
            doc.save(`Demande_Frais_${window.demandeData.id}.pdf`);
        }
    </script>

    <?php
    include __DIR__ . '/footer.php';
    exit();
}

// Liste des demandes
$stmt = $pdo->prepare("
    SELECT d.*, 
           COALESCE(SUM(df.montant), 0) as montant_total
    FROM demande_frais d
    LEFT JOIN details_frais df ON d.id = df.demande_id
    WHERE d.user_id = ?
    GROUP BY d.id
    ORDER BY d.date_soumission DESC
");
$stmt->execute([$userId]);
$demandes = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="mb-0"><i class="bi bi-list-ul"></i> Mes demandes</h1>
    <a href="nouvelle_demande.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nouvelle demande
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($demandes)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucune demande pour le moment.
                <a href="nouvelle_demande.php" class="alert-link">Créer votre première demande</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Objectif</th>
                            <th>Lieu</th>
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
                                <td><?php echo htmlspecialchars(substr($demande['objectif_mission'], 0, 50)); ?><?php echo strlen($demande['objectif_mission']) > 50 ? '...' : ''; ?>
                                </td>
                                <td><?php echo htmlspecialchars($demande['lieu_mission']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($demande['date_mission'])); ?></td>
                                <td><strong><?php echo number_format($demande['montant_total'], 2, ',', ' '); ?> DH</strong>
                                </td>
                                <td><?php echo getStatutBadge($demande['statut']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($demande['date_soumission'])); ?></td>
                                <td>
                                    <a href="mes_demandes.php?action=voir&id=<?php echo $demande['id']; ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Voir
                                    </a>
                                    <?php if (in_array($demande['statut'], ['soumis', 'rejete_manager', 'rejete_admin'])): ?>
                                        <a href="mes_demandes.php?action=modifier&id=<?php echo $demande['id']; ?>"
                                            class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pencil"></i> Modifier
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
<script src="../../../public/js/currency_converter.js"></script>