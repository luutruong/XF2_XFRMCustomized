<?php

namespace Truonglv\XFRMCustomized\Service\License;

use XF;
use XF\Entity\User;
use XFRM\Entity\ResourceItem;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class Transfer extends AbstractService
{
    use ValidateAndSavableTrait;

    /**
     * @var ResourceItem
     */
    protected $resource;
    /**
     * @var User|null
     */
    protected $fromUser;
    /**
     * @var User|null
     */
    protected $toUser;

    public function __construct(\XF\App $app, ResourceItem $resource)
    {
        parent::__construct($app);

        $this->resource = $resource;
        $this->fromUser = XF::visitor();
    }

    public function setFromUsername(string $username): void
    {
        /** @var User|null $user */
        $user = $this->finder('XF:User')->where('username', $username)->fetchOne();
        $this->fromUser = $user;
    }

    public function setToUsername(string $username): void
    {
        /** @var User|null $user */
        $user = $this->finder('XF:User')->where('username', $username)->fetchOne();
        $this->setToUser($user);
    }

    public function setToUser(?User $toUser): void
    {
        $this->toUser = $toUser;
    }

    /**
     * @return array
     */
    protected function _validate()
    {
        $errors = [];

        if ($this->fromUser === null) {
            $errors[] = XF::phrase('xfrmc_please_enter_valid_from_user');
        }
        if ($this->toUser === null) {
            $errors[] = XF::phrase('xfrmc_please_enter_valid_target_user');
        }

        if ($this->fromUser !== null
            && $this->toUser !== null
            && $this->fromUser->user_id === $this->toUser->user_id
        ) {
            $errors[] = XF::phrase('xfrmc_transfer_to_same_user');
        }

        return $errors;
    }

    /**
     * @return bool
     */
    protected function _save()
    {
        $db = $this->db();
        $db->beginTransaction();

        /** @var User $toUser */
        $toUser = $this->toUser;
        /** @var User $fromUser */
        $fromUser = $this->fromUser;

        $db->update(
            'xf_xfrmc_resource_purchase',
            [
                'user_id' => $toUser->user_id,
                'username' => $toUser->username,
            ],
            'resource_id = ? AND user_id = ?',
            [$this->resource->resource_id, $fromUser->user_id]
        );
        $db->update(
            'xf_xfrmc_license',
            [
                'user_id' => $toUser->user_id
            ],
            'resource_id = ? AND user_id = ?',
            [$this->resource->resource_id, $fromUser->user_id]
        );

        $db->commit();

        return true;
    }
}
