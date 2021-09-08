<?php

namespace Truonglv\XFRMCustomized\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\ControllerPlugin\Delete;
use XF\Mvc\Reply\AbstractReply;
use XF\Admin\Controller\AbstractController;

class Coupon extends AbstractController
{
    public function actionIndex()
    {
        $page = $this->filterPage();
        $perPage = 20;

        $finder = $this->finder('Truonglv\XFRMCustomized:Coupon');
        $finder->order('coupon_code');

        $total = $finder->total();
        $coupons = $finder->limitByPage($page, $perPage)->fetch();

        return $this->view(
            'Truonglv\XFRMCustomized:Coupon\List',
            'xfrmc_coupon_list',
            [
                'linkPrefix' => $this->getLinkPrefix(),
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'coupons' => $coupons,
            ]
        );
    }

    public function actionAdd()
    {
        return $this->couponAddEdit($this->getNewCoupon());
    }

    public function actionEdit(ParameterBag $params)
    {
        return $this->couponAddEdit($this->assertCouponExists($params['coupon_id']));
    }

    public function actionSave(ParameterBag $params)
    {
        if ($params['coupon_id'] > 0) {
            $coupon = $this->assertCouponExists($params['coupon_id']);
        } else {
            $coupon = $this->getNewCoupon();

            $visitor = \XF::visitor();
            $coupon->user_id = $visitor->user_id;
            $coupon->username = $visitor->username;
        }

        $inputData = $this->filter([
            'title' => 'str',
            'coupon_code' => 'str',
            'discount_amount' => 'uint',
            'discount_unit' => 'str',
            'max_use_count' => 'uint',
        ]);
        $coupon->bulkSet($inputData);

        $criteria = [
            'user' => $this->filter('user_criteria', 'array'),
            'resource' => []
        ];
        $resourceCriteria = $this->filter([
            'resource_criteria' => [
                'category_ids' => 'array-uint',
                'resource_ids' => 'str',
            ]
        ]);

        $resourceIds = explode(',', $resourceCriteria['resource_criteria']['resource_ids']);
        $resourceIds = array_map('intval', $resourceIds);
        sort($resourceIds, SORT_NUMERIC);
        $resourceIds = array_diff($resourceIds, [0]);

        $criteria['resource'] = [
            'category_ids' => in_array(0, $resourceCriteria['resource_criteria']['category_ids'], true)
                ? []
                : $resourceCriteria['resource_criteria']['category_ids'],
            'resource_ids' => implode(', ', $resourceIds),
        ];
        $coupon->criteria = $criteria;

        /** @var \DateTime|null $beginDate */
        $beginDate = $this->filter('begin_date', 'datetime,obj');
        if ($beginDate === null) {
            return $this->error(\XF::phrase('please_enter_valid_date_format'));
        }
        $coupon->begin_date = $beginDate->setTime(0, 0, 0)->getTimestamp();

        /** @var \DateTime|null $endDate */
        $endDate = $this->filter('end_date', 'datetime,obj');
        if ($endDate !== null) {
            $coupon->end_date = $endDate->setTime(23, 59, 59)->getTimestamp();
        }

        $coupon->save();

        return $this->redirect(
            $this->buildLink($this->getLinkPrefix())
            . $this->app()->getRedirectHash(strval($coupon->coupon_id))
        );
    }

    public function actionDelete(ParameterBag $params)
    {
        $coupon = $this->assertCouponExists($params['coupon_id']);

        /** @var Delete $delete */
        $delete = $this->plugin('XF:Delete');

        return $delete->actionDelete(
            $coupon,
            $this->buildLink($this->getLinkPrefix() . '/delete', $coupon),
            $this->buildLink($this->getLinkPrefix() . '/edit', $coupon),
            $this->buildLink($this->getLinkPrefix()),
            $coupon->title
        );
    }

    protected function couponAddEdit(\Truonglv\XFRMCustomized\Entity\Coupon $coupon): AbstractReply
    {
        $userCriteria = $this->app->criteria('XF:User', $coupon->criteria['user'] ?? []);
        /** @var \XFRM\Repository\Category $xfrmCategoryRepo */
        $xfrmCategoryRepo = $this->repository('XFRM:Category');

        return $this->view(
            'Truonglv\XFRMCustomized:Coupon\Edit',
            'xfrmc_coupon_edit',
            [
                'coupon' => $coupon,
                'linkPrefix' => $this->getLinkPrefix(),
                'userCriteria' => $userCriteria,
                'xfrmCategories' => $xfrmCategoryRepo->getCategoryOptionsData(),
            ]
        );
    }

    protected function getNewCoupon(): \Truonglv\XFRMCustomized\Entity\Coupon
    {
        /** @var \Truonglv\XFRMCustomized\Entity\Coupon $coupon */
        $coupon = $this->em()->create('Truonglv\XFRMCustomized:Coupon');

        return $coupon;
    }

    /**
     * @param mixed $couponId
     * @return \Truonglv\XFRMCustomized\Entity\Coupon
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCouponExists($couponId): \Truonglv\XFRMCustomized\Entity\Coupon
    {
        /** @var \Truonglv\XFRMCustomized\Entity\Coupon $coupon */
        $coupon = $this->assertRecordExists(
            'Truonglv\XFRMCustomized:Coupon',
            $couponId,
            [],
            'requested_page_not_found'
        );

        return $coupon;
    }

    protected function getLinkPrefix(): string
    {
        return 'xfrmc-coupons';
    }
}
