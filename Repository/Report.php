<?php
/**
 * @license
 * Copyright 2019 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Repository;

use XF\Mvc\Entity\Repository;

class Report extends Repository
{
    public function getReportsData(int $fromDate, int $toDate): array
    {
        $db = $this->db();
        $records = $db->fetchAll('
            SELECT purchased_date, amount
            FROM xf_xfrmc_resource_purchase
            WHERE purchased_date >= ? AND purchased_date <= ?
            ORDER BY purchased_date
        ', [$fromDate, $toDate]);

        $groups = [];
        $language = \XF::app()->userLanguage(\XF::visitor());
        foreach ($records as $record) {
            $date = $language->date($record['purchased_date'], 'Y-m-d');
            if (!isset($groups[$date])) {
                $groups[$date] = 0;
            }

            $groups[$date] += $record['amount'];
        }

        $nextDate = floor($fromDate / 86400) * 86400;
        $results = [];
        $maxDate = floor($toDate / 86400) * 86400;

        while ($nextDate <= $maxDate) {
            $date = $language->date($nextDate, 'Y-m-d');
            $results[] = [
                'date' => $date,
                'amount' => $groups[$date] ?? 0
            ];

            $nextDate += 86400;
        }

        return $results;
    }
}
