<?php

namespace Truonglv\XFRMCustomized;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use Truonglv\XFRMCustomized\DevHelper\SetupTrait;

class Setup extends AbstractSetup
{
    use SetupTrait;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        /** @var \XF\Entity\Purchasable $purchasable */
        $purchasable = $this->app->em()->create('XF:Purchasable');
        $purchasable->purchasable_type_id = App::PURCHASABLE_ID;
        $purchasable->purchasable_class = 'Truonglv\XFRMCustomized:Resource';
        $purchasable->addon_id = 'Truonglv/XFRMCustomized';
        /** @noinspection PhpUnhandledExceptionInspection */
        $purchasable->save();
    }

    public function installStep2()
    {
        $this->doCreateTables($this->getTables());
        $this->doAlterTables($this->getAlters());
    }

    public function uninstallStep1()
    {
        $this->db()->delete(
            'xf_purchasable',
            'purchasable_type_id = ?',
            App::PURCHASABLE_ID
        );

        $this->doDropColumns($this->getAlters1());
    }

    public function upgrade3000300Step1()
    {
        $this->doAlterTables($this->getAlters3());
    }

    public function upgrade3000900Step1()
    {
        $this->doAlterTables($this->getAlters4());
    }

    public function upgrade3010000Step1()
    {
        // rename tables
        $sm = $this->schemaManager();
        $sm->renameTable('tl_xfrm_resource_purchase', 'xf_xfrmc_resource_purchase');
        $sm->renameTable('tl_xfrm_coupon', 'xf_xfrmc_coupon');
        $sm->renameTable('tl_xfrm_coupon_user', 'xf_xfrmc_coupon_user');

        $this->doAlterTables($this->getAlters5());
    }

    public function upgrade3020100Step1(): void
    {
        $this->doCreateTables($this->getTables3());
    }

    /**
     * @return array
     */
    protected function getAlters1()
    {
        $alters = [];

        $alters['xf_rm_resource'] = [
            'payment_profile_ids' => function (Alter $table) {
                $table->addColumn('payment_profile_ids', 'VARCHAR', 255)->setDefault('');
            },
            'renew_price' => function (Alter $table) {
                $table->addColumn('renew_price', 'DECIMAL', '10,2')->setDefault(0);
            }
        ];

        return $alters;
    }

    /**
     * @return array
     */
    protected function getAlters2()
    {
        $alters = [];

        $alters['xf_xfrmc_resource_purchase'] = [
            'purchase_request_key' => function (Alter $table) {
                $table->addColumn('purchase_request_key', 'VARBINARY', 32)->setDefault('');
            },
            'purchase_request_keys' => function (Alter $table) {
                $table->addColumn('purchase_request_keys', 'MEDIUMBLOB')->nullable();
            }
        ];

        return $alters;
    }

    protected function getAlters3(): array
    {
        return [
            'xf_xfrmc_resource_purchase' => [
                'new_purchase_id' => function (Alter $table) {
                    $table->addColumn('new_purchase_id', 'int')->setDefault(0);
                }
            ]
        ];
    }

    protected function getAlters4(): array
    {
        return [
            'xf_xfrmc_coupon' => [
                'criteria' => function (Alter $table) {
                    $table->renameColumn('apply_rules', 'criteria');
                }
            ]
        ];
    }

    protected function getAlters5(): array
    {
        return [
            'xf_xfrmc_coupon' => [
                'max_use_count' => function (Alter $table) {
                    $table->dropColumns('max_use_count');
                },
            ],
        ];
    }

    protected function getTables1(): array
    {
        $tables = [];

        $tables['xf_xfrmc_resource_purchase'] = function (Create $table) {
            $table->addColumn('purchase_id', 'int')->unsigned()->autoIncrement();
            $table->addColumn('resource_id', 'int')->unsigned();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('username', 'varchar', 50);
            $table->addColumn('resource_version_id', 'int')->unsigned();
            $table->addColumn('amount', 'decimal', '10,2')->unsigned()->setDefault(0);
            $table->addColumn('expire_date', 'int')->unsigned()->setDefault(0);
            $table->addColumn('purchased_date', 'int')->unsigned();
            $table->addColumn('note', 'varchar', 255)->setDefault('');

            $table->addKey(['resource_id', 'user_id']);

            $table->addKey('resource_version_id');
        };

        return $tables;
    }

    protected function getTables2(): array
    {
        $tables = [];

        $tables['xf_xfrmc_coupon'] = function (Create $create) {
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
        };
        $tables['xf_xfrmc_coupon_user'] = function (Create $create) {
            $create->addColumn('coupon_user_id', 'int')->unsigned()->autoIncrement();
            $create->addColumn('user_id', 'int')->unsigned();
            $create->addColumn('username', 'varchar', 50);
            $create->addColumn('resource_id', 'int')->unsigned();
            $create->addColumn('coupon_id', 'int')->unsigned();
            $create->addColumn('purchase_id', 'int')->unsigned()->setDefault(0);
            $create->addColumn('created_date', 'int')->unsigned();

            $create->addUniqueKey(['user_id', 'resource_id', 'coupon_id']);
        };

        return $tables;
    }

    protected function getTables3(): array
    {
        return [
            'xf_xfrmc_license' => function (Create $table) {
                $table->addColumn('license_id', 'int')->autoIncrement();
                $table->addColumn('resource_id', 'int');
                $table->addColumn('user_id', 'int');
                $table->addColumn('license_url', 'varchar', 100);
                $table->addColumn('added_date', 'int');
                $table->addColumn('deleted_date', 'int')->setDefault(0);

                $table->addKey(['resource_id', 'user_id']);
            },
        ];
    }
}
