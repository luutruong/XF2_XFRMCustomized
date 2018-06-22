<?php

namespace Truonglv\XFRMCustomized;

use XF\Db\Schema\Create;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        /** @var \XF\Entity\Purchasable $purchasable */
        $purchasable = $this->app->em()->create('XF:Purchasable');
        $purchasable->purchasable_type_id = GlobalStatic::PURCHASABLE_ID;
        $purchasable->purchasable_class = 'Truonglv\XFRMCustomized:Resource';
        $purchasable->addon_id = 'Truonglv/XFRMCustomized';
        /** @noinspection PhpUnhandledExceptionInspection */
        $purchasable->save();
    }

    public function installStep2()
    {
        try {
            $this->query('ALTER TABLE `xf_rm_resource` 
                ADD COLUMN `payment_profile_ids` VARBINARY(255) NOT NULL DEFAULT \'\'');
        } catch (\XF\Db\Exception $e) {
        }

        try {
            $this->query('ALTER TABLE `xf_rm_resource` 
                ADD COLUMN `renew_price` DECIMAL(10, 2) NOT NULL DEFAULT \'0\'');
        } catch (\XF\Db\Exception $e) {
        }
    }

    public function installStep3()
    {
        $sm = $this->schemaManager();
        $sm->createTable('tl_xfrm_resource_purchase', function (Create $table) {
            $table->addColumn('resource_id', 'int')->unsigned();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('username', 'varchar', 50);
            $table->addColumn('resource_version_id', 'int')->unsigned();
            $table->addColumn('amount', 'decimal', '10,2')->unsigned()->setDefault(0);
            $table->addColumn('expire_date', 'int')->unsigned()->setDefault(0);
            $table->addColumn('purchased_date', 'int')->unsigned();

            $table->addPrimaryKey(['resource_id', 'user_id']);

            $table->addKey('resource_version_id');
        });
    }

    public function upgrade1000170Step1()
    {
        try {
            $this->query('ALTER TABLE tl_xfrm_resource_purchase ADD COLUMN purchase_request_key VARBINARY(32) NOT NULL');
        } catch (\XF\Db\Exception $e) {
        }
    }

    public function upgrade1000770Step1()
    {
        try {
            $this->query('ALTER TABLE tl_xfrm_resource_purchase ADD COLUMN purchase_request_keys MEDIUMBLOB NOT NULL');
        } catch (\XF\Db\Exception $e) {
        }
    }

    public function upgrade1000970Step1()
    {
        $sm = $this->schemaManager();
        $sm->createTable('tl_xfrm_coupon', function (Create $create) {
            $create->checkExists(true);

            $create->addColumn('coupon_id', 'int')->unsigned()->autoIncrement();
            $create->addColumn('coupon_code', 'varchar', 25);
            $create->addColumn('title', 'varchar', 100);
            $create->addColumn('created_date', 'int')->unsigned()->setDefault(0);
            $create->addColumn('begin_date', 'int')->unsigned()->setDefault(0);
            $create->addColumn('end_date', 'int')->unsigned()->setDefault(0);
            $create->addColumn('max_use_count', 'int')->unsigned()->setDefault(0);
            $create->addColumn('used_count', 'int')->unsigned()->setDefault(0);
            $create->addColumn('apply_rules', 'blob');
            $create->addColumn('discount_unit', 'enum', ['percent', 'fixed'])->setDefault('percent');
            $create->addColumn('discount_amount', 'int')->unsigned()->setDefault(0);
            $create->addColumn('user_id', 'int')->unsigned();
            $create->addColumn('username', 'varchar', 50);

            $create->addKey('user_id');
            $create->addUniqueKey('coupon_code');
            $create->addKey(['begin_date', 'end_date']);
        });

        $sm->createTable('tl_xfrm_coupon_user', function (Create $create) {
            $create->checkExists(true);

            $create->addColumn('coupon_user_id', 'int')->unsigned()->autoIncrement();
            $create->addColumn('user_id', 'int')->unsigned();
            $create->addColumn('username', 'varchar', 50);
            $create->addColumn('resource_id', 'int')->unsigned();
            $create->addColumn('coupon_id', 'int')->unsigned();
            $create->addColumn('created_date', 'int')->unsigned();

            $create->addUniqueKey(['user_id', 'resource_id', 'coupon_id']);
        });

        try {
            $this->query("ALTER TABLE tl_xfrm_resource_purchase DROP PRIMARY KEY");
        } catch (\XF\Db\Exception $e) {}

        try {
            $this->query("ALTER TABLE tl_xfrm_resource_purchase 
                ADD COLUMN purchase_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
        } catch (\XF\Db\Exception $e) {}

        try {
            $this->query("ALTER TABLE tl_xfrm_resource_purchase
                ADD COLUMN note VARCHAR(255) NOT NULL DEFAULT ''");
        } catch (\XF\Db\Exception $e) {}
    }
}
