<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized;

use XF;
use XF\Repository\AddOn;
use XF\Template\Templater;
use InvalidArgumentException;
use XFRM\Entity\ResourceItem;

class Callback
{
    /**
     * @param string $value
     * @param array $params
     * @param Templater $templater
     * @return string
     */
    public static function renderPaymentProfiles(string $value, array $params, Templater $templater)
    {
        if (!isset($params['resource']) || !$params['resource'] instanceof ResourceItem) {
            throw new InvalidArgumentException('Missing resource data in callback.');
        }

        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = $params['resource'];

        /** @var \XF\Repository\Payment $paymentRepo */
        $paymentRepo = XF::repository('XF:Payment');

        $controlOptions = [
            'name' => 'payment_profile_ids',
        ];

        $choices = [];
        /** @var \XF\Entity\PaymentProfile $paymentProfile */
        foreach ($paymentRepo->findPaymentProfilesForList()->fetch() as $paymentProfile) {
            $choices[] = [
                'value' => $paymentProfile->payment_profile_id,
                'label' => $paymentProfile->title,
                'selected' => in_array($paymentProfile->payment_profile_id, $resource->payment_profile_ids, true)
            ];
        }

        $rowOptions = [
            'label' => XF::phrase('payment_profiles'),
        ];

        return $templater->formCheckBoxRow($controlOptions, $choices, $rowOptions);
    }

    /**
     * @return bool|null
     */
    public static function getIsXFRMSupportLargeFileEnabled()
    {
        /** @var AddOn $addOnRepo */
        $addOnRepo = XF::repository('XF:AddOn');
        $addOns = $addOnRepo->getEnabledAddOns();

        return isset($addOns['Truonglv/XFRMSupportLargeFile']) ? true : null;
    }
}
