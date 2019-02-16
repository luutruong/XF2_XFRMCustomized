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

        $fromDate = $this->filter('from', 'datetime');
        $toDate = $this->filter('to', 'datetime');

        if (!$toDate) {
            $toDate = \XF::$time;
        }
        if (!$fromDate) {
            $fromDate = $toDate - 30 * 86400;
        }

        /** @var \Truonglv\XFRMCustomized\Repository\Report $reportRepo */
        $reportRepo = $this->repository('Truonglv\XFRMCustomized:Report');
        $data = $reportRepo->getReportsData($fromDate, $toDate);

        $dataJs = [];
        foreach ($data as $item) {
            $dataJs[] = [$item['date'], (int) $item['amount']];
        }

        $params = [
            'reports' => $data,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'dataJs' => $dataJs
        ];

        return $this->view('Truonglv\XFRMCustomized:Report\Index', 'xfrmc_report_index', $params);
    }
}
