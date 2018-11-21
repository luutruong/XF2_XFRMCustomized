<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Job;

use XF\Job\AbstractRebuildJob;

class MigrateBDPaygate extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit('
            SELECT purchase_id
            FROM xf_bdpaygate_purchase
            WHERE purchase_id > ?
            ORDER BY purchase_id
        ', $batch), $start);
    }

    public function getStatusType()
    {
        return 'Import data from add-on [bd] Paygate...';
    }

    protected function rebuildById($id)
    {
        $db = $this->app->db();
        $paygatePurchase = $db->fetchRow('
            SELECT purchase.*, user.username
            FROM xf_bdpaygate_purchase AS purchase
                LEFT JOIN xf_user AS user ON (user.user_id = purchase.user_id)
            WHERE purchase.purchase_id = ?
        ', $id);

        if (!$paygatePurchase) {
            return;
        }

        $versionId = $db->fetchOne('
            SELECT resource_version_id
            FROM xf_rm_resource_version
            WHERE resource_id = ? AND release_date <= ?
            ORDER BY release_date DESC
        ', [
            $paygatePurchase['content_id'],
            $paygatePurchase['purchase_date']
        ]);

        /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchase */
        $purchase = \XF::finder('Truonglv\XFRMCustomized:Purchase')
                ->where('resource_id', $paygatePurchase['content_id'])
                ->where('user_id', $paygatePurchase['user_id'])
                ->fetchOne();

        if (!$purchase) {
            /** @var \Truonglv\XFRMCustomized\Entity\Purchase $purchase */
            $purchase = \XF::em()->create('Truonglv\XFRMCustomized:Purchase');
        }

        $purchase->resource_id = $paygatePurchase['content_id'];
        $purchase->user_id = $paygatePurchase['user_id'];
        $purchase->resource_version_id = $versionId;
        $purchase->username = $paygatePurchase['username'] ?? 'Guest';
        $purchase->purchased_date = $paygatePurchase['purchase_date'];

        $expireDate = max($purchase->expire_date, $paygatePurchase['purchase_date'] + 365 * 86400);
        $purchase->expire_date = intval($expireDate);
        $purchase->amount = $purchase->amount + $paygatePurchase['purchased_amount'];

        $purchase->save();
    }
}
