<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Entity;

use XF\Phrase;
use XF\Mvc\Entity\Structure;
use Truonglv\XFRMCustomized\GlobalStatic;

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
    /**
     * @param mixed $message
     * @param null|string $key
     * @param mixed $specificError
     * @return void
     */
    public function error($message, $key = null, $specificError = true)
    {
        if ($key === 'currency'
            && $message instanceof Phrase
            && $message->getName() === 'xfrm_non_purchasable_resources_may_not_define_purchasable_components'
        ) {
            return;
        }

        parent::error($message, $key, $specificError);
    }

    /**
     * @return bool
     */
    public function isExternalPurchasable()
    {
        if (GlobalStatic::isDisabledCategory($this->resource_category_id)) {
            return parent::isExternalPurchasable();
        }

        if ($this->price > 0) {
            return true;
        }

        return parent::isExternalPurchasable();
    }

    /**
     * @return bool
     */
    public function isExternalDownload()
    {
        if (GlobalStatic::isDisabledCategory($this->resource_category_id)) {
            return parent::isExternalDownload();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXFRMCCommerceItem()
    {
        if (GlobalStatic::isDisabledCategory($this->resource_category_id)) {
            return false;
        }

        return $this->price > 0;
    }

    /**
     * @return bool
     */
    public function isDownloadable()
    {
        if (GlobalStatic::isDisabledCategory($this->resource_category_id)) {
            return parent::isDownloadable();
        }

        if (\XF::visitor()->user_id == $this->user_id) {
            return true;
        }

        if ($this->CurrentVersion->canDownload()) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getExternalPurchaseUrl()
    {
        return $this->app()->router('public')->buildLink('canonical:resources/purchase', $this);
    }

    /**
     * @param null|string $error
     * @return bool
     */
    public function canPurchase(&$error = null)
    {
        return $this->price > 0;
    }

    /**
     * @return bool
     */
    public function isRenewLicense()
    {
        $purchases = GlobalStatic::purchaseRepo()->getAllPurchases($this);

        return $purchases->count() > 0;
    }

    /**
     * @return float
     */
    public function getPurchasePrice()
    {
        if ($this->renew_price > 0 && $this->isRenewLicense()) {
            return $this->renew_price;
        }

        return $this->price;
    }

    /**
     * @param null|string $error
     * @return bool
     */
    public function canAddBuyer(&$error = null)
    {
        return $this->hasPermission('xfrmc_addBuyer');
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
