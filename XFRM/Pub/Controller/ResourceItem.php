<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Pub\Controller;

use XF\Entity\User;
use XF\Mvc\Reply\View;
use XF\Mvc\ParameterBag;
use Truonglv\XFRMCustomized\App;
use Truonglv\XFRMCustomized\Entity\Purchase;

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

        if (App::purchaseRepo()->getActivePurchase($resource) !== null) {
            return $this->redirect($this->buildLink('resources', $resource));
        }

        /** @var \XF\Repository\Payment $paymentRepo */
        $paymentRepo = \XF::repository('XF:Payment');
        $paymentProfiles = $paymentRepo->findPaymentProfilesForList()
            ->whereIds($resource->payment_profile_ids)
            ->fetch();

        $purchasePrice = $resource->getPurchasePrice();
        $isRenewPurchase = $resource->isRenewLicense();

        $selPaymentProfile = null;
        if ($paymentProfiles->count() === 1) {
            $selPaymentProfile = $paymentProfiles->first();
        }

        $viewParams = [
            'resource' => $resource,
            'purchasable' => $this->em()->find('XF:Purchasable', App::PURCHASABLE_ID),
            'paymentProfiles' => $paymentProfiles,
            'isRenewPurchase' => $isRenewPurchase,
            'purchasePrice' => $purchasePrice,
            'selPaymentProfile' => $selPaymentProfile
        ];

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\Purchase',
            'xfrmc_resource_purchase',
            $viewParams
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

        if ($resource->user_id !== \XF::visitor()->user_id) {
            $anyVersions = $this->finder('Truonglv\XFRMCustomized:Purchase')
                ->where('resource_id', $resource->resource_id)
                ->where('user_id', \XF::visitor()->user_id)
                ->total();
            if ($anyVersions === 0) {
                return $this->error(\XF::phrase('xfrmc_you_may_purchase_this_resource_to_download'));
            }
        }

        /** @var Purchase|null $lastPurchase */
        $lastPurchase = $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('user_id', \XF::visitor()->user_id)
            ->where('resource_id', $resource->resource_id)
            ->order('purchased_date', 'desc')
            ->fetchOne();

        $versionId = $this->filter('version_id', 'uint');
        if ($versionId > 0) {
            $canDownload = false;
            if ($resource->user_id !== \XF::visitor()->user_id) {
                if ($lastPurchase !== null) {
                    if ($lastPurchase->isExpired()) {
                        $canDownload = $versionId <= $lastPurchase->resource_version_id;
                    } else {
                        $canDownload = true;
                    }
                }
            } else {
                $canDownload = true;
            }

            if ($canDownload) {
                return $this->rerouteController('XFRM:ResourceVersion', 'download', [
                    'resource_id' => $resource->resource_id,
                    'resource_version_id' => $versionId
                ]);
            }
        }

        $versions = $this->finder('XFRM:ResourceVersion')
            ->where('resource_id', $resource->resource_id)
            ->where('version_state', 'visible')
            ->order('release_date', 'DESC')
            ->fetchColumns([
                'resource_version_id',
                'version_string',
                'release_date'
            ]);
        foreach ($versions as &$version) {
            if (\XF::visitor()->user_id === $resource->user_id) {
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

        return $this->view(
            'Truonglv\XFRMCustomized:Resource\Download',
            'xfrmc_resource_download',
            [
                'resource' => $resource,
                'versions' => $versions,
                'inlineDownload' => $this->filter('_xfWithData', 'bool'),
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
