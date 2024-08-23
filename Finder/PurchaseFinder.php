<?php

namespace Truonglv\XFRMCustomized\Finder;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\AbstractCollection;
use Truonglv\XFRMCustomized\Entity\Purchase;

/**
 * @method AbstractCollection<Purchase> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Purchase> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Purchase|null fetchOne(?int $offset = null)
 * @extends Finder<Purchase>
 */
class PurchaseFinder extends Finder
{
}
