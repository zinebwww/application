<?php
// Determine session/role
require_once __DIR__ . '/../../../config/config.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();
$role = $_SESSION['user_role'] ?? '';

// Include appropriate header based on role logic
if ($role === 'admin') {
    require_once __DIR__ . '/../../../config/auth_admin.php';
    requireAdmin();
    include __DIR__ . '/../admin/header.php';
} elseif ($role === 'manager') {
    require_once __DIR__ . '/../../../config/auth.php';
    requireManager();
    include __DIR__ . '/../manager/header.php';
} else {
    // Fallback or employee
    require_once __DIR__ . '/../../../config/auth.php';
    include __DIR__ . '/../employee/header.php';
}
?>

<!-- Leaflet CSS & MarkerCluster -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js.map" />
<!-- Optional -->
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

    /* Animation pour la Top Destination */
    .pulse-ring {
        border: 3px solid #FFD700;
        border-radius: 50%;
        height: 40px;
        width: 40px;
        position: absolute;
        left: -12px;
        top: -12px;
        -webkit-animation: pulsate 2s ease-out;
        -webkit-animation-iteration-count: infinite;
        opacity: 0.0;
        z-index: 100 !important;
    }

    @-webkit-keyframes pulsate {
        0% {
            -webkit-transform: scale(0.1, 0.1);
            opacity: 0.0;
        }

        50% {
            opacity: 1.0;
        }

        100% {
            -webkit-transform: scale(1.2, 1.2);
            opacity: 0.0;
        }
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-6 fw-bold text-primary"><i class="bi bi-globe-europe-africa"></i> Carte Stratégique</h1>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4" id="statsContainer">
        <div class="col-md-4">
            <div class="stats-overlay d-flex align-items-center justify-content-between h-100">
                <div class="stat-item">
                    <h3 id="totalMissions">0</h3>
                    <p>Missions</p>
                </div>
                <i class="bi bi-pin-map fs-1 opacity-50"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-overlay d-flex align-items-center justify-content-between h-100"
                style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="stat-item">
                    <h3 id="activeUsers">0</h3>
                    <p>Collaborateurs</p>
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
        const map = L.map('globalMap').setView([31.7917, -7.0926], 6); // Morocco

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: 'Map data &copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        const markers = L.markerClusterGroup();

        // Use absolute path relative to web root or relative calculation
        // Try relative first which works for localhost/V22/V2/app/Views/admin/map.php
        // Path to public: ../../../public/api/geo_stats.php
        fetch('../../../public/api/geo_stats.php')
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                // Update Stats
                document.getElementById('totalMissions').innerText = data.stats.total_points;
                document.getElementById('activeUsers').innerText = data.stats.active_users;

                let topDestName = "";
                const tops = Object.keys(data.stats.top_destinations);
                if (tops.length > 0) {
                    topDestName = tops[0]; // Full name for comparison
                    // Display short name in card
                    document.getElementById('topDest').innerText = topDestName.split(',')[0].substr(0, 15);
                }

                // Custom Gold Icon for Top Destination (PLUS VISIBLE)
                const goldIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: `
                        <div class="pulse-ring"></div>
                        <div style='background-color:#FFD700;width:20px;height:20px;border-radius:50%;border:3px solid white;box-shadow: 0 0 15px #FFD700; position:relative; z-index:1001;'></div>
                        <span style='position:absolute;top:-35px;left:-15px;font-size:35px; z-index:1002; filter: drop-shadow(0 0 5px rgba(0,0,0,0.5));'>👑</span>
                    `,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });

                // Add Markers
                data.points.forEach(point => {
                    let markerOptions = { title: point.name };

                    // Highlight if this is the Top Destination ARRIVAL
                    if (point.type === 'arrivee' && topDestName && point.name === topDestName) {
                        markerOptions.icon = goldIcon;
                        markerOptions.zIndexOffset = 2000; // Put really on top
                    }

                    const marker = L.marker([point.lat, point.lng], markerOptions);

                    let badge = point.type === 'depart' ? '🛫 Départ' : '🏁 Arrivée';
                    if (point.name === topDestName && point.type === 'arrivee') {
                        badge = '👑 TOP DESTINATION';

                        // AUTO ZOOM ON TOP DESTINATION
                        setTimeout(() => {
                            map.flyTo([point.lat, point.lng], 12, {
                                animate: true,
                                duration: 1.5
                            });
                            marker.openPopup();
                        }, 1000);
                    }

                    marker.bindPopup(`<b>${badge}</b><br>${point.name}<br><small>👤 ${point.user}</small>`);
                    markers.addLayer(marker);
                });
                map.addLayer(markers);
                if (data.points.length > 0) map.fitBounds(markers.getBounds());
            })
            .catch(err => console.error("Map Error:", err));
    });
</script>
</body>

</html>