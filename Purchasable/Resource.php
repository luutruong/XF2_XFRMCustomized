<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Purchasable;

use XF;
use LogicException;
use XF\Purchasable\Purchase;
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use XFRM\Entity\ResourceItem;
use XF\Purchasable\AbstractPurchasable;
use Truonglv\XFRMCustomized\Entity\Coupon;
use Truonglv\XFRMCustomized\Entity\CouponUser;

class Resource extends AbstractPurchasable
{
    const EXTRA_OLD_PURCHASE_ID = 'old_purchase_id';
    const EXTRA_DATA_COUPON_ID = 'coupon_id';
    const EXTRA_DATA_RESOURCE_ID = 'resource_id';
    const EXTRA_DATA_PURCHASE_ID = 'purchase_id';

    /**
     * @var Coupon|null
     */
    protected $coupon;
    /**
     * @var \Truonglv\XFRMCustomized\Entity\Purchase|null
     */
    protected $oldPurchase;

    /**
     * @param \XF\Http\Request $request
     * @param \XF\Entity\User $purchaser
     * @param null|string $error
     * @return Purchase|bool
     */
    public function getPurchaseFromRequest(\XF\Http\Request $request, \XF\Entity\User $purchaser, &$error = null)
    {
        $profileId = $request->filter('payment_profile_id', 'uint');
        /** @var PaymentProfile|null $paymentProfile */
        $paymentProfile = XF::em()->find('XF:PaymentProfile', $profileId);

        if ($paymentProfile === null || !$paymentProfile->active) {
            $error = XF::phrase('please_choose_valid_payment_profile_to_continue_with_your_purchase');

            return false;
        }

        $resourceId = $request->filter('resource_id', 'uint');
        $purchaseId = $request->filter('purchase_id', 'uint');
        $canUseCouponCode = true;

        if ($purchaseId > 0) {
            /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchase */
            $purchase = XF::em()->find('Truonglv\XFRMCustomized:Purchase', $purchaseId);
            if ($purchase === null
                || $purchase->user_id !== $purchaser->user_id
                || $purchase->new_purchase_id > 0
            ) {
                $error = XF::phrase('this_item_cannot_be_purchased_at_moment');

                return false;
            }

            /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
            $resource = $purchase->Resource;
            $canUseCouponCode = false;
            $this->oldPurchase = $purchase;
        } else {
            /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem|null $resource */
            $resource = XF::em()->find('XFRM:ResourceItem', $resourceId);
            if ($resource === null || !$resource->canPurchase()) {
                $error = XF::phrase('this_item_cannot_be_purchased_at_moment');

                return false;
            }
        }

        $couponCode = $request->filter('coupon_code', 'str');
        if ($couponCode !== '' && $canUseCouponCode) {
            /** @var Coupon|null $coupon */
            $coupon = XF::em()->findOne('Truonglv\XFRMCustomized:Coupon', [
                'coupon_code' => $couponCode
            ]);

            if ($coupon === null || !$coupon->canView()) {
                $error = XF::phrase('xfrmc_requested_coupon_not_found');

                return false;
            }

            if (!$coupon->canUseWith($resource, $error, $purchaser)) {
                $error = $error !== null ? $error : XF::phrase('xfrmc_coupon_has_been_expired_or_deleted');

                return false;
            }

            $this->coupon = $coupon;
        }

        if (!in_array($profileId, $resource->payment_profile_ids, true)) {
            $error = XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');

            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $resource, $purchaser);
    }

    /**
     * @param array $extraData
     * @return array
     */
    public function getPurchasableFromExtraData(array $extraData)
    {
        $output = [
            'link' => '',
            'title' => '',
            'purchasable' => null
        ];

        /** @var ResourceItem|null $resource */
        $resource = XF::em()->find('XFRM:ResourceItem', $extraData['resource_id']);
        if ($resource !== null) {
            $output['link'] = XF::app()->router('public')->buildLink('resources/edit', $resource);
            $output['title'] = $resource->title;
            $output['purchasable'] = $resource;
        }

        return $output;
    }

