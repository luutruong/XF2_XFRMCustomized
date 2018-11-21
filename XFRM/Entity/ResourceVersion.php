<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Entity;

use Truonglv\XFRMCustomized\GlobalStatic;

class ResourceVersion extends XFCP_ResourceVersion
{
    public function canDownload(&$error = null)
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem|null $resource */
        $resource = $this->Resource;
        $visitor = \XF::visitor();

        if ($resource
            && $resource->price > 0
            && $this->file_count > 0
            && $resource->user_id != $visitor->user_id
        ) {
            $purchases = GlobalStatic::purchaseRepo()->getAllPurchases($resource, $visitor);
            if (!$purchases->count()) {
                $error = \XF::phrase('xfrmc_you_may_purchase_this_resource_to_download');

                return false;
            }

            foreach ($purchases as $purchase) {
                if (!$purchase->isExpired()) {
                    return parent::canDownload($error);
                }
            }

            // all purchases has been expired.
            $canDownloadThisVersion = false;
            foreach ($purchases as $purchase) {
                if ($purchase->canDownloadVersion($this)) {
                    $canDownloadThisVersion = true;

                    break;
                }
            }

            if ($canDownloadThisVersion) {
                return parent::canDownload($error);
            } else {
                $error = \XF::phrase('xfrmc_your_license_expired_renew_to_download_latest_version');

                return false;
            }
        }

        return parent::canDownload($error);
    }
}
