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

        $visitor = \XF::visitor();
        /** @var \DateTime|null $fromDate */
        $fromDate = $this->filter('from', 'datetime,obj,tz:' . $visitor->timezone);
        /** @var \DateTime|null $toDate */
        $toDate = $this->filter('to', 'datetime,obj,tz:' . $visitor->timezone);

        if ($toDate === null) {
            $toDate = new \DateTime('@' . time(), new \DateTimeZone($visitor->timezone));
            $toDate->modify('last day of this month');
        }
        if ($fromDate === null) {
            $fromDate = new \DateTime('@' . $toDate->getTimestamp(), new \DateTimeZone($visitor->timezone));
            $fromDate->modify('first day of this month');
        }

        $fromDate->setTime(0, 0, 0);
        $toDate->setTime(23,59, 59);

        /** @var \Truonglv\XFRMCustomized\Repository\Report $reportRepo */
        $reportRepo = $this->repository('Truonglv\XFRMCustomized:Report');
        $data = $reportRepo->getReportsData($fromDate->getTimestamp(), $toDate->getTimestamp());

        $dataJs = [];
        $totalAmount = 0;
        foreach ($data as $item) {
            $dataJs[] = [$item['date'], (int) $item['amount']];
            $totalAmount += $item['amount'];
        }

        $params = [
            'reports' => $data,
            'fromDate' => $fromDate->format('Y-m-d'),
            'toDate' => $toDate->format('Y-m-d'),
            'dataJs' => $dataJs,
            'totalAmount' => $totalAmount
        ];

        return $this->view('Truonglv\XFRMCustomized:Report\Index', 'xfrmc_report_index', $params);
    }
}
