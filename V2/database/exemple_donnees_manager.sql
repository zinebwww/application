-- Exemple de données pour tester l'espace Manager
-- À exécuter après avoir créé la structure de base de données

-- Insérer un manager
INSERT INTO `users` (`nom`, `email`, `mot_de_passe`, `role`, `manager_id`) VALUES
('Jean Dupont', 'manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', NULL);
-- Mot de passe : "password" (hashé avec password_hash)

-- Récupérer l'ID du manager (remplacer X par l'ID réel après insertion)
-- SELECT id FROM users WHERE email = 'manager@example.com';

-- Insérer des employés liés au manager (remplacer X par l'ID du manager)
INSERT INTO `users` (`nom`, `email`, `mot_de_passe`, `role`, `manager_id`) VALUES
('Marie Martin', 'marie.martin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employe', X),
('Pierre Durand', 'pierre.durand@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employe', X),
('Sophie Bernard', 'sophie.bernard@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employe', X);
-- Mot de passe pour tous : "password"

-- Insérer des catégories de frais (si pas déjà fait)
INSERT INTO `categories_frais` (`nom`) VALUES
('Transport'),
('Hébergement'),
('Restauration'),
('Parking'),
('Péage'),
('Autre');

-- Exemple de demande de frais (remplacer Y par l'ID d'un employé)
INSERT INTO `demande_frais` (`user_id`, `objectif_mission`, `lieu_mission`, `date_mission`, `statut`) VALUES
(Y, 'Formation PHP avancée', 'Paris', '2025-12-01', 'soumis');

-- Récupérer l'ID de la demande (remplacer Z par l'ID réel)
-- SELECT id FROM demande_frais WHERE user_id = Y LIMIT 1;

-- Exemple de détails de frais (remplacer Z par l'ID de la demande)
INSERT INTO `details_frais` (`demande_id`, `categorie_id`, `date_depense`, `montant`, `description`) VALUES
(Z, 1, '2025-12-01', 45.50, 'Train aller-retour'),
(Z, 3, '2025-12-01', 25.00, 'Déjeuner'),
(Z, 1, '2025-12-01', 12.50, 'Métro');