    /**
     * @param CallbackState $state
     * @return void
     * @throws \XF\PrintableException
     */
    public function completePurchase(CallbackState $state)
    {
        if ($state->legacy) {
            $purchaseRequest = null;
            $resourceId = $state->{ self::EXTRA_DATA_RESOURCE_ID };
            $purchaseId = $state->{ self::EXTRA_DATA_PURCHASE_ID };
            $oldPurchaseId = $state->{ self::EXTRA_OLD_PURCHASE_ID };
        } else {
            $purchaseRequest = $state->getPurchaseRequest();
            $resourceId = $purchaseRequest->extra_data[self::EXTRA_DATA_RESOURCE_ID];
            $purchaseId = $purchaseRequest->extra_data[self::EXTRA_DATA_PURCHASE_ID] ?? null;
            $oldPurchaseId = $purchaseRequest->extra_data[self::EXTRA_OLD_PURCHASE_ID] ?? null;
        }

        $paymentResult = $state->paymentResult;
        /** @var PaymentProfile $paymentProfile */
        $paymentProfile = $purchaseRequest->PaymentProfile;
        $purchaser = $state->getPurchaser();
        $canUseCoupon = true;
        /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $oldPurchase */
        $oldPurchase = null;

        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = XF::em()->find('XFRM:ResourceItem', $resourceId);

        $licenseStartDate = 0;
        if ($oldPurchaseId > 0) {
            /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $oldPurchase */
            $oldPurchase = XF::em()->find('Truonglv\XFRMCustomized:Purchase', $oldPurchaseId);
            $canUseCoupon = false;

            if ($oldPurchase !== null) {
                $licenseStartDate = $oldPurchase->expire_date;
            }
        }

        /** @var Coupon|null $coupon */
        $coupon = null;
        if (isset($purchaseRequest->extra_data[self::EXTRA_DATA_COUPON_ID]) && $canUseCoupon) {
            /** @var Coupon|null $coupon */
            $coupon = XF::em()->find(
                'Truonglv\XFRMCustomized:Coupon',
                $purchaseRequest->extra_data[self::EXTRA_DATA_COUPON_ID]
            );
        }

        $purchase = null;

        switch ($paymentResult) {
            case CallbackState::PAYMENT_RECEIVED:
                $logMessages = [
                    sprintf(
                        'User %d bought resource %s (%d)',
                        $purchaser->user_id,
                        $resource->title,
                        $resource->resource_id
                    )
                ];

                /** @var \Truonglv\XFRMCustomized\Entity\Purchase $purchase */
                $purchase = XF::em()->create('Truonglv\XFRMCustomized:Purchase');
                $purchase->resource_id = $resource->resource_id;
                $purchase->user_id = $purchaser->user_id;
                $purchase->username = $purchaser->username;
                $purchase->expire_date = max(time(), $licenseStartDate) + XF::app()->options()->xfrmc_licenseDuration * 86400;
                $purchase->resource_version_id = $resource->current_version_id;
                if ($purchaseRequest->request_key !== '') {
                    $purchase->purchase_request_key = substr($purchaseRequest->request_key, 0, 32);
                }

                $couponUser = null;
                if ($coupon !== null) {
                    $purchase->note = 'Using coupon code: ' . $coupon->coupon_code;
                    $purchase->amount = $resource->getXFRMCPriceForProfile($paymentProfile, $coupon);

                    /** @var CouponUser $couponUser */
                    $couponUser = XF::em()->create('Truonglv\XFRMCustomized:CouponUser');
                    $couponUser->resource_id = $resource->resource_id;
                    $couponUser->user_id = $purchaser->user_id;
                    $couponUser->username = $purchaser->username;
                    $couponUser->coupon_id = $coupon->coupon_id;
                    $couponUser->purchase_id = 0;

                    $purchase->addCascadedSave($couponUser);
                    $logMessages[] = 'Using coupon code: ' . $coupon->coupon_code;
                } else {
                    $purchase->amount = $this->oldPurchase === null
                        ? $resource->getXFRMCPriceForProfile($paymentProfile)
                        : $resource->getRenewPrice($paymentProfile);
                }

                $purchase->save();

                if ($couponUser !== null) {
                    $couponUser->fastUpdate('purchase_id', $purchase->purchase_id);
                }

                if ($oldPurchase !== null) {
                    $oldPurchase->new_purchase_id = $purchase->purchase_id;
                    $oldPurchase->save();

                    $logMessages[] = 'Old purchase ID: ' . $oldPurchase->purchase_id;
                }

                $state->logType = 'payment';
                $state->logMessage = implode(', ', $logMessages);

                break;
            case CallbackState::PAYMENT_REINSTATED:
                if ($purchaseId) {
                    /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchase */
                    $purchase = XF::em()->find('Truonglv\XFRMCustomized:Purchase', $purchaseId);
                    if ($purchase !== null) {
                        $purchase->expire_date = $purchase->purchased_date + XF::app()->options()->xfrmc_licenseDuration * 86400;
                        $purchase->save();

                        $state->logType = 'payment';
                        $state->logMessage = sprintf(
                            'Update existing purchase record. $purchaseId=%d',
                            $purchaseId
                        );
                    } else {
                        $state->logType = 'info';
                        $state->logMessage = sprintf(
                            'No purchase record. $purchaseId=%d',
                            $purchaseId
                        );
                    }
                } else {
                    $state->logType = 'info';
                    $state->logMessage = sprintf(
                        'No purchase record. $purchaseId=%d',
                        $purchaseId
                    );
                }

                break;
        }

        if ($purchaseRequest !== null && $purchase !== null) {
            $extraData = $purchaseRequest->extra_data;
            $extraData[self::EXTRA_DATA_PURCHASE_ID] = $purchase->purchase_id;
            $purchaseRequest->extra_data = $extraData;

            $purchaseRequest->save();
        }
    }

