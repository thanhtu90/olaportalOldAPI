<?php

use Phinx\Seed\AbstractSeed;

class SubscriptionPlanSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            [
                'uuid' => $this->generateUuid(),
                'name' => 'Basic Plan',
                'description' => 'Basic subscription plan with essential features',
                'price' => 29.99,
                'interval' => 'monthly',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'uuid' => $this->generateUuid(),
                'name' => 'Standard Plan',
                'description' => 'Standard subscription plan with additional features',
                'price' => 59.99,
                'interval' => 'monthly',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'uuid' => $this->generateUuid(),
                'name' => 'Premium Plan',
                'description' => 'Premium subscription plan with all features included',
                'price' => 99.99,
                'interval' => 'monthly',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'uuid' => $this->generateUuid(),
                'name' => 'Basic Annual Plan',
                'description' => 'Basic subscription plan billed annually (20% discount)',
                'price' => 287.90,
                'interval' => 'annual',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'uuid' => $this->generateUuid(),
                'name' => 'Standard Annual Plan',
                'description' => 'Standard subscription plan billed annually (20% discount)',
                'price' => 575.90,
                'interval' => 'annual',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'uuid' => $this->generateUuid(),
                'name' => 'Premium Annual Plan',
                'description' => 'Premium subscription plan billed annually (20% discount)',
                'price' => 959.90,
                'interval' => 'annual',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];

        $this->table('subscription_plans')->insert($data)->save();
    }

    /**
     * Generate a UUIDv4
     * 
     * @return string
     */
    private function generateUuid() 
    {
        // Generate 16 random bytes
        $data = random_bytes(16);
        
        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        // Output the 36 character UUID
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
} 