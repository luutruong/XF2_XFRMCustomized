<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Service\Coupon;

use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use Truonglv\XFRMCustomized\Entity\Coupon;

class Editor extends AbstractService
{
    use ValidateAndSavableTrait;

    /**
     * @var Coupon
     */
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

    /**
     * @return array
     */
    protected function _validate()
    {
        $coupon = $this->coupon;
        $coupon->preSave();

        return $coupon->getErrors();
    }

    /**
     * @return Coupon
     * @throws \XF\PrintableException
     */
    protected function _save()
    {
        $coupon = $this->coupon;

        $coupon->save();

        return $coupon;
    }
}
