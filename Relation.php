<?php
/**
 * THIS CLASS IS DEPRECATED !
 * Do not use in new projects.
 */
namespace shozu;
//trigger_error('"Relation" is deprecated: use ActiveBean and RedBean.', E_USER_DEPRECATED);
use \shozu\Record as Record;
use \shozu\Persistent as Persistent;
/**
 * Holds relations between Persistent instances.
 */
class Relation extends Record
{
    protected function setTableDefinition()
    {
        $this->addColumn(array(
            'name'    => 'relation_parties',
            'type'    => 'string',
            'length'  => 128,
            'primary' => true
        ));
        $this->addColumn(array(
            'name'    => 'uid_a',
            'type'    => 'string',
            'length'  => 22,
            'primary' => true
        ));
        $this->addColumn(array(
            'name'    => 'uid_b',
            'type'    => 'string',
            'length'  => 22,
            'primary' => true
        ));
        $this->addColumn(array(
            'name' => 'created_at',
            'type' => 'datetime'
        ));
    }

    /**
     * Links 2 objects
     *
     * <code>
     * Relation::link($instanceA, $instanceB);
     * </code>
     *
     * @param Persistent object A
     * @param Persistent object B
     */
    public static function link(Persistent $a, Persistent $b)
    {
        if($a->uid == $b->uid)
        {
            return false;
        }
        $objects = self::sort($a, $b);
        $relation = new Relation(array(
            'relation_parties' => self::getClass($objects[0]) . '-' . self::getClass($objects[1]),
            'uid_a'            => $objects[0]->uid,
            'uid_b'            => $objects[1]->uid,
            'created_at'       => date('Y-m-d H:i:s',time())
        ));
        try
        {
            $relation->replace();
            return true;
        }
        catch(\PDOException $e)
        {
            if($e->getCode() == self::SQL_UNKNOWN_TABLE)
            {
                $relation->createTable();
                $relation->replace();
                return true;
            }
            else
            {
                throw $e;
            }
        }
    }

    public static function getClass($o)
    {
        $class = get_class($o);
        if(substr($class,0,1) != '\\')
        {
            $class = '\\' . $class;
        }
        return $class;
    }

    /**
     * Unlinks 2 objects
     *
     * <code>
     * Relation::unlink($instanceA, $instanceB);
     * </code>
     *
     * @param Persistent object A
     * @param Persistent object B
     */
    public static function unlink(Persistent $a, Persistent $b)
    {
        $objects = self::sort($a, $b);
        $relationType = self::getClass($objects[0]) . '-' . self::getClass($objects[1]);
        $db = self::getDB();
        try
        {
            $nbrows = $db->exec('delete from ' . self::getTableName() . ' where relation_parties=' . $db->quote($relationType)
                                        . ' and uid_a=' . $db->quote($objects[0]->uid)
                                        . ' and uid_b=' . $db->quote($objects[1]->uid));
            /*
            $nbrows = $db->exec('delete from ' . self::getTableName() . ' where uid_a=' . $db->quote($objects[0]->uid)
                                        . ' and uid_b=' . $db->quote($objects[1]->uid));
            */
            return $nbrows;
        }
        catch(\PDOException $e)
        {
            if($e->getCode() != self::SQL_UNKNOWN_TABLE)
            {
                throw $e;
            }
        }
    }

    /**
     * Remove all relations involving given object or uid
     *
     * <code>
     * // remove relations involving object
     * Relation::remove($persistentInstance);
     * // remove by uid
     * Relation::remove('123456');
     * </code>
     *
     * @param mixed
     */
    public static function remove($a)
    {
        if($a instanceof Persistent)
        {
            $uid = $a->uid;
        }
        else
        {
            $uid = (string)$a;
        }
        $db = self::getDB();
        try
        {
            $nbrows = $db->exec('delete from ' . self::getTableName() . ' where uid_a=' . $db->quote($uid) . ' or uid_b=' . $db->quote($uid));
        }
        catch(\PDOException $e)
        {
            if($e->getCode() != self::SQL_UNKNOWN_TABLE)
            {
                throw $e;
            }
        }
        return $nbrows;
    }

    /**
     * Sort 2 instances
     */
    private static function sort($a, $b)
    {
        $classes = array(self::getClass($a), self::getClass($b));
        if($classes[0] == $classes[1])
        {
            $objects = array($a->uid => $a, $b->uid => $b);
            ksort($objects);
            return array_values($objects);
        }
        sort($classes);
        if(self::getClass($a) == $classes[0])
        {
            return array($a, $b);
        }
        return array($b, $a);
    }
}
