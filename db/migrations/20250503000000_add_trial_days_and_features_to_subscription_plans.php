<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class AddTrialDaysAndFeaturesToSubscriptionPlans extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('subscription_plans')) {
            $table = $this->table('subscription_plans');
            if (!$table->hasColumn('trial_days')) {
                $table->addColumn('trial_days', 'integer', [
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'signed' => false,
                    'default' => 0,
                    'null' => false,
                    'after' => 'billing_interval',
                ]);
            }
            if (!$table->hasColumn('features')) {
                $table->addColumn('features', 'json', [
                    'null' => true,
                    'after' => 'trial_days',
                ]);
            }
            $table->update();
        }
    }

    public function down(): void
    {
        if ($this->hasTable('subscription_plans')) {
            $table = $this->table('subscription_plans');
            if ($table->hasColumn('features')) {
                $table->removeColumn('features');
            }
            if ($table->hasColumn('trial_days')) {
                $table->removeColumn('trial_days');
            }
            $table->update();
        }
    }
} 