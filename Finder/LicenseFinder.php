<?php

namespace Truonglv\XFRMCustomized\Finder;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\AbstractCollection;
use Truonglv\XFRMCustomized\Entity\License;

/**
 * @method AbstractCollection<License> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<License> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method License|null fetchOne(?int $offset = null)
 * @extends Finder<License>
 */
class LicenseFinder extends Finder
{
}
