<?php
/**
 * @license
 * Copyright 2019 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class Report extends AbstractController
{
    public function actionIndex()
    {
        if (!\XF::visitor()->hasPermission('general', 'xfrmc_viewReports')) {
            return $this->noPermission();
        }

        $dateStringToTimestamp = function ($date) {
            $dt = \DateTime::createFromFormat('Y-m-d', $date);
            if ($dt !== false) {
                return $dt->format('U');
            }

            return 0;
        };
        $fromDate = $dateStringToTimestamp($this->filter('from', 'str'));
        $toDate = $dateStringToTimestamp($this->filter('to', 'str'));

        if (!$toDate) {
            $dt = new \DateTime();
            $dt->modify('last day of this month');

            $toDate = $dt->format('U');
        }
        if (!$fromDate) {
            $dt = new \DateTime('@' . $toDate);
            $dt->modify('first day of this month');

            $fromDate = $dt->format('U');
        }

        /** @var \Truonglv\XFRMCustomized\Repository\Report $reportRepo */
        $reportRepo = $this->repository('Truonglv\XFRMCustomized:Report');
        $data = $reportRepo->getReportsData($fromDate, $toDate);

        $dataJs = [];
        $totalAmount = 0;
        foreach ($data as $item) {
            $dataJs[] = [$item['date'], (int) $item['amount']];
            $totalAmount += $item['amount'];
        }

        $params = [
            'reports' => $data,
            'fromDate' => date('Y-m-d', $fromDate),
            'toDate' => date('Y-m-d', $toDate),
            'dataJs' => $dataJs,
            'totalAmount' => $totalAmount
        ];

        return $this->view('Truonglv\XFRMCustomized:Report\Index', 'xfrmc_report_index', $params);
    }
}
