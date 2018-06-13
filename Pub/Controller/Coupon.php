<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\XFRMCustomized\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class Coupon extends AbstractController
{
    public function actionIndex()
    {
    }

    public function actionAdd()
    {
        $coupon = $this->em()->create('Truonglv\XFRMCustomized:Coupon');
        return $this->getCouponForm($coupon);
    }

    public function actionEdit()
    {
    }

    protected function getCouponForm(\Truonglv\XFRMCustomized\Entity\Coupon $coupon)
    {
        $viewParams = [
            'coupon' => $coupon
        ];

        return $this->view(
            'Truonglv\XFRMCustomized:Coupon\Add',
            'xfrm_customized_coupon_add',
            $viewParams
        );
    }
}