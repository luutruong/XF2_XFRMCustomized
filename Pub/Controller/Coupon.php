<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
namespace Truonglv\XFRMCustomized\Pub\Controller;

use Truonglv\XFRMCustomized\Service\Coupon\Creator;
use Truonglv\XFRMCustomized\Service\Coupon\Editor;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Repository\UserGroup;
use XFRM\Entity\ResourceItem;

class Coupon extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        if ($params->coupon_id) {
            return $this->rerouteController(__CLASS__,'view', $params);
        }

        $finder = $this->finder('Truonglv\XFRMCustomized:Coupon')
            ->order('created_date', 'DESC');

        $page = $this->filterPage();
        $perPage = 20;

        $coupons = $finder->limitByPage($page, $perPage)->fetch();
        $totalCodes = $finder->total();

        return $this->view('', 'xfrm_customized_coupon_list', [
            'page' => $page,
            'perPage' => $perPage,
            'coupons' => $coupons,
            'total' => $totalCodes
        ]);
    }

    public function actionView(ParameterBag $params)
    {
        $coupon = $this->assertCouponViewable($params->coupon_id);

        $viewParams = [
            'coupon' => $coupon
        ];

        return $this->view('Truonglv\XFRMCustomized:Coupon\View', 'xfrmc_coupon_view', $viewParams);
    }

    public function actionCheck()
    {
        $this->assertPostOnly();

        $input = $this->filter([
            'resource_id' => 'uint',
            'coupon_code' => 'str'
        ]);

        /** @var \Truonglv\XFRMCustomized\Entity\Coupon $coupon */
        $coupon = $this->em()->findOne('Truonglv\XFRMCustomized:Coupon', [
            'coupon_code' => $input['coupon_code']
        ]);

        if (!$coupon || !$coupon->canView()) {
            return $this->error(\XF::phrase('tl_xfrm_customized.requested_coupon_not_found'));
        }

        /** @var \Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem $resource */
        $resource = $this->em()->find('XFRM:ResourceItem', $input['resource_id']);
        if (!$resource || !$resource->canView()) {
            return $this->error(\XF::phrase('xfrm_requested_resource_not_found'));
        }

        if (!$coupon->canUse($resource)) {
            return $this->error(\XF::phrase('tl_xfrm_customized.coupon_has_been_expired_or_deleted'));
        }

        $message = $this->message(\XF::phrase('tl_xfrm_customized.coupon_code_available_for_use'));

        $price = $coupon->getFinalPrice($resource);
        $price = $this->app()->templater()->filter($price, [['currency', [$resource->currency]]]);
        $message->setJsonParam('newPrice', $price);

        return $message;
    }

    public function actionAdd()
    {
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

        $coupon = $this->em()->create('Truonglv\XFRMCustomized:Coupon');
        return $this->getCouponForm($coupon);
    }

    public function actionEdit(ParameterBag $params)
    {
        $coupon = $this->assertCouponViewable($params->coupon_id);

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

    protected function assertCouponViewable($id)
    {
        /** @var \Truonglv\XFRMCustomized\Entity\Coupon $coupon */
        $coupon = $this->em()->find('Truonglv\XFRMCustomized:Coupon', $id);
        if (!$coupon) {
            throw $this->exception($this->notFound(\XF::phrase('tl_xfrm_customized.requested_coupon_not_found')));
        }

        if (!$coupon->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $coupon;
    }

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
                'resource_ids' => 'str'
            ]
        ]);

        return $input;
    }

    protected function getCouponForm(\Truonglv\XFRMCustomized\Entity\Coupon $coupon)
    {
        /** @var UserGroup $userGroupRepo */
        $userGroupRepo = $this->repository('XF:UserGroup');

        $viewParams = [
            'coupon' => $coupon,
            'userGroups' => $userGroupRepo->getUserGroupTitlePairs()
        ];

        return $this->view(
            'Truonglv\XFRMCustomized:Coupon\Add',
            'xfrmc_coupon_add',
            $viewParams
        );
    }
}