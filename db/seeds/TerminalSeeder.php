<?php


use Phinx\Seed\AbstractSeed;

class TerminalSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        if (getenv('APP_ENV') === 'production') {
            return;
        }
        $data = [
            [
                'vendors_id'    => 1,
                'serial'    => 'APN7002',
                'description'    => 'kitchen',
                'enterdate' => date('Y-m-d H:i:s'),
                'lastmod' => date('Y-m-d H:i:s'),
            ]
        ];

        $posts = $this->table('terminals');
        $posts->insert($data)
            ->saveData();
    }
}
