<?php
/**
 * THIS CLASS IS DEPRECATED !
 * Do not use in new projects.
 */
namespace shozu;
//trigger_error('"Persistent" is deprecated: use ActiveBean and RedBean.', E_USER_DEPRECATED);
use \shozu\Record as Record;
use \shozu\Relation as Relation;
/**
 * Further extend Record to handle relations, uids, etc.
 *
 * @package MVC
 */
abstract class Persistent extends Record
{
    private $isNew = true;
    private $linkop = array();
    /**
     * Persistent object
     *
     * @param array array as key=>value pairs
     */
    public function __construct(array $values = null)
    {
        // uid is returned by the PHP function uniqid(null, true)
        $this->addColumn(array(
            'name'    => 'uid',
            'type'    => 'string',
            'length'  => 22,
            'primary' => true
        ));
        $this->addColumn(array(
            'name'    => 'modified_at',
            'type'    => 'datetime'
        ));
        $this->addColumn(array(
            'name'    => 'created_at',
            'type'    => 'datetime'
        ));
        parent::__construct($values);
        if(is_null($this->uid))
        {
            $this->uid = self::uidgen();
        }
        else
        {
            // if uid is available, we *assume* object is not new and is clean
            $this->isNew = false;
            $this->isDirty = false;
        }
    }

    /**
     * Save object to database
     *
     * @param boolean $force force save even if instance hasn't changed
     */
    public function save($force = false)
    {
        if(method_exists($this, 'preSave'))
        {
            $this->preSave();
        }
        // stamp object
        $now = time();
        if(is_null($this->created_at))
        {
            $this->created_at = $now;
        }
        $this->modified_at = $now;

        try
        {
            if($this->_validates())
            {
                $this->_save($force);
            }
            else
            {
                throw new \Exception('Record validation error. ' . $this->lastError);
            }
        }
        catch(\PDOException $e)
        {
            // table not found, try to create it
            if($e->getCode() == self::SQL_UNKNOWN_TABLE)
            {
                $this->createTable();
                $this->_save($force);
            }
            else
            {
                throw $e;
            }
        }
        if(method_exists($this, 'postSave'))
        {
            $this->postSave();
        }
    }

    private function _save($force = false)
    {
        try
        {
            self::getDB()->beginTransaction();
            $oldIsDirty = $this->isDirty;
            $oldIsNew = $this->isNew;
            if($force)
            {
                $this->isDirty = true;
            }
            if($this->isDirty)
            {
                if($this->isNew)
                {
                    $this->insert();
                    $this->isNew = false;
                }
                else
                {
                    $this->update();
                }
                $this->isDirty = false;
            }
            foreach($this->linkop as $op)
            {
                if($op[0] == 'link')
                {
                    Relation::link($this, $op[1]);
                }

                if($op[0] == 'unlink')
                {
                    Relation::unlink($this, $op[1]);
                }
            }
            self::getDB()->commit();
        }
        catch(\Exception $e)
        {
            self::getDB()->rollBack();
            $this->isDirty = $oldIsDirty;
            $this->isNew = $oldIsNew;
            throw $e;
        }
    }

    /**
     * Link list of objects. Objects must inherit from Persistent.
     *
     * <code>
     * $book->link($author1, $author2, $editor, $tag1, $tag2);
     * </code>
     *
     * @param Persistent
     */
    public function link()
    {
        foreach(func_get_args() as $object)
        {
            $this->linkop[] = array('link', $object);
        }
    }

    /**
     * Unlink objects. Objects must inherit from Persistent.
     *
     * <code>
     * // unlink DOES NOT delete objects from their own table.
     * $book->unlink($author1, $author2, $editor, $tag1, $tag2);
     * // unlink all instances of class
     * $book->unlink('Tag');
     * </code>
     *
     * @param Persistent
     */
    public function unlink()
    {
        if(func_num_args() === 1 && is_string(func_get_arg(0)))
        {
            $class = func_get_arg(0);
            $instances = $this->getRelated($class);
            foreach($instances as $instance)
            {
                $this->linkop[] = array('unlink', $instance);
            }
        }
        else
        {
            foreach(func_get_args() as $object)
            {
                $this->linkop[] = array('unlink', $object);
            }
        }
    }

