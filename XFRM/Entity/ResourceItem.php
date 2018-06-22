<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Entity;

use Truonglv\XFRMCustomized\GlobalStatic;
use XF\Mvc\Entity\Structure;

/**
 * Class ResourceItem
 * @package Truonglv\XFRMCustomized\XFRM\Entity
 * @inheritdoc
 *
 * @property array payment_profile_ids
 * @property float renew_price
 * @property \Truonglv\XFRMCustomized\Entity\Purchase[] Purchases
 */
class ResourceItem extends XFCP_ResourceItem
{
    public function error($message, $key = null, $specificError = true)
    {
        if ($key === 'currency'
            && $message->getName() === 'xfrm_non_purchasable_resources_may_not_define_purchasable_components'
        ) {
            return;
        }

        parent::error($message, $key, $specificError);
    }

    public function isExternalPurchasable()
    {
        if ($this->price > 0) {
            return true;
        }

        return parent::isExternalPurchasable();
    }

    public function isExternalDownload()
    {
        return false;
    }

    public function isDownloadable()
    {
        if (\XF::visitor()->user_id == $this->user_id) {
            return true;
        }

        if ($this->CurrentVersion->canDownload()) {
            return true;
        }

        return false;
    }

    public function getExternalPurchaseUrl()
    {
        return $this->app()->router('public')->buildLink('canonical:resources/purchase', $this);
    }

    public function canPurchase(&$error = null)
    {
        return $this->price > 0;
    }

    public function getPurchasePrice()
    {
        $purchases = GlobalStatic::purchaseRepo()->getAllPurchases($this);
        if ($this->renew_price > 0 && $purchases->count() > 0) {
            return $this->renew_price;
        }

        return $this->price;
    }

    public function canAddBuyer(&$error = null)
    {
        return $this->hasPermission('xfrm_customized_addBuyer');
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns += [
            'payment_profile_ids' => ['type' => self::LIST_COMMA, 'default' => []],
            'renew_price' => ['type' => self::FLOAT, 'default' => 0]
        ];

        $structure->getters['external_purchase_url'] = true;

        return $structure;
    }
}
