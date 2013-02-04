<?php
/**
 * dbDbModel : database abstraction layer
 * 
 * @package 
 * @version $id$
 * @copyright 
 * @author Pierre-Alexis <pa@quai13.com> 
 * @license 
 */
class dbDbModel extends dbDbModel_Parent
{
    /**
     * connect : connects to the database, using the right encoding
     * 
     * @access public
     * @return void
     */
    public function connect()
    {
        // connexion si necessaire
        if (!(isset(Clementine::$register['clementine_db']) && isset(Clementine::$register['clementine_db']['connection']) && Clementine::$register['clementine_db']['connection'])) {
            // mise en cache des champs recuperes par list_fields()
            if (!isset(Clementine::$register['clementine_db']['table_fields'])) {
                Clementine::$register['clementine_db']['table_fields'] = array();
            }
            // mise en cache des champs recuperes par foreign_keys()
            if (!isset(Clementine::$register['clementine_db']['foreign_keys'])) {
                Clementine::$register['clementine_db']['foreign_keys'] = array();
            }
            // pour le tagging de requetes
            if (!(isset(Clementine::$register['clementine_db']['tag']) && is_array(Clementine::$register['clementine_db']['tag']))) {
                Clementine::$register['clementine_db']['tag'] = array();
            }
            // connexion et selection de la BD
            $dbconf = Clementine::$config['clementine_db'];
            Clementine::$register['clementine_db']['connection'] = mysql_connect($dbconf['host'], $dbconf['user'], $dbconf['pass']);
            if (!Clementine::$register['clementine_db']['connection']) {
                echo 'La connexion à la base de données à échoué.';
                if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['sql']) {
                    $backtrace = debug_backtrace();
                    $err_msg = mysql_error();
                    echo "<br />\n" . '<strong>Clementine fatal error</strong>: ' . htmlentities($err_msg, ENT_COMPAT, mb_internal_encoding()) . ' in <strong>' . $backtrace[1]['file'] . '</strong> on line <strong>' . $backtrace[1]['line'] . '</strong>' . "<br />\n" . '<br />';
                }
                die();
            } else {
                if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['sql']) {
                    Clementine::$clementine_debug['sql'] = array();
                }
            }
            $this->query('USE `' . $dbconf['name'] . '`');
            mysql_select_db('`' . $dbconf['name'] . '`');
            $this->query('SET NAMES ' . __SQL_ENCODING__);
            $this->query('SET CHARACTER SET ' . __SQL_ENCODING__);
        }
    }

    /**
     * query : passe les requetes a la BD en initiant la connexion si necessaire, et log pour debug des requetes
     * 
     * @param mixed $sql 
     * @param mixed $nonfatal : do not die even if query is bad
     * @access public
     * @return void
     */
    public function query($sql, $nonfatal = false)
    {
        // connexion si necessaire
        $this->connect();
        if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['sql']) {
            if ($nonfatal) {
                $this->tag('<span style="background: #F80">nonfatal</span>');
            }
            $backtrace = debug_backtrace();
            $nb = array_push(Clementine::$clementine_debug['sql'], array('file'  => '<em>' . $backtrace[0]['file'] . ':' . $backtrace[0]['line'] . '</em>',
                                                                         'query' => implode('', Clementine::$register['clementine_db']['tag']) . htmlentities($sql, ENT_COMPAT, mb_internal_encoding())));
            $deb = microtime(true);
            // log query to error_log, with it's tags if any
            if (__DEBUGABLE__ && Clementine::$config['module_db']['log_queries']) {
                error_log(implode('', Clementine::$register['clementine_db']['tag']) . $sql);
            }
            $res = mysql_query($sql, Clementine::$register['clementine_db']['connection']);
            $fin = microtime(true);
            $duree = $fin - $deb;
            Clementine::$clementine_debug['sql'][$nb - 1]['duree'] = $duree;
            if ($res === false && $nonfatal == false) {
                $err_msg = mysql_error();
                if (substr($err_msg, - (strlen('at line 1'))) == 'at line 1') {
                    $err_msg = substr(mysql_error(), 0, - (strlen(' at line 1')));
                }
                echo "<br />\n" . '<strong>Clementine fatal error</strong>: ' . htmlentities($err_msg, ENT_COMPAT, mb_internal_encoding()) . ' in <strong>' . $backtrace[0]['file'] . '</strong> on line <strong>' . $backtrace[0]['line'] . '</strong>' . "<br />\n" . '<br />';
                echo 'Query : ';
                echo '<pre>';
                echo htmlentities($sql, ENT_COMPAT, mb_internal_encoding());
                echo '</pre>';
                die();
            }
            if ($nonfatal) {
                $this->untag();
            }
        } else {
            // log query to error_log, with it's tags if any
            if (__DEBUGABLE__ && Clementine::$config['module_db']['log_queries']) {
                error_log(implode('', Clementine::$register['clementine_db']['tag']) . $sql);
            }
            $res = mysql_query($sql, Clementine::$register['clementine_db']['connection']);
            if ($res === false && $nonfatal == false) {
                die();
            }
        }
        return $res;
    }

    /**
     * tag : add a debug tag to next queries
     * 
     * @param mixed $tag 
     * @access public
     * @return void
     */
    public function tag($tag)
    {
        Clementine::$register['clementine_db']['tag'][] = $tag;
    }

    /**
     * untag : pop last debug tag
     * 
     * @access public
     * @return void
     */
    public function untag()
    {
        array_pop(Clementine::$register['clementine_db']['tag']);
    }

    /**
     * escape_string : wrapper pour mysql_real_escape_string qui s'assure que la connexion est deja faite
     * 
     * @param mixed $str 
     * @access public
     * @return void
     */
    public function escape_string($str)
    {
        // connexion si necessaire
        $this->connect();
        return mysql_real_escape_string($str);
    }

    /**
     * fetch_array : wrapper for mysql_fetch_array
     * 
     * @param mixed $stmt 
     * @param mixed $type 
     * @access public
     * @return void
     */
    public function fetch_array($stmt, $type = MYSQL_BOTH)
    {
        return mysql_fetch_array($stmt, $type);
    }

    /**
     * fetch_assoc : wrapper for mysql_fetch_assoc
     * 
     * @param mixed $stmt 
     * @access public
     * @return void
     */
    public function fetch_assoc($stmt)
    {
        return mysql_fetch_assoc($stmt);
    }

    /**
     * affected_rows : wrapper for mysql_affected_rows
     * 
     * @param mixed $stmt 
     * @access public
     * @return void
     */
    public function affected_rows($stmt = null)
    {
        if ($stmt) {
            return mysql_affected_rows($stmt);
        } else {
            return mysql_affected_rows();
        }
    }

    /**
     * num_rows : wrapper for mysql_num_rows
     * 
     * @param mixed $stmt 
     * @access public
     * @return void
     */
    public function num_rows($stmt)
    {
        return mysql_num_rows($stmt);
    }

    /**
     * insert_id : wrapper for mysql_insert_id
     * 
     * @access public
     * @return void
     */
    public function insert_id()
    {
        return mysql_insert_id();
    }

    /**
     * list_fields : wrapper for mysql_list_fields
     * 
     * @param mixed $table 
     * @access public
     * @return void
     */
    public function list_fields($table)
    {
        if (!isset(Clementine::$register['clementine_db']['table_fields'][$table])) {
            $sql = "SHOW FULL COLUMNS FROM `" . $this->escape_string($table) . "` ";
            $result = array();
            $res = $this->query($sql);
            if ($res === false) {
                return false;
            } else {
                for (; $res && $row = $this->fetch_assoc($res); $result[] = $row) {
                }
            }
            Clementine::$register['clementine_db']['table_fields'][$table] = $result;
        }
        return Clementine::$register['clementine_db']['table_fields'][$table];
    }

    /**
     * foreign_keys : returns foreign keys for $table
     * 
     * @param mixed $table 
     * @param mixed $database 
     * @access public
     * @return void
     */
    public function foreign_keys($table = null, $database = null)
    {
        if (!$database) {
            if (isset(Clementine::$config['clementine_db']) && isset(Clementine::$config['clementine_db']['name'])) {
                $database = Clementine::$config['clementine_db']['name'];
            } else {
                return false;
            }
        }
        if (!isset(Clementine::$register['clementine_db']['foreign_keys'][$table])) {
            $sql = "SELECT CONCAT(table_name, '.', column_name) AS 'foreign_key',
                           CONCAT(referenced_table_name, '.', referenced_column_name) AS 'references'
                      FROM information_schema.key_column_usage
                     WHERE referenced_table_name IS NOT NULL 
                       AND constraint_schema = '" .  $this->escape_string($database) . "' ";
            if ($table) {
                $sql .= "AND table_name = '" . $this->escape_string($table) . "'";
            }
            $result = array();
            $res = $this->query($sql);
            if ($res === false) {
                return false;
            } else {
                for (; $res && $row = $this->fetch_assoc($res); $result[] = $row) {
                }
            }
            Clementine::$register['clementine_db']['foreign_keys'][$table] = $result;
        }
        return Clementine::$register['clementine_db']['foreign_keys'][$table];
    }

    /**
     * distinct_values : returns an array with the distinct values of a table field
     * 
     * @param mixed $table 
     * @param mixed $field 
     * @access public
     * @return void
     */
    public function distinct_values($table, $field)
    {
        $sql = 'SELECT DISTINCT(`' . $this->escape_string($field) . '`)
                  FROM `' . $this->escape_string($table) . '` ';
        $result = array();
        $res = $this->query($sql);
        if ($res === false) {
            return false;
        } else {
            for (; $res && $row = $this->fetch_assoc($res); $result[$row[$field]] = $row[$field]) {
            }
        }
        return $result;
    }

    /**
     * enum_values : returns an array with the available values of an enum/set field
     * 
     * @param mixed $table 
     * @param mixed $field 
     * @access public
     * @return void
     */
    public function enum_values($table, $field)
    {
        // connexion si necessaire
        $this->connect();
        $sql = "SHOW COLUMNS FROM `" . $this->escape_string($table) . "` 
                  WHERE Field = '" . $this->escape_string($field) . "' ";
        $result = array();
        $res = $this->query($sql);
        if ($res === false) {
            return false;
        } else {
            $row = $this->fetch_assoc($res);
            $type = preg_replace('/[( ].*/', '', $row['Type']);
            if ($type == 'enum' || $type == 'set') {
                $enum_array = array();
                preg_match_all("/'(.*?)'/", $row['Type'], $enum_array);
                $values = array();
                foreach ($enum_array[1] as $val) {
                    $values[$val] = $val;
                }
            } else {
                return false;
            }
        }
        return $values;
    }

}
?>
