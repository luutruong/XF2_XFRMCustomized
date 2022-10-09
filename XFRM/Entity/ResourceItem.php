<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Entity;

use XF;
use XF\Phrase;
use function sprintf;
use XF\Mvc\Entity\Structure;
use XF\Entity\PaymentProfile;
use Truonglv\XFRMCustomized\App;
use Truonglv\XFRMCustomized\Entity\Coupon;

/**
 * Class ResourceItem
 * @package Truonglv\XFRMCustomized\XFRM\Entity
 * @inheritdoc
 *
 * @property array $payment_profile_ids
 * @property float $renew_price
 * @property bool $is_purchased
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

        return true;
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

        if (XF::visitor()->user_id == $this->user_id) {
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

    public function isRenewLicense(): bool
    {
        if (XF::visitor()->user_id <= 0) {
            return false;
        }

        if (\array_key_exists('xfrmc_is_renew_license', $this->_getterCache)) {
            return $this->_getterCache['xfrmc_is_renew_license'];
        }

        $isRenew = $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('user_id', XF::visitor()->user_id)
            ->where('resource_id', $this->resource_id)
            ->where('new_purchase_id', 0)
            ->total() > 0;
        $this->_getterCache['xfrmc_is_renew_license'] = $isRenew;

        return $isRenew;
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canPurchase(&$error = null)
    {
        $visitor = XF::visitor();
        if ($visitor->user_id <= 0 || $visitor->user_id === $this->user_id) {
            return false;
        }

        return $this->price > 0;
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canTransferLicense(&$error = null): bool
    {
        $visitor = XF::visitor();
        if ($visitor->user_id <= 0) {
            return false;
        }

        if ($this->hasPermission('editAny')) {
            return true;
        }

        return $this->is_purchased;
    }

    public function getRenewPrice(PaymentProfile $paymentProfile): float
    {
        return App::getPriceWithTax($paymentProfile, $this->renew_price);
    }

    public function getXFRMCPriceForProfile(PaymentProfile $paymentProfile, Coupon $coupon = null): float
    {
        $price = App::getPriceWithTax($paymentProfile, $this->price);

        return $coupon === null ? $price : $coupon->calcPrice($price);
    }

    public function getXFRMCPriceBadges(bool $checkRenew = false): array
    {
        if ($this->price < 1) {
            return [];
        }

        $paymentProfiles = App::getPaymentProfiles();
        $templater = $this->app()->templater();
        $badges = [];
        $price = $this->price;

        if ($checkRenew && $this->isRenewLicense()) {
            $price = $this->renew_price;
        }

        foreach ($this->payment_profile_ids as $paymentProfileId) {
            if (!isset($paymentProfiles[$paymentProfileId])) {
                continue;
            }

            $paymentProfileRef = $paymentProfiles[$paymentProfileId];
            $badges[] = [
                'text' => sprintf(
                    '%s: %s',
                    $paymentProfileRef->display_title,
                    $templater->filter(
                        App::getPriceWithTax($paymentProfileRef, $price),
                        [['currency', [$this->currency]]]
                    )
                ),
                'link' => $this->app()->router('public')->buildLink('resources/purchase', $this, [
                    'payment_profile_id' => $paymentProfileRef->payment_profile_id,
                ])
            ];
        }

        return $badges;
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

    public function getXFRMCStructureJsonLd(): array
    {
        $router = $this->app()->router('public');
        $currency = strlen($this->currency) > 0 ? $this->currency : $this->app()->options()->xfrmc_defaultCurrency;

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $router->buildLink('canonical:resources', $this),
            'name' => $this->title,
            'description' => $this->tag_line,
            'sku' => strval($this->resource_id),
            'brand' => $this->app()->options()->boardTitle,
            'mpn' => 'E-' . $this->resource_id,
            'offers' => [
                '@type' => 'Offer',
                'availability' => 'https://schema.org/InStock',
                'price' => $this->price,
                'priceCurrency' => $currency,
            ],
            'image' => $this->icon_date > 0
                ? $this->getIconUrl('m', true)
                : $this->User->getAvatarUrl('m', null, true),
            'category' => $this->Category->title,
            'productID' => 'E-' . $this->resource_id,
            'releaseDate' => $this->app()->language()->date($this->resource_date, 'c'),
        ];

        if ($this->review_count > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $this->rating_avg,
                'reviewCount' => $this->review_count,
            ];
        }

        return $data;
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canViewHistory(&$error = null)
    {
        $visitor = XF::visitor();
        if ($visitor->user_id <= 0) {
            return false;
        }

        if ($this->hasPermission('xfrmc_viewHistoryAny')) {
            return true;
        }

        return $visitor->user_id === $this->user_id;
    }

    public function setIsPurchased(bool $flag): void
    {
        $this->_getterCache['is_purchased'] = $flag;
    }

    public function getIsPurchased(): bool
    {
        if (array_key_exists('is_purchased', $this->_getterCache)) {
            return $this->_getterCache['is_purchased'];
        }

        return App::purchaseRepo()->isPurchased($this, XF::visitor());
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
        $structure->getters['is_purchased'] = true;

        return $structure;
    }
}
