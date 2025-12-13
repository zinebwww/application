<?php
// Determine header based on role (simple include logic)
// This file is standalone but needs auth
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php'; // Default to admin check for now, can be adjusted

// Check session to decide which header to load
if (session_status() === PHP_SESSION_NONE)
    session_start();

$role = $_SESSION['user_role'] ?? '';

if ($role === 'admin') {
    // Already included auth_admin
    requireAdmin(); // Security check
    include __DIR__ . '/../admin/header.php';
} elseif ($role === 'manager') {
    require_once __DIR__ . '/../../../config/auth.php';
    requireManager();
    include __DIR__ . '/../manager/header.php';
} else {
    // Employee access? Maybe useful too.
    require_once __DIR__ . '/../../../config/auth.php';
    requireEmploye();
    include __DIR__ . '/../employee/header.php';
}
?>

<!-- Leaflet CSS & MarkerCluster -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
<style>
    #globalMap {
        height: 75vh;
        width: 100%;
        border-radius: 15px;
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
        z-index: 1;
    }

    .stats-overlay {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .stat-item h3 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0;
    }

    .stat-item p {
        opacity: 0.8;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .pulse-icon {
        background-color: #ff3d00;
        border-radius: 50%;
        width: 15px;
        height: 15px;
        box-shadow: 0 0 0 rgba(255, 61, 0, 0.4);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(255, 61, 0, 0.7);
        }

        70% {
            transform: scale(1);
            box-shadow: 0 0 0 10px rgba(255, 61, 0, 0);
        }

        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(255, 61, 0, 0);
        }
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-6 fw-bold text-primary"><i class="bi bi-globe-europe-africa"></i> Carte Stratégique
                d'Activité</h1>
            <p class="text-muted">Visualisation géospatiale des déplacements et zones d'intervention.</p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4" id="statsContainer">
        <div class="col-md-4">
            <div class="stats-overlay d-flex align-items-center justify-content-between h-100">
                <div class="stat-item">
                    <h3 id="totalMissions">0</h3>
                    <p>Missions Cartographiées</p>
                </div>
                <i class="bi bi-pin-map fs-1 opacity-50"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-overlay d-flex align-items-center justify-content-between h-100"
                style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="stat-item">
                    <h3 id="activeUsers">0</h3>
                    <p>Collaborateurs Mobiles</p>
                </div>
                <i class="bi bi-people fs-1 opacity-50"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-overlay d-flex align-items-center justify-content-between h-100"
                style="background: linear-gradient(135deg, #FF416C 0%, #FF4B2B 100%);">
                <div class="stat-item">
                    <h3 id="topDest">...</h3>
                    <p>Top Destination</p>
                </div>
                <i class="bi bi-trophy fs-1 opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Map Container -->
    <div class="row">
        <div class="col-12">
            <div id="globalMap"></div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Init Map
        const map = L.map('globalMap').setView([31.7917, -7.0926], 6); // Centered on Morocco

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        const markers = L.markerClusterGroup({
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true
        });

        // Custom Icons
        const departIcon = L.divIcon({
            className: 'custom-div-icon',
            html: "<div style='background-color:#2a5298;width:12px;height:12px;border-radius:50%;border:2px solid white;'></div>",
            iconSize: [30, 42],
            iconAnchor: [15, 42]
        });

        // Fetch Data
        fetch('/V22/V2/public/api/geo_stats.php')
            .then(response => response.json())
            .then(data => {
                // Update Stats
                animateValue("totalMissions", 0, data.stats.total_points, 1000);
                animateValue("activeUsers", 0, data.stats.active_users, 1000);

                const tops = Object.keys(data.stats.top_destinations);
                if (tops.length > 0) {
                    document.getElementById('topDest').innerText = tops[0].split(',')[0].substr(0, 15);
                }

                // Add Markers
                data.points.forEach(point => {
                    const marker = L.marker([point.lat, point.lng], {
                        title: point.name
                    });

                    // Popup Content
                    const popupContent = `
                        <div class="p-2">
                            <h6 class="mb-1 fw-bold text-primary">${point.type === 'depart' ? '🛫 Départ' : '🏁 Arrivée'}</h6>
                            <p class="mb-1"><strong>${point.name}</strong></p>
                            <p class="mb-1 small text-muted"><i class="bi bi-person"></i> ${point.user}</p>
                            <p class="mb-0 small text-muted"><i class="bi bi-calendar"></i> ${new Date(point.date).toLocaleDateString()}</p>
                        </div>
                    `;
                    marker.bindPopup(popupContent);
                    markers.addLayer(marker);
                });

                map.addLayer(markers);

                if (data.points.length > 0) {
                    map.fitBounds(markers.getBounds(), { padding: [50, 50] });
                }
            })
            .catch(err => console.error('Error loading map data:', err));

        // Animation Helper
        function animateValue(id, start, end, duration) {
            if (start === end) return;
            const range = end - start;
            let current = start;
            const increment = end > start ? 1 : -1;
            const stepTime = Math.abs(Math.floor(duration / range));
            const obj = document.getElementById(id);
            const timer = setInterval(function () {
                current += increment;
                obj.innerHTML = current;
                if (current == end) {
                    clearInterval(timer);
                }
            }, stepTime > 0 ? stepTime : 10); // Minimum 10ms for safety
        }
    });
</script>

<?php
// Footer include based on role again? Or just generic close?
// Let's rely on the simple footer closes depending on structure, usually footer.php ends body/html.
// But we need to match the specific footers.
if ($role === 'admin') {
    // include __DIR__ . '/footer.php'; // Admin footer usually in same dir
}
// For safety, just close the tags if no footer is robustly available in that context using this hybrid file.
// Ideally, we should create 3 files (one per role) that include this content, but to be "Plug and Play" and single file:
?>
</body>

</html>