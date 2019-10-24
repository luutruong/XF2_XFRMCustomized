<?php

namespace Truonglv\XFRMCustomized;

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
}
