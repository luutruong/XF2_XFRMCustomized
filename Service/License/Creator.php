<?php

namespace Truonglv\XFRMCustomized\Service\License;

use XF;
use LogicException;
use XF\Entity\User;
use XF\Validator\Url;
use XFRM\Entity\ResourceItem;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use Truonglv\XFRMCustomized\Entity\License;

class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    protected ResourceItem $resource;
    protected User $user;
    protected License $license;

    protected ?string $licenseUrl = null;
    protected bool $removeOthers = true;

    public function __construct(\XF\App $app, ResourceItem $resource)
    {
        parent::__construct($app);

        $this->resource = $resource;
        $this->user = XF::visitor();

        /** @var License $license */
        $license = $app->em()->create('Truonglv\XFRMCustomized:License');
        $this->license = $license;
    }

    public function setLicenseUrl(string $licenseUrl): self
    {
        $this->licenseUrl = $this->validator()->coerceValue($licenseUrl);

        return $this;
    }

    protected function finalizeSetup(): void
    {
        $this->license->resource_id = $this->resource->resource_id;
        $this->license->user_id = $this->user->user_id;
        if ($this->licenseUrl !== null) {
            $this->license->license_url = $this->licenseUrl;
        }
    }

    /**
     * @return array
     */
    protected function _validate()
    {
        if ($this->licenseUrl === null) {
            throw new LogicException('Must be set licenseUrl property');
        }

        $this->finalizeSetup();
        $this->license->preSave();

        $errors = $this->license->getErrors();
        if (!$this->validator()->isValid($this->licenseUrl, $errorKey)) {
            $errors[] = $this->validator()->getPrintableErrorValue($errorKey);
        }

        return $errors;
    }

    protected function validator(): Url
    {
        /** @var Url $validator */
        $validator = $this->app->validator('XF:Url');

        return $validator;
    }

    /**
     * @return License
     * @throws \XF\PrintableException
     */
    protected function _save()
    {
        $db = $this->db();
        $db->beginTransaction();

        if ($this->removeOthers) {
            $db->update(
                'xf_xfrmc_license',
                [
                    'deleted_date' => XF::$time
                ],
                'resource_id = ? AND user_id = ? AND deleted_date = ?',
                [$this->resource->resource_id, $this->user->user_id, 0]
            );
        }

        $this->license->save(true, false);

        $db->commit();

        return $this->license;
    }
}
