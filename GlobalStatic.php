<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\XFRMCustomized;

use XF\Entity\User;

class GlobalStatic
{
    const PURCHASABLE_ID = 'tl_xfrm_customized_resource';

    public static function hasPermission($permission, User $user = null)
    {
        $user = $user ?: \XF::visitor();

        return $user->hasPermission('xfrmc', $permission);
    }
}
