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
    protected $module;
    protected $class;
    protected $apc_enabled;

    public function __construct()
    {
        // checks if APC is available
        $this->apc_enabled = false;
        $finalClass = strtolower(substr(get_class($this) , 0, -strlen("Model")));
        $this->module = 'module_' . $finalClass;
        $this->clementine_db_config = 'clementine_' . $finalClass;
        if (Clementine::$config[$this->module]['use_apc'] && ini_get('apc.enabled')) {
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
        if (!(isset(Clementine::$register[$this->clementine_db_config]) && isset(Clementine::$register[$this->clementine_db_config]['connection']) && Clementine::$register[$this->clementine_db_config]['connection'])) {
            // mise en cache des champs recuperes par list_fields()
            if (!isset(Clementine::$register[$this->clementine_db_config]['table_fields'])) {
                Clementine::$register[$this->clementine_db_config]['table_fields'] = array();
            }
            // mise en cache des champs recuperes par foreign_keys()
            if (!isset(Clementine::$register[$this->clementine_db_config]['foreign_keys'])) {
                Clementine::$register[$this->clementine_db_config]['foreign_keys'] = array();
            }
            // pour le tagging de requetes
            if (!(isset(Clementine::$register[$this->clementine_db_config]['tag']) && is_array(Clementine::$register[$this->clementine_db_config]['tag']))) {
                Clementine::$register[$this->clementine_db_config]['tag'] = array();
            }
            if (!(isset(Clementine::$register[$this->clementine_db_config]['untag']) && is_array(Clementine::$register[$this->clementine_db_config]['untag']))) {
                Clementine::$register[$this->clementine_db_config]['untag'] = array();
            }
            // connexion et selection de la BD
            $dbconf = Clementine::$config[$this->clementine_db_config];
            Clementine::$register[$this->clementine_db_config]['connection'] = mysqli_init();
            $db_port = null;
            if (!empty($dbconf['port'])) {
                $db_port = $dbconf['port'];
            }
            $is_connected = @mysqli_real_connect(Clementine::$register[$this->clementine_db_config]['connection'], $dbconf['host'], $dbconf['user'], $dbconf['pass'], $dbconf['name'], $db_port);
            if (!$is_connected) {
                if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['display_errors']) {
                    $errmsg = 'La connexion à la base de données à échoué.';
                    $errmore = '';
                    if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['sql']) {
                        $errmore = Clementine::$register[$this->clementine_db_config]['connection']->connect_error;
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
            // properly set charset, cf. http://www.php.net/manual/en/mysqlinfo.concepts.charset.php
            $is_charset_set = mysqli_set_charset(Clementine::$register[$this->clementine_db_config]['connection'], __SQL_ENCODING__);
            if (!$is_charset_set) {
                if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['display_errors']) {
                    $errmsg = 'La communication avec la base de données est mal encodée.';
                    $errmore = '';
                    if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['sql']) {
                        $errmore = $this->error();
                    }
                    Clementine::$register['clementine_debug_helper']->trigger_error(array(
                        $errmsg,
                        $errmore
                    ), E_USER_ERROR, 0);
                }
            }
        }
    }

    /**
     * close : wrapper pour mysqli_close
     *
     * @access public
     * @return void
     */
    public function close()
    {
        if ($ret = mysqli_close(Clementine::$register[$this->clementine_db_config]['connection'])) {
            unset(Clementine::$register[$this->clementine_db_config]['connection']);
            return $ret;
        }
        return false;
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
        if (Clementine::$config[$this->module]['sql_no_cache']) {
            $sql = preg_replace('/\bselect\b/i', 'SELECT SQL_NO_CACHE', $sql, 1);
        }
        if (__DEBUGABLE__ && Clementine::$config['clementine_debug']['sql']) {
            $sql = trim($sql);
            if ($nonfatal) {
                if (Clementine::$config[$this->module]['log_queries']) {
                    $this->tag('<span style="background: #F80">nonfatal</span>' . "\033" . Clementine::$config['clementine_shell_colors']['green'], "\033" . Clementine::$config['clementine_shell_colors']['normal']);
                } else {
                    $this->tag('<span style="background: #F80">nonfatal</span>');
                }
            }
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $tags = implode('', Clementine::$register[$this->clementine_db_config]['tag']);
            $untags = implode('', array_reverse(Clementine::$register[$this->clementine_db_config]['untag']));
            $rank = 0;
            if (Clementine::$config['clementine_debug']['generate_tests']) {
                $rank = 1;
            }
            $deb = microtime(true);
            $res = mysqli_query(Clementine::$register[$this->clementine_db_config]['connection'], $sql);
            $fin = microtime(true);
            $duree = $fin - $deb;
            // log query to error_log, with it's tags if any
            if (__DEBUGABLE__ && Clementine::$config[$this->module]['log_queries']) {
                $log_query_msg = 'timestamp=' . Clementine::$register['request']->SERVER['REQUEST_TIME'] . ':duree=' . round($duree * 1000, 2) . 'ms: ' . $tags . $sql . $untags;
                Clementine::log($log_query_msg);
            }
            $backtrace = array_slice($backtrace, 0, -3, true);
            $backtrace_query = '';
            foreach ($backtrace as $trace) {
                $trace_msg = '';
                if (!empty($trace['file'])) {
                    $trace_msg .= str_replace(__FILES_ROOT__ . '/', '', $trace['file']) . ':' . $trace['line'];
                }
                if (!empty($trace['class']) || !empty($trace['type']) || !empty($trace['function'])) {
                    $trace_msg .= ' <small style="color: gray; text-align: right; ">';
                    if (!empty($trace['class'])) {
                        $trace_msg .= $trace['class'];
                    }
                    if (!empty($trace['type'])) {
                        $trace_msg .= $trace['type'];
                    }
                    if (!empty($trace['function'])) {
                        $trace_msg .= $trace['function'];
                    }
                    $trace_msg .= '</small>';
                }
                $trace_msg .= '<br />';
                if (!empty($trace['class']) && !empty($trace['function']) && $trace['class'] == 'Clementine' && $trace['function'] == '_require') {
                    $trace_msg = '';
                }
                if (empty($trace['class']) && !empty($trace['function']) && $trace['function'] == 'require') {
                    $trace_msg = '';
                }
                $backtrace_query.= $trace_msg;
            }
            Clementine::$clementine_debug['sql'][$sql][] = array(
                //'file' => '<em>' . $backtrace[$rank]['file'] . ':' . $backtrace[$rank]['line'] . '</em>',
                'file' => $backtrace_query,
                'duree' => $duree,
                'query' => $tags . Clementine::dump($sql , true) . $untags,
            );
            if ($res === false && $nonfatal == false) {
                $err_msg = $this->error();
                if (substr($err_msg, -(strlen('at line 1'))) == 'at line 1') {
                    $err_msg = substr($this->error(), 0, -(strlen(' at line 1')));
                }
                // erreur fatale en affichant le detail de la requete
                $errmore = 'Query : ';
                $errmore.= PHP_EOL . Clementine::dump(preg_replace('/^[\r\n]*|[ 	\r\n]*$/', '', $sql), true);
                Clementine::$register['clementine_debug_helper']->trigger_error(array(
                    $err_msg,
                    $errmore => 'html'
                ) , E_USER_ERROR, 1);
            }
            if ($nonfatal) {
                $this->untag();
            }
        } else {
            // log query to error_log, with it's tags if any
            if (__DEBUGABLE__ && Clementine::$config[$this->module]['log_queries']) {
                if ($nonfatal) {
                    $this->tag("\033" . Clementine::$config['clementine_shell_colors']['green'], "\033" . Clementine::$config['clementine_shell_colors']['normal']);
                }
                $tags = implode('', Clementine::$register[$this->clementine_db_config]['tag']);
                $untags = implode('', array_reverse(Clementine::$register[$this->clementine_db_config]['untag']));
                Clementine::log($tags . $sql . $untags);
            }
            $res = mysqli_query(Clementine::$register[$this->clementine_db_config]['connection'], $sql);
            if ($res === false && $nonfatal == false) {
                $err_msg = $this->error();
                if (substr($err_msg, -(strlen('at line 1'))) == 'at line 1') {
                    $err_msg = substr($this->error(), 0, -(strlen(' at line 1')));
                }
                // erreur fatale en affichant le detail de la requete
                $errmore = 'Query : ';
                $errmore.= PHP_EOL . Clementine::dump(preg_replace('/^[\r\n]*|[ 	\r\n]*$/', '', $sql), true);
                Clementine::$register['clementine_debug_helper']->trigger_error(array(
                    $err_msg,
                    $errmore => 'html'
                ) , E_USER_ERROR, 1);
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
        Clementine::$register[$this->clementine_db_config]['tag'][] = $opening_tag;
        Clementine::$register[$this->clementine_db_config]['untag'][] = $closing_tag;
    }

    /**
     * untag : pop last debug tag
     *
     * @access public
     * @return void
     */
    public function untag()
    {
        array_pop(Clementine::$register[$this->clementine_db_config]['tag']);
        array_pop(Clementine::$register[$this->clementine_db_config]['untag']);
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
        return mysqli_real_escape_string(Clementine::$register[$this->clementine_db_config]['connection'], $str);
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
        return mysqli_error(Clementine::$register[$this->clementine_db_config]['connection']);
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
        if ($stmt === false) {
            $this->getHelper('debug')->trigger_error('fetch_assoc() expects parameter 1 to be db_result, boolean given', E_USER_WARNING, 1);
            return false;
        }
        return mysqli_fetch_assoc($stmt);
    }

    /**
     * fetch_all : wrapper for mysqli_fetch_all
     *
     * @param mixed $stmt
     * @access public
     * @return void
     */
    public function fetch_all($stmt, $type = MYSQLI_NUM)
    {
        if (function_exists('mysqli_fetch_all')) {
            $res = mysqli_fetch_all($stmt, $type);
        } else {
            for ($res = array(); $tmp = $this->fetch_array($stmt, $type);) {
                $res[] = $tmp;
            }
        }
        return $res;
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
            return mysqli_affected_rows(Clementine::$register[$this->clementine_db_config]['connection'], $stmt);
        } else {
            return mysqli_affected_rows(Clementine::$register[$this->clementine_db_config]['connection']);
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
        return mysqli_insert_id(Clementine::$register[$this->clementine_db_config]['connection']);
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
        if (!isset(Clementine::$register[$this->clementine_db_config]['table_fields'][$table])) {
            $database = Clementine::$config[$this->clementine_db_config]['name'];
            $fromcache = null;
            if ($this->apc_enabled) {
                $result = apc_fetch($this->clementine_db_config . '-list_fields.' . $database . '-' . $table, $fromcache);
            }
            if (!$fromcache) {
                if (empty($table)) {
                    return false;
                }
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
                    apc_store($this->clementine_db_config . '-list_fields.' . $database . '-' . $table, $result);
                }
            }
            Clementine::$register[$this->clementine_db_config]['table_fields'][$table] = $result;
        }
        return Clementine::$register[$this->clementine_db_config]['table_fields'][$table];
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
            if (isset(Clementine::$config[$this->clementine_db_config]) && isset(Clementine::$config[$this->clementine_db_config]['name'])) {
                $database = Clementine::$config[$this->clementine_db_config]['name'];
            } else {
                return false;
            }
        }
        if (!isset(Clementine::$register[$this->clementine_db_config]['foreign_keys'][$table])) {
            $fromcache = null;
            if ($this->apc_enabled) {
                $result = apc_fetch($this->clementine_db_config . '-foreign_keys.' . $database . '-' . $table, $fromcache);
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
                    apc_store($this->clementine_db_config . '-foreign_keys.' . $database . '-' . $table, $result);
                }
            }
            Clementine::$register[$this->clementine_db_config]['foreign_keys'][$table] = $result;
        }
        return Clementine::$register[$this->clementine_db_config]['foreign_keys'][$table];
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
