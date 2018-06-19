<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\XFRMCustomized\Service\Coupon;

use Truonglv\XFRMCustomized\Entity\Coupon;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class Editor extends AbstractService
{
    use ValidateAndSavableTrait;

    protected $coupon;

    public function __construct(\XF\App $app, Coupon $coupon)
    {
        parent::__construct($app);

        $this->coupon = $coupon;
    }

    /**
     * @return Coupon
     */
    public function getCoupon()
    {
        return $this->coupon;
    }

    protected function _validate()
    {
        $coupon = $this->coupon;
        $coupon->preSave();

        return $coupon->getErrors();
    }

    protected function _save()
    {
        $coupon = $this->coupon;

        $coupon->save();

        return $coupon;
    }
}