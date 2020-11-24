<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Repository;

use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;
use XFRM\Entity\ResourceItem;

class Purchase extends Repository
{
    public function getActivePurchase(ResourceItem $resource, User $user = null): ?\Truonglv\XFRMCustomized\Entity\Purchase
    {
        $user = $user !== null ? $user : \XF::visitor();

        /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchase */
        $purchase = $this->finder('Truonglv\XFRMCustomized:Purchase')
                ->where('resource_id', $resource->resource_id)
                ->where('user_id', $user->user_id)
                ->where('expire_date', '>', \XF::$time)
                ->order('expire_date', 'ASC')
                ->fetchOne();

        return $purchase;
    }

    public function getAllPurchases(ResourceItem $resource, User $user = null): AbstractCollection
    {
        $user = $user !== null ? $user : \XF::visitor();

       return $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('resource_id', $resource->resource_id)
            ->where('user_id', $user->user_id)
            ->order('expire_date', 'ASC')
            ->fetch();
    }

    public function getPurchasedResources(User $user): AbstractCollection
    {
        $db = $this->db();
        $resourceIds = $db->fetchAllColumn('
            SELECT DISTINCT(resource_id)
            FROM tl_xfrm_resource_purchase
            WHERE user_id = ?
        ', $user->user_id);

        if (count($resourceIds) === 0) {
            return $this->em->getEmptyCollection();
        }

        return $this->finder('XFRM:ResourceItem')
            ->with(['Category', 'User', 'Featured'])
            ->whereIds($resourceIds)
            ->fetch();
    }
}
