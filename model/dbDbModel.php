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

    protected $apc_enabled;

    public function __construct()
    {
        // checks if APC is available
        $this->apc_enabled = false;
        if (Clementine::$config['module_db']['use_apc'] && ini_get('apc.enabled')) {
            $this->apc_enabled = true;
        }
    }

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
            if (!(isset(Clementine::$register['clementine_db']['untag']) && is_array(Clementine::$register['clementine_db']['untag']))) {
                Clementine::$register['clementine_db']['untag'] = array();
            }
            // connexion et selection de la BD
            $dbconf = Clementine::$config['clementine_db'];
            Clementine::$register['clementine_db']['connection'] = mysqli_init();
            $db_port = null;
            if (!empty($dbconf['port'])) {
                $db_port = $dbconf['port'];
            }
            $is_connected = @mysqli_real_connect(Clementine::$register['clementine_db']['connection'], $dbconf['host'], $dbconf['user'], $dbconf['pass'], $dbconf['name'], $db_port);
            if (!$is_connected) {
                if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['display_errors']) {
                    $errmsg = 'La connexion à la base de données à échoué.';
                    $errmore = '';
                    if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['sql']) {
                        $errmore = Clementine::$register['clementine_db']['connection']->connect_error;
                    }
                    Clementine::$register['clementine_debug_helper']->trigger_error(array(
                        $errmsg,
                        $errmore
                    ), E_USER_ERROR, 0);
                }
            } else {
                if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['sql']) {
                    Clementine::$clementine_debug['sql'] = array();
                }
            }
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
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $tags = implode('', Clementine::$register['clementine_db']['tag']);
            $untags = implode('', array_reverse(Clementine::$register['clementine_db']['untag']));
            $nb = array_push(Clementine::$clementine_debug['sql'], array(
                'file' => '<em>' . $backtrace[0]['file'] . ':' . $backtrace[0]['line'] . '</em>',
                'query' => $tags . Clementine::dump(trim($sql) , true) . $untags
            ));
            $deb = microtime(true);
            // log query to error_log, with it's tags if any
            if (__DEBUGABLE__ && Clementine::$config['module_db']['log_queries']) {
                error_log($tags . $sql . $untags);
            }
            $res = mysqli_query(Clementine::$register['clementine_db']['connection'], $sql);
            $fin = microtime(true);
            $duree = $fin - $deb;
            Clementine::$clementine_debug['sql'][$nb - 1]['duree'] = $duree;
            if ($res === false && $nonfatal == false) {
                $err_msg = $this->error();
                if (substr($err_msg, -(strlen('at line 1'))) == 'at line 1') {
                    $err_msg = substr($this->error(), 0, -(strlen(' at line 1')));
                }
                // erreur fatale en affichant le detail de la requete
                $errmore = 'Query : ';
                $errmore.= PHP_EOL . Clementine::dump(preg_replace('/^[\r\n]*|[ 	\r\n]*$/', '', $sql), true);
                if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['display_errors']) {
                    Clementine::$register['clementine_debug_helper']->trigger_error(array(
                        $err_msg,
                        $errmore => 'html'
                    ) , E_USER_ERROR, 1);
                }
            }
            if ($nonfatal) {
                $this->untag();
            }
        } else {
            // log query to error_log, with it's tags if any
            if (__DEBUGABLE__ && Clementine::$config['module_db']['log_queries']) {
                error_log($tags . $sql . $untags);
            }
            $res = mysqli_query(Clementine::$register['clementine_db']['connection'], $sql);
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
    public function tag($opening_tag, $closing_tag = '')
    {
        $tags = array(
            'opening_tag' => $opening_tag,
            'closing_tag' => $closing_tag
        );
        $nocolor = "\e[0m";
        $color = '';
        switch ($opening_tag) {
        case 'info':
        case 'green':
            $color = "\033[32m";
            break;
        case 'warning':
        case 'warn':
        case 'yellow':
        case 'orange':
            $color = "\033[33m";
            break;
        case 'fatal':
        case 'error':
        case 'err':
        case 'red':
            $color = "\033[31m";
            break;
        case 'blue':
            $color = "\033[34m";
            break;
        }
        if ($color) {
            $opening_tag = $color;
            $closing_tag = $nocolor;
        }
        Clementine::$register['clementine_db']['tag'][] = $opening_tag;
        Clementine::$register['clementine_db']['untag'][] = $closing_tag;
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
        array_pop(Clementine::$register['clementine_db']['untag']);
    }

    /**
     * escape_string : wrapper pour mysqli_real_escape_string qui s'assure que la connexion est deja faite
     *
     * @param mixed $str
     * @access public
     * @return void
     */
    public function escape_string($str)
    {
        // connexion si necessaire
        $this->connect();
        return mysqli_real_escape_string(Clementine::$register['clementine_db']['connection'], $str);
    }

    /**
     * error : wrapper pour mysqli_error
     *
     * @param mixed $str
     * @access public
     * @return void
     */
    public function error($link = null)
    {
        return mysqli_error(Clementine::$register['clementine_db']['connection']);
    }

    /**
     * fetch_array : wrapper for mysqli_fetch_array
     *
     * @param mixed $stmt
     * @param mixed $type
     * @access public
     * @return void
     */
    public function fetch_array($stmt, $type = MYSQLI_BOTH)
    {
        return mysqli_fetch_array($stmt, $type);
    }

    /**
     * fetch_assoc : wrapper for mysqli_fetch_assoc
     *
     * @param mixed $stmt
     * @access public
     * @return void
     */
    public function fetch_assoc($stmt)
    {
        return mysqli_fetch_assoc($stmt);
    }


    /**
     * fetch_assoc : wrapper for mysqli_fetch_all
     *
     * @param mixed $stmt
     * @access public
     * @return void
     */
    public function fetch_all($stmt)
    {
        return mysqli_fetch_all($stmt)
    }

    /**
     * affected_rows : wrapper for mysqli_affected_rows
     *
     * @param mixed $stmt
     * @access public
     * @return void
     */
    public function affected_rows($stmt = null)
    {
        if ($stmt) {
            return mysqli_affected_rows(Clementine::$register['clementine_db']['connection'], $stmt);
        } else {
            return mysqli_affected_rows(Clementine::$register['clementine_db']['connection']);
        }
    }

    /**
     * num_rows : wrapper for mysqli_num_rows
     *
     * @param mixed $stmt
     * @access public
     * @return void
     */
    public function num_rows($stmt)
    {
        return mysqli_num_rows($stmt);
    }

    /**
     * insert_id : wrapper for mysqli_insert_id
     *
     * @access public
     * @return void
     */
    public function insert_id()
    {
        return mysqli_insert_id(Clementine::$register['clementine_db']['connection']);
    }

    /**
     * found_rows : renvoie le resultat de SELECT FOUND_ROWS()
     *
     * @access public
     * @return void
     */
    public function found_rows()
    {
        $sql = 'SELECT FOUND_ROWS(); ';
        $res = $this->query($sql);
        if ($res === false) {
            return false;
        } else {
            $row = $this->fetch_assoc($res);
            return $row['FOUND_ROWS()'];
        }
    }

    /**
     * list_fields : wrapper for mysqli_list_fields
     *
     * @param mixed $table
     * @access public
     * @return void
     */
    public function list_fields($table)
    {
        if (!isset(Clementine::$register['clementine_db']['table_fields'][$table])) {
            $database = Clementine::$config['clementine_db']['name'];
            $fromcache = null;
            if ($this->apc_enabled) {
                $result = apc_fetch('clementine_db-list_fields.' . $database . '-' . $table, $fromcache);
            }
            if (!$fromcache) {
                $sql = "SHOW FULL COLUMNS FROM `" . $this->escape_string($table) . "` ";
                $result = array();
                $res = $this->query($sql);
                if ($res === false) {
                    return false;
                } else {
                    for (; $res && $row = $this->fetch_assoc($res); $result[] = $row) {
                    }
                }
                if ($this->apc_enabled) {
                    apc_store('clementine_db-list_fields.' . $database . '-' . $table, $result);
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
            $fromcache = null;
            if ($this->apc_enabled) {
                $result = apc_fetch('clementine_db-foreign_keys.' . $database . '-' . $table, $fromcache);
            }
            if (!$fromcache) {
                // version réécrite : plus rapide que d'aller chercher dans la base information_schema (lent selon versions de mysql)
                $result = array();
                $sql = "
                    SELECT *
                      FROM information_schema.KEY_COLUMN_USAGE
                     WHERE constraint_schema = '" . $database . "' AND table_name = '" . $table . "'
                       AND referenced_table_name IS NOT NULL;
                ";
                $res = $this->query($sql);
                if ($res === false) {
                    return false;
                }
                for (; $row = $this->fetch_assoc($res);) {
                    $fk = array();
                    $fk['foreign_key'] = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'];
                    $fk['references'] = $row['REFERENCED_TABLE_NAME'] . '.' . $row['REFERENCED_COLUMN_NAME'];
                    $fk['constraint_name'] = $row['CONSTRAINT_NAME'];
                    $result[] = $fk;
                }
                if ($this->apc_enabled) {
                    apc_store('clementine_db-foreign_keys.' . $database . '-' . $table, $result);
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
    public function distinct_values($table, $field, $label_field = null)
    {
        $sql = '
            SELECT DISTINCT(`' . $this->escape_string($field) . '`)
        ';
        if ($label_field) {
            $sql.= ', ' . $label_field . ' AS `' . $label_field . '`';
        } else {
            // pour le fetch
            $label_field = $field;
        }
        $sql.= '
                  FROM `' . $this->escape_string($table) . '` 
        ';
        $result = array();
        $res = $this->query($sql);
        if ($res === false) {
            return false;
        } else {
            for (; $res && $row = $this->fetch_assoc($res); $result[$row[$field]] = $row[$label_field]) {
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
