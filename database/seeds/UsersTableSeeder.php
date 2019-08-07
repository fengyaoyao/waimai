<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		DB::table('users')->insert([
		    'username' => 'admin',
		    'email'    => '477995344.@qq.com',
		    'password' => password_hash('123123',PASSWORD_DEFAULT),
		]);
    }
}
