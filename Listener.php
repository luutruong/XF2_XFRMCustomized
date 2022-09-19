<?php

namespace Truonglv\XFRMCustomized;

use XF;

class Listener
{
    /**
     * @param \XF\Pub\App $app
     * @return void
     */
    public static function appPubSetup(\XF\Pub\App $app)
    {
        $c = $app->container();

        $c['xfrmIconSizeMap'] = function () use ($app) {
            return [
                'm' => $app->options()->xfrmc_resourceIconSize
            ];
        };
    }

    /**
     * @param string $rule
     * @param array $data
     * @param \XF\Entity\User $user
     * @param mixed $returnValue
     * @return void
     */
    public static function criteria_user($rule, array $data, \XF\Entity\User $user, &$returnValue): void
    {
        if ($rule === 'xfrmc_purchase_resources'
            && $data['total'] > 0
        ) {
            $total = XF::finder('Truonglv\XFRMCustomized:Purchase')
                ->where('user_id', $user->user_id)
                ->total();
            if ($total >= $data['total']) {
                $returnValue = true;
            }
        }
    }
}
