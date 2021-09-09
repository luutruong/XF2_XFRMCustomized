<?php
/**
 * @license
 * Copyright 2019 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Repository;

use XF\Mvc\Entity\Repository;

class Report extends Repository
{
    /**
     * @param int $fromDate
     * @param int $toDate
     * @return array
     */
    public function getReportsData($fromDate, $toDate)
    {
        $db = $this->db();
        $records = $db->fetchAllKeyed('
            SELECT FROM_UNIXTIME(purchased_date, "%Y-%m-%d") AS date, SUM(amount) AS total_amount
            FROM xf_xfrmc_resource_purchase
            WHERE purchased_date >= ? AND purchased_date <= ?
            GROUP BY FLOOR(purchased_date/86400)
            ORDER BY purchased_date
        ', 'date', [$fromDate, $toDate]);

        $nextDate = floor($fromDate / 86400) * 86400;
        $results = [];
        $maxDate = floor($toDate / 86400) * 86400;

        while ($nextDate <= $maxDate) {
            $date = date('Y-m-d', (int) $nextDate);

            $results[] = [
                'date' => $date,
                'amount' => isset($records[$date]) ? $records[$date]['total_amount'] : 0
            ];

            $nextDate += 86400;
        }

        return $results;
    }
}
