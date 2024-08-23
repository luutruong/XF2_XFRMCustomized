<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Service\Coupon;

use XF;
use XF\Entity\User;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use Truonglv\XFRMCustomized\Entity\Coupon;

class CreatorService extends AbstractService
{
    use ValidateAndSavableTrait;

    protected Coupon $coupon;

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);

        $coupon = $app->em()->create(Coupon::class);
        $this->coupon = $coupon;

        $this->setUser(XF::visitor());
    }

    protected function setUser(User $user): void
    {
        $this->coupon->user_id = $user->user_id;
        $this->coupon->username = $user->username;
    }

    public function getCoupon(): Coupon
    {
        return $this->coupon;
    }

    public function setTitle(string $title): void
    {
        $this->coupon->title = $title;
    }

    public function setCouponCode(string $couponCode): void
    {
        $this->coupon->coupon_code = $couponCode;
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
        $db = $this->db();

        $db->beginTransaction();
        $coupon->save(true, false);
        $db->commit();

        return $coupon;
    }
}
