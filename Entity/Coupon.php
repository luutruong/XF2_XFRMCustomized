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
use Truonglv\XFRMCustomized\GlobalStatic;

/**
 * Class Coupon
 * @package Truonglv\XFRMCustomized\Entity
 *
 * @property int coupon_id
 * @property string coupon_code
 * @property string title
 * @property int created_date
 * @property int begin_date
 * @property int end_date
 * @property int max_use_count
 * @property int used_count
 * @property array apply_rules
 * @property string discount_unit
 * @property int discount_amount
 * @property int user_id
 * @property string username
 */
class Coupon extends Entity
{
    /**
     * @param null|string $error
     * @return bool
     */
    public function canView(&$error = null)
    {
        return GlobalStatic::hasPermission('viewItem');
    }

    /**
     * @param null|string $error
     * @return bool
     */
    public function canEdit(&$error = null)
    {
        return GlobalStatic::hasPermission('editCoupon');
    }

    /**
     * @param null|string $error
     * @return bool
     */
    public function canDelete(&$error = null)
    {
        return GlobalStatic::hasPermission('deleteCoupon');
    }

    /**
     * @param ResourceItem $resourceItem
     * @param null|string $error
     * @param User|null $purchaser
     * @return bool
     */
    public function canUseWith(ResourceItem $resourceItem, &$error = null, User $purchaser = null)
    {
        $purchaser = $purchaser ?: \XF::visitor();

        if ($this->begin_date >= \XF::$time
            || $this->end_date <= \XF::$time
        ) {
            // not begin or expired
            return false;
        }

        if ($this->used_count >= $this->max_use_count) {
            // reached the limit
            return false;
        }

        $couponUser = $this->em()->findOne('Truonglv\XFRMCustomized:CouponUser', [
            'coupon_id' => $this->coupon_id,
            'resource_id' => $resourceItem->resource_id,
            'user_id' => $purchaser->user_id
        ]);

        if ($couponUser) {
            $error = \XF::phrase('xfrmc_this_code_is_only_valid_once_per_resource');

            return false;
        }

        $rules = $this->apply_rules;
        if (count($rules['usable_user_group_ids']) === 0
            && count($rules['resource_ids']) === 0
        ) {
            // no rules.
            return false;
        }

        if (count($rules['usable_user_group_ids']) > 0
            && !$purchaser->isMemberOf($rules['usable_user_group_ids'])
        ) {
            return false;
        }

        if (count($rules['category_ids']) > 0
            && !in_array($resourceItem->resource_category_id, $rules['category_ids'], true)
        ) {
            $error = \XF::phrase('xfrmc_resource_category_not_discountable');

            return false;
        }

        if (count($rules['resource_ids']) > 0
            && !in_array($resourceItem->resource_id, $rules['resource_ids'], true)
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

        if ($existCoupon && $existCoupon->coupon_id !== $this->coupon_id) {
            $this->error(\XF::phrase('xfrmc_coupon_code_x_not_available', [
                'code' => $value
            ]));

            return false;
        }

        return true;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'tl_xfrm_coupon';
        $structure->primaryKey = 'coupon_id';
        $structure->shortName = 'Truonglv\XFRMCustomized:Coupon';
        $structure->columns = [
            'coupon_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'coupon_code' => ['type' => self::STR, 'required' => true, 'maxLength' => 25],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'begin_date' => ['type' => self::UINT, 'required' => true],
            'end_date' => ['type' => self::UINT, 'required' => true],
            'max_use_count' => ['type' => self::UINT, 'default' => 0],
            'used_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0],
            'apply_rules' => ['type' => self::JSON_ARRAY, 'default' => []],
            'discount_unit' => ['type' => self::STR, 'allowedValues' => ['percent', 'fixed'], 'default' => 'percent'],
            'discount_amount' => ['type' => self::UINT, 'default' => 0],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'required' => true, 'maxLength' => 50]
        ];

        $structure->relations = [
            'User' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];

        return $structure;
    }

    protected function _preSave()
    {
        if ($this->end_date <= $this->begin_date) {
            throw new \LogicException('End-date must be great than begin-date');
        }
    }

    protected function _postDelete()
    {
        $this->db()->delete('tl_xfrm_coupon_user', 'coupon_id = ?', $this->coupon_id);
    }
}
