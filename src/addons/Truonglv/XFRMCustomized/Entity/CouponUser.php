<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\XFRMCustomized\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CouponUser extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'tl_xfrm_coupon_user';
        $structure->primaryKey = ['user_id', 'coupon_id', 'resource_id'];
        $structure->shortName = 'Truonglv\XFRMCustomized:CouponUser';

        $structure->columns = [
            'user_id' => ['type' => self::UINT, 'required' => true],
            'coupon_id' => ['type' => self::UINT, 'required' => true],
            'resource_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'required' => true, 'maxLength' => 50],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
        ];

        $structure->relations = [
            'Coupon' => [
                'type' => self::TO_ONE,
                'entity' => 'Truonglv\XFRMCustomized:Coupon',
                'conditions' => 'coupon_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}