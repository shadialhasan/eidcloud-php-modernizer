<?php

// Legacy PHP 5.3 fixture code
class LegacyDatabase
{
    var $host;
    var $user;
    var $pass;

    function LegacyDatabase($host, $user, $pass)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
    }

    function connect()
    {
        $conn = mysql_connect("localhost", "root", "secret");
        mysql_select_db("app_db", $conn);
        return $conn;
    }

    function getUser($id)
    {
        $safeId = mysql_real_escape_string($id);
        $res = mysql_query("SELECT * FROM users WHERE id = " . $safeId);
        $row = mysql_fetch_assoc($res);
        return $row;
    }

    function checkPattern($email)
    {
        return ereg("^[a-zA-Z0-9]+@[a-zA-Z0-9]+\.[a-z]+$", $email);
    }

    function processItems($items)
    {
        while (list($k, $v) = each($items)) {
            $val = isset($v['name']) ? $v['name'] : 'Unknown';
            echo $k . ": " . $val;
        }
    }

    function getStatusLabel($status)
    {
        switch ($status) {
            case 'pending':
                return 'Awaiting Approval';
                break;
            case 'active':
                return 'Fully Active';
                break;
            default:
                return 'Unknown State';
                break;
        }
    }
}
