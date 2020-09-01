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

    /**
     * @param string $permission
     * @param User|null $user
     * @return bool
     */
    public static function hasPermission(string $permission, User $user = null)
    {
        $user = $user !== null ? $user : \XF::visitor();

        return $user->hasPermission('xfrmc', $permission);
    }

    /**
     * @param int $categoryId
     * @return bool
     */
    public static function isDisabledCategory(int $categoryId)
    {
        $disabled = \XF::options()->xfrmc_disableCategories;
        $disabled = array_map('intval', $disabled);

        return in_array($categoryId, $disabled, true);
    }

    /**
     * @param float $amount
     * @return float
     */
    public static function getFee(float $amount)
    {
        $formula = \XF::options()->xfrmc_feeFormula;
        if (strlen($formula) <= 0) {
            return 0.0;
        }

        if ($amount < 1) {
            return 0.0;
        }

        $formula = str_replace('{price}', strval($amount), $formula);
        $price = eval("return $formula;");

        return round($price, 2);
    }

    /**
     * @return Purchase
     */
    public static function purchaseRepo()
    {
        /** @var Purchase $repo */
        $repo = \XF::repository('Truonglv\XFRMCustomized:Purchase');

        return $repo;
    }
}
