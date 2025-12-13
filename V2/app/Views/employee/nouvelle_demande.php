<?php
/**
 * Création et soumission d'une nouvelle demande de frais
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../includes/notifications.php';
require_once __DIR__ . '/../../../services/send_mail.php';
requireEmploye();

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Récupérer les catégories de frais
$stmt = $pdo->query("SELECT id, nom FROM categories_frais ORDER BY nom");
$categories = $stmt->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Validation des données
        $objectif_mission = trim($_POST['objectif_mission'] ?? '');
        $lieu_mission = trim($_POST['lieu_mission'] ?? '');
        $date_mission = $_POST['date_mission'] ?? '';
        $justificatif_principal = null;

        if (empty($objectif_mission) || empty($lieu_mission) || empty($date_mission)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        // Upload du justificatif principal

        // Upload du justificatif principal
        if (!isset($_FILES['justificatif_principal']) || $_FILES['justificatif_principal']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Le justificatif principal est obligatoire.");
        }

        if (isset($_FILES['justificatif_principal']) && $_FILES['justificatif_principal']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['justificatif_principal'];

            // Vérifier la taille
            if ($file['size'] > UPLOAD_MAX_SIZE) {
                throw new Exception("Le fichier est trop volumineux (max 5 Mo).");
            }

            // Vérifier l'extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                throw new Exception("Format de fichier non autorisé. Formats acceptés : PDF, JPG, PNG.");
            }

            // Générer un nom unique
            $filename = uniqid('justif_', true) . '.' . $ext;
            $filepath = UPLOAD_DIR . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception("Erreur lors de l'upload du fichier.");
            }

            $justificatif_principal = $filename;
        }

        // Créer la demande
        $stmt = $pdo->prepare("
            INSERT INTO demande_frais (user_id, objectif_mission, lieu_mission, date_mission, statut, justificatif_principal)
            VALUES (?, ?, ?, ?, 'soumis', ?)
        ");
        $stmt->execute([$userId, $objectif_mission, $lieu_mission, $date_mission, $justificatif_principal]);
        $demandeId = $pdo->lastInsertId();

        // Ajouter les détails de frais
        if (isset($_POST['details']) && is_array($_POST['details'])) {
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

                $justificatif = null;

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
                    // Upload du justificatif pour ce détail
                    $fileKey = 'detail_justif_' . $index;
                    $justificatif = null;

                    // Vérification : Justificatif obligatoire pour les frais NON "voiture" (pas de points)
                    // Si ce n'est pas un transport avec points (voiture), le justificatif est obligatoire
                    if (!$isTransportWithPoints) {
                        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                            throw new Exception("Le justificatif est obligatoire pour tous les frais (Frais #" . ($index + 1) . ").");
                        }
                    }

                    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES[$fileKey];

                        // Vérifier la taille
                        if ($file['size'] <= UPLOAD_MAX_SIZE) {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (in_array($ext, ALLOWED_EXTENSIONS)) {
                                $filename = uniqid('detail_', true) . '.' . $ext;
                                $filepath = UPLOAD_DIR . $filename;

                                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                                    $justificatif = $filename;
                                }
                            }
                        }
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

        // Créer l'entrée dans l'historique
        $stmt = $pdo->prepare("
            INSERT INTO historique_statuts (demande_id, statut, user_id, commentaire)
            VALUES (?, 'soumis', ?, 'Demande créée et soumise')
        ");
        $stmt->execute([$demandeId, $userId]);

        // Récupérer le manager de l'employé pour la notification
        $stmt = $pdo->prepare("SELECT manager_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch();

        // Créer une notification pour le manager s'il existe
        if ($userInfo && !empty($userInfo['manager_id'])) {
            createNotification(
                $userInfo['manager_id'],
                'warning',
                "Nouvelle demande de frais #{$demandeId} à valider.",
                "../../../app/Views/manager/demandes.php?action=voir&id={$demandeId}",
                false // Pas d'email pour les nouvelles demandes
            );
        }



        $pdo->commit();

        // Envoyer un email de confirmation à l'employé
        sendDemandeSubmissionEmail(
            $_SESSION['user_email'],
            $_SESSION['user_nom'],
            $demandeId,
            $objectif_mission
        );

        // Redirection vers les détails de la demande
        header("Location: mes_demandes.php?action=voir&id=$demandeId&success=1");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1><i class="bi bi-plus-circle"></i> Nouvelle demande de frais</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="demandeForm">
    <!-- Informations générales -->
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
                        required><?php echo htmlspecialchars($_POST['objectif_mission'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="lieu_mission" class="form-label">Lieu de la mission <span
                            class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="lieu_mission" name="lieu_mission"
                        value="<?php echo htmlspecialchars($_POST['lieu_mission'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="date_mission" class="form-label">Date de la mission <span
                            class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date_mission" name="date_mission"
                        value="<?php echo htmlspecialchars($_POST['date_mission'] ?? ''); ?>" required>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="justificatif_principal" class="form-label">Justificatif principal (PDF, JPG, PNG - max 5
                        Mo) <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="justificatif_principal" name="justificatif_principal"
                        accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this, 'justificatif_principal_label')"
                        required>
                    <small class="text-muted" id="justificatif_principal_label">Format accepté : PDF, JPG, PNG. Taille
                        maximale : 5 Mo</small>
                </div>
            </div>
        </div>
    </div>


    <!-- Détails des frais -->
    <div class="card mb-4">
        <div class="card-body p-0">
            <div id="detailsContainer" class="p-3">
                <!-- Les détails seront ajoutés dynamiquement ici -->
            </div>
        </div>
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i> Détails des frais</h5>
            <button type="button" class="btn btn-sm btn-outline-light" id="addDetail">
                <i class="bi bi-plus"></i> Ajouter un frais
            </button>
        </div>
        <div class="card-body">
            <div class="text-muted text-center py-3" id="noDetails">
                Aucun frais ajouté. Cliquez sur "Ajouter un frais" pour commencer.
            </div>
        </div>
    </div>

    <!-- Boutons de soumission -->
    <div class="d-flex justify-content-between">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Soumettre la demande
        </button>
    </div>
</form>

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

<!-- Leaflet JS -->
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.css" />

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<!-- Geocoding pour la recherche d'adresses -->
<script src="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.js"></script>
<script src="../../../public/js/currency_converter.js"></script>

<script>
    let detailIndex = 0;
    // Stockage des cartes et marqueurs par index
    const maps = {};
    const routingControls = {};
    const markers = {};
    const currentSelectionModes = {};
    // Tarif par kilomètre (configurable depuis config.php)
    const TARIF_PAR_KM = <?php echo defined('TARIF_PAR_KM') ? TARIF_PAR_KM : 1.2; ?>; // DH par km
    const ALLER_RETOUR_MULTIPLICATEUR = 2; // Aller + retour

    function formatNumber(value) {
        const num = Number(value);
        if (Number.isNaN(num)) {
            return '0';
        }
        return parseFloat(num.toFixed(2)).toString();
    }

    function formatKmValue(value) {
        return `${formatNumber(value)} km`;
    }

    function formatMadCurrency(value) {
        return `${formatNumber(value)} DH`;
    }



    function updateDescriptionWithDistance(index, distanceKm, roundTripKm, amountMad, options = {}) {
        const descriptionInput = document.querySelector(`textarea[name*="[${index}][description]"]`);
        if (!descriptionInput) return;

        const estimationLabel = options.estimated ? '[Estimation] ' : '';
        const infoLine = `${estimationLabel}Distance calculée : ${formatKmValue(distanceKm)} (aller-retour : ${formatKmValue(roundTripKm)}) – Montant estimé : ${formatMadCurrency(amountMad)}`;

        const currentValue = descriptionInput.value.trim();
        const lines = currentValue.split('\n').map(line => line.trim()).filter(line => line);

        const cleanedLines = lines.filter(line => !line.includes('Distance calculée :') && !line.startsWith('[Estimation] Distance calculée :'));

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
        const currencySelect = document.querySelector(`select.currency-select[data-index="${index}"]`);

        if (currencySelect) {
            currencySelect.value = 'MAD';
        }

        if (montantInput) {
            montantInput.value = amountMad;
            if (window.CurrencyConverterUI) {
                CurrencyConverterUI.triggerConversion(index);
            }
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


    }

    function addDetail() {
        const container = document.getElementById('detailsContainer');
        const noDetails = document.getElementById('noDetails');

        if (noDetails) {
            noDetails.style.display = 'none';
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
                        <input type="file" class="form-control justificatif-input" name="detail_justif_${detailIndex}" accept=".pdf,.jpg,.jpeg,.png" data-index="${detailIndex}" onchange="updateFileName(this, 'detail_justif_label_${detailIndex}')" required>
                        <small class="text-muted" id="detail_justif_label_${detailIndex}">Optionnel</small>
                    </div>
                    
                    <!-- Options de transport (affichées si Transport est sélectionné) -->
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
                    
                    <!-- Section Carte Leaflet (affichée uniquement si Voiture est sélectionné) -->
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
                                        <input type="hidden" name="details[${detailIndex}][lat_depart]" id="lat_depart_${detailIndex}" value="">
                                        <input type="hidden" name="details[${detailIndex}][lng_depart]" id="lng_depart_${detailIndex}" value="">
                                        <small class="text-muted point-helper" id="point_depart_helper_${detailIndex}" data-default-helper="Cliquez sur la carte ou recherchez une adresse">Cliquez sur la carte ou recherchez une adresse</small>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Point d'arrivée</label>
                                        <input type="text" class="form-control point-arrivee" id="point_arrivee_${detailIndex}" name="details[${detailIndex}][point_arrivee]" placeholder="Rechercher ou cliquer sur la carte" data-default-placeholder="Rechercher ou cliquer sur la carte" data-index="${detailIndex}">
                                        <!-- Champ caché pour garantir l'envoi même si la section est masquée -->
                                        <input type="hidden" name="details[${detailIndex}][point_arrivee_hidden]" id="point_arrivee_hidden_${detailIndex}" value="">
                                        <input type="hidden" name="details[${detailIndex}][lat_arrivee]" id="lat_arrivee_${detailIndex}" value="">
                                        <input type="hidden" name="details[${detailIndex}][lng_arrivee]" id="lng_arrivee_${detailIndex}" value="">
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

        // Ajouter l'événement de suppression
        container.querySelectorAll('.removeDetail').forEach(btn => {
            btn.addEventListener('click', function () {
                const item = this.closest('.detail-item');
                const index = item.getAttribute('data-index');

                // Nettoyer la carte si elle existe
                cleanupMap(index);

                item.remove();

                // Afficher le message si plus aucun détail
                if (container.children.length === 0 && noDetails) {
                    noDetails.style.display = 'block';
                }
            });
        });
    }

    document.getElementById('addDetail').addEventListener('click', addDetail);

    // Fonction pour afficher le nom du fichier sélectionné
    function updateFileName(input, labelId) {
        const label = document.getElementById(labelId);
        if (input.files && input.files.length > 0) {
            label.textContent = 'Fichier sélectionné : ' + input.files[0].name;
            label.className = 'text-success';
        } else {
            if (labelId === 'justificatif_principal_label') {
                label.textContent = 'Format accepté : PDF, JPG, PNG. Taille maximale : 5 Mo';
            } else {
                label.textContent = 'Aucun fichier';
            }
            label.className = 'text-muted';
        }
    }

    // Validation du formulaire
    document.getElementById('demandeForm').addEventListener('submit', function (e) {
        const details = document.querySelectorAll('.detail-item');
        if (details.length === 0) {
            e.preventDefault();
            alert('Veuillez ajouter au moins un frais.');
            return;
        }

        // Pour chaque détail, s'assurer que les champs cachés point_depart et point_arrivee sont à jour
        details.forEach(item => {
            const index = item.getAttribute('data-index');
            if (index !== null) {
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
            }
        });
    });

    // ========== GESTION DES DÉTAILS DE FRAIS ET CARTES LEAFLET ==========

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

        // Focus sur les champs de points pour changer le mode
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

            pointDepart.addEventListener('blur', function () {
                syncHiddenField(pointDepart, pointDepartHidden);
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

            pointArrivee.addEventListener('blur', function () {
                syncHiddenField(pointArrivee, pointArriveeHidden);
            });
        }
    }

    // Gérer le changement de catégorie
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
            // Justificatif obligatoire pour les autres catégories
            if (justificatifInput) {
                justificatifInput.required = true;
            }
        }
    }

    // Gérer le changement de moyen de transport
    function handleTransportChange(index, transportValue) {
        const carteSection = document.getElementById(`carte_section_${index}`);
        const montantContainer = document.querySelector(`div.montant-container[data-index="${index}"]`);
        const justificatifContainer = document.querySelector(`div.justificatif-container[data-index="${index}"]`);
        const montantInput = document.querySelector(`input.montant-input[data-index="${index}"]`);
        const justificatifInput = document.querySelector(`input.justificatif-input[data-index="${index}"]`);

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
            // Justificatif obligatoire pour les autres transports
            if (justificatifInput) {
                justificatifInput.required = true;
            }
            if (window.CurrencyConverterUI) {
                CurrencyConverterUI.triggerConversion(index);
            }
        }
    }

    // Initialiser la carte Leaflet pour un index spécifique
    function initMap(index) {
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
            }).setView([46.6034, 1.8883], 6);

            markers[index] = { start: null, end: null, startLatLng: null, endLatLng: null };
            currentSelectionModes[index] = 'depart';

            const currentMap = maps[index];
            const currentMarkers = markers[index];

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

            // Initialiser le mode de sélection
            currentSelectionModes[index] = 'depart';
            updateSelectionMode(index, 'depart');

            currentMap.on('click', function (e) {
                const latlng = e.latlng;

                if (currentSelectionModes[index] === 'depart' || !currentMarkers.start) {
                    // Définir le point de départ
                    if (currentMarkers.start) {
                        currentMap.removeLayer(currentMarkers.start);
                    }
                    currentMarkers.start = L.marker(latlng, {
                        draggable: true, icon: L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34],
                            shadowSize: [41, 41]
                        })
                    }).addTo(currentMap)
                        .bindPopup('Point de départ (glissez pour déplacer)').openPopup();
                    currentMarkers.startLatLng = latlng;

                    // Mettre à jour le champ de recherche
                    reverseGeocode(index, latlng, `point_depart_${index}`);

                    // Passer au mode arrivée si le départ est défini
                    if (currentMarkers.start && !currentMarkers.end) {
                        updateSelectionMode(index, 'arrivee');
                    }

                    // Recalculer si les deux points sont définis
                    if (currentMarkers.end) {
                        calculateRoute(index);
                    }
                } else {
                    // Définir le point d'arrivée
                    if (currentMarkers.end) {
                        currentMap.removeLayer(currentMarkers.end);
                    }
                    currentMarkers.end = L.marker(latlng, {
                        draggable: true, icon: L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34],
                            shadowSize: [41, 41]
                        })
                    }).addTo(currentMap)
                        .bindPopup('Point d\'arrivée (glissez pour déplacer)').openPopup();
                    currentMarkers.endLatLng = latlng;

                    // Mettre à jour le champ de recherche
                    reverseGeocode(index, latlng, `point_arrivee_${index}`);

                    // Calculer l'itinéraire
                    calculateRoute(index);
                }

                // Gérer le glisser-déposer des marqueurs
                if (currentMarkers.start) {
                    currentMarkers.start.off('dragend');
                    currentMarkers.start.on('dragend', function (e) {
                        currentMarkers.startLatLng = e.target.getLatLng();
                        reverseGeocode(index, currentMarkers.startLatLng, `point_depart_${index}`);
                        if (currentMarkers.end) {
                            calculateRoute(index);
                        }
                    });
                }

                if (currentMarkers.end) {
                    currentMarkers.end.off('dragend');
                    currentMarkers.end.on('dragend', function (e) {
                        currentMarkers.endLatLng = e.target.getLatLng();
                        reverseGeocode(index, currentMarkers.endLatLng, `point_arrivee_${index}`);
                        calculateRoute(index);
                    });
                }
            });

            // Recherche d'adresses pour le point de départ
            const startGeocoder = L.Control.geocoder({
                geocoder: geocoder,
                position: 'topleft',
                placeholder: 'Rechercher départ...',
                errorMessage: 'Aucun résultat trouvé',
                defaultMarkGeocode: false
            }).on('markgeocode', function (e) {
                const latlng = e.geocode.center;
                if (currentMarkers.start) {
                    currentMap.removeLayer(currentMarkers.start);
                }
                currentMarkers.start = L.marker(latlng, {
                    draggable: true, icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).addTo(currentMap)
                    .bindPopup('Point de départ (glissez pour déplacer)').openPopup();
                currentMarkers.startLatLng = latlng;
                // Récupérer les coordonnées depuis le résultat du géocodage
                const lat = e.geocode.center ? e.geocode.center.lat : null;
                const lng = e.geocode.center ? e.geocode.center.lng : null;
                setPointFieldValue(`point_depart_${index}`, e.geocode.name, lat, lng);
                currentMap.setView(latlng, 13);

                currentMarkers.start.on('dragend', function (e) {
                    currentMarkers.startLatLng = e.target.getLatLng();
                    reverseGeocode(index, currentMarkers.startLatLng, `point_depart_${index}`);
                    if (currentMarkers.end) {
                        calculateRoute(index);
                    }
                });

                if (currentMarkers.end) {
                    calculateRoute(index);
                } else {
                    updateSelectionMode(index, 'arrivee');
                }
            }).addTo(currentMap);

            // Recherche d'adresses pour le point d'arrivée
            const endGeocoder = L.Control.geocoder({
                geocoder: geocoder,
                position: 'topright',
                placeholder: 'Rechercher arrivée...',
                errorMessage: 'Aucun résultat trouvé',
                defaultMarkGeocode: false
            }).on('markgeocode', function (e) {
                const latlng = e.geocode.center;
                if (currentMarkers.end) {
                    currentMap.removeLayer(currentMarkers.end);
                }
                currentMarkers.end = L.marker(latlng, {
                    draggable: true, icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).addTo(currentMap)
                    .bindPopup('Point d\'arrivée (glissez pour déplacer)').openPopup();
                currentMarkers.endLatLng = latlng;
                // Récupérer les coordonnées depuis le résultat du géocodage
                const lat = e.geocode.center ? e.geocode.center.lat : null;
                const lng = e.geocode.center ? e.geocode.center.lng : null;
                setPointFieldValue(`point_arrivee_${index}`, e.geocode.name, lat, lng);
                currentMap.setView(latlng, 13);

                currentMarkers.end.on('dragend', function (e) {
                    currentMarkers.endLatLng = e.target.getLatLng();
                    reverseGeocode(index, currentMarkers.endLatLng, `point_arrivee_${index}`);
                    calculateRoute(index);
                });

                if (currentMarkers.start) {
                    calculateRoute(index);
                }
            }).addTo(currentMap);

        } catch (error) {
            console.error('Erreur lors de l\'initialisation de la carte:', error);
            alert('Erreur lors de l\'initialisation de la carte. Veuillez réessayer.');
        }
    }

    // Fonction pour mettre à jour le mode de sélection
    function updateSelectionMode(index, mode) {
        currentSelectionModes[index] = mode;
        const infoText = document.getElementById(`carte_instructions_${index}`);
        if (infoText) {
            if (mode === 'depart') {
                infoText.innerHTML = '<i class="bi bi-info-circle"></i> <strong>Mode :</strong> Sélectionnez le <strong>point de départ</strong> (marqueur bleu) en cliquant sur la carte ou en recherchant une adresse.';
            } else {
                infoText.innerHTML = '<i class="bi bi-info-circle"></i> <strong>Mode :</strong> Sélectionnez le <strong>point d\'arrivée</strong> (marqueur rouge) en cliquant sur la carte ou en recherchant une adresse.';
            }
        }
    }

    // Fonction utilitaire pour mettre à jour les champs d'un point (adresse et coordonnées)
    function setPointFieldValue(fieldId, address, lat, lng) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = address;

            // Mettre à jour le champ caché pour l'adresse (point_depart_hidden_X ou point_arrivee_hidden_X)
            // fieldId est du type "point_depart_0" ou "point_arrivee_0"
            let hiddenId = '';
            if (fieldId.includes('point_depart_')) {
                hiddenId = fieldId.replace('point_depart_', 'point_depart_hidden_');
            } else if (fieldId.includes('point_arrivee_')) {
                hiddenId = fieldId.replace('point_arrivee_', 'point_arrivee_hidden_');
            }

            const hiddenField = document.getElementById(hiddenId);
            if (hiddenField) hiddenField.value = address;

            // Mettre à jour les coordonnées cachées
            let latId = '', lngId = '';
            if (fieldId.includes('point_depart_')) {
                latId = fieldId.replace('point_depart_', 'lat_depart_');
                lngId = fieldId.replace('point_depart_', 'lng_depart_');
            } else if (fieldId.includes('point_arrivee_')) {
                latId = fieldId.replace('point_arrivee_', 'lat_arrivee_');
                lngId = fieldId.replace('point_arrivee_', 'lng_arrivee_');
            }

            const latField = document.getElementById(latId);
            const lngField = document.getElementById(lngId);

            if (latField) latField.value = lat !== null ? lat : '';
            if (lngField) lngField.value = lng !== null ? lng : '';

            // Mettre à jour le helper text
            let helperId = '';
            if (fieldId.includes('point_depart_')) {
                helperId = fieldId.replace('point_depart_', 'point_depart_helper_');
            } else if (fieldId.includes('point_arrivee_')) {
                helperId = fieldId.replace('point_arrivee_', 'point_arrivee_helper_');
            }

            const helper = document.getElementById(helperId);
            if (helper) {
                if (lat && lng) {
                    helper.textContent = `Coordonnées : ${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
                    helper.classList.remove('text-muted');
                    helper.classList.add('text-success');
                } else {
                    helper.textContent = helper.dataset.defaultHelper || 'Cliquez sur la carte ou recherchez une adresse';
                    helper.classList.remove('text-success');
                    helper.classList.add('text-muted');
                }
            }
        }
    }

    // Géocodage inverse (coordonnées -> adresse)
    function reverseGeocode(index, latlng, fieldId, attempt = 1) {
        const lat = latlng.lat;
        const lon = latlng.lng;

        // Vérifier que lat et lon sont valides
        if (!lat || !lon || isNaN(lat) || isNaN(lon)) {
            console.error('Coordonnées invalides:', lat, lon);
            setPointFieldValue(fieldId, 'Lieu sélectionné (offline)', lat, lon);
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
                setPointFieldValue(fieldId, readableAddress, lat, lon);
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Erreur géocodage (tentative ' + attempt + '):', error);

                // Si c'est une erreur d'annulation (timeout), on utilise directement le fallback
                if (error.name === 'AbortError') {
                    const offlineAddress = `Lieu sélectionné (${lat.toFixed(6)}, ${lon.toFixed(6)})`;
                    setPointFieldValue(fieldId, offlineAddress, lat, lon);
                    return;
                }

                // Si on a une erreur réseau mais qu'on semble être en ligne, on réessaie (max 3 fois)
                if (attempt < 3 && navigator.onLine) {
                    console.log('Nouvelle tentative de géocodage dans 1s...');
                    setTimeout(() => reverseGeocode(index, latlng, fieldId, attempt + 1), 1000);
                    return;
                }

                // En cas d'erreur réseau persistante (pas d'Internet ou blocage), afficher les coordonnées
                const offlineAddress = `Lieu sélectionné (${lat.toFixed(6)}, ${lon.toFixed(6)})`;
                setPointFieldValue(fieldId, offlineAddress, lat, lon);
            });
    }

    // Calculer l'itinéraire et la distance
    function calculateRoute(index) {
        const currentMarkers = markers[index];
        if (!currentMarkers.startLatLng || !currentMarkers.endLatLng) {
            return;
        }

        const currentMap = maps[index];

        // Supprimer l'ancien itinéraire s'il existe
        if (routingControls[index]) {
            currentMap.removeControl(routingControls[index]);
            routingControls[index] = null;
        }

        // Afficher un message de chargement
        const distanceField = document.getElementById(`distance_calculee_${index}`);
        const distanceArField = document.getElementById(`distance_ar_calculee_${index}`);
        const montantField = document.getElementById(`montant_calcule_${index}`);

        if (distanceField) distanceField.value = 'Calcul en cours...';
        if (distanceArField) distanceArField.value = 'Calcul en cours...';
        if (montantField) montantField.value = 'Calcul en cours...';


        // Créer le contrôleur d'itinéraire avec OSRM
        console.log('Using OSRM service URL:', '/V22/V2/public/osrm_proxy.php/route/v1');
        routingControls[index] = L.Routing.control({
            waypoints: [
                L.latLng(currentMarkers.startLatLng.lat, currentMarkers.startLatLng.lng),
                L.latLng(currentMarkers.endLatLng.lat, currentMarkers.endLatLng.lng)
            ],
            routeWhileDragging: false,
            router: L.Routing.osrmv1({
                serviceUrl: '/V22/V2/public/osrm_proxy.php/route/v1',
                profile: 'driving',
                timeout: 10000
            }),
            lineOptions: {
                styles: [{ color: '#3388ff', weight: 5, opacity: 0.7 }]
            },
            addWaypoints: false,
            draggableWaypoints: false,
            fitSelectedRoutes: true,
            showAlternatives: false,
            createMarker: function () { return null; }
        }).on('routesfound', function (e) {
            const routes = e.routes;
            if (routes && routes.length > 0) {
                const route = routes[0];
                const distanceKm = parseFloat((route.summary.totalDistance / 1000).toFixed(2));
                const roundTripKm = parseFloat((distanceKm * ALLER_RETOUR_MULTIPLICATEUR).toFixed(2));
                // Montant = distance aller-retour (km) × tarif par km (DH/km)
                const montantMad = parseFloat((roundTripKm * TARIF_PAR_KM).toFixed(2));

                // Mettre à jour les champs
                if (distanceField) distanceField.value = formatKmValue(distanceKm);
                if (distanceArField) distanceArField.value = formatKmValue(roundTripKm);
                if (montantField) montantField.value = formatMadCurrency(montantMad);
                const distanceKmHidden = document.getElementById(`distance_km_${index}`);
                if (distanceKmHidden) {
                    distanceKmHidden.value = distanceKm;
                }
                const distanceArHidden = document.getElementById(`distance_km_ar_${index}`);
                if (distanceArHidden) {
                    distanceArHidden.value = roundTripKm;
                }

                // Remplir automatiquement le champ montant du détail correspondant
                applyMadAmountToDetail(index, montantMad);

                // Résumé et description

                updateDescriptionWithDistance(index, distanceKm, roundTripKm, montantMad);
            }
        }).on('routingerror', function (e) {
            console.error('Erreur Routing OSRM:', e);
            // Force fallback immediately
            calculateDistanceFallback(index);
        }).addTo(currentMap);
    }

    // Calcul de distance de secours (à vol d'oiseau avec coefficient)
    function calculateDistanceFallback(index) {
        const currentMarkers = markers[index];
        if (!currentMarkers.startLatLng || !currentMarkers.endLatLng) {
            return;
        }

        const currentMap = maps[index];

        // Calculer la distance à vol d'oiseau en km (formule de Haversine)
        const R = 6371;
        const dLat = (currentMarkers.endLatLng.lat - currentMarkers.startLatLng.lat) * Math.PI / 180;
        const dLon = (currentMarkers.endLatLng.lng - currentMarkers.startLatLng.lng) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(currentMarkers.startLatLng.lat * Math.PI / 180) * Math.cos(currentMarkers.endLatLng.lat * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const distanceVolOiseau = R * c;

        // Appliquer un coefficient pour estimer la distance routière
        const distanceKm = parseFloat((distanceVolOiseau * 1.3).toFixed(2));
        const roundTripKm = parseFloat((distanceKm * ALLER_RETOUR_MULTIPLICATEUR).toFixed(2));
        // Montant = distance aller-retour (km) × tarif par km (DH/km)
        const montantMad = parseFloat((roundTripKm * TARIF_PAR_KM).toFixed(2));

        // Mettre à jour les champs
        const distanceField = document.getElementById(`distance_calculee_${index}`);
        const distanceArField = document.getElementById(`distance_ar_calculee_${index}`);
        const montantField = document.getElementById(`montant_calcule_${index}`);

        if (distanceField) distanceField.value = formatKmValue(distanceKm) + ' (estimation)';
        if (distanceArField) distanceArField.value = formatKmValue(roundTripKm) + ' (estimation)';
        if (montantField) montantField.value = formatMadCurrency(montantMad);

        const distanceKmHidden = document.getElementById(`distance_km_${index}`);
        if (distanceKmHidden) {
            distanceKmHidden.value = distanceKm;
        }
        const distanceArHidden = document.getElementById(`distance_km_ar_${index}`);
        if (distanceArHidden) {
            distanceArHidden.value = roundTripKm;
        }

        // Remplir automatiquement le champ montant
        applyMadAmountToDetail(index, montantMad);

        // Résumé et description

        updateDescriptionWithDistance(index, distanceKm, roundTripKm, montantMad, { estimated: true });

        // Dessiner une ligne droite sur la carte (SUPPRIMÉ)
        if (routingControls[index]) {
            currentMap.removeControl(routingControls[index]);
        }
        // L.polyline([currentMarkers.startLatLng, currentMarkers.endLatLng], { color: '#ff7800', weight: 3, opacity: 0.7, dashArray: '10, 10' }).addTo(currentMap);
    }

    // Réinitialiser uniquement les points (sans détruire la carte)
    function resetCartePoints(index) {
        const currentMarkers = markers[index];
        const currentMap = maps[index];

        if (currentMarkers && currentMap) {
            if (currentMarkers.start) {
                currentMap.removeLayer(currentMarkers.start);
                currentMarkers.start = null;
            }
            if (currentMarkers.end) {
                currentMap.removeLayer(currentMarkers.end);
                currentMarkers.end = null;
            }
        }

        if (currentMarkers) {
            currentMarkers.startLatLng = null;
            currentMarkers.endLatLng = null;
        }

        if (routingControls[index] && currentMap) {
            currentMap.removeControl(routingControls[index]);
            routingControls[index] = null;
        } else {
            routingControls[index] = null;
        }

        if (currentMap) {
            currentMap.eachLayer(function (layer) {
                if (layer instanceof L.Polyline) {
                    currentMap.removeLayer(layer);
                }
            });
        }

        clearDistanceDisplays(index);
        updateSelectionMode(index, 'depart');
    }

    // Nettoyer la carte complètement
    function cleanupMap(index) {
        if (maps[index]) {
            maps[index].remove();
            delete maps[index];
        }
        if (routingControls[index]) {
            delete routingControls[index];
        }
        if (markers[index]) {
            delete markers[index];
        }
    }
</script>

<?php include __DIR__ . '/footer.php'; ?>
<script src="../../../public/js/currency_converter.js"></script>