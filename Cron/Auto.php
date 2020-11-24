<?php

namespace Truonglv\XFRMCustomized\Cron;

use XF\Entity\User;
use XF\Service\Conversation\Creator;
use Truonglv\XFRMCustomized\Entity\Purchase;
use Truonglv\XFRMCustomized\XFRM\Entity\ResourceItem;

class Auto
{
    public static function reminderLicenseExpires(): void
    {
        $conversationStarter = \XF::app()->options()->xfrmc_conversationStarter;
        if (strlen($conversationStarter) === 0) {
            return;
        }

        /** @var User|null $user */
        $user = \XF::finder('XF:User')->where('username', $conversationStarter)->fetch();
        if ($user === null) {
            return;
        }

        // daily at 00:00
        $days = \XF::app()->options()->xfrmc_reminderRenewDays;
        $days = explode(',', $days);
        $days = array_map('intval', $days);

        sort($days, SORT_NUMERIC);

        foreach (array_reverse($days) as $day) {
            self::reminderLicenseExpiresBefore($user, $day);
        }
    }

    protected static function reminderLicenseExpiresBefore(User $conversationStarter, int $day): void
    {
        $dt = new \DateTime();
        $dt->setTimezone(new \DateTimeZone('UTC'));
        $dt->modify('+' . $day . ' days');

        $finder = \XF::finder('Truonglv\XFRMCustomized:Purchase');
        $finder->with('User', true);
        $finder->with('Resource');
        $finder->where('expire_date', 'BETWEEN', [
            (clone $dt)->setTime(0, 0, 0)->format('U'),
            (clone $dt)->setTime(23, 59, 59)->format('U')
        ]);
        $finder->order('expire_date');

        $app = \XF::app();

        /** @var Purchase $purchase */
        foreach ($finder->fetch() as $purchase) {
            /** @var User $purchaser */
            $purchaser = $purchase->User;
            /** @var ResourceItem|null $resource */
            $resource = $purchase->Resource;
            if ($resource === null) {
                continue;
            }
            $canView = (bool) \XF::asVisitor($purchaser, function () use ($resource) {
                return $resource->canView();
            });
            if (!$canView) {
                continue;
            }
            /** @var Creator $conversationCreator */
            $conversationCreator = \XF::asVisitor($purchaser, function () use ($conversationStarter) {
                return \XF::service('XF:Conversation\Creator', $conversationStarter);
            });

            $conversationCreator->setRecipientsTrusted($purchaser);
            $conversationCreator->setIsAutomated();

            $title = $app->language($purchaser->language_id)
                ->phrase('xfrmc_reminder_license_expires_conversation_title', [
                    'title' => $resource->title,
                    'day' => $day,
                ]);
            $body = $app->language($purchaser->language_id)
                ->phrase('xfrmc_reminder_license_expires_conversation_body', [
                    'day' => $day,
                    'receiver' => $purchaser->username,
                    'resource_url' => $app->router('public')->buildLink('canonical:resources', $resource),
                    'renew_url' => $app->router('public')->buildLink('canonical:resources/renew', $resource)
                ]);

            $conversationCreator->setContent($title->render(), $body->render());

            if ($conversationCreator->validate($errors)) {
                continue;
            }

            $conversationCreator->save();
        }
    }
}
