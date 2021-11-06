<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Pub\Controller;

use XF\Entity\User;
use XF\Mvc\Reply\View;
use XF\Mvc\ParameterBag;
use XF\Entity\PaymentProfile;
use Truonglv\XFRMCustomized\App;
use XFRM\Entity\ResourceVersion;
use Truonglv\XFRMCustomized\Entity\License;
use Truonglv\XFRMCustomized\Entity\Purchase;
use Truonglv\XFRMCustomized\Service\License\Creator;

class ResourceItem extends XFCP_ResourceItem
{
    /**
     * @param \XFRM\Entity\ResourceItem $resource
     * @return \XFRM\Service\ResourceItem\Edit
     */
    protected function setupResourceEdit(\XFRM\Entity\ResourceItem $resource)
    {
        $editor = parent::setupResourceEdit($resource);

        $editor->getResource()->bulkSet($this->filter([
            'price' => 'float',
            'currency' => 'str',
            'renew_price' => 'float',
            'payment_profile_ids' => 'array-uint'
        ]));

        return $editor;
    }

    public function actionAddBuyer(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params->resource_id);
        if (!$resource->canAddBuyer($error)) {
            return $this->noPermission($error);
        }

        if ($this->isPost()) {
            $names = $this->filter('names', 'str');
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');

            $names = preg_split("/\s*,\s*/", $names, -1, PREG_SPLIT_NO_EMPTY);
            if ($names === false) {
                throw new \InvalidArgumentException('Cannot parse names. $names=' . $names);
            }

            $users = $userRepo->getUsersByNames($names, $notFound);

            if ($notFound) {
                return $this->error(\XF::phrase('following_members_not_found_x', [
                    'members' => implode(', ', $notFound)
                ]));
            }

            if ($users->count() === 0) {
                return $this->error(\XF::phrase('please_enter_valid_name'));
            }

            /** @var \XF\Entity\User $user */
            foreach ($users as $user) {
                $exists = $this->em()->findOne('Truonglv\XFRMCustomized:Purchase', [
                    'user_id' => $user->user_id,
                    'resource_id' => $resource->resource_id
                ]);

                if ($exists !== null) {
                    continue;
                }

                $purchasedDate = $this->filter('purchased_date', 'datetime');
                if ($purchasedDate < 1) {
                    $purchasedDate = \XF::$time;
                }

                $expireDate = $this->filter('expire_date', 'datetime');
                if ($expireDate < 1) {
                    $expireDate = $purchasedDate + 365 * 86400;
                }

                if ($expireDate < $purchasedDate) {
                    return $this->error(\XF::phrase('xfrmc_expire_date_must_be_great_than_purchased_date'));
                }

                /** @var \Truonglv\XFRMCustomized\Entity\Purchase $entity */
                $entity = $this->em()->create('Truonglv\XFRMCustomized:Purchase');
                $entity->resource_id = $resource->resource_id;
                $entity->user_id = $user->user_id;
                $entity->username = $user->username;
                $entity->purchased_date = $purchasedDate;
                $entity->expire_date = $expireDate;
                $entity->amount = $this->filter('amount', 'uint');
                $entity->resource_version_id = $resource->current_version_id;

                $entity->save();
            }

            return $this->redirect($this->buildLink('resources/buyers', $resource));
        }

