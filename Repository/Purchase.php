<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\XFRMCustomized\Repository;

use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XFRM\Entity\ResourceItem;

class Purchase extends Repository
{
    protected $activePurchases = [];
    protected $purchases = [];

    /**
     * @param ResourceItem $resource
     * @param User|null $user
     * @return null|\Truonglv\XFRMCustomized\Entity\Purchase
     */
    public function getActivePurchase(ResourceItem $resource, User $user = null)
    {
        $user = $user ?: \XF::visitor();
        $cacheKey = $resource->resource_id . '_' . $user->user_id;

        if (array_key_exists($cacheKey, $this->activePurchases)) {
            return $this->activePurchases[$cacheKey];
        }

        $purchase = $this->finder('Truonglv\XFRMCustomized:Purchase')
                ->where('resource_id', $resource->resource_id)
                ->where('user_id', $user->user_id)
                ->where('expire_date', '>', \XF::$time)
                ->order('expire_date', 'ASC')
                ->fetchOne();

        $this->activePurchases[$cacheKey] = $purchase;
        return $purchase;
    }

    /**
     * @param ResourceItem $resource
     * @param User|null $user
     * @return \XF\Mvc\Entity\ArrayCollection|\Truonglv\XFRMCustomized\Entity\Purchase[]
     */
    public function getAllPurchases(ResourceItem $resource, User $user = null)
    {
        $user = $user ?: \XF::visitor();
        $cacheKey = $resource->resource_id . '_' . $user->user_id;

        if (array_key_exists($cacheKey, $this->purchases)) {
            return $this->purchases[$cacheKey];
        }

        $purchases = $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('resource_id', $resource->resource_id)
            ->where('user_id', $user->user_id)
            ->order('expire_date', 'ASC')
            ->fetch();

        $this->purchases[$cacheKey] = $purchases;

        return $purchases;
    }

    public function getPurchasedResources(User $user)
    {
        $db = $this->db();
        $resourceIds = $db->fetchAllColumn('
            SELECT DISTINCT(resource_id)
            FROM tl_xfrm_resource_purchase
            WHERE user_id = ?
        ', $user->user_id);

        if (empty($resourceIds)) {
            return [];
        }

        return $this->finder('XFRM:ResourceItem')
            ->with(['Category', 'User', 'Featured'])
            ->whereIds($resourceIds)
            ->fetch();
    }
}
