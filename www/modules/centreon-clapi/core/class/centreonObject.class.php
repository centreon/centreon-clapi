<?php
/**
 * Copyright 2005-2010 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 * SVN : $URL$
 * SVN : $Id$
 */
require_once "Centreon/Db/Manager/Manager.php";
require_once "centreonClapiException.class.php";

abstract class CentreonObject
{
    const MISSINGPARAMETER = "Missing parameters";
    const MISSINGNAMEPARAMETER = "Missing name parameter";
    const OBJECTALREADYEXISTS = "Object already exists";
    const OBJECT_NOT_FOUND = "Object not found";
    const UNKNOWN_METHOD = "Method not implemented into Centreon API";
    const NAMEALREADYINUSE = "Name is already in use";
    const NB_UPDATE_PARAMS = 3;
    /**
     * Db adapter
     *
     * @var Zend_Db_Adapter
     */
    protected $db;
    /**
     * Version of Centreon
     *
     * @var string
     */
    protected $version;
    /**
     * Centreon Configuration object type
     *
     * @var Centreon_Object
     */
    protected $object;
    /**
     * Default params
     *
     * @var array
     */
    protected $params;
    /**
     * Number of compulsory parameters when adding a new object
     *
     * @var int
     */
    protected $nbOfCompulsoryParams;
    /**
     * Delimiter
     *
     * @var string
     */
    protected $delim;
    /**
     * Table column used for activating and deactivating object
     *
     * @var string
     */
    protected $activateField;
    /**
     * Export : Table columns that are used for 'add' action
     *
     * @var array
     */
    protected $insertParams;
    /**
     * Export : Table columns which will not be exported for 'setparam' action
     *
     * @var array
     */
    protected $exportExcludedParams;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->db = Centreon_Db_Manager::factory('centreon');
        $res = $this->db->query("SELECT `value` FROM informations WHERE `key` = 'version'");
        $row = $res->fetch();
        $this->version = $row['value'];
        $this->params = array();
        $this->insertParams = array();
        $this->exportExcludedParams = array();
        $this->action = "";
        $this->delim = ";";
    }

    /**
     * Get Centreon Version
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Checks if object exists
     *
     * @param string $name
     * @return bool
     */
    protected function objectExists($name, $updateId = null)
    {
        $ids = $this->object->getList($this->object->getPrimaryKey(), -1, 0, null, null, array($this->object->getUniqueLabelField() => $name), "AND");
        if (isset($updateId) && count($ids)) {
            if ($ids[0][$this->object->getPrimaryKey()] == $updateId) {
                return false;
            } else {
                return true;
            }
        } elseif (count($ids)) {
            return true;
        }
        return false;
    }

    /**
     * Get Object Id
     *
     * @param string $name
     * @return int
     */
    public function getObjectId($name)
    {
        $ids = $this->object->getIdByParameter($this->object->getUniqueLabelField(), array($name));
        if (count($ids)) {
            return $ids[0];
        }
        return 0;
    }

    /**
     * Get Object Name
     *
     * @param int $id
     * @return string
     */
    public function getObjectName($id)
    {
        $tmp = $this->object->getParameters($id, array($this->object->getUniqueLabelField()));
        if (isset($tmp[$this->object->getUniqueLabelField()])) {
            return $tmp[$this->object->getUniqueLabelField()];
        }
        return "";
    }


    /**
     * Checks if parameters are correct
     *
     * @throws Exception
     */
    protected function checkParameters()
    {
        if (!isset($this->params[$this->object->getUniqueLabelField()])) {
            throw new CentreonClapiException(self::MISSINGNAMEPARAMETER);
        }
        if ($this->objectExists($this->params[$this->object->getUniqueLabelField()]) === true) {
            throw new CentreonClapiException(self::OBJECTALREADYEXISTS);
        }
    }

    /**
     * Add Action
     *
     * @return int
     */
    public function add()
    {
        return $this->object->insert($this->params);
    }

    /**
     * Del Action
     *
     * @param string $objectName
     * @return void
     * @throws CentreonClapiException
     */
    public function del($objectName)
    {
        $ids = $this->object->getIdByParameter($this->object->getUniqueLabelField(), array($objectName));
        if (count($ids)) {
            $this->object->delete($ids[0]);
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND.":".$objectName);
        }
    }

    /**
     * Set Param
     *
     * @param int $objectId
     * @param array $params
     */
    public function setparam($objectId, $params = array())
    {
        if (isset($params[$this->object->getUniqueLabelField()]) && $this->objectExists($params[$this->object->getUniqueLabelField()], $objectId) == true) {
            throw new CentreonClapiException(self::NAMEALREADYINUSE);
        }
        $this->object->update($objectId, $params);
    }

    /**
     * Shows list
     *
     * @param array $params
     * @return void
     */
    public function show($params = array(), $filters = array())
    {
        echo str_replace("_", " ", implode($this->delim, $params)) . "\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters);
        foreach ($elements as $tab) {
            echo implode($this->delim, $tab) . "\n";
        }
    }

    /**
     * Set the activate field
     *
     * @param string $objectName
     * @param int $value
     * @throws CentreonClapiException
     */
    protected function activate($objectName, $value)
    {
        if (!isset($objectName) || !$objectName) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (isset($this->activateField)) {
            $ids = $this->object->getIdByParameter($this->object->getUniqueLabelField(), array($objectName));
            if (count($ids)) {
                $this->object->update($ids[0], array($this->activateField => $value));
            } else {
                throw new CentreonClapiException(self::OBJECT_NOT_FOUND.":".$objectName);
            }
        } else {
            throw new CentreonClapiException(self::UNKNOWN_METHOD);
        }
    }

    /**
     * Enable object
     *
     * @param string $objectName
     * @return void
     */
    public function enable($objectName)
    {
        $this->activate($objectName, 1);
    }

    /**
     * Disable object
     *
     * @param string $objectName
     * @return void
     */
    public function disable($objectName)
    {
        $this->activate($objectName, 0);
    }

	/**
	 * Export data
	 *
	 * @param string $parameters
	 * @return void
	 */
	public function export()
	{
        $elements = $this->object->getList("*", -1, 0);
        foreach ($elements as $element) {
            $addStr = $this->action.$this->delim."ADD";
            foreach ($this->insertParams as $param) {
                $addStr .= $this->delim.$element[$param];
            }
            $addStr .= "\n";
            echo $addStr;
            foreach ($element as $parameter => $value) {
                if (!in_array($parameter, $this->exportExcludedParams)) {
                    if (!is_null($value) && $value != "") {
                        echo $this->action.$this->delim."setparam".$this->delim.$element[$this->object->getUniqueLabelField()].$this->delim.$parameter.$this->delim.$value."\n";
                    }
                }
            }
        }
	}
}