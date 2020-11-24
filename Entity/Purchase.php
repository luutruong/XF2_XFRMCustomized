<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XFRM\Entity\ResourceVersion;

/**
 * COLUMNS
 * @property int|null purchase_id
 * @property int resource_id
 * @property int user_id
 * @property string username
 * @property int resource_version_id
 * @property float amount
 * @property int expire_date
 * @property int purchased_date
 * @property string purchase_request_key
 * @property array purchase_request_keys
 * @property string note
 * @property int new_purchase_id
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \XFRM\Entity\ResourceItem Resource
 */
class Purchase extends Entity
{
    /**
     * @return bool
     */
    public function isExpired()
    {
        return $this->expire_date <= \XF::$time || $this->new_purchase_id > 0;
    }

    /**
     * @param ResourceVersion $version
     * @return bool
     */
    public function canDownloadVersion(ResourceVersion $version)
    {
        if ($this->new_purchase_id > 0) {
            return false;
        }

        return $version->resource_version_id <= $this->resource_version_id;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'tl_xfrm_resource_purchase';
        $structure->primaryKey = 'purchase_id';
        $structure->shortName = 'Truonglv\XFRMCustomized:Purchase';
        $structure->contentType = 'xfrmc_purchase';

        $structure->columns = [
            'purchase_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'resource_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'required' => true, 'maxLength' => 50],
            'resource_version_id' => ['type' => self::UINT, 'required' => true],
            'amount' => ['type' => self::FLOAT, 'default' => 0],
            'expire_date' => ['type' => self::UINT, 'default' => 0],
            'purchased_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'purchase_request_key' => ['type' => self::STR, 'default' => '', 'maxLength' => 32],
            'purchase_request_keys' => ['type' => self::JSON_ARRAY, 'default' => []],
            'note' => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
            'new_purchase_id' => ['type' => self::UINT, 'default' => 0],
        ];

        $structure->relations = [
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

    protected function _postDelete()
    {
        $this->db()->update(
            $this->structure()->table,
            [
                'new_purchase_id' => 0
            ],
            'new_purchase_id = ?',
            $this->purchase_id
        );
    }
}
