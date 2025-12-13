-- Migration : Ajout des colonnes manquantes dans details_frais
-- Date : 2025-12-03

ALTER TABLE `details_frais`
ADD COLUMN `moyen_transport` VARCHAR(50) DEFAULT NULL COMMENT 'voiture, train, avion, autre' AFTER `categorie_id`,
ADD COLUMN `currency` VARCHAR(3) DEFAULT 'MAD' COMMENT 'MAD, EUR, USD' AFTER `montant`,
ADD COLUMN `lat_depart` DECIMAL(10, 8) DEFAULT NULL COMMENT 'Latitude point départ' AFTER `point_depart`,
ADD COLUMN `lng_depart` DECIMAL(11, 8) DEFAULT NULL COMMENT 'Longitude point départ' AFTER `lat_depart`,
ADD COLUMN `lat_arrivee` DECIMAL(10, 8) DEFAULT NULL COMMENT 'Latitude point arrivée' AFTER `point_arrivee`,
ADD COLUMN `lng_arrivee` DECIMAL(11, 8) DEFAULT NULL COMMENT 'Longitude point arrivée' AFTER `lat_arrivee`;

