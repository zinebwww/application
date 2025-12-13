<?php
// Wrapper for Admin
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth_admin.php'; // Ensures Admin session
// The shared file handles the inclusion of the header based on session role.
// We just need to ensure the session is active and role is correct before including.

// Force role if needed, or let shared handle it. Shared handles it.
include __DIR__ . '/../shared/strategic_map.php';
?>