    /**
     * @param array $extraData
     * @param PaymentProfile $paymentProfile
     * @param \XF\Entity\User $purchaser
     * @param null|string $error
     * @return Purchase|bool
     */
    public function getPurchaseFromExtraData(
        array $extraData,
        PaymentProfile $paymentProfile,
        \XF\Entity\User $purchaser,
        &$error = null
    ) {
        $data = $this->getPurchasableFromExtraData($extraData);
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem|null $purchasable */
        $purchasable = $data['purchasable'];
        if ($purchasable === null || !$purchasable->canPurchase()) {
            $error = XF::phrase('this_item_cannot_be_purchased_at_moment');

            return false;
        }

        if (!in_array($paymentProfile->payment_profile_id, $purchasable->payment_profile_ids, true)) {
            $error = XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');

            return false;
        }

        $canUseCoupon = true;
        if (isset($extraData[self::EXTRA_OLD_PURCHASE_ID])) {
            /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchase */
            $purchase = XF::em()->find('Truonglv\XFRMCustomized:Purchase', $extraData[self::EXTRA_OLD_PURCHASE_ID]);
            if ($purchase === null
                || $purchase->user_id !== $purchaser->user_id
                || $purchase->new_purchase_id > 0
            ) {
                $error = XF::phrase('this_item_cannot_be_purchased_at_moment');

                return false;
            }
            $this->oldPurchase = $purchase;

            $canUseCoupon = false;
        }

        if (isset($extraData[self::EXTRA_DATA_COUPON_ID]) && $canUseCoupon) {
            /** @var Coupon|null $coupon */
            $coupon = XF::em()->find('Truonglv\XFRMCustomized:Coupon', $extraData['coupon_id']);
            if ($coupon === null || !$coupon->canView()) {
                $error = XF::phrase('xfrmc_requested_coupon_not_found');

                return false;
            }

            if (!$coupon->canUseWith($purchasable, $error)) {
                $error = $error !== null ? $error : XF::phrase('xfrmc_coupon_has_been_expired_or_deleted');

                return false;
            }

            $this->coupon = $coupon;
        }

        return $this->getPurchaseObject($paymentProfile, $purchasable, $purchaser);
    }

    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return XF::phrase('xfrmc_resource');
    }

    /**
     * @param CallbackState $state
     * @return void
     * @throws \XF\PrintableException
     */
    public function reversePurchase(CallbackState $state)
    {
        if ($state->legacy) {
            $purchaseRequest = null;
            $purchaseId = $state->{ self::EXTRA_DATA_PURCHASE_ID };
        } else {
            $purchaseRequest = $state->getPurchaseRequest();
            $purchaseId = $purchaseRequest->extra_data[self::EXTRA_DATA_PURCHASE_ID] ?? null;
        }

        /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchase */
        $purchase = null;
        if ($purchaseId) {
            $purchase = XF::em()->find('Truonglv\XFRMCustomized:Purchase', $purchaseId);
        }

        if ($purchase !== null) {
            $purchase->delete();

            $state->logType = 'cancel';
            $state->logMessage = 'Deleted purchase record. $purchaseId=' . $purchaseId;
        } else {
            $state->logType = 'info';
            $state->logMessage = sprintf(
                'No purchase record. $purchaseId=%d',
                $purchaseId
            );
        }
    }

    /**
     * @param mixed $profileId
     * @return array
     */
    public function getPurchasablesByProfileId($profileId)
    {
        $finder = XF::finder('XFRM:ResourceItem');

        $quotedProfileId = $finder->quote($profileId);
        $columnName = $finder->columnSqlName('payment_profile_ids');

        $router = XF::app()->router('public');
        $upgrades = $finder->whereSql("FIND_IN_SET($quotedProfileId, $columnName)")->fetch();

        return $upgrades->pluck(function (\XFRM\Entity\ResourceItem $resource, $key) use ($router) {
            return ['tl_xfrm_resource' . $key, [
                'title' => $this->getTitle() . ': ' . $resource->title,
                'link' => $router->buildLink('resources/edit', $resource)
            ]];
        }, false);
    }

    /**
     * @param PaymentProfile $paymentProfile
     * @param mixed $purchasable
     * @param \XF\Entity\User $purchaser
     * @return Purchase
     */
    public function getPurchaseObject(
        PaymentProfile $paymentProfile,
        $purchasable,
        \XF\Entity\User $purchaser
    ) {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $purchasable */
        if ($this->coupon !== null) {
            if ($this->oldPurchase !== null) {
                throw new LogicException('Cannot apply coupon code to renew license.');
            }
        }

        $cost = $this->oldPurchase === null
            ? $purchasable->getXFRMCPriceForProfile($paymentProfile, $this->coupon)
            : $purchasable->getRenewPrice($paymentProfile);

        $purchase = new Purchase();

        $purchase->title = sprintf(
            '%s: %s (%s)',
            $this->oldPurchase === null
                ? XF::phrase('xfrmc_buy_resource')
                : XF::phrase('xfrmc_renew_license'),
            $purchasable->title,
            $purchaser->username
        );

        $purchase->description = $purchasable->tag_line;
        $purchase->cost = $cost;
        $purchase->currency = $purchasable->currency;
        // TODO: Support recurring
        $purchase->recurring = false;

        $purchase->lengthAmount = 0;
        $purchase->lengthUnit = '';

        $purchase->purchaser = $purchaser;
        $purchase->paymentProfile = $paymentProfile;

        $purchase->purchasableTypeId = $this->purchasableTypeId;
        $purchase->purchasableId = strval($purchasable->resource_id);

        $purchase->purchasableTitle = $purchasable->title;
        $extraData = [
            self::EXTRA_DATA_RESOURCE_ID => $purchasable->resource_id,
        ];
        if ($this->coupon !== null) {
            $extraData[self::EXTRA_DATA_COUPON_ID] = $this->coupon->coupon_id;
        }
        if ($this->oldPurchase !== null) {
            // link to existing purchase if this request is renew license
            $extraData[self::EXTRA_OLD_PURCHASE_ID] = $this->oldPurchase->purchase_id;
        }
        $purchase->extraData = $extraData;

        $router = XF::app()->router('public');

        $purchase->returnUrl = $router->buildLink('canonical:resources', $purchasable);
        $purchase->cancelUrl = $router->buildLink('canonical:resources', $purchasable);

        return $purchase;
    }
}
