<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Service\Coupon;

use XF\Entity\User;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use Truonglv\XFRMCustomized\Entity\Coupon;

class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    /**
     * @var Coupon
     */
    protected $coupon;

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);

        /** @var Coupon $coupon */
        $coupon = $app->em()->create('Truonglv\XFRMCustomized:Coupon');
        $this->coupon = $coupon;

        $this->setUser(\XF::visitor());
    }

    protected function setUser(User $user)
    {
        $this->coupon->user_id = $user->user_id;
        $this->coupon->username = $user->username;
    }

    /**
     * @return Coupon
     */
    public function getCoupon()
    {
        return $this->coupon;
    }

    public function setTitle($title)
    {
        $this->coupon->title = $title;
    }

    public function setCouponCode($couponCode)
    {
        $this->coupon->coupon_code = $couponCode;
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
        $db = $this->db();

        $db->beginTransaction();
        $coupon->save(true, false);
        $db->commit();

        return $coupon;
    }
}