    /**
     * Get related object by class.
     *
     * <code>
     * // fetch book authors whose name is "london"
     * $authors = $book->getRelated('Author',
     *                              'Author.name like :name order by Author.year',
     *                               array(':name' => 'london'));
     * </code>
     *
     * @param string $class Class name.
     * @param string $conditions additionnal filters
     * @param array $replace strings to quote
     */
    public function getRelated($class, $conditions = '', array $replace = array())
    {
        if(substr($class,0,1) != '\\')
        {
            $class = '\\' . $class;
        }
        $db = self::getDB();
        if(!empty($conditions))
        {
            if(!is_null($replace))
            {
                foreach($replace as $key => $val)
                {
                    $replace[$key] = $db->quote($val);
                }
            }
            $conditions = str_replace($class . '.', 'a.', $conditions);
            $conditions = ' and ' . str_replace(array_keys($replace), array_values($replace), $conditions);
        }
        $classes = array(Relation::getClass($this), $class);
        sort($classes);
        $relationType = $classes[0] . '-' . $classes[1];

        if($classes[0] == Relation::getClass($this))
        {
            $uid_local = 'uid_a';
            $uid_foreign = 'uid_b';
        }
        else
        {
            $uid_local = 'uid_b';
            $uid_foreign = 'uid_a';
        }
        $sql = 'a.* FROM ' . Relation::getTableName() . ' r inner join ' . \shozu\Inflector::model2dbName($class) . ' a on a.uid = r.:UID_FOREIGN: where :UID_LOCAL:=' . $db->quote($this->uid) . ' and r.relation_parties='. $db->quote($relationType) . $conditions;
        try
        {
            $rows = $db->fetchAll(str_replace(array(':UID_FOREIGN:',':UID_LOCAL:'),
                                              array($uid_foreign, $uid_local),
                                              $sql));
        }
        catch(\PDOException $e)
        {
            if($e->getCode() == self::SQL_UNKNOWN_TABLE)
            {
                return array();
            }
        }
        // additionnal query for Xyz-Xyz relations
        // TODO: find a better way ! This will not give expected results if used
        // with an order by clause
        if($classes[0] == $classes[1])
        {
            // swap uids
            list($uid_local, $uid_foreign) = array($uid_foreign, $uid_local);
            // re-run query, merge results
            $rows = array_merge($rows,$db->fetchAll(str_replace(array(':UID_FOREIGN:',':UID_LOCAL:'),
                                                                array($uid_foreign, $uid_local),
                                                                $sql)));
        }

        if(count($rows) > 0)
        {
            return Record::hydrate($class, $rows);
        }
        return array();
    }

    /**
     * Delete instance from database, removing all relations.
     */
    public function delete()
    {
        $db = self::getDB();
        try
        {
            $db->beginTransaction();
            Relation::remove($this);
            $db->exec('delete from ' . \shozu\Inflector::model2dbName(Relation::getClass($this)) . ' where uid=' . $db->quote($this->uid));
            $db->commit();
        }
        catch(\Exception $e)
        {
            $db->rollBack();
            throw $e;
        }
    }

    public function isNew()
    {
        return $this->isNew;
    }

