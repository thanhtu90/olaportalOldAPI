<?php
// used to get mysql database connection
class DatabaseService
{
    private $db_host = "127.0.0.1";
    private $db_name = "app_db";
    private $db_user = "app_user";
    private $db_password = "app_user_password";
    private $connection;
    public function getConnection()
    {
        $this->connection = null;
        try {
            $this->connection = new PDO("mysql:host=" . $this->db_host . ";port=3306;dbname=" . $this->db_name, $this->db_user, $this->db_password);
        } catch (PDOException $exception) {
            echo "Connection failed: " . $exception->getMessage();
        }
        return $this->connection;
    }
}
