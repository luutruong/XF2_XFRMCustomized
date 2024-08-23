<?php

namespace Truonglv\XFRMCustomized\Finder;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\AbstractCollection;
use Truonglv\XFRMCustomized\Entity\Coupon;

/**
 * @method AbstractCollection<Coupon> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Coupon> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Coupon|null fetchOne(?int $offset = null)
 * @extends Finder<Coupon>
 */
class CouponFinder extends Finder
{
}
