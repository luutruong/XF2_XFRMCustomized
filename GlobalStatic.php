<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized;

use XF\Entity\User;
use Truonglv\XFRMCustomized\Repository\Purchase;

class GlobalStatic
{
    const PURCHASABLE_ID = 'tl_xfrm_customized_resource';

    public static function hasPermission($permission, User $user = null)
    {
        $user = $user ?: \XF::visitor();

        return $user->hasPermission('xfrmc', $permission);
    }

    public static function getFee($amount)
    {
        if (!\XF::options()->xfrmc_enableFee) {
            return 0;
        }

        return round((4.4 * $amount)/100 + 0.3, 2);
    }

    /**
     * @return Purchase
     */
    public static function purchaseRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return \XF::repository('Truonglv\XFRMCustomized:Purchase');
    }
}
