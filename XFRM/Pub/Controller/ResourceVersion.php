<?php

namespace Truonglv\XFRMCustomized\XFRM\Pub\Controller;

use XF;
use XF\Mvc\ParameterBag;
use Truonglv\XFRMCustomized\App;
use Truonglv\XFRMCustomized\Finder\LicenseFinder;

class ResourceVersion extends XFCP_ResourceVersion
{
    public function actionDownload(ParameterBag $params)
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceVersion $version */
        $version = $this->assertViewableVersion($params['resource_version_id']);
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = $version->Resource;
        if (App::isDisabledCategory($resource->resource_category_id)) {
            return parent::actionDownload($params);
        }

        $licenses = $this->finder(LicenseFinder::class)
            ->where('resource_id', $resource->resource_id)
            ->where('user_id', XF::visitor()->user_id)
            ->where('deleted_date', 0)
            ->total();
        if ($licenses <= 0) {
            return $this->redirect($this->buildLink('resources/license-url', $resource, [
                'redirect' => $this->buildLink('resources/version/download', $version),
            ]));
        }

        return parent::actionDownload($params);
    }
}
