<?php

class CalendarService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getEventsForUser($userId, $role)
    {
        $events = [];

        if ($role === 'employe') {
            $events = $this->getEmployeeEvents($userId);
        } elseif ($role === 'manager') {
            $events = $this->getManagerEvents($userId);
        } elseif ($role === 'admin') {
            $events = $this->getAdminEvents();
        }

        return $events;
    }

    // ----------------------------------------------------------------
    // EMPLOYEE LOGIC
    // ----------------------------------------------------------------
    private function getEmployeeEvents($userId)
    {
        $sql = "SELECT id, Objectif_mission as title, date_mission, date_soumission, statut, lieu_mission
                FROM demande_frais 
                WHERE user_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($rows as $row) {
            $status = $row['statut'];
            $color = '#ffc107'; // Default Yellow
            $textColor = '#000000';

            if (in_array($status, ['soumis', 'valide_manager'])) {
                $color = '#ffc107';
                $textColor = '#000000';
            } elseif (in_array($status, ['valide_admin', 'rembourse'])) {
                $color = '#198754';
                $textColor = '#ffffff';
            } elseif (in_array($status, ['refuse', 'rejete_manager', 'rejete_admin'])) {
                $color = '#dc3545';
                $textColor = '#ffffff';
            }

            $events[] = [
                'id' => 'emp_' . $row['id'],
                'title' => $this->formatTitle($row['title']),
                'start' => $row['date_mission'],
                // No end date (assumed 1 day)
                'allDay' => true,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => $textColor,
                'extendedProps' => [
                    'status' => $status,
                    'detail_id' => $row['id'],
                    'role_view' => 'employee',
                    'description' => "Lieu: " . $row['lieu_mission'] . "\nStatut: " . $status,
                    'is_rejected' => in_array($status, ['rejete_manager', 'rejete_admin', 'refuse'])
                ],
                'editable' => false
            ];
        }
        return $events;
    }

    // ----------------------------------------------------------------
    // MANAGER LOGIC
    // ----------------------------------------------------------------
    private function getManagerEvents($managerId)
    {
        $events = [];

        $sql = "SELECT d.id, d.user_id, u.nom, d.date_soumission, d.date_mission, d.statut, d.Objectif_mission, d.lieu_mission
                FROM demande_frais d
                JOIN users u ON d.user_id = u.id
                WHERE u.manager_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$managerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $status = $row['statut'];
            $color = '#6c757d';
            $textColor = '#ffffff';
            $titlePrefix = "";
            $isUrgent = false;
            $isRejected = false;

            if ($status === 'soumis') {
                $daysPending = $this->daysSince($row['date_soumission']);
                if ($daysPending > 3) {
                    $color = '#dc3545';
                    $titlePrefix = "⚠️ ";
                    $isUrgent = true;
                } else {
                    $color = '#6f42c1';
                }
            } elseif (in_array($status, ['valide_manager', 'valide_admin', 'rembourse'])) {
                $color = '#e9ecef';
                $textColor = '#495057';
            } elseif (in_array($status, ['rejete_manager', 'rejete_admin'])) {
                $color = '#343a40';
                $isRejected = true;
            }

            $events[] = [
                'id' => 'mgr_' . $row['id'],
                'title' => $titlePrefix . $row['nom'] . " - " . $row['Objectif_mission'],
                'start' => $row['date_mission'],
                'allDay' => true,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => $textColor,
                'extendedProps' => [
                    'status' => $status,
                    'detail_id' => $row['id'],
                    'role_view' => 'manager',
                    'description' => "Employé: " . $row['nom'] . "\nLieu: " . $row['lieu_mission'],
                    'is_urgent' => $isUrgent,
                    'is_rejected' => $isRejected
                ],
                'editable' => false
            ];
        }

        return $events;
    }

    // ----------------------------------------------------------------
    // ADMIN LOGIC
    // ----------------------------------------------------------------
    private function getAdminEvents()
    {
        $events = [];

        $sql = "SELECT d.id, u.nom, d.date_mission, d.statut, d.Objectif_mission, d.lieu_mission,
                       (SELECT date_changement FROM historique_statuts h WHERE h.demande_id = d.id AND h.statut = 'valide_manager' ORDER BY h.id DESC LIMIT 1) as date_valide_manager
                FROM demande_frais d
                JOIN users u ON d.user_id = u.id
                WHERE d.statut IN ('valide_manager', 'valide_admin', 'rembourse', 'rejete_admin')";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $status = $row['statut'];
            $color = '#6c757d';
            $textColor = '#ffffff';
            $titlePrefix = "";
            $isUrgent = false;
            $isRejected = false;

            if ($status === 'valide_manager') {
                $daysPending = 0;
                if (!empty($row['date_valide_manager'])) {
                    $daysPending = $this->daysSince($row['date_valide_manager']);
                }

                if ($daysPending > 3) {
                    $color = '#dc3545';
                    $titlePrefix = "⚠️ ";
                    $isUrgent = true;
                } else {
                    $color = '#6f42c1';
                }
            } elseif ($status === 'rembourse') {
                $color = '#198754';
            } elseif ($status === 'rejete_admin') {
                $color = '#343a40';
                $isRejected = true;
            } elseif ($status === 'valide_admin') {
                $color = '#6f42c1';
            }

            $events[] = [
                'id' => 'adm_' . $row['id'],
                'title' => $titlePrefix . $row['nom'] . " - " . $row['Objectif_mission'],
                'start' => $row['date_mission'],
                'allDay' => true,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => $textColor,
                'extendedProps' => [
                    'status' => $status,
                    'detail_id' => $row['id'],
                    'role_view' => 'admin',
                    'description' => "Employé: " . $row['nom'] . "\nLieu: " . $row['lieu_mission'],
                    'is_urgent' => $isUrgent,
                    'is_rejected' => $isRejected
                ],
                'editable' => false
            ];
        }

        return $events;
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------
    private function daysSince($dateString)
    {
        if (empty($dateString)) {
            return 0;
        }
        try {
            return (time() - strtotime($dateString)) / (60 * 60 * 24);
        } catch (Exception $e) {
            return 0;
        }
    }

    private function formatTitle($title)
    {
        if (strlen($title) > 30) {
            if (function_exists('mb_substr')) {
                return mb_substr($title, 0, 27) . "...";
            }
            return substr($title, 0, 27) . "...";
        }
        return $title;
    }
}