        $viewParams = [
            'resource' => $resource,
            'formAction' => $this->buildLink('resources/add-buyer', $resource)
        ];

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\AddBuyer',
            'xfrmc_resource_add_buyer',
            $viewParams
        );
    }

    public function actionBuyerEdit(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params->resource_id);
        if (!$resource->canAddBuyer($error)) {
            return $this->noPermission($error);
        }

        /** @var User|null $user */
        $user = $this->em()->find('XF:User', $this->filter('user_id', 'uint'));
        if ($user === null) {
            return $this->notFound(\XF::phrase('requested_member_not_found'));
        }

        /** @var \Truonglv\XFRMCustomized\Entity\Purchase|null $purchased */
        $purchased = $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('resource_id', $resource->resource_id)
            ->where('user_id', $user->user_id)
            ->fetchOne();

        if ($purchased === null) {
            return $this->error(\XF::phrase('xfrmc_user_x_did_not_bought_this_resource', [
                'name' => $user->username
            ]));
        }

        if ($this->isPost()) {
            $expireDate = $this->filter('expire_date', 'datetime');
            if ($expireDate < $purchased->purchased_date) {
                return $this->error(\XF::phrase('xfrmc_expire_date_must_be_great_than_purchased_date'));
            }

            $purchased->amount = $this->filter('amount', 'float');
            $purchased->expire_date = $expireDate;

            $purchased->save();

            return $this->redirect($this->buildLink('resources/buyers', $resource));
        }

        $viewParams = [
            'resource' => $resource,
            'purchased' => $purchased,
            'formAction' => $this->buildLink('resources/buyer-edit', $resource)
        ];

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\BuyerEdit',
            'xfrmc_resource_add_buyer',
            $viewParams
        );
    }

    public function actionBuyers(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params->resource_id);
        if (!$resource->canEdit($error)) {
            return $this->noPermission($error);
        }

        $this->assertCanonicalUrl($this->buildLink('resources/buyers', $resource));

        $finder = $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->with('User')
            ->where('resource_id', $resource->resource_id)
            ->order('purchased_date', 'DESC');

        $username = $this->filter('username', 'str');
        $user = null;

        if ($username !== '') {
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            /** @var User|null $user */
            $user = $userRepo->getUserByNameOrEmail($username);

            $finder->where('user_id', $user !== null ? $user->user_id : 0);
        }

        $page = $this->filterPage();
        $perPage = 30;

        $total = $finder->total();
        $buyers = $finder->limitByPage($page, $perPage)
                         ->fetch();

        $this->assertValidPage($page, $perPage, $total, 'resources/buyers', $resource);

        $viewParams = [
            'resource' => $resource,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'buyers' => $buyers,
            'user' => $user
        ];

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\Buyers',
            'xfrmc_resource_buyers',
            $viewParams
        );
    }

    public function actionPurchase(ParameterBag $params)
    {
        $this->assertRegistrationRequired();

        $resource = $this->assertViewableResource($params->resource_id);
        if (!$resource->canPurchase($error)) {
            return $this->noPermission($error);
        }

        $existingLicenses = $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('user_id', \XF::visitor()->user_id)
            ->where('resource_id', $resource->resource_id)
            ->where('new_purchase_id', 0)
            ->total();
        if ($existingLicenses > 0) {
            return $this->rerouteController(__CLASS__, 'renew', $params);
        }

        /** @var \XF\Repository\Payment $paymentRepo */
        $paymentRepo = \XF::repository('XF:Payment');
        $paymentProfiles = $paymentRepo->findPaymentProfilesForList()
            ->whereIds($resource->payment_profile_ids)
            ->fetch();

        $paymentProfileId = $this->filter('payment_profile_id', 'uint');
        $viewParams = [
            'resource' => $resource,
            'paymentProfiles' => $paymentProfiles,
        ];

        if (isset($paymentProfiles[$paymentProfileId])) {
            /** @var PaymentProfile $paymentProfile */
            $paymentProfile = $paymentProfiles[$paymentProfileId];
            $viewParams += [
                'selectedPaymentProfile' => $paymentProfile,
                'purchasable' => $this->em()->find('XF:Purchasable', App::PURCHASABLE_ID),
                'purchasePrice' => $resource->getXFRMCPriceForProfile($paymentProfile),
                'checkCouponUrl' => $this->buildLink('resources/check-coupon', $resource, [
                    'payment_profile_id' => $paymentProfile->payment_profile_id,
                ]),
            ];
        }

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\Purchase',
            'xfrmc_resource_purchase',
            $viewParams
        );
    }

    public function actionRenew(ParameterBag $params)
    {
        $this->assertRegistrationRequired();

        $resource = $this->assertViewableResource($params->resource_id);
        if ($resource->canDownload()) {
            // skip users who has permission to download resource directly
            return $this->noPermission();
        }

        $purchases = $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('resource_id', $resource->resource_id)
            ->where('user_id', \XF::visitor()->user_id)
            ->where('new_purchase_id', 0)
            ->order('expire_date')
            ->fetch();
        if ($purchases->count() === 0) {
            return $this->error(\XF::phrase('xfrmc_you_did_not_have_any_licenses_for_this_resource'));
        }

        /** @var \XF\Repository\Payment $paymentRepo */
        $paymentRepo = \XF::repository('XF:Payment');
        $paymentProfiles = $paymentRepo->findPaymentProfilesForList()
            ->whereIds($resource->payment_profile_ids)
            ->fetch();

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\Renew',
            'xfrmc_resource_license_renew',
            [
                'resource' => $resource,
                'purchases' => $purchases,
                'purchasable' => $this->em()->find('XF:Purchasable', App::PURCHASABLE_ID),
                'paymentProfiles' => $paymentProfiles,
            ]
        );
    }

    public function actionPurchased()
    {
        $this->assertRegistrationRequired();

        $visitor = \XF::visitor();
        $resources = App::purchaseRepo()->getPurchasedResources($visitor);

        $viewParams = [
            'resources' => $resources
        ];

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\Purchased',
            'xfrmc_resource_purchased',
            $viewParams
        );
    }

    public function actionHistory(ParameterBag $params)
    {
        $response = parent::actionHistory($params);
        if ($response instanceof View) {
            /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
            $resource = $response->getParam('resource');
            if (!$resource->canViewHistory($error)) {
                throw $this->exception($this->noPermission($error));
            }
        }

        return $response;
    }

    public function actionDownload(ParameterBag $params)
    {
        $this->assertRegistrationRequired();
        $resource = $this->assertViewableResource($params['resource_id']);

        $visitor = \XF::visitor();
        if ($resource->user_id !== $visitor->user_id
            && !$resource->canDownload()
        ) {
            $activeLicenses = $this->finder('Truonglv\XFRMCustomized:Purchase')
                ->where('resource_id', $resource->resource_id)
                ->where('user_id', $visitor->user_id)
                ->where('new_purchase_id', 0)
                ->total();
            if ($activeLicenses === 0) {
                return $this->error(\XF::phrase('xfrmc_you_may_purchase_this_resource_to_download'));
            }
        }

        /** @var Purchase|null $lastPurchase */
        $lastPurchase = $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('user_id', $visitor->user_id)
            ->where('resource_id', $resource->resource_id)
            ->where('new_purchase_id', 0)
            ->order('expire_date', 'desc')
            ->fetchOne();

        $versionId = $this->filter('version_id', 'uint');
        if ($versionId > 0) {
            return $this->rerouteController('XFRM:ResourceVersion', 'download', [
                'resource_id' => $resource->resource_id,
                'resource_version_id' => $versionId,
            ]);
        }

        $limitVersionOp = '>=';
        $limitVersionValue = 0;

        if (($lastPurchase !== null && $lastPurchase->isExpired() && $lastPurchase->resource_version_id > 0)
            && !$resource->canDownload()
        ) {
            // only allow users download resources from previous version
            $limitVersionOp = '<=';
            $limitVersionValue = $lastPurchase->resource_version_id;
        }

        $versions = $this->finder('XFRM:ResourceVersion')
            ->where('resource_id', $resource->resource_id)
            ->where('version_state', 'visible')
            ->where('resource_version_id', $limitVersionOp, $limitVersionValue)
            ->order('release_date', 'DESC')
            ->fetchColumns([
                'resource_version_id',
                'version_string',
                'release_date'
            ]);
        foreach ($versions as &$version) {
            if ($visitor->user_id === $resource->user_id
                || $resource->canDownload()
            ) {
                $version['canDownload'] = true;

                continue;
            }

            if ($lastPurchase === null) {
                $version['canDownload'] = false;

                continue;
            }

            if ($lastPurchase->isExpired()) {
                $version['canDownload'] = $version['resource_version_id'] <= $lastPurchase->resource_version_id;
            } else {
                $version['canDownload'] = true;
            }
        }
        unset($version);

        $licenses = $this->finder('Truonglv\XFRMCustomized:License')
            ->where('resource_id', $resource->resource_id)
            ->where('user_id', $visitor->user_id)
            ->where('deleted_date', 0)
            ->fetch();

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\Download',
            'xfrmc_resource_download',
            [
                'resource' => $resource,
                'versions' => $versions,
                'inlineDownload' => $this->filter('_xfWithData', 'bool'),
                'purchase' => $lastPurchase,
                'licenses' => $licenses,
            ]
        );
    }

    public function actionLicenseUrl(ParameterBag $params)
    {
        $this->assertRegistrationRequired();
        $resource = $this->assertViewableResource($params['resource_id']);

        $redirect = $this->filter('redirect', 'str');
        /** @var License|null $lastLicense */
        $lastLicense = $this->finder('Truonglv\XFRMCustomized:License')
            ->where('resource_id', $resource->resource_id)
            ->where('user_id', \XF::visitor()->user_id)
            ->where('deleted_date', 0)
            ->order('added_date', 'desc')
            ->fetchOne();

        if ($this->isPost()) {
            $licenseUrl = $this->filter('license_url', 'str');
            $create = true;
            if ($lastLicense !== null && $lastLicense->license_url === $licenseUrl) {
                $create = false;
            }

            if ($create) {
                /** @var Creator $creator */
                $creator = $this->service('Truonglv\XFRMCustomized:License\Creator', $resource);
                $creator->setLicenseUrl($licenseUrl);

                if (!$creator->validate($errors)) {
                    return $this->error($errors);
                }

                $creator->save();
            }

            if ($redirect !== '') {
                return $this->redirect($redirect);
            }

            return $this->redirect($this->buildLink('resources', $resource));
        }

        return $this->view('', 'xfrmc_resource_license_url', [
            'resource' => $resource,
            'redirect' => $redirect,
            'lastLicense' => $lastLicense,
        ]);
    }

    public function actionDownloadLogs(ParameterBag $params)
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = $this->assertViewableResource($params['resource_id']);
        if (!$resource->canViewHistory($error)) {
            return $this->noPermission($error);
        }

        $versionId = $this->filter('version_id', 'uint');
        /** @var ResourceVersion $version */
        $version = $this->assertRecordExists(
            'XFRM:ResourceVersion',
            $versionId
        );

        if ($version->resource_id !== $resource->resource_id) {
            return $this->noPermission();
        }

        $finder = $this->finder('XFRM:ResourceDownload');
        $finder->with('User');
        $finder->where('resource_version_id', $version->resource_version_id);
        $finder->order('last_download_date', 'DESC');

        $page = $this->filterPage();
        $perPage = 20;

        $total = $finder->total();
        $entities = $total > 0
            ? $finder->limitByPage($page, $perPage)->fetch()
            : $this->em()->getEmptyCollection();

        return $this->view(
            'Truonglv\XFRMCustomized:ResourceVersion\DownloadLogs',
            'xfrmc_resource_download_logs',
            [
                'resource' => $resource,
                'entities' => $entities,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'version' => $version,
                'pageNavParams' => [
                    'version_id' => $version->resource_version_id,
                ],
            ]
        );
    }

    public function actionCheckCoupon(ParameterBag $params)
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = $this->assertViewableResource($params['resource_id']);
        $this->assertPostOnly();

        $input = $this->filter([
            'payment_profile_id' => 'uint',
            'coupon_code' => 'str'
        ]);

        /** @var \Truonglv\XFRMCustomized\Entity\Coupon|null $coupon */
        $coupon = $this->em()->findOne('Truonglv\XFRMCustomized:Coupon', [
            'coupon_code' => $input['coupon_code']
        ]);

        if ($coupon === null) {
            return $this->error(\XF::phrase('xfrmc_requested_coupon_not_found'));
        }

        /** @var PaymentProfile|null $paymentProfile */
        $paymentProfile = $this->em()->find('XF:PaymentProfile', $input['payment_profile_id']);
        if ($paymentProfile === null
            || !in_array($paymentProfile->payment_profile_id, $resource->payment_profile_ids, true)
        ) {
            return $this->noPermission();
        }

        if (!$coupon->canUseWith($resource, $error)) {
            return $this->error($error !== null
                ? $error
                : \XF::phrase('xfrmc_coupon_has_been_expired_or_deleted'));
        }

        $message = $this->message(\XF::phrase('xfrmc_coupon_code_available_for_use'));
        $price = $resource->getXFRMCPriceForProfile($paymentProfile, $coupon);

        $message->setJsonParam(
            'newPrice',
            $this->app()->templater()->filter($price, [['currency', [$resource->currency]]])
        );

        $message->setJsonParam(
            'newTotal',
            $this->app()->templater()->filter($price, [['currency', [$resource->currency]]])
        );

        return $message;
    }

    public function actionInvoices()
    {
        $this->assertRegistrationRequired();

        $purchaseId = $this->filter('purchase_id', 'uint');
        if ($purchaseId > 0) {
            /** @var Purchase|null $purchase */
            $purchase = $this->em()->find('Truonglv\XFRMCustomized:Purchase', $purchaseId);
            if ($purchase !== null && $purchase->canView()) {
                $purchaseRequest = $this->em()->findOne('XF:PurchaseRequest', [
                    'request_key' => $purchase->purchase_request_key,
                ]);

                return $this->view(
                    '',
                    'xfrmc_invoice_view',
                    [
                        'purchase' => $purchase,
                        'purchaseRequest' => $purchaseRequest,
                    ]
                );
            }
        }

        $finder = $this->finder('Truonglv\XFRMCustomized:Purchase');
        $finder->with('Resource');
        $finder->order('purchased_date', 'desc');

        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('resource', 'xfrmc_viewPurchaseAny')) {
            $finder->where('user_id', $visitor->user_id);
        }

        $page = $this->filterPage();
        $perPage = 20;

        $total = $finder->total();
        $purchases = $finder->limitByPage($page, $perPage)->fetch();

        return $this->view(
            '',
            'xfrmc_invoice_list',
            [
                'purchases' => $purchases,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
            ]
        );
    }

    /**
     * @param int|string|mixed $resourceId
     * @param array $extraWith
     * @return \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableResource($resourceId, array $extraWith = [])
    {
        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = parent::assertViewableResource($resourceId, $extraWith);

        return $resourceItem;
    }
}
