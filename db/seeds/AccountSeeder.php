<?php


use Phinx\Seed\AbstractSeed;

class AccountSeeder extends AbstractSeed
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
                'accounts_id'    => 1,
                'firstname' => 'tu',
                'lastname'  => 'le',
                'email' => 'tu.le@teamsable.com',
                'password' => md5('123456'),
                'role' => 'vendor',
                'companyname' => 'teamsable',
                'address' => '123 abc',
                'landline' => '123456',
                'mobile' => '123456',
                'title' => 'ceo',
                'enterdate' => date('Y-m-d H:i:s'),
                'lastmod' => date('Y-m-d H:i:s'),
            ]
        ];

        $posts = $this->table('accounts');
        $posts->insert($data)
            ->saveData();
    }
}
