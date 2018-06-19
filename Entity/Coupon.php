<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\XFRMCustomized\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XFRM\Entity\ResourceItem;

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
 * @property string discount_uint
 * @property int discount_amount
 * @property int user_id
 * @property string username
 */
class Coupon extends Entity
{
    public function canView(&$error = null)
    {
        return true;
    }

    public function canEdit(&$error = null)
    {
        return true;
    }

    public function canDelete(&$error = null)
    {
        return true;
    }

    public function canUse(ResourceItem $resourceItem)
    {
        $visitor = \XF::visitor();

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

        $rules = $this->apply_rules;
        if (empty($rules['usable_user_group_ids']) && empty($rules['resource_ids'])) {
            // no rules.
            return false;
        }

        if (!empty($rules['usable_user_group_ids'])
            && !$visitor->isMemberOf($rules['usable_user_group_ids'])
        ) {
            return false;
        }

        if (!empty($rules['resource_ids'])
            && strpos($rules['resource_ids'], $resourceItem->resource_id) === false
        ) {
            return false;
        }

        return true;
    }

    public function verifyCouponCode(&$value)
    {
        if ($value === $this->getExistingValue('coupon_code') && $this->isUpdate()) {
            return true;
        }

        /** @var static $existCoupon */
        $existCoupon = $this->em()->findOne('Truonglv\XFRMCustomized:Coupon', [
            'coupon_code' => $value
        ]);

        if ($existCoupon || ($existCoupon && $existCoupon->coupon_id != $this->coupon_id)) {
            $this->error(\XF::phrase('tl_xfrm_customized.coupon_code_x_not_available', [
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
}