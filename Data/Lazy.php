<?php

namespace Truonglv\XFRMCustomized\Data;

class Lazy
{
    /**
     * @var null|array
     */
    protected $purchasedPairs = null;

    /**
     * @param int $resourceId
     * @param null|int $userId
     * @return bool
     */
    public function isPurchasedResource($resourceId, $userId = null)
    {
        $userId = ($userId === null) ? \XF::visitor()->user_id : $userId;
        if ($userId <= 0) {
            return false;
        }

        if ($this->purchasedPairs === null) {
            $this->purchasedPairs = [];

            $db = \XF::db();
            $results = $db->fetchAll('
                SELECT resource_id, user_id
                FROM xf_xfrmc_resource_purchase
                GROUP BY resource_id, user_id
            ');

            foreach ($results as $result) {
                $this->purchasedPairs[$result['user_id']][] = $result['resource_id'];
            }
        }

        return (isset($this->purchasedPairs[$userId]) && in_array($resourceId, $this->purchasedPairs[$userId], true));
    }
}
