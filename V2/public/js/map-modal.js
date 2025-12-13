/**
 * Logique commune pour la modale de carte (Leaflet + OSRM)
 * Utilisé dans: 
 * - app/Views/employee/mes_demandes.php
 * - app/Views/manager/demandes.php
 * - app/Views/admin/details_demande.php
 */

let mapInstance;
let departMarker, arriveeMarker, routingControl;
const TARIF_PAR_KM = 0.60; // DH par km

/**
 * Ouvre la modale et initialise/met à jour la carte
 * @param {string} depart - Coordonnées départ "lat,lng"
 * @param {string} arrivee - Coordonnées arrivée "lat,lng"
 * @param {number} montant - Montant déjà enregistré (si > 0, on l'affiche directement)
 */
function openMapModal(depart, arrivee, montant) {
    console.log("openMapModal called with:", depart, arrivee, montant);

    montant = parseFloat(montant) || 0;

    // Gestion de l'instance du modal Bootstrap
    const modalElement = document.getElementById('mapModal');
    if (!modalElement) {
        console.error("Modal #mapModal introuvable dans le DOM.");
        return;
    }

    // Vérifier si Bootstrap est disponible
    if (typeof bootstrap === 'undefined') {
        console.error("Bootstrap n'est pas chargé ! Impossible d'ouvrir le modal.");
        alert("Erreur : Bootstrap n'est pas chargé.");
        return;
    }

    const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
    modal.show();

    // Attendre que la modal soit affichée (transition CSS) pour initialiser la carte
    setTimeout(() => {
        initMap(depart, arrivee, montant);
    }, 300);
}

/**
 * Initialise ou met à jour la carte Leaflet
 */
// Helper pour résoudre une localisation (coordonnées ou adresse)
function resolveLocation(input, callback) {
    if (!input) {
        callback(null, null);
        return;
    }

    // Essayer de parser "lat,lng"
    const coordRegex = /^(-?\d+(\.\d+)?),\s*(-?\d+(\.\d+)?)$/;
    const match = input.match(coordRegex);

    if (match) {
        // C'est une coordonnée
        const latlng = L.latLng(parseFloat(match[1]), parseFloat(match[3]));
        callback(latlng, input); // Nom = l'input original
    } else {
        // C'est une adresse -> Géocodage via Proxy Local
        console.log("Géocodage de l'adresse:", input);
        const url = `/V22/V2/public/proxy_nominatim.php?q=${encodeURIComponent(input)}`;

        fetch(url)
            .then(response => response.json())
            .then(results => {
                if (results && results.length > 0) {
                    console.log("Géocodage réussi:", results[0]);
                    // Nominatim retourne lat/lon en string, Leaflet veut des nombres
                    const center = L.latLng(parseFloat(results[0].lat), parseFloat(results[0].lon));
                    callback(center, results[0].display_name);
                } else {
                    console.error("Géocodage échoué pour:", input);
                    callback(null, input);
                }
            })
            .catch(err => {
                console.error("Erreur Fetch Géocodage:", err);
                callback(null, input);
            });
    }
}

/**
 * Initialise ou met à jour la carte Leaflet
 */
