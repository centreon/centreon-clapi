<?php
require_once "Centreon/Object/Object.php";

/**
 * Used for interacting with host extended information
 *
 * @author sylvestre
 */
class Centreon_Object_Host_Extended extends Centreon_Object
{
    protected $table = "extended_host_information";
    protected $primaryKey = "host_host_id";
    protected $uniqueLabelField = "host_host_id";

    /**
     * Used for inserting object into database
     *
     * @param array $params
     * @return int
     */
    public function insert($params = array())
    {
        $sql = "INSERT INTO $this->table ";
        $sqlFields = "";
        $sqlValues = "";
        $sqlParams = array();
        foreach ($params as $key => $value) {
            if ($sqlFields != "") {
                $sqlFields .= ",";
            }
            if ($sqlValues != "") {
                $sqlValues .= ",";
            }
            $sqlFields .= $key;
            $sqlValues .= "?";
            $sqlParams[] = $value;
        }
        if ($sqlFields && $sqlValues) {
            $sql .= "(".$sqlFields.") VALUES (".$sqlValues.")";
            $this->db->query($sql, $sqlParams);
            return $this->db->lastInsertId($this->table, $this->primaryKey);
        }
        return null;
    }

    public function duplicate()
    {

    }
}