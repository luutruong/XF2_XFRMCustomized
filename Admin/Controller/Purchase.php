<?php

namespace Truonglv\XFRMCustomized\Admin\Controller;

use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Admin\Controller\AbstractController;

class Purchase extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        if ($params['purchase_id'] > 0) {
            return $this->rerouteController(__CLASS__, 'view', $params);
        }

        $finder = $this->finder('Truonglv\XFRMCustomized:Purchase');

        $page = $this->filterPage();
        $perPage = 20;

        $finder->with('Resource');
        $finder->with('User');
        $finder->order('purchased_date', 'DESC');

        $filters = $this->filter([
            'user_id' => 'uint',
            'username' => 'str',
            'resource_id' => 'uint',
        ]);

        if ($filters['username'] !== '') {
            /** @var User|null $user */
            $user = $this->em()->findOne('XF:User', [
                'username' => $filters['username']
            ]);
            if ($user === null) {
                $finder->whereImpossible();
            } else {
                $filters['user_id'] = $user->user_id;
            }
        } else {
            unset($filters['username']);
        }

        if ($filters['user_id'] > 0) {
            $finder->where('user_id', $filters['user_id']);
        } else {
            unset($filters['user_id']);
        }

        if ($filters['resource_id'] > 0) {
            $finder->where('resource_id', $filters['resource_id']);
        } else {
            unset($filters['resource_id']);
        }

        $total = $finder->total();
        $purchases = $finder->limitByPage($page, $perPage)->fetch();

        return $this->view(
            'Truonglv\XFRMCustomized:Purchase\List',
            'xfrmc_purchase_list',
            [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'purchases' => $purchases,
                'filters' => $filters,
                'linkPrefix' => $this->getLinkPrefix(),
            ]
        );
    }

    public function actionView(ParameterBag $params)
    {
        $purchase = $this->assertPurchaseExists($params['purchase_id']);

        return $this->view(
            'Truonglv\XFRMCustomized:Purchase\View',
            'xfrmc_purchase_view',
            [
                'purchase' => $purchase,
                'linkPrefix' => $this->getLinkPrefix(),
            ]
        );
    }

    public function actionEdit(ParameterBag $params)
    {
        $purchase = $this->assertPurchaseExists($params['purchase_id']);

        $resourceVersions = $this->finder('XFRM:ResourceVersion')
            ->where('resource_id', $purchase->resource_id)
            ->order('release_date', 'desc')
            ->fetch();

        if ($this->isPost()) {
            $inputData = $this->filter([
                'amount' => 'float',
                'resource_version_id' => 'uint',
                'expire_type' => 'str',
                'note' => 'str',
            ]);

            if (!isset($resourceVersions[$inputData['resource_version_id']])) {
                return $this->error(\XF::phrase('xfrmc_please_select_valid_resource_version'));
            }
            if ($inputData['expire_type'] === 'update') {
                $inputData['expire_date'] = $this->filter('expire_date', 'datetime');
            }
            unset($inputData['expire_type']);

            $purchase->bulkSet($inputData);
            $purchase->save();

            return $this->redirect($this->buildLink($this->getLinkPrefix(), $purchase));
        }

        return $this->view(
            'Truonglv\XFRMCustomized:Purchase\Edit',
            'xfrmc_purchase_edit',
            [
                'purchase' => $purchase,
                'linkPrefix' => $this->getLinkPrefix(),
                'resourceVersions' => $resourceVersions,
            ]
        );
    }

    public function actionReports()
    {
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
        $toDate->setTime(23, 59, 59);

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
            'totalAmount' => $totalAmount,
            'linkPrefix' => $this->getLinkPrefix(),
        ];

        return $this->view('Truonglv\XFRMCustomized:Purchase\Report', 'xfrmc_purchase_report', $params);
    }

    protected function assertPurchaseExists(int $purchaseId): \Truonglv\XFRMCustomized\Entity\Purchase
    {
        /** @var \Truonglv\XFRMCustomized\Entity\Purchase $purchase */
        $purchase = $this->assertRecordExists('Truonglv\XFRMCustomized:Purchase', $purchaseId);

        return $purchase;
    }

    protected function getLinkPrefix(): string
    {
        return 'xfrmc-purchases';
    }
}
