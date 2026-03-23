<?php
class PaymentsDB {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $this->conn = new mysqli(
            "sql302.infinityfree.com",
            "if0_40974310",
            "1p7F4ABr5utA4",
            "if0_40974310_payments"
        );
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8");
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new PaymentsDB();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
}
?>