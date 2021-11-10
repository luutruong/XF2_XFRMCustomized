<?php

namespace Truonglv\XFRMCustomized\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $license_id
 * @property int $resource_id
 * @property int $user_id
 * @property string $license_url
 * @property int $added_date
 * @property int $deleted_date
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \XFRM\Entity\ResourceItem $Resource
 */
class License extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xfrmc_license';
        $structure->primaryKey = 'license_id';
        $structure->shortName = 'Truonglv\XFRMCustomized:License';

        $structure->columns = [
            'license_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'resource_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'license_url' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
            'added_date' => ['type' => self::UINT, 'default' =>  time()],
            'deleted_date' => ['type' => self::UINT, 'default' => 0]
        ];

        $structure->relations = [
            'User' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true,
            ],
            'Resource' => [
                'type' => self::TO_ONE,
                'entity' => 'XFRM:ResourceItem',
                'conditions' => 'resource_id',
                'primary' => true,
            ],
        ];

        return $structure;
    }
}
