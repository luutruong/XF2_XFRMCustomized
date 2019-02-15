<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Pub\Controller;

class Category extends XFCP_Category
{
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
