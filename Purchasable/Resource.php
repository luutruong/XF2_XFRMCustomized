<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Purchasable;

use Truonglv\XFRMCustomized\Entity\Coupon;
use XF\Purchasable\Purchase;
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use XF\Purchasable\AbstractPurchasable;

class Resource extends AbstractPurchasable
{
    public function getPurchaseFromRequest(\XF\Http\Request $request, \XF\Entity\User $purchaser, &$error = null)
    {
        $profileId = $request->filter('payment_profile_id', 'uint');
        /** @var PaymentProfile $paymentProfile*/
        $paymentProfile = \XF::em()->find('XF:PaymentProfile', $profileId);

        if (!$paymentProfile || !$paymentProfile->active) {
            $error = \XF::phrase('please_choose_valid_payment_profile_to_continue_with_your_purchase');

            return false;
        }

        $resourceId = $request->filter('resource_id', 'uint');
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = \XF::em()->find('XFRM:ResourceItem', $resourceId);
        if (!$resource || !$resource->canPurchase()) {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');

            return false;
        }

        $couponCode = $request->filter('coupon_code', 'str');
        if (!empty($couponCode)) {
            /** @var Coupon $coupon */
            $coupon = \XF::em()->findOne('Truonglv\XFRMCustomized:Coupon', [
                'coupon_code' => $couponCode
            ]);

            if (!$coupon || !$coupon->canUseWith($resource, $purchaser)) {
                $error = \XF::phrase('xfrmc_coupon_has_been_expired_or_deleted');

                return false;
            }
        }

        if (!in_array($profileId, $resource->payment_profile_ids)) {
            $error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');

            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $resource, $purchaser);
    }

    public function getPurchasableFromExtraData(array $extraData)
    {
        $output = [
            'link' => '',
            'title' => '',
            'purchasable' => null
        ];

        $resource = \XF::em()->find('XFRM:ResourceItem', $extraData['resource_id']);
        if ($resource) {
            $output['link'] = \XF::app()->router('public')->buildLink('resources/edit', $resource);
            $output['title'] = $resource->title;
            $output['purchasable'] = $resource;
        }

        return $output;
    }

    public function completePurchase(CallbackState $state)
    {
        if ($state->legacy) {
            $purchaseRequest = null;
            $resourceId = $state->resource_id;
        } else {
            $purchaseRequest = $state->getPurchaseRequest();
            $resourceId = $purchaseRequest->extra_data['resource_id'];
        }

        $paymentResult = $state->paymentResult;
        $purchaser = $state->getPurchaser();

        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = \XF::em()->find('XFRM:ResourceItem', $resourceId);

        /** @var \Truonglv\XFRMCustomized\Entity\Purchase $purchase */
        $purchase = \XF::finder('Truonglv\XFRMCustomized:Purchase')
            ->with('Resource')
            ->where('resource_id', $purchaseRequest->extra_data['resource_id'])
            ->where('user_id', $purchaser->user_id)
            ->fetchOne();

        if (!$purchase) {
            // create basic
            $purchase = \XF::em()->create('Truonglv\XFRMCustomized:Purchase');
            $purchase->resource_id = $resource->resource_id;
            $purchase->user_id = $purchaser->user_id;
            $purchase->username = $purchaser->username;
            $purchase->amount = 0;
        }

        if ($purchaseRequest->request_key) {
            $requestKeys = $purchase->purchase_request_keys;
            $requestKeys[] = $purchaseRequest->request_key;
            $purchase->purchase_request_keys = $requestKeys;

            $requestKey = substr($purchaseRequest->request_key, 0, 32);
            $purchase->purchase_request_key = $requestKey;
        }

        $purchase->resource_version_id = $resource->current_version_id;

        switch ($paymentResult) {
            case CallbackState::PAYMENT_RECEIVED:
                if ($purchase->expire_date < 1) {
                    $purchase->expire_date = \XF::$time;
                }

                // 1 year license?
                $purchase->expire_date += 365 * 86400;
                $purchase->amount += $resource->getPurchasePrice();

                $state->logType = 'payment';
                $state->logMessage = 'Payment received, upgraded/extended.';

                break;

            case CallbackState::PAYMENT_REINSTATED:
                $purchase->expire_date += 365 * 86400;

                $state->logType = 'payment';
                $state->logMessage = 'Reversal cancelled, upgrade reactivated.';

                break;
        }

        $purchase->save();

        if ($purchaseRequest) {
            $extraData = $purchaseRequest->extra_data;
            $extraData['purchase_id'] = $purchase->purchase_id;
            $purchaseRequest->extra_data = $extraData;
            $purchaseRequest->save();
        }
    }

    public function getPurchaseFromExtraData(
        array $extraData,
        PaymentProfile $paymentProfile,
        \XF\Entity\User $purchaser,
        &$error = null
    ) {
        $data = $this->getPurchasableFromExtraData($extraData);
        if (!$data['purchasable'] || !$data['purchasable']->canPurchase()) {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');

            return false;
        }

        if (!in_array($paymentProfile->payment_profile_id, $data['purchasable']->payment_profile_ids)) {
            $error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');

            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $data['purchasable'], $purchaser);
    }

    public function getTitle()
    {
        return \XF::phrase('xfrmc_resource');
    }

    public function reversePurchase(CallbackState $state)
    {
        $purchaseRequest = $state->getPurchaseRequest();
        $purchaser = $state->getPurchaser();

        /** @var \Truonglv\XFRMCustomized\Entity\Purchase $purchased */
        $purchased = \XF::finder('Truonglv\XFRMCustomized:Purchase')
            ->with('Resource')
            ->where('resource_id', $purchaseRequest->extra_data['resource_id'])
            ->where('user_id', $purchaser->user_id)
            ->fetchOne();

        if (!$purchased) {
            return;
        }

        $purchased->expire_date = \XF::$time;
        $purchased->save();

        $state->logType = 'cancel';
        $state->logMessage = 'Payment refunded/reversed, downgraded.';
    }

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
     * @param \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $purchasable
     * @param \XF\Entity\User $purchaser
     * @return mixed|Purchase
     */
    public function getPurchaseObject(
        PaymentProfile $paymentProfile,
        $purchasable,
        \XF\Entity\User $purchaser
    ) {
        /** @var \Truonglv\XFRMCustomized\Entity\Purchase $purchased */
//        $purchased = \XF::finder('Truonglv\XFRMCustomized:Purchase')
//            ->with('Resource')
//            ->where('resource_id', $purchasable->resource_id)
//            ->where('user_id', $purchaser->user_id)
//            ->fetchOne();

        $cost = $purchasable->price;
//        if ($purchased && $purchased->isExpired()) {
//            $cost = $purchasable->renew_price ?: $purchasable->price;
//        }

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
        $purchase->purchasableId = $purchasable->resource_id;

        $purchase->purchasableTitle = $purchasable->title;
        $purchase->extraData = [
            'resource_id' => $purchasable->resource_id,
            'coupon_code' => ''
        ];

        $router = \XF::app()->router('public');

        $purchase->returnUrl = $router->buildLink('canonical:resources', $purchasable);
        $purchase->cancelUrl = $router->buildLink('canonical:resources', $purchasable);

        return $purchase;
    }
}
