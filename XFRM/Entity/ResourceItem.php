<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Entity;

use XF\Phrase;
use XF\Mvc\Entity\Structure;
use Truonglv\XFRMCustomized\App;
use Truonglv\XFRMCustomized\Data\Lazy;

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
     * @param mixed $key
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
        if (App::isDisabledCategory($this->resource_category_id)) {
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
        if (App::isDisabledCategory($this->resource_category_id)) {
            return parent::isExternalDownload();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXFRMCCommerceItem()
    {
        if (App::isDisabledCategory($this->resource_category_id)) {
            return false;
        }

        return $this->price > 0;
    }

    /**
     * @return bool
     */
    public function isDownloadable()
    {
        if (App::isDisabledCategory($this->resource_category_id)) {
            return parent::isDownloadable();
        }

        if (\XF::visitor()->user_id == $this->user_id) {
            return true;
        }

        if ($this->CurrentVersion !== null && $this->CurrentVersion->canDownload()) {
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
        if (\XF::visitor()->user_id <= 0) {
            return false;
        }

        $purchases = App::purchaseRepo()->getAllPurchases($this);

        return $purchases->count() > 0;
    }

    /**
     * @return float
     */
    public function getPurchasePrice()
    {
        if ($this->renew_price > 0 && $this->isRenewLicense()) {
            $price = $this->renew_price;
        } else {
            $price = $this->price;
        }

        return $price + App::getFee($price);
    }

    /**
     * @return array
     */
    public function getPaymentProfileIds()
    {
        $ids = $this->get('payment_profile_ids_');
        $ids = array_map('intval', $ids);

        return $ids;
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canAddBuyer(&$error = null)
    {
        return $this->hasPermission('xfrmc_addBuyer');
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canViewHistory(&$error = null)
    {
        $visitor = \XF::visitor();
        if ($visitor->user_id <= 0) {
            return false;
        }

        if ($this->hasPermission('xfrmc_viewHistoryAny')) {
            return true;
        }

        if ($visitor->user_id === $this->user_id) {
            return true;
        }

        /** @var Lazy $lazy */
        $lazy = $this->app()->data('Truonglv\XFRMCustomized:Lazy');

        return $lazy->isPurchasedResource($this->resource_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns += [
            'payment_profile_ids' => ['type' => self::LIST_COMMA, 'default' => []],
            'renew_price' => ['type' => self::FLOAT, 'default' => 0]
        ];

        $structure->getters['payment_profile_ids'] = true;
        $structure->getters['external_purchase_url'] = true;

        return $structure;
    }
}
