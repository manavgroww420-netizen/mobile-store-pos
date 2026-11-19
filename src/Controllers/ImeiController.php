<?php
declare(strict_types=1);

namespace AMH\Controllers;

use AMH\Auth;
use AMH\Authorization;
use AMH\Database;
use AMH\Security;
use AMH\Financial;
use AMH\View;

/**
 * IMEI Registry & Diagnostic Counter Search Controller
 *
 * Provides shop staff and owner with an instantaneous counter lookup
 * for any physical device by IMEI 1, IMEI 2, or serial number.
 * Displays physical hardware data, current active status, and entire commercial episode history.
 */
class ImeiController
{
    /**
     * Counter lookup search page and result inspector.
     */
    public function search(): void
    {
        Auth::requireLogin();

        $query = trim($_GET['q'] ?? '');
        $device = null;
        $activeEpisode = null;
        $historyEpisodes = [];
        $directCosts = [];
        $searchPerformed = ($query !== '');

        if ($searchPerformed) {
            // Clean query input (strip spaces, hyphens)
            $cleanQuery = preg_replace('/[^a-zA-Z0-9]/', '', $query);

            // 1. Search devices by IMEI 1, IMEI 2, or Serial Number
            $device = Database::fetchOne("
                SELECT * 
                FROM devices 
                WHERE imei_1 = :q1 OR imei_2 = :q2 OR serial_number = :q3
                LIMIT 1
            ", [
                ':q1' => $cleanQuery,
                ':q2' => $cleanQuery,
                ':q3' => $cleanQuery,
            ]);

            // If not matched, try matching by phone episode ID if query is numeric
            if (!$device && is_numeric($cleanQuery)) {
                $phone = Database::fetchOne("SELECT device_id FROM phones WHERE id = :pid", [':pid' => (int)$cleanQuery]);
                if ($phone) {
                    $device = Database::fetchOne("SELECT * FROM devices WHERE id = :did", [':did' => $phone['device_id']]);
                }
            }

            if ($device) {
                $deviceId = (int)$device['id'];

                // 2. Fetch all historical episodes for this physical hardware
                $historyEpisodes = Database::fetchAll("
                    SELECT ph.*, 
                           p.brand AS product_brand, p.model_name AS product_model, p.sku,
                           u.username AS operator_username, u.full_name AS operator_name
                    FROM phones ph
                    JOIN products p ON p.id = ph.product_id
                    LEFT JOIN users u ON u.id = ph.created_by
                    WHERE ph.device_id = :did
                    ORDER BY ph.id DESC
                ", [':did' => $deviceId]);

                // Identify active episode
                if (!empty($device['active_phone_id'])) {
                    foreach ($historyEpisodes as $ep) {
                        if ((int)$ep['id'] === (int)$device['active_phone_id']) {
                            $activeEpisode = $ep;
                            break;
                        }
                    }
                } elseif (!empty($historyEpisodes)) {
                    $activeEpisode = $historyEpisodes[0];
                }

                // 3. Fetch direct costs for active episode
                if ($activeEpisode) {
                    $directCosts = Database::fetchAll("
                        SELECT pdc.*, u.username AS added_by_username
                        FROM phone_direct_costs pdc
                        LEFT JOIN users u ON u.id = pdc.created_by
                        WHERE pdc.phone_id = :pid
                        ORDER BY pdc.cost_date DESC, pdc.id DESC
                    ", [':pid' => $activeEpisode['id']]);
                }
            }
        }

        // Recent device entries for counter quick-reference
        $recentDevices = Database::fetchAll("
            SELECT d.id, d.brand, d.model_name, d.imei_1, d.imei_2, d.active_phone_id, d.created_at,
                   ph.phone_type, ph.status, ph.expected_sale_price
            FROM devices d
            LEFT JOIN phones ph ON ph.id = d.active_phone_id
            ORDER BY d.id DESC
            LIMIT 10
        ");

        // Permissions
        $canViewCost = Authorization::hasPermission('view_purchase_costs');
        $canViewSensitiveId = Authorization::hasPermission('view_sensitive_id');

        // Apply financial cost suppression if unprivileged
        if (!$canViewCost) {
            if ($activeEpisode) {
                $activeEpisode['purchase_cost'] = null;
                $activeEpisode['repair_cost'] = null;
                $activeEpisode['other_cost'] = null;
                $activeEpisode['total_cost_basis'] = null;
            }

            foreach ($historyEpisodes as &$ep) {
                $ep['purchase_cost'] = null;
                $ep['repair_cost'] = null;
                $ep['other_cost'] = null;
                $ep['total_cost_basis'] = null;
            }
            unset($ep);

            foreach ($directCosts as &$dc) {
                $dc['amount'] = null;
            }
            unset($dc);
        }

        View::render('imei/search', [
            'pageTitle'          => 'IMEI Registry & Counter Search',
            'query'              => $query,
            'searchPerformed'    => $searchPerformed,
            'device'             => $device,
            'activeEpisode'      => $activeEpisode,
            'historyEpisodes'    => $historyEpisodes,
            'directCosts'        => $directCosts,
            'recentDevices'      => $recentDevices,
            'canViewCost'        => $canViewCost,
            'canViewSensitiveId' => $canViewSensitiveId,
        ], 'main');
    }
}