function initMap(depart, arrivee, montant) {
    // 1. Initialiser la carte si nécessaire
    if (!mapInstance) {
        if (!document.getElementById('mapContainer')) {
            console.error("Conteneur #mapContainer introuvable.");
            return;
        }

        mapInstance = L.map('mapContainer').setView([31.63, -7.99], 13); // Centré sur le Maroc

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(mapInstance);
    }

    // CRITIQUE : forcer le redimensionnement
    mapInstance.invalidateSize();

    // 2. Nettoyer les anciens calques
    if (departMarker) mapInstance.removeLayer(departMarker);
    if (arriveeMarker) mapInstance.removeLayer(arriveeMarker);
    if (routingControl) {
        mapInstance.removeControl(routingControl);
        routingControl = null;
    }

    // 3. Résoudre les emplacements (Asynchrone)
    resolveLocation(depart, function (depLatLng, depName) {
        resolveLocation(arrivee, function (arrLatLng, arrName) {

            if (depLatLng && arrLatLng) {
                console.log("Points résolus:", depLatLng, arrLatLng);

                // Marqueurs avec icônes personnalisées
                const redIcon = L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });

                const blueIcon = L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });

                // Le point de départ doit être ROUGE (demande utilisateur)
                departMarker = L.marker(depLatLng, { icon: redIcon })
                    .addTo(mapInstance)
                    .bindPopup(`<strong>Point de départ</strong><br>${depName || depart}`);

                // Le point d'arrivée en BLEU pour le contraste
                arriveeMarker = L.marker(arrLatLng, { icon: blueIcon })
                    .addTo(mapInstance)
                    .bindPopup(`<strong>Point d'arrivée</strong><br>${arrName || arrivee}`);

                // 4. Tracer l'itinéraire avec OSRM
                routingControl = L.Routing.control({
                    waypoints: [depLatLng, arrLatLng],
                    routeWhileDragging: false,
                    addWaypoints: false,
                    draggableWaypoints: false,
                    fitSelectedRoutes: false,
                    showAlternatives: false,
                    createMarker: function () { return null; },
                    router: L.Routing.osrmv1({
                        serviceUrl: '/V22/V2/public/proxy_osrm.php/route/v1',
                        profile: 'driving',
                        timeout: 10000
                    }),
                    lineOptions: {
                        styles: [{ color: '#0d6efd', weight: 5, opacity: 0.8 }]
                    },
                    containerClassName: 'd-none'
                }).addTo(mapInstance);

                // Cacher explicement le conteneur
                const routingContainer = document.querySelector('.leaflet-routing-container');
                if (routingContainer) routingContainer.style.display = 'none';

                // 5. Calculs et Callbacks
                routingControl.on('routesfound', function (e) {
                    const route = e.routes[0];
                    const distanceKm = route.summary.totalDistance / 1000;
                    const distanceAR = distanceKm * 2;
                    const montantAffiche = montant > 0 ? montant : (distanceAR * TARIF_PAR_KM);

                    updateDomElement('modal_distance_aller', distanceKm.toFixed(2));
                    updateDomElement('modal_distance_ar', distanceAR.toFixed(2));
                    updateDomElement('modal_montant', montantAffiche.toFixed(2));

                    const summaryEl = document.getElementById('modal_distance_summary');
                    if (summaryEl) {
                        summaryEl.innerHTML = `
                            <i class="bi bi-info-circle"></i> 
                            Distance aller : <strong>${distanceKm.toFixed(2).replace('.', ',')} km</strong>
                            | Aller-retour : <strong>${distanceAR.toFixed(2).replace('.', ',')} km</strong>
                            | Montant : <strong>${montantAffiche.toFixed(2).replace('.', ',')} DH</strong>
                        `;
                    }

                    // Centrage sur l'itinéraire trouvé
                    const group = L.featureGroup([departMarker, arriveeMarker]);
                    mapInstance.fitBounds(group.getBounds(), { padding: [50, 50] });
                });

                // Gestion des erreurs de routing (CORS, Timeout, etc.)
                routingControl.on('routingerror', function (e) {
                    console.error("Erreur Routing OSRM:", e);
                    // console.warn("Routing OSRM échoué, passage au fallback (vol d'oiseau).");

                    // Fallback : Distance à vol d'oiseau
                    const distanceVolOiseau = depLatLng.distanceTo(arrLatLng) / 1000; // en km
                    const distanceKm = distanceVolOiseau * 1.3; // Estimation route (~ +30%)
                    const distanceAR = distanceKm * 2;
                    const montantAffiche = montant > 0 ? montant : (distanceAR * TARIF_PAR_KM);

                    // Tracer une ligne droite pointillée (SUPPRIMÉ)
                    /*
                    L.polyline([depLatLng, arrLatLng], {
                        color: '#ff7800',
                        weight: 3,
                        opacity: 0.7,
                        dashArray: '10, 10'
                    }).addTo(mapInstance);
                    */

                    // Mettre à jour l'UI avec mention "Estimation"
                    updateDomElement('modal_distance_aller', distanceKm.toFixed(2) + ' (est.)');
                    updateDomElement('modal_distance_ar', distanceAR.toFixed(2) + ' (est.)');
                    updateDomElement('modal_montant', montantAffiche.toFixed(2));

                    const summaryEl = document.getElementById('modal_distance_summary');
                    if (summaryEl) {
                        summaryEl.innerHTML = `
                            <i class="bi bi-exclamation-triangle text-warning"></i> 
                            Distance aller : <strong>${distanceKm.toFixed(2).replace('.', ',')} km (est.)</strong>
                            | Aller-retour : <strong>${distanceAR.toFixed(2).replace('.', ',')} km (est.)</strong>
                            | Montant : <strong>${montantAffiche.toFixed(2).replace('.', ',')} DH</strong>
                        `;
                    }

                    // Centrage sur les points (puisque pas de route)
                    const group = L.featureGroup([departMarker, arriveeMarker]);
                    mapInstance.fitBounds(group.getBounds(), { padding: [50, 50] });
                });

            } else {
                console.error("Impossible de résoudre les points:", depart, arrivee);
                alert("Erreur: Impossible de localiser l'adresse de départ ou d'arrivée.");
            }
        });
    });
}

// Helper pour mettre à jour le texte sans planter si l'ID n'existe pas
function updateDomElement(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}
