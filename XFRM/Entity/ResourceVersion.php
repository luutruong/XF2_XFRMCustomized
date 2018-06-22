<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Entity;

use Truonglv\XFRMCustomized\Entity\Purchase;

class ResourceVersion extends XFCP_ResourceVersion
{
    public function canDownload(&$error = null)
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = $this->Resource;
        $visitor = \XF::visitor();

        if ($resource
            && $resource->price > 0
            && $this->file_count > 0
            && $resource->user_id != $visitor->user_id
        ) {
            if (!$resource->Purchases[$visitor->user_id]) {
                $error = \XF::phrase('xfrmc_you_may_purchase_this_resource_to_download');

                return false;
            }

            /** @var Purchase $purchase */
            foreach ($resource->Purchases[$visitor->user_id] as $purchase) {
                if (!$purchase->isExpired()) {
                    return parent::canDownload($error);
                }
            }

//            if ($resource->Purchase->isExpired()) {
//
//                // check the version which user can download.
//                if ($this->resource_version_id <= $resource->Purchase->resource_version_id) {
//                    return true;
//                }
//
//                $error = \XF::phrase('xfrmc_your_license_expired_renew_to_download_latest_version');
//
//                return false;
//            }
        }

        return parent::canDownload($error);
    }
}
