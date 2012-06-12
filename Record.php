<?php
/**
 * THIS CLASS IS DEPRECATED !
 * Do not use in new projects.
 */
namespace shozu;
//trigger_error('"Record" is deprecated: use ActiveBean and RedBean.', E_USER_DEPRECATED);
/**
 * Super basic implementation of Active Record pattern
 *
 * @package MVC
 */
abstract class Record implements \Iterator
{
    const SQL_UNKNOWN_TABLE                  = '42S02';
    const SQL_INTEGRITY_CONSTRAINT_VIOLATION = '23000';
    private static $tableName;
    private static $DB;
    private static $engine = 'InnoDB';
    protected $isDirty = true;
    protected $hasAutoId;
    protected $columns;
    protected $lastError;

    /**
     * New record
     *
     * <code>
     * $myRecords = new MyRecord(array('title' => 'test', 'content' => 'test'));
     * $myRecord->title = 'test again';
     * $myRecord->save();
     * </code>
     *
     * @param array key/value pairs
     */
    public function __construct(array $values = null)
    {
        $this->setTableDefinition();
        if(!$this->hasPrimaryKeys())
        {
            $this->setAutoId();
        }

        foreach($this->columns as $k => $v)
        {
            if(isset($v['default']))
            {
                $this->setValue($k, $v['default']);
            }
        }
        if(!is_null($values))
        {
            foreach($values as $key => $value)
            {
                $this->setValue($key, $value);
            }
        }
    }

    public function __call($name, $args)
    {
        $method_prefix = substr($name, 0, 3);

        if(in_array($method_prefix, array('set', 'get')))
        {
            $property = \shozu\Inflector::underscore(substr($name, 3));

            if($this->hasColumn($property))
            {
                if($method_prefix == 'set')
                {
                    return $this->__set($property, $args[0]);
                }
                elseif($method_prefix == 'get')
                {
                    return $this->__get($property);
                }
            }
        }

        throw new \BadMethodCallException(sprintf(
            "Call to undefined method %s::%s()",
            get_class($this),
            $name
        ));
    }

