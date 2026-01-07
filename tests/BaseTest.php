<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public $db_host = "127.0.0.1";
    public $db_name = "test_db";
    public $db_user = "app_user";
    public $db_password = "app_user_password";

    protected function setUp(): void {}
}
