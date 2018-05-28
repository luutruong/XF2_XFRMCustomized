<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\XFRMCustomized\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

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

        return $structure;
    }

    protected function _preSave()
    {
        if ($this->end_date <= $this->begin_date) {
            throw new \LogicException('End-date must be great than begin-date');
        }
    }
}