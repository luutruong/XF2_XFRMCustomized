<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Purchasable;

use XF\Purchasable\Purchase;
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use XFRM\Entity\ResourceItem;
use XF\Purchasable\AbstractPurchasable;
use Truonglv\XFRMCustomized\GlobalStatic;
use Truonglv\XFRMCustomized\Entity\Coupon;
use Truonglv\XFRMCustomized\Entity\CouponUser;

class Resource extends AbstractPurchasable
{
    /**
     * @var Coupon|null
     */
    protected $coupon;

    /**
     * @param \XF\Http\Request $request
     * @param \XF\Entity\User $purchaser
     * @param null|string $error
     * @return Purchase|mixed
     */
    public function getPurchaseFromRequest(\XF\Http\Request $request, \XF\Entity\User $purchaser, &$error = null)
    {
        $profileId = $request->filter('payment_profile_id', 'uint');
        /** @var PaymentProfile|null $paymentProfile */
        $paymentProfile = \XF::em()->find('XF:PaymentProfile', $profileId);

        if ($paymentProfile === null || !$paymentProfile->active) {
            $error = \XF::phrase('please_choose_valid_payment_profile_to_continue_with_your_purchase');

            return false;
        }

        $resourceId = $request->filter('resource_id', 'uint');
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem|null $resource */
        $resource = \XF::em()->find('XFRM:ResourceItem', $resourceId);
        if ($resource === null || !$resource->canPurchase()) {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');

            return false;
        }

        $couponCode = $request->filter('coupon_code', 'str');
        if ($couponCode !== '') {
            /** @var Coupon|null $coupon */
            $coupon = \XF::em()->findOne('Truonglv\XFRMCustomized:Coupon', [
                'coupon_code' => $couponCode
            ]);

            if ($coupon === null) {
                $error = \XF::phrase('xfrmc_requested_coupon_not_found');

                return false;
            }

            if (!$coupon->canUseWith($resource, $error, $purchaser)) {
                $error = $error !== null ? $error : \XF::phrase('xfrmc_coupon_has_been_expired_or_deleted');

                return false;
            }

            $this->coupon = $coupon;
        }

        if (!in_array($profileId, $resource->payment_profile_ids, true)) {
            $error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');

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
        $resource = \XF::em()->find('XFRM:ResourceItem', $extraData['resource_id']);
        if ($resource !== null) {
            $output['link'] = \XF::app()->router('public')->buildLink('resources/edit', $resource);
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
            $resourceId = $state->resource_id;
            $purchaseId = $state->purchase_id;
        } else {
            $purchaseRequest = $state->getPurchaseRequest();
            $resourceId = $purchaseRequest->extra_data['resource_id'];
            $purchaseId = isset($purchaseRequest->extra_data['purchase_id'])
                ? $purchaseRequest->extra_data['purchase_id']
                : null;
        }

        $paymentResult = $state->paymentResult;
        $purchaser = $state->getPurchaser();

        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = \XF::em()->find('XFRM:ResourceItem', $resourceId);

        /** @var Coupon|null $coupon */
        $coupon = null;
        if (isset($purchaseRequest->extra_data['coupon_id'])) {
            /** @var Coupon|null $coupon */
            $coupon = \XF::em()->find('Truonglv\XFRMCustomized:Coupon', $purchaseRequest->extra_data['coupon_id']);
        }

        $purchase = null;

        switch ($paymentResult) {
            case CallbackState::PAYMENT_RECEIVED:
                /** @var \Truonglv\XFRMCustomized\Entity\Purchase $purchase */
                $purchase = \XF::em()->create('Truonglv\XFRMCustomized:Purchase');
                $purchase->resource_id = $resource->resource_id;
                $purchase->user_id = $purchaser->user_id;
                $purchase->username = $purchaser->username;
                $purchase->expire_date = \XF::$time + 365 * 86400;
                $purchase->resource_version_id = $resource->current_version_id;
                if ($purchaseRequest->request_key !== '') {
                    $purchase->purchase_request_key = substr($purchaseRequest->request_key, 0, 32);
                }

                $couponUser = null;
                if ($coupon !== null) {
                    $purchase->note = 'Using coupon code: ' . $coupon->coupon_code;
                    $purchase->amount = $coupon->getFinalPrice($resource);

                    /** @var CouponUser $couponUser */
                    $couponUser = \XF::em()->create('Truonglv\XFRMCustomized:CouponUser');
                    $couponUser->resource_id = $resource->resource_id;
                    $couponUser->user_id = $purchaser->user_id;
                    $couponUser->username = $purchaser->username;
                    $couponUser->coupon_id = $coupon->coupon_id;
                    $couponUser->purchase_id = 0;

                    $purchase->addCascadedSave($couponUser);
                } else {
                    $purchase->amount = $resource->getPurchasePrice();
                }

                $purchase->save();

                if ($couponUser !== null) {
                    $couponUser->fastUpdate('purchase_id', $purchase->purchase_id);
                }

                $state->logType = 'payment';
                $state->logMessage = sprintf(
                    'User (%s) bought resource (%s) with coupon code (%s)',
                    $purchaser->username,
                    $resource->resource_id . ' - ' . $resource->title,
                    $coupon !== null ? $coupon->coupon_code : ''
                );

                break;
            case CallbackState::PAYMENT_REINSTATED:
                if ($purchaseId) {
                    /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchase */
                    $purchase = \XF::em()->find('Truonglv\XFRMCustomized:Purchase', $purchaseId);
                    if ($purchase !== null) {
                        $purchase->expire_date = $purchase->purchased_date + 365 * 86400;
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
            $extraData['purchase_id'] = $purchase->purchase_id;
            $purchaseRequest->extra_data = $extraData;

            $purchaseRequest->save();
        }
    }

    /**
     * @param array $extraData
     * @param PaymentProfile $paymentProfile
     * @param \XF\Entity\User $purchaser
     * @param null|string $error
     * @return bool|mixed|Purchase
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
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');

            return false;
        }

        if (!in_array($paymentProfile->payment_profile_id, $purchasable->payment_profile_ids, true)) {
            $error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');

            return false;
        }

        if (isset($extraData['coupon_id'])) {
            /** @var Coupon|null $coupon */
            $coupon = \XF::em()->find('Truonglv\XFRMCustomized:Coupon', $extraData['coupon_id']);
            if ($coupon === null) {
                $error = \XF::phrase('xfrmc_requested_coupon_not_found');

                return false;
            }

            if (!$coupon->canUseWith($purchasable, $error)) {
                $error = $error !== null ? $error : \XF::phrase('xfrmc_coupon_has_been_expired_or_deleted');

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
        return \XF::phrase('xfrmc_resource');
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
            $purchaseId = $state->purchase_id;
        } else {
            $purchaseRequest = $state->getPurchaseRequest();
            $purchaseId = isset($purchaseRequest->extra_data['purchase_id'])
                ? $purchaseRequest->extra_data['purchase_id']
                : null;
        }

        /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchase */
        $purchase = null;
        if ($purchaseId) {
            $purchase = \XF::em()->find('Truonglv\XFRMCustomized:Purchase', $purchaseId);
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
        $finder = \XF::finder('XFRM:ResourceItem');

        $quotedProfileId = $finder->quote($profileId);
        $columnName = $finder->columnSqlName('payment_profile_ids');

        $router = \XF::app()->router('public');
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
        if ($this->coupon !== null) {
            $cost = $this->coupon->getFinalPrice($purchasable);
        } else {
            $cost = $purchasable->getPurchasePrice();
        }

        $purchase = new Purchase();

        $purchase->title = sprintf(
            '%s: %s (%s)',
            \XF::phrase('xfrmc_buy_resource'),
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
        $purchase->extraData = [
            'resource_id' => $purchasable->resource_id,
            'coupon_id' => $this->coupon !== null ? $this->coupon->coupon_id : 0
        ];

        $router = \XF::app()->router('public');

        $purchase->returnUrl = $router->buildLink('canonical:resources', $purchasable);
        $purchase->cancelUrl = $router->buildLink('canonical:resources', $purchasable);

        return $purchase;
    }
}
