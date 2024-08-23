<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized;

use XF;
use function ceil;
use XF\Entity\User;
use function strpos;
use function explode;
use Truonglv\XFRMCustomized\Repository\PurchaseRepository;

class App
{
    const PURCHASABLE_ID = 'tl_xfrm_customized_resource';

    /**
     * @var null|XF\Entity\PaymentProfile[]
     */
    protected static $paymentProfiles = null;

    /**
     * @param string $permission
     * @param User|null $user
     * @return bool
     */
    public static function hasPermission(string $permission, User $user = null)
    {
        $user = $user !== null ? $user : XF::visitor();

        return $user->hasPermission('xfrmc', $permission);
    }

    /**
     * @return XF\Entity\PaymentProfile[]
     */
    public static function getPaymentProfiles()
    {
        if (self::$paymentProfiles === null) {
            $paymentProfileRepo = XF::repository(XF\Repository\PaymentRepository::class);
            /** @var XF\Entity\PaymentProfile[] $paymentProfiles */
            $paymentProfiles = $paymentProfileRepo->findPaymentProfilesForList()->fetch();

            self::$paymentProfiles = $paymentProfiles;
        }

        return self::$paymentProfiles;
    }

    /**
     * @param int $categoryId
     * @return bool
     */
    public static function isDisabledCategory(int $categoryId)
    {
        $disabled = XF::options()->xfrmc_disableCategories;
        $disabled = array_map('intval', $disabled);

        return in_array($categoryId, $disabled, true);
    }

    /**
     * @param float $amount
     * @return float
     */
    public static function getPriceWithTax(XF\Entity\PaymentProfile $paymentProfile, float $amount)
    {
        $formula = trim(XF::options()->xfrmc_feeFormula);
        if (strlen($formula) <= 0) {
            return $amount;
        }

        if ($amount < 1) {
            return $amount;
        }

        $formulaRules = [];
        $rawRules = XF\Util\Arr::stringToArray($formula, "/\r?\n/");
        foreach ($rawRules as $rawRule) {
            if (strpos($rawRule, '=') === false) {
                continue;
            }

            $parts = explode('=', $rawRule, 2);
            $paymentProfileId = \trim($parts[0]);
            $formulaRules[$paymentProfileId] = \trim($parts[1]);
        }

        if (!isset($formulaRules[$paymentProfile->provider_id])) {
            return $amount;
        }

        $formula = str_replace('{price}', strval($amount), $formulaRules[$paymentProfile->provider_id]);
        $price = eval("return $formula;");

        return ceil($price);
    }

    /**
     * @return PurchaseRepository
     */
    public static function purchaseRepo()
    {
        $repo = XF::repository(PurchaseRepository::class);

        return $repo;
    }
}
