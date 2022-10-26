<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XFRM\Entity\ResourceItem;
use XFRM\Entity\ResourceVersion;
use Truonglv\XFRMCustomized\Purchasable\Resource;

/**
 * COLUMNS
 * @property int|null $purchase_id
 * @property int $resource_id
 * @property int $user_id
 * @property string $username
 * @property int $resource_version_id
 * @property float $amount
 * @property int $expire_date
 * @property int $purchased_date
 * @property string $purchase_request_key
 * @property array $purchase_request_keys
 * @property string $note
 * @property int $new_purchase_id
 * @property int $total_license
 *
 * GETTERS
 * @property float $purchased_amount
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \XFRM\Entity\ResourceItem $Resource
 * @property \XFRM\Entity\ResourceVersion $ResourceVersion
 * @property \XF\Entity\PurchaseRequest $PurchaseRequest
 */
class Purchase extends Entity
{
    /**
     * @param mixed $error
     * @return bool
     */
    public function canView(&$error = null): bool
    {
        $visitor = XF::visitor();
        if ($visitor->user_id <= 0) {
            return false;
        }

        if ($visitor->hasPermission('resource', 'xfrmc_viewPurchaseAny')) {
            return true;
        }

        return $visitor->user_id === $this->user_id;
    }

    /**
     * @return bool
     */
    public function isExpired()
    {
        return $this->expire_date <= time() || $this->new_purchase_id > 0;
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

        if ($this->expire_date > time()) {
            return true;
        }

        // expired.
        return $version->release_date <= $this->expire_date;
    }

    public function getPurchasedAmount(): float
    {
        if ($this->PurchaseRequest !== null) {
            $couponId = $this->PurchaseRequest->extra_data[Resource::EXTRA_DATA_COUPON_ID] ?? 0;
            if ($couponId > 0) {
                /** @var Coupon|null $coupon */
                $coupon = $this->em()->find('Truonglv\XFRMCustomized:Coupon', $couponId);
                if ($coupon !== null) {
                    return $coupon->calcPrice($this->amount);
                }
            }
        }

        return $this->amount;
    }

    public function getRenewPrice(ResourceItem $resource, XF\Entity\PaymentProfile $profile): string
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $price = $resource->getRenewPrice($profile);

        $price *= $this->total_license;
        $templater = $this->app()->templater();

        return $templater->filter($price, [['currency', [$resource->currency]]]);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xfrmc_resource_purchase';
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
            'purchased_date' => ['type' => self::UINT, 'default' => XF::$time],
            'purchase_request_key' => ['type' => self::STR, 'default' => '', 'maxLength' => 32],
            'purchase_request_keys' => ['type' => self::JSON_ARRAY, 'default' => []],
            'note' => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
            'new_purchase_id' => ['type' => self::UINT, 'default' => 0],

            'total_license' => ['type' => self::UINT, 'default' => 1],
        ];

        $structure->getters = [
            'purchased_amount' => true,
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
            ],
            'ResourceVersion' => [
                'type' => self::TO_ONE,
                'entity' => 'XFRM:ResourceVersion',
                'conditions' => 'resource_version_id',
                'primary' => true,
            ],
            'PurchaseRequest' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:PurchaseRequest',
                'conditions' => [
                    ['request_key', '=', '$purchase_request_key']
                ],
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
