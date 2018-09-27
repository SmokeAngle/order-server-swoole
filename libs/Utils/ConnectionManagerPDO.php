<?php
namespace OrderServer\Libs\Utils;

class ConnectionManagerPDO {
    
    private $dsn;
    private $username;
    private $passwd;
    private $options;
    /**
     * @var \PDO
     */
    private $db;
    private $shouldReconnect;

    const RETRY_ATTEMPTS = 3;

    public function __construct($dsn, $username, $passwd, $options = array())
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->passwd = $passwd;
        $this->options = $options;
        $this->shouldReconnect = true;
        try {
            $this->connect();
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     * @throws Exception
     * @throws PDOException
     */
    public function __call($method, $args)
    {
        $has_gone_away = false;
        $retry_attempt = 0;
        try_again:
        try {
            if (is_callable(array($this->db, $method))) {
                $result = call_user_func_array(array($this->db, $method), $args);
                if ($result instanceof PDOStatement) {
                    $result = new ReconnectingPDOStatement($result);
                }
                return $result;
            } else {
                trigger_error("Call to undefined method '{$method}'");
            }
        } catch (\PDOException $e) {

            $exception_message = $e->getMessage();
            if (
                ($this->shouldReconnect)
                && strpos($exception_message, 'server has gone away') !== false
                && $retry_attempt <= self::RETRY_ATTEMPTS
            ) {
                $has_gone_away = true;
            } else {
                throw $e;
            }
        }

        if ($has_gone_away) {
            $retry_attempt++;
            $this->reconnect();
            goto try_again;
        }
    }


    /**
     * Connects to DB
     */
    private function connect()
    {
        
        $this->db = new \PDO($this->dsn, $this->username, $this->passwd, $this->options);
        $this->db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
    }

    /**
     * Reconnects to DB
     */
    private function reconnect()
    {
        $this->db = null;
        $this->connect();
    }
}

class ReconnectingPDOStatement
{
    private $_stmt = null;
 
    public function __construct($stmt) {
        $this->_stmt = $stmt;
    }
 
    public function __call($name, array $arguments) {
        $result = false;
        try {
            $result = call_user_func_array(array($this->_stmt, $name), $arguments);
        } catch (PDOException $e) {
            throw $e;
        }
        return $result;
    }
}