    public function isValid()
    {
        return $this->_validates();
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    private function hasPrimaryKeys()
    {
        $keys = $this->getPrimaryKeys();
        return !empty($keys);
    }

    private function setAutoId()
    {
        $this->addColumn(array(
            'name'    => 'id',
            'type'    => 'integer',
            'primary' => true,
            'autoinc' => true
        ));
        $this->hasAutoId = true;
    }

    /**
     * Add a column to the model definition.
     *
     * <code>
     * class User extends Record
     * {
     *     protected function setTableDefinition()
     *     {
     *         $this->addColumn(array(
     *                            'name'       => 'login',
     *                            'type'       => 'string',
     *                            'length'     => 64,
     *                            'formatters' => array('trim', 'lowercase'),
     *                            'validators' => array('notblank'),
     *                            'notnull'    => true,
     *                            'unique'     => true
     *                           ));
     *     }
     * }
     * </code>
     *
     * @param array $config Configuration array. Keys: name, type, length, formatters, validators, default, unique, references, primary, autoinc, notnull, collate, ondelete
     */
    protected function addColumn(array $config)
    {
        if(empty($config['name']) || empty($config['type']))
        {
            throw new \Exception('missing parameters');
        }
        $this->columns[$config['name']] = array(
            'name'       => $config['name'],
            'type'       => $config['type'],
            'verbose'    => isset($config['verbose'])    ? $config['verbose']              : null,
            'length'     => isset($config['length'])     ? $config['length']               : null,
            'formatters' => isset($config['formatters']) ? (array)$config['formatters']    : array(),
            'validators' => isset($config['validators']) ? (array)$config['validators']    : array(),
            'default'    => isset($config['default'])    ? $config['default']              : null,
            'unique'     => isset($config['unique'])     ? (bool)$config['unique']         : false,
            'references' => isset($config['references']) ? $config['references']           : null,
            'primary'    => isset($config['primary'])    ? (bool)$config['primary']        : false,
            'autoinc'    => isset($config['autoinc'])    ? (bool)$config['autoinc']        : false,
            'notnull'    => isset($config['notnull'])    ? (bool)$config['notnull']        : false,
            'collate'    => isset($config['collate'])    ? $config['collate']              : 'utf8_unicode_ci',
            'ondelete'   => isset($config['ondelete'])   ? $config['ondelete']             : null
        );
    }

    protected function addColumns(array $columns)
    {
        foreach($columns as $column)
        {
            $this->addColumn($column);
        }
    }

    /**
     * Test if the column whose name was passed as argument exists.
     *
     * @param   string  $name   Column name
     * @return  bool            Column existence
     */
    public function hasColumn($name)
    {
        return isset($this->columns[$name]) ? true : false;
    }

    /**
     * Fetch create table SQL statement
     *
     * @param string $dialect (mysql || sqlite)
     * @return string SQL
     */
    public function getTableSql($dialect = null)
    {
        if(empty($dialect))
        {
            $dialect = self::getDB()->getAdapter()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        }
        if($dialect == 'sqlite' || $dialect == 'sqlite2')
        {
            return $this->getSqliteTableSql();
        }
        if($dialect == 'mysql')
        {
            return $this->getMysqlTableSql();
        }
        throw new \Exception('unsupported database dialect');
    }


    public function createTable()
    {
        self::getDB()->exec($this->getTableSql());
    }

    private function getSqliteTableSql()
    {
        $fields = array();
        foreach($this->columns as $fieldName => $description)
        {
            if($description['primary'] && $description['autoinc'])
            {
                $fields[] = $fieldName . ' integer primary key';
            }
            elseif(isset($description['unique']) && $description['unique'] == true)
            {
                $fields[] = $fieldName . ' unique';
            }
            else
            {
                $fields[] = $fieldName;
            }
        }
        return 'create table ' . self::getTableName() . '(' . implode(', ', $fields) . ');';
    }

    public function getPropertiesTags()
    {
        $out = '';
        foreach($this->columns as $fieldName => $description)
        {
            $type = $description['type'];
            if($type == 'date' || $type == 'datetime')
            {
                $type = '\DateTime';
            }
            if($type == 'boolean')
            {
                $type = 'bool';
            }
            if($type == 'integer')
            {
                $type = 'int';
            }
            $out.= '* @property '.$type.' '.$fieldName.PHP_EOL;
            $upper = ucfirst(Inflector::camelize($fieldName));
            $out.= '* @method void set'.$upper.'() set'.$upper.'('.($this->isPrimitive($type) ? '' : $type).' $'.$fieldName.')'.PHP_EOL;
            $out.= '* @method '.$type.' get'.$upper.'() get'.$upper.'()'.PHP_EOL;
        }
        return $out;
    }

    private function isPrimitive($type)
    {
        return in_array($type, array('int','bool','float','string'));
    }

    private function getMysqlTableSql()
    {
        $fields      = array();
        $primaryKeys = array();
        $unique      = array();
        $references  = array();
        foreach($this->columns as $fieldName => $description)
        {
            if(!is_null($description['references']))
            {
                $references[$fieldName] = $description['references'];
            }
            if($description['unique'])
            {
                $unique[] = $fieldName;
            }
            if($description['primary'])
            {
                $primaryKeys[] = $fieldName;
            }
            if($description['primary'] && $description['autoinc'])
            {
                $fields[] = "\n\t" . strtolower($fieldName) . ' bigint(20) NOT NULL auto_increment';
            }
            else
            {
                $fields[] = "\n\t" . strtolower($fieldName) . ' '
                    . $this->getMysqlType($description['type'], $description['length'])
                    . ($description['type'] == 'string' ? ' collate ' . $description['collate'] : '')
                    . ($description['notnull'] ? ' NOT NULL' : '')
                    . $this->getSqlDefaultValue($fieldName);
            }
        }
        $out = 'CREATE TABLE IF NOT EXISTS ' . self::getTableName() . '(' . implode(', ', $fields);
        $alter = '';
        if(!empty($primaryKeys))
        {
            $out .= ',' . "\n\t" . 'PRIMARY KEY (' . implode(',', $primaryKeys) . ')';
        }
        if(!empty($unique))
        {
            foreach($unique as $u)
            {
                $out .= ",\n\t" . 'UNIQUE KEY ' . $u . '_idx (' . $u . ')';
            }
        }
        if(!empty($references))
        {
            foreach($references as $idName => $ref)
            {
                list($refTable, $refField) = explode('.', $ref);
                $alter .= "\nALTER TABLE " . self::getTableName() . " ADD CONSTRAINT " . 'ref_' . md5(self::getTableName() . '_' . $refTable . '_' . $refField . '_'. $idName)
                    . ' FOREIGN KEY (' . $idName . ') REFERENCES ' . $refTable . '(' . $refField . ')'
                    . (isset($this->columns[$idName]['ondelete']) ? ' ON DELETE ' . $this->columns[$idName]['ondelete'] : '') . ';';
            }
        }
        $out .= "\n) ENGINE=" . self::$engine . " DEFAULT CHARSET=utf8 COLLATE=utf8_bin;" . $alter . "\n\n";
        return $out;
    }

    private function getSqlDefaultValue($field)
    {
        $default = $this->columns[$field]['default'];

        if(is_null($default))
        {
            return '';
        }

        $type = $this->columns[$field]['type'];

        if($type == 'bool' || $type == 'boolean')
        {
            return ' default ' . self::getDB()->quote((int)$default);
        }

        if($type == 'array' || $type == 'object')
        {
            return ' default ' . self::getDB()->quote(serialize($default));
        }

        return ' default ' . self::getDB()->quote($default);
    }

    private function getMysqlType($phptype, $length)
    {
        if($phptype == 'string')
        {
            if(is_null($length))
            {
                return 'TEXT';
            }
            if($length > 65535)
            {
                return 'LONGTEXT';
            }
            if($length >= 256 && $length <= 65535)
            {
                return 'TEXT';
            }
            if($length < 256)
            {
                return 'VARCHAR(' . $length . ')';
            }
        }

        if($phptype == 'int' || $phptype == 'integer')
        {
            if(is_null($length))
            {
                return 'BIGINT';
            }
            if($length > 255)
            {
                return 'BIGINT';
            }
            if($length < 256)
            {
                return 'TINYINT';
            }
        }

        if($phptype == 'bool' || $phptype == 'boolean')
        {
            return 'TINYINT(1)';
        }

        if($phptype == 'float')
        {
            return 'FLOAT';
        }

        if($phptype == 'array' || $phptype == 'object')
        {
            return 'LONGBLOB';
        }

        if($phptype == 'datetime' || $phptype == 'time')
        {
            return 'DATETIME';
        }
    }

    public function rewind()
    {
        reset($this->columns);
    }

    public function current()
    {
        $field = current($this->columns);
        if(!isset($field['value']))
        {
            $field['value'] = null;
        }
        return $field['value'];
    }

    public function key()
    {
        $var = key($this->columns);
        return $var;
    }

    public function next()
    {
        $field = next($this->columns);
        if(!isset($field['value']))
        {
            $field['value'] = null;
        }
        return $field['value'];
    }

    public function valid()
    {
        if(!is_null($this->key()))
        {
            return true;
        }
        return false;
    }

    public function __set($key, $value)
    {
        if(isset($this->columns[$key]))
        {
            $this->setValue($key, $value);
        }
    }

    public function __get($key)
    {
        if(isset($this->columns[$key]['value']))
        {
            if($this->columns[$key]['type'] == 'datetime' || $this->columns[$key]['type'] == 'time')
            {
                return new \DateTime($this->columns[$key]['value']);
            }
            if($this->columns[$key]['type'] == 'array')
            {
                return (array)unserialize($this->columns[$key]['value']);
            }
            if($this->columns[$key]['type'] == 'object')
            {
                return unserialize($this->columns[$key]['value']);
            }
            return $this->columns[$key]['value'];
        }
    }

    public function __isset($key)
    {
        return isset($this->columns[$key]['value']);
    }

    public function __toString()
    {
        return print_r($this->valuesArray(), true);
    }

    public function __unset($key)
    {
        unset($this->columns[$key]['value']);
    }

    private function setValue($key, $value)
    {
        if(!isset($this->columns[$key]))
        {
            return;
        }
        if(!empty($this->columns[$key]['formatters']))
        {
            foreach($this->columns[$key]['formatters'] as $formatter)
            {
                if($formatter == 'limiter' && !empty($this->columns[$key]['length']))
                {
                    $value = mb_substr($value, 0, $this->columns[$key]['length'], 'UTF-8');
                }
                else
                {
                    $value = $this->{$formatter.'Formatter'}($value);
                }
            }
        }
        switch($this->columns[$key]['type'])
        {
            case 'int':
            case 'integer':
                $value = (int)$value;
                break;
            case 'bool':
            case 'boolean':
                $value = (bool)$value;
                break;
            case 'array':
                if(!is_string($value))
                {
                    $value = serialize($value);
                }
                break;
            case 'object':
                if(!is_string($value))
                {
                    $value = serialize($value);
                }
                break;
            case 'datetime':
            case 'time':
                if($value instanceof \DateTime)
                {
                    $value = $value->format('Y-m-d H:i:s');
                }
                if(is_integer($value))
                {
                    $value = date('Y-m-d H:i:s', $value);
                }
            default:
                $value = $value;
                break;
        }
        if(!isset($this->columns[$key]['value']) || $this->columns[$key]['value'] !== $value)
        {
            if(!empty($this->columns[$key]['length']) && mb_strlen($value, 'UTF-8') > $this->columns[$key]['length'])
            {
                throw new \Exception('value exceeds length limit for ' . $key);
            }
            $this->columns[$key]['value'] = $value;
            $this->isDirty = true;
        }
    }

    protected function trimFormatter($string)
    {
        return trim($string);
    }

    protected function uppercaseFormatter($string)
    {
        return mb_strtoupper($string, 'UTF-8');
    }

    protected function lowercaseFormatter($string)
    {
        return mb_strtolower($string, 'UTF-8');
    }

    protected function ucfirstFormatter($str, $e = 'utf-8')
    {
        $fc = mb_strtoupper(mb_substr($str, 0, 1, $e), $e);
        return $fc . mb_substr($str, 1, mb_strlen($str, $e), $e);
    }

    protected function nodiacriticsFormatter($utfString)
    {
        return strtr(utf8_decode($utfString),
                     utf8_decode('ÀÁÂÃÄÅàáâãäåÒÓÔÕÖØòóôõöøÈÉÊËèéêëÇçÌÍÎÏìíîïÙÚÛÜùúûüÿÑñ'),
                     'AAAAAAaaaaaaOOOOOOooooooEEEEeeeeCcIIIIiiiiUUUUuuuuyNn');
    }

    protected function emailValidator($string)
    {
        if(function_exists('filter_var'))
        {
            if(filter_var($string, FILTER_VALIDATE_EMAIL))
            {
                return true;
            }
            return false;
        }
        return preg_match("!^[a-z0-9._-]+@[a-z0-9._-]{2,}\.[a-z]{2,4}$!", $string);
    }

    protected function sha1Validator($string)
    {
        return preg_match("!^[a-f0-9]{40}$!", $string);
    }

    protected function _validates()
    {
        foreach($this->columns as $fieldName => $description)
        {
            if(empty($description['validators']))
            {
                continue;
            }
            if(empty($this->columns[$fieldName]['value']) and
                !in_array('notblank', $description['validators']))
            {
                continue;
            }
            if(empty($this->columns[$fieldName]['value']) and
                in_array('notblank', $description['validators']))
            {
                $this->lastError = (!is_null($this->columns[$fieldName]['verbose']) ? $this->columns[$fieldName]['verbose'] : $fieldName) . ': notblank';
                return false;
            }
            foreach($description['validators'] as $validator)
            {
                if($validator === 'notblank')
                {
                    continue;
                }
                if(!$this->{$validator.'Validator'}($this->columns[$fieldName]['value'], $description))
                {
                    $this->lastError = (!is_null($this->columns[$fieldName]['verbose']) ? $this->columns[$fieldName]['verbose'] : $fieldName) . ': ' . $validator;
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Save to database
     */
    public function save()
    {
        if(method_exists($this, 'preSave'))
        {
            $this->preSave();
        }

        if($this->_validates())
        {
            if(empty($this->columns['id']['value']))
            {
                $this->insert();
            }
            else
            {
                $this->update();
            }
        }
        else
        {
            throw new \Exception('Record validation error. ' . $this->lastError);
        }

        if(method_exists($this, 'postSave'))
        {
            $this->postSave();
        }
    }

    /**
     * delete from database
     */
    public function delete()
    {
        //@todo use natural keys if available
        if(!$this->hasAutoId)
        {
            throw new \Exception('cant delete record without auto id');
        }
        self::getDB()->exec('delete from ' . self::getTableName() . 'where id=' . (int)$this->id);
    }

    public function insert()
    {
        $return = self::getDB()->insert(self::getTableName(), $this->valuesArray());
        //@TODO unsafe, find a better way
        if($this->hasAutoId)
        {
            $this->columns['id']['value'] = self::getDB()->lastInsertId();
        }
        return $return;
    }

    public function update()
    {
        $primaryKeys = $this->getPrimaryKeys();
        if(empty($primaryKeys))
        {
            throw new \Exception('cant update without primary keys');
            return false;
        }

        $where = array();
        foreach($primaryKeys as $key => $description)
        {
            $where[] = $key . '=' . self::getDB()->quote($description['value']);
        }

        return self::getDB()->update(self::getTableName(), $this->valuesArray(),
                              implode(' AND ', $where));
    }

    public function replace()
    {
        self::getDB()->replace(self::getTableName(), $this->valuesArray());
        //@TODO unsafe, find a better way
        if($this->hasAutoId)
        {
            $this->columns['id']['value'] = self::getDB()->lastInsertId();
        }
        return true;
    }

    /**
     * Return instance as key => value array
     *
     * @return array
     */
    public function toArray()
    {
        $a = array();
        foreach($this->columns as $key => $description)
        {
            $a[$key] = isset($description['value']) ? $description['value'] : null;
        }
        return $a;
    }

    private function getPrimaryKeys()
    {
        $a = array();
        foreach($this->columns as $key => $description)
        {
            if($description['primary'])
            {
                $a[$key] = $description;
            }
        }
        return $a;
    }

    private function valuesArray()
    {
        $a = array();
        foreach($this->columns as $key => $description)
        {
            // do not fetch primary keys
            if($description['primary'] && $description['autoinc'])
            {
                continue;
            }
            if(isset($description['value']) && $description['value'] instanceof \DateTime)
            {
                $a[$key] = $description['value']->format('Y-m-d H:i:s');
            }
            else
            {
                $a[$key] = isset($description['value']) ? $description['value'] : null;
            }
        }
        return $a;
    }

    public static function getTableName($class = '')
    {
        if(!empty($class))
        {
            return \shozu\Inflector::model2dbName($class);
        }
        if(!empty(self::$tableName))
        {
            return self::$tableName;
        }
        return \shozu\Inflector::model2dbName(get_called_class());
    }

    public static function setTableName($name)
    {
        self::$tableName = $name;
    }

    public static function setDB(\shozu\DB $db)
    {
        self::$DB = $db;
    }

    /**
     * @static
     * @return \shozu\DB
     */
    public static function getDB()
    {
        if(empty(self::$DB))
        {
            self::$DB = \shozu\DB::getInstance('default',
                                        Shozu::getInstance()->db_dsn,
                                        Shozu::getInstance()->db_user,
                                        Shozu::getInstance()->db_pass);
        }
        return self::$DB;
    }

    /**
     * Create objects out of rows.
     *
     * <code>
     * // if User inherits from Record
     * $users = User::hydrate('User', User::getDB()->fetchAll('* from user'));
     * foreach($users as $user)
     * {
     *     echo "\n" . $user->login;
     * }
     * </code>
     *
     * @param string $class Class
     * @param array $rows data rows
     * @return array array of instances
     */
    public static function hydrate($class, array $rows)
    {
        if(substr($class, 0, 1) == '\\')
        {
            $class = substr($class, 1);
        }
        $objects = array();
        foreach($rows as $row)
        {
            $objects[] = new $class($row);
        }
        return $objects;
    }

    public static function setEngine($engine)
    {
        self::$engine = $engine;
        return true;
    }

    public static function className()
    {
        return get_called_class();
    }
    
}
