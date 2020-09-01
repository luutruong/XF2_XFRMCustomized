<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Pub\Controller;

use XF\Mvc\Reply\View;
use XF\Mvc\ParameterBag;
use Truonglv\XFRMCustomized\App;

class Category extends XFCP_Category
{
    public function actionAdd(ParameterBag $params)
    {
        $response = parent::actionAdd($params);
        if ($response instanceof View) {
            /** @var \XFRM\Entity\Category $category */
            $category = $response->getParam('category');
            $response->setParam('xfrmc_isEnabled', !App::isDisabledCategory($category->resource_category_id));
        }

        return $response;
    }

    protected function setupResourceCreate(\XFRM\Entity\Category $category)
    {
        $creator = parent::setupResourceCreate($category);

        $creator->getResource()->bulkSet($this->filter([
            'price' => 'str',
            'currency' => 'str',
            'renew_price' => 'float',
            'payment_profile_ids' => 'array-uint'
        ]));

        return $creator;
    }
}
