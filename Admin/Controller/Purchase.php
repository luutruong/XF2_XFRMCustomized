<?php

namespace Truonglv\XFRMCustomized\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Admin\Controller\AbstractController;

class Purchase extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $finder = $this->finder('Truonglv\XFRMCustomized:Purchase');

        $page = $this->filterPage();
        $perPage = 20;

        $finder->with('Resource');
        $finder->with('User');
        $finder->order('purchased_date', 'DESC');

        $filters = $this->filter([
            'user_id' => 'uint'
        ]);

        if ($filters['user_id'] > 0) {
            $finder->where('user_id', $filters['user_id']);
        } else {
            unset($filters['user_id']);
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

    protected function getLinkPrefix(): string
    {
        return 'xfrmc-purchases';
    }
}
