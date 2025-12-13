<?php

class MapIntelligence
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getGeoData($role, $userId = null): array
    {
        $params = [];
        $sql = "SELECT 
                    df.lat_depart, df.lng_depart, 
                    df.lat_arrivee, df.lng_arrivee,
                    df.montant, df.point_depart, df.point_arrivee,
                    d.date_mission, u.nom as employe
                FROM details_frais df
                JOIN demande_frais d ON df.demande_id = d.id
                JOIN users u ON d.user_id = u.id
                WHERE df.lat_depart IS NOT NULL 
                AND df.lng_depart IS NOT NULL";

        // Filtering based on role
        if ($role === 'manager' && $userId) {
            $sql .= " AND u.manager_id = ?";
            $params[] = $userId;
        } elseif ($role === 'employe' && $userId) {
            $sql .= " AND d.user_id = ?";
            $params[] = $userId;
        }

        // Fetch data
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $points = [];
        $totalDistance = 0; // Simulated if not stored
        $cities = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // Add Departure Point
            $points[] = [
                'type' => 'depart',
                'lat' => $row['lat_depart'],
                'lng' => $row['lng_depart'],
                'name' => $row['point_depart'],
                'user' => $row['employe'],
                'date' => $row['date_mission'],
                'amount' => $row['montant']
            ];

            // Add Arrival Point (if different)
            if (!empty($row['lat_arrivee'])) {
                $points[] = [
                    'type' => 'arrivee',
                    'lat' => $row['lat_arrivee'],
                    'lng' => $row['lng_arrivee'],
                    'name' => $row['point_arrivee'],
                    'user' => $row['employe'],
                    'date' => $row['date_mission'],
                    'amount' => 0 // Attribution only once
                ];

                // Extract City rough approximation or just use label
                $cities[$row['point_arrivee']] = ($cities[$row['point_arrivee']] ?? 0) + 1;
            }
        }

        // Sort Top Destinations
        arsort($cities);
        $topDestinations = array_slice($cities, 0, 5);

        return [
            'points' => $points,
            'stats' => [
                'total_points' => count($points),
                'top_destinations' => $topDestinations,
                'active_users' => count(array_unique(array_column($points, 'user')))
            ]
        ];
    }
}
