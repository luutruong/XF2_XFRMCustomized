<?php

namespace Truonglv\XFRMCustomized\XFRM\Pub\Controller;

use XF\Mvc\ParameterBag;

class ResourceVersion extends XFCP_ResourceVersion
{
    public function actionDownload(ParameterBag $params)
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceVersion $version */
        $version = $this->assertViewableVersion($params['resource_version_id']);
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = $version->Resource;

        $licenses = $this->finder('Truonglv\XFRMCustomized:License')
            ->where('resource_id', $resource->resource_id)
            ->where('user_id', \XF::visitor()->user_id)
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
