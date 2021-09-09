<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XFRM\Entity\ResourceItem;

/**
 * COLUMNS
 * @property int|null coupon_id
 * @property string coupon_code
 * @property string title
 * @property int created_date
 * @property int begin_date
 * @property int end_date
 * @property int used_count
 * @property array criteria
 * @property string discount_unit
 * @property int discount_amount
 * @property int user_id
 * @property string username
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \XF\Mvc\Entity\AbstractCollection|\Truonglv\XFRMCustomized\Entity\CouponUser[] CouponUsers
 */
class Coupon extends Entity
{
    /**
     * @param mixed $error
     * @return bool
     */
    public function canView(&$error = null)
    {
        return true;
    }

    public function isActive(): bool
    {
        if ($this->begin_date > 0 && $this->begin_date > \XF::$time) {
            return false;
        }

        if ($this->end_date > 0 && $this->end_date <= \XF::$time) {
            return false;
        }

        return true;
    }

    /**
     * @param ResourceItem $resourceItem
     * @param mixed $error
     * @param User|null $purchaser
     * @return bool
     */
    public function canUseWith(ResourceItem $resourceItem, &$error = null, User $purchaser = null)
    {
        $purchaser = $purchaser !== null ? $purchaser : \XF::visitor();
        if ($purchaser->user_id <= 0) {
            return false;
        }

        if (!$this->isActive()) {
            return false;
        }

        $couponUser = $this->em()->findOne('Truonglv\XFRMCustomized:CouponUser', [
            'coupon_id' => $this->coupon_id,
            'resource_id' => $resourceItem->resource_id,
            'user_id' => $purchaser->user_id
        ]);

        if ($couponUser !== null) {
            $error = \XF::phrase('xfrmc_this_code_is_only_valid_once_per_resource');

            return false;
        }

        $criteria = $this->criteria;
        if (!isset($criteria['user']) || !isset($criteria['resource'])) {
            // OLD coupon code
            return false;
        }

        if (isset($criteria['limit'])) {
            $total = $criteria['limit']['total'];
            $perUser = $criteria['limit']['per_user'];

            if ($total >= 0 && $this->used_count >= $total) {
                return false;
            }

            if ($perUser >= 0) {
                $userTotal = $this->finder('Truonglv\XFRMCustomized:CouponUser')
                    ->where('coupon_id', $this->coupon_id)
                    ->where('user_id', $purchaser->user_id)
                    ->total();
                if ($userTotal >= $perUser) {
                    $error = \XF::phrase('xfrmc_you_reached_maximum_times_used_this_coupon_code');

                    return false;
                }
            }
        }

        $userCriteria = $this->app()->criteria('XF:User', $criteria['user']);
        if (!$userCriteria->isMatched($purchaser)) {
            return false;
        }

        if (count($criteria['resource']['category_ids']) > 0
            && !in_array($resourceItem->resource_category_id, $criteria['resource']['category_ids'], true)
        ) {
            $error = \XF::phrase('xfrmc_resource_category_not_discountable');

            return false;
        }

        if (count($criteria['resource']['resource_ids']) > 0
            && !in_array($resourceItem->resource_id, $criteria['resource']['resource_ids'], true)
        ) {
            $error = \XF::phrase('xfrmc_resource_not_discountable');

            return false;
        }

        return true;
    }

    /**
     * @param ResourceItem $resourceItem
     * @return float
     */
    public function getFinalPrice(ResourceItem $resourceItem)
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $mixed */
        $mixed = $resourceItem;
        $price = $mixed->getPurchasePrice();

        if ($this->discount_unit === 'fixed') {
            $price = max(0, $price - $this->discount_amount);
        } else {
            $price -= ($price * $this->discount_amount)/100;
        }

        return $price;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function verifyCouponCode(&$value)
    {
        if ($value === $this->getExistingValue('coupon_code') && $this->isUpdate()) {
            return true;
        }

        /** @var static|null $existCoupon */
        $existCoupon = $this->em()->findOne('Truonglv\XFRMCustomized:Coupon', [
            'coupon_code' => $value
        ]);

        if ($existCoupon !== null && $existCoupon->coupon_id !== $this->coupon_id) {
            $this->error(\XF::phrase('xfrmc_coupon_code_x_not_available', [
                'code' => $value
            ]));

            return false;
        }

        return true;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xfrmc_coupon';
        $structure->primaryKey = 'coupon_id';
        $structure->shortName = 'Truonglv\XFRMCustomized:Coupon';
        $structure->columns = [
            'coupon_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'coupon_code' => ['type' => self::STR, 'required' => true, 'maxLength' => 25],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'begin_date' => ['type' => self::UINT, 'required' => true],
            'end_date' => ['type' => self::UINT, 'default' => 0],
            'used_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0],
            'criteria' => ['type' => self::JSON_ARRAY, 'default' => []],
            'discount_unit' => ['type' => self::STR, 'allowedValues' => ['percent', 'fixed'], 'default' => 'percent'],
            'discount_amount' => ['type' => self::UINT, 'default' => 0],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'required' => true, 'maxLength' => 50],
        ];

        $structure->relations = [
            'User' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true
            ],
            'CouponUsers' => [
                'type' => self::TO_MANY,
                'entity' => 'Truonglv\XFRMCustomized:CouponUser',
                'conditions' => 'coupon_id',
            ],
        ];

        return $structure;
    }

    protected function _preSave()
    {
        if ($this->end_date > 0 && $this->end_date <= $this->begin_date) {
            throw new \LogicException('End-date must be great than begin-date');
        }
    }

    protected function _postDelete()
    {
        $this->db()->delete('xf_xfrmc_coupon_user', 'coupon_id = ?', $this->coupon_id);
    }
}
