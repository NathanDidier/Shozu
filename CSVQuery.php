<?php
namespace shozu;
/**
 * This class loads data from a CSV file and turn it in a SQL database.
 * Once you have edited the data with standard SQL statements, you can dump it
 * to another CSV file:
 *
 * <code>
 * $csv = new CSVQuery;
 * $csv->loadFromFile('yourInput.csv', ';');
 * $csv->getPDO()->exec('delete from csvdata where name not like "doe"');
 * $csv->dumpToFile('yourOutput.csv');
 * </code>
 */
class CSVQuery
{
    private $pdo;
    private $table_name = 'csvdata';
    private $headers;
    
    /**
     *
     * @return \PDO
     */
    public function getPDO()
    {
        if(is_null($this->pdo))
        {
            $this->pdo = new \PDO('sqlite::memory:');
        }
        return $this->pdo;
    }
    
    /**
     *
     * @return CSVQuery
     */
    public function setPDO(\PDO $pdo)
    {
        $this->pdo = $pdo;
        return $this;
    }

    /**
     *
     * @return CSVQuery
     */    
    public function loadFromFile($file_name, $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        $this->getPDO()->exec('DROP TABLE IF EXISTS ' . $this->table_name);
        $this->getPDO()->exec('CREATE TABLE ' . $this->table_name);
        
        $handle = fopen($file_name, 'r');
        $first = true;
        
        // get column names from first row if not explicitely specified
        if(is_null($this->headers))
        {
            $row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);
            if($row)
            {
                $this->headers = $row;
            }
        }
        
        // create table, insert data
        $this->getPDO()->exec('create table ' . $this->table_name . '(' . implode(',', $this->headers) . ')');
        $sql = 'insert into ' . $this->table_name . '(' . implode(',', $this->headers) . ') values(' . implode(',', array_fill(0, count($this->headers), '?')) . ')';
        $statement = $this->getPDO()->prepare($sql);
        while(($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false)
        {
            $statement->execute($row);
        }
        fclose($handle);
        return $this;
    }
    
    /**
     *
     * @return CSVQuery
     */
    public function dumpToFile($file_name, $delimiter = ',', $enclosure = '"')
    {
        $handle = fopen($file_name, 'w');
        $query = $this->getPDO()->query('select * from ' . $this->table_name);
        while(($row = $query->fetch(\PDO::FETCH_ASSOC)) !== false)
        {
            fputcsv($handle, $row, $delimiter, $enclosure);
        }
        fclose($handle);
        return $this;
    }
}

