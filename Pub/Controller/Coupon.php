<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Repository\UserGroup;
use XFRM\Repository\Category;
use Truonglv\XFRMCustomized\GlobalStatic;
use XF\Pub\Controller\AbstractController;
use Truonglv\XFRMCustomized\Service\Coupon\Editor;
use Truonglv\XFRMCustomized\Service\Coupon\Creator;

class Coupon extends AbstractController
{
    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Reroute|\XF\Mvc\Reply\View
     */
    public function actionIndex(ParameterBag $params)
    {
        if ($params->coupon_id) {
            return $this->rerouteController(__CLASS__, 'view', $params);
        }

        if (!GlobalStatic::hasPermission('viewList')) {
            return $this->noPermission();
        }

        $finder = $this->finder('Truonglv\XFRMCustomized:Coupon')
            ->order('created_date', 'DESC');

        $page = $this->filterPage();
        $perPage = 20;

        $coupons = $finder->limitByPage($page, $perPage)->fetch();
        $totalCodes = $finder->total();

        return $this->view('', 'xfrmc_coupon_list', [
            'page' => $page,
            'perPage' => $perPage,
            'coupons' => $coupons,
            'total' => $totalCodes
        ]);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionView(ParameterBag $params)
    {
        $coupon = $this->assertCouponViewable($params->coupon_id);

        $this->assertCanonicalUrl($this->buildLink('resources/coupons', $coupon));

        $page = $this->filterPage();
        $perPage = 20;

        $finder = $this->finder('Truonglv\XFRMCustomized:CouponUser')
            ->with(['User', 'Resource'])
            ->where('coupon_id', $coupon->coupon_id)
            ->order('created_date', 'desc');

        $users = $finder->limitByPage($page, $perPage)->fetch();
        $total = $finder->total();

        $this->assertValidPage($page, $perPage, $total, 'resources/coupons', $coupon);

        $userGroups = null;
        $categories = null;
        $resources = null;

        if (count($coupon->apply_rules['usable_user_group_ids']) > 0) {
            $userGroups = \XF::em()->findByIds('XF:UserGroup', $coupon->apply_rules['usable_user_group_ids']);
        }

        if (count($coupon->apply_rules['category_ids']) > 0) {
            $categories = \XF::em()->findByIds('XFRM:Category', $coupon->apply_rules['category_ids']);
        }

        if (count($coupon->apply_rules['resource_ids']) > 0) {
            $resources = \XF::em()->findByIds('XFRM:ResourceItem', $coupon->apply_rules['resource_ids']);
        }

        $viewParams = [
            'coupon' => $coupon,
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'userGroups' => $userGroups,
            'categories' => $categories,
            'resources' => $resources
        ];

        return $this->view('Truonglv\XFRMCustomized:Coupon\View', 'xfrmc_coupon_view', $viewParams);
    }

    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionCheck()
    {
        $this->assertPostOnly();

        $input = $this->filter([
            'resource_id' => 'uint',
            'coupon_code' => 'str'
        ]);

        /** @var \Truonglv\XFRMCustomized\Entity\Coupon|null $coupon */
        $coupon = $this->em()->findOne('Truonglv\XFRMCustomized:Coupon', [
            'coupon_code' => $input['coupon_code']
        ]);

        if ($coupon === null) {
            return $this->error(\XF::phrase('xfrmc_requested_coupon_not_found'));
        }

        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem|null $resource */
        $resource = $this->em()->find('XFRM:ResourceItem', $input['resource_id']);
        if ($resource === null || !$resource->canView()) {
            return $this->error(\XF::phrase('xfrm_requested_resource_not_found'));
        }

        if (!$coupon->canUseWith($resource, $error)) {
            return $this->error($error !== null
                ? $error
                : \XF::phrase('xfrmc_coupon_has_been_expired_or_deleted'));
        }

        $message = $this->message(\XF::phrase('xfrmc_coupon_code_available_for_use'));
        $price = $coupon->getFinalPrice($resource);

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

    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionAdd()
    {
        if (!GlobalStatic::hasPermission('add')) {
            return $this->noPermission();
        }

        if ($this->isPost()) {
            /** @var Creator $creator */
            $creator = $this->service('Truonglv\XFRMCustomized:Coupon\Creator');

            $creator->getCoupon()->bulkSet($this->getCouponInput());

            if (!$creator->validate($errors)) {
                return $this->error($errors);
            }
            $coupon = $creator->save();

            return $this->redirect($this->buildLink('resources/coupons', $coupon));
        }
        /** @var \Truonglv\XFRMCustomized\Entity\Coupon $coupon */
        $coupon = $this->em()->create('Truonglv\XFRMCustomized:Coupon');

        return $this->getCouponForm($coupon);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionEdit(ParameterBag $params)
    {
        $coupon = $this->assertCouponViewable($params->coupon_id);
        $error = null;
        if (!$coupon->canEdit($error)) {
            return $this->noPermission($error);
        }

        $this->assertCanonicalUrl($this->buildLink('resources/coupons/edit', $coupon));

        if (!$coupon->canEdit($error)) {
            return $this->noPermission($error);
        }

        if ($this->isPost()) {
            /** @var Editor $editor */
            $editor = $this->service('Truonglv\XFRMCustomized:Coupon\Editor', $coupon);

            $editor->getCoupon()->bulkSet($this->getCouponInput());
            if (!$editor->validate($errors)) {
                return $this->error($errors);
            }

            $coupon = $editor->save();

            return $this->redirect($this->buildLink('resources/coupons', $coupon));
        }

        return $this->getCouponForm($coupon);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionDelete(ParameterBag $params)
    {
        $coupon = $this->assertCouponViewable($params->coupon_id);
        $error = null;
        if (!$coupon->canDelete($error)) {
            return $this->noPermission($error);
        }

        $this->assertCanonicalUrl($this->buildLink('resources/coupons/delete', $coupon));

        if ($this->isPost()) {
            $coupon->delete();

            return $this->redirect($this->buildLink('resources/coupons'));
        }

        return $this->view('', 'xfrmc_coupon_delete', [
            'coupon' => $coupon
        ]);
    }

    /**
     * @param int $id
     * @return \Truonglv\XFRMCustomized\Entity\Coupon
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCouponViewable($id)
    {
        /** @var \Truonglv\XFRMCustomized\Entity\Coupon|null $coupon */
        $coupon = $this->em()->find('Truonglv\XFRMCustomized:Coupon', $id);
        if ($coupon === null) {
            throw $this->exception($this->notFound(\XF::phrase('xfrmc_requested_coupon_not_found')));
        }

        $error = null;
        if (!$coupon->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $coupon;
    }

    /**
     * @return array
     */
    protected function getCouponInput()
    {
        $input = $this->filter([
            'title' => 'str',
            'coupon_code' => 'str',
            'begin_date' => 'datetime',
            'end_date' => 'datetime,end',
            'max_use_count' => 'uint',
            'discount_amount' => 'uint',
            'discount_unit' => 'str',
            'apply_rules' => [
                'usable_user_group_ids' => 'array-uint',
                'resource_ids' => 'str',
                'category_ids' => 'array-uint'
            ]
        ]);

        $resourceIds = explode(',', $input['apply_rules']['resource_ids']);
        $resourceIds = array_map('intval', $resourceIds);

        $resources = \XF::em()->findByIds('XFRM:ResourceItem', $resourceIds);
        $validResourceIds = [];

        foreach ($resources as $resource) {
            $validResourceIds[] = $resource->resource_id;
        }

        $input['apply_rules']['resource_ids'] = $validResourceIds;

        return $input;
    }

    /**
     * @param \Truonglv\XFRMCustomized\Entity\Coupon $coupon
     * @return \XF\Mvc\Reply\View
     */
    protected function getCouponForm(\Truonglv\XFRMCustomized\Entity\Coupon $coupon)
    {
        /** @var UserGroup $userGroupRepo */
        $userGroupRepo = $this->repository('XF:UserGroup');

        /** @var Category $xfrmCategoryRepo */
        $xfrmCategoryRepo = $this->repository('XFRM:Category');

        $viewParams = [
            'coupon' => $coupon,
            'userGroups' => $userGroupRepo->getUserGroupTitlePairs(),
            'xfrmCategoryTree' => $xfrmCategoryRepo->createCategoryTree()
        ];

        return $this->view(
            'Truonglv\XFRMCustomized:Coupon\Add',
            'xfrmc_coupon_add',
            $viewParams
        );
    }
}
