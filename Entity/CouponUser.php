<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null coupon_user_id
 * @property int user_id
 * @property int coupon_id
 * @property int resource_id
 * @property string username
 * @property int created_date
 * @property int purchase_id
 *
 * RELATIONS
 * @property \Truonglv\XFRMCustomized\Entity\Coupon Coupon
 * @property \XF\Entity\User User
 * @property \XFRM\Entity\ResourceItem Resource
 */
class CouponUser extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xfrmc_coupon_user';
        $structure->primaryKey = 'coupon_user_id';
        $structure->shortName = 'Truonglv\XFRMCustomized:CouponUser';

        $structure->columns = [
            'coupon_user_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'coupon_id' => ['type' => self::UINT, 'required' => true],
            'resource_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'required' => true, 'maxLength' => 50],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'purchase_id' => ['type' => self::UINT, 'default' => 0]
        ];

        $structure->relations = [
            'Coupon' => [
                'type' => self::TO_ONE,
                'entity' => 'Truonglv\XFRMCustomized:Coupon',
                'conditions' => 'coupon_id',
                'primary' => true
            ],
            'User' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Resource' => [
                'type' => self::TO_ONE,
                'entity' => 'XFRM:ResourceItem',
                'conditions' => 'resource_id',
                'primary' => true
            ]
        ];

        return $structure;
    }

    protected function _postSave()
    {
        if ($this->isInsert() && $this->Coupon !== null) {
            $this->Coupon->used_count++;
            $this->Coupon->save();
        }
    }

    protected function _postDelete()
    {
        if ($this->Coupon !== null) {
            $this->Coupon->used_count--;
            $this->Coupon->save();
        }
    }
}