    /**
     * Override Record hydrate
     *
     * <code>
     * $myClassInstances = myClass::hydrate('firstname=? and lastname=?',array('john', 'doe'));
     * </code>
     *
     * @param string sql query conditions
     * @param array to be replaced
     * @return array
     */
    public static function hydrate($query = '', array $replace = null, $return_hydrator = false)
    {
        $class = get_called_class();
        $query = '* from ' . self::getTableName($class) . (!empty($query) ? ' where ' . $query : '');
        if($return_hydrator)
        {
            if(is_null($replace))
            {
                $replace = array();
            }
            return new \shozu\ActiveBean\Hydrator(self::getDB()->getAdapter(), $class, 'select '.$query, $replace);
        }
        try
        {
            return parent::hydrate($class, self::getDB()->fetchAll($query, $replace));
        }
        catch(\PDOException $e)
        {
            // table not found
            if($e->getCode() == self::SQL_UNKNOWN_TABLE)
            {
                // do nothing, will be created with first save
                return array();
            }
            else
            {
                throw $e;
            }
        }
    }

    /**
     * Hydrate only one object
     *
     * <code>
     * $mySingleClassInstance = myClass::hydrateOne('firstname=? and lastname=?',array('john', 'doe'));
     * </code>
     *
     * @param string sql query conditions
     * @param array to be replaced
     * @return Persistent
     */
    public static function hydrateOne($query, array $replace = null)
    {
        $class = get_called_class();
        $query = '* from ' . self::getTableName($class) . ' where ' . $query . ' limit 1';
        try
        {
            $objects = parent::hydrate($class, self::getDB()->fetchAll($query, $replace));
            if(count($objects) > 0)
            {
                return $objects[0];
            }
            return false;
        }
        catch(\PDOException $e)
        {
            // table not found
            if($e->getCode() == self::SQL_UNKNOWN_TABLE)
            {
                // do nothing, will be created with first save
                return false;
            }
            else
            {
                throw $e;
            }
        }
    }

    /**
     * Hydrate instances from given uids
     *
     * @param array $uids
     * @return array
     */
    public static function hydrateFromUids(array $uids)
    {
        $db = self::getDB();
        $class = get_called_class();
        $temp_uids = array();
        foreach($uids as $key => $uid)
        {
            $uid = trim($uid);
            if(empty($uid))
            {
                continue;
            }
            $temp_uids[$key] = $db->quote($uid);
        }
        if(count($temp_uids) === 0)
        {
            return array();
        }
        unset($uids);
        $query = '* FROM ' . self::getTableName($class) . ' WHERE uid IN (' . implode(',', $temp_uids) . ') ORDER BY FIELD(uid,'. implode(',', $temp_uids) .')';
        try
        {
            return parent::hydrate($class, $db->fetchAll($query));
        }
        catch(\PDOException $e)
        {
            // table not found
            if($e->getCode() == self::SQL_UNKNOWN_TABLE)
            {
                // do nothing, will be created with first save
                return array();
            }
            else
            {
                throw $e;
            }
        }
    }

    /**
     * Hydrate one instance from uid
     *
     * @param string $uid
     * @return mixed
     */
    public static function hydrateOneFromUid($uid)
    {
        $res = self::hydrateFromUids(array($uid));
        if(count($res) == 0)
        {
            return false;
        }
        return $res[0];
    }

    /**
     * hydrateOneFromUid alias
     *
     * @param string $uid
     * @return mixed
     */
    public static function findOneByUid($uid)
    {
        return self::hydrateOneFromUid($uid);
    }

    /**
     * Count all instances in database
     *
     * @return integer
     */
    public static function countAll()
    {
        return self::count();
    }


    /**
     * Count all instances in database with given where clause
     *
     * @param string $where
     * @return integer
     */
    public static function count($where = '1', array $replace = null)
    {
        try
        {
            $res =  self::getDB()->fetchOne('COUNT(*) as total FROM ' . self::getTableName(get_called_class()) . ' WHERE ' . $where, $replace);
        }
        catch(\PDOException $e)
        {
            // table not found
            if($e->getCode() == self::SQL_UNKNOWN_TABLE)
            {
                // do nothing, will be created with first save
                return 0;
            }
            else
            {
                throw $e;
            }
        }
        if($res)
        {
            return (int)$res;
        }
        return 0;
    }

    public static function uidgen()
    {
        return str_replace('.', '', uniqid(null, true));
    }
}
