<?php
/**
 * Copyright 2005-2015 CENTREON
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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
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
require_once "centreonObject.class.php";
require_once "centreonInstance.class.php";
require_once "Centreon/Object/Resource/Resource.php";
require_once "Centreon/Object/Relation/Instance/Resource.php";

/**
 *
 * @author sylvestre
 */
class CentreonResourceCfg extends CentreonObject
{
    const ORDER_UNIQUENAME        = 0;
    const ORDER_VALUE             = 1;
    const ORDER_INSTANCE          = 2;
    const ORDER_COMMENT           = 3;
    const MACRO_ALREADY_IN_USE    = "Resource is already tied to instance";
    protected $instanceObj;
    protected $relObj;

    public static $aDepends = array(
        'Instance'
    );

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->instanceObj = new CentreonInstance();
        $this->relObj = new Centreon_Object_Relation_Instance_Resource();
        $this->object = new Centreon_Object_Resource();
        $this->params = array(  'resource_line'                             => '',
                                'resource_comment'   	                    => '',
                                'resource_activate'                         => '1'
                            );
        $this->insertParams = array($this->object->getUniqueLabelField(), 'resource_line', 'instance_id', 'resource_comment');
        $this->exportExcludedParams = array_merge($this->insertParams, array($this->object->getPrimaryKey()));
        $this->nbOfCompulsoryParams = 4;
        $this->activateField = "resource_activate";
        $this->action = 'RESOURCECFG';
    }

    /**
     * Checks if macro is unique on a given poller
     *
     * @param mixed $macroName
     * @param int $pollerId
     * @return boolean
     * @throws CentreonClapiException
     */
    protected function isUnique($macro, $pollerId)
    {
        if (is_numeric($macro)) {
            $stmt = $this->db->query("SELECT resource_name FROM cfg_resource WHERE resource_id = ?", array($macro));
            $res = $stmt->fetchAll();
            if (count($res)) {
                $macroName = $res[0]['resource_name'];
            } else {
                throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
            }
            unset($res);
            unset($stmt);
        } else {
            $macroName = $macro;
        }
        $stmt = $this->db->query("SELECT r.resource_id
                                  FROM cfg_resource r, cfg_resource_instance_relations rir
                                  WHERE r.resource_id = rir.resource_id
                                  AND rir.instance_id = ?
                                  AND r.resource_name = ?", array($pollerId, $macroName));
        $res = $stmt->fetchAll();
        if (count($res)) {
            return false;
        }
        return true;
    }


    /**
     * Add action
     *
     * @param string $parameters
     * @return void
     */
    public function add($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = array();
        $instanceNames = explode("|", $params[self::ORDER_INSTANCE]);
        $instanceIds = array();
        foreach ($instanceNames as $instanceName) {
            $instanceIds[] = $this->instanceObj->getInstanceId($instanceName);
        }
        foreach ($instanceIds as $instanceId) {
            if ($this->isUnique("$".$params[self::ORDER_UNIQUENAME]."$", $instanceId) == false) {
                throw new CentreonClapiException(self::MACRO_ALREADY_IN_USE);
            }
        }
        $addParams[$this->object->getUniqueLabelField()] = "$".$params[self::ORDER_UNIQUENAME]."$";
        $addParams['resource_line'] = $params[self::ORDER_VALUE];
        $addParams['resource_comment'] = $params[self::ORDER_COMMENT];
        $this->params = array_merge($this->params, $addParams);
        $resourceId = parent::add();
        $this->setRelations($resourceId, $instanceIds);
    }

    /**
     * Set Parameters
     *
     * @param string $parameters
     * @return void
     * @throws Exception
     */
    public function setparam($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (($objectId = $params[self::ORDER_UNIQUENAME]) != 0) {
            if ($params[1] == "instance") {
                $instanceNames = explode("|", $params[self::ORDER_INSTANCE]);
                $instanceIds = array();
                foreach ($instanceNames as $instanceName) {
                    $instanceIds[] = $this->instanceObj->getInstanceId($instanceName);
                }
                $this->setRelations($objectId, $instanceIds);
            } else {
                $params[1] = str_replace("value", "line ", $params[1]);
                if ($params[1] == "name") {
                    $params[2] = "$".$params[2]."$";
                }
                $params[1] = "resource_".$params[1];
                $updateParams = array($params[1] => $params[2]);
                parent::setparam($objectId, $updateParams);
            }
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND.":".$params[self::ORDER_UNIQUENAME]);
        }
    }

    /**
     * Del Action
     *
     * @param int $objectId
     * @return void
     * @throws Exception
     */
    public function del($objectId)
    {
        $this->object->delete($objectId);
    }

    /**
     * Show
     *
     * @param string $parameters
     * @return void
     */
    public function show($parameters = null)
    {
        $filters = array();
        if (isset($parameters)) {
            $filters = array($this->object->getUniqueLabelField() => "%".$parameters."%");
        }
        $params = array("resource_id", "resource_name", "resource_line", "resource_comment", "resource_activate");
        $paramString = str_replace("_", " ", implode($this->delim, $params));
        $paramString = str_replace("resource ", "", $paramString);
        $paramString = str_replace("line", "value", $paramString);
        echo $paramString . $this->delim . "instance"."\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters);
        foreach ($elements as $tab) {
            $str = "";
            foreach ($tab as $key => $value) {
                $str .= $value . $this->delim;
            }
            $instanceIds = $this->relObj->getinstance_idFromresource_id(trim($tab['resource_id']));
            $strInstance = "";
            foreach ($instanceIds as $instanceId) {
                if ($strInstance != "") {
                    $strInstance .= "|";
                }
                $strInstance .= $this->instanceObj->getInstanceName($instanceId);
            }
            $str .= $strInstance;
            $str = trim($str, $this->delim) . "\n";
            echo $str;
        }
    }

    /**
     * Set Instance relations
     *
     * @param int $resourceId
     * @param array $instances
     * @return void
     */
    protected function setRelations($resourceId, $instances)
    {
        $this->relObj->delete_resource_id($resourceId);
        foreach ($instances as $instanceId) {
            $this->relObj->insert($instanceId, $resourceId);
        }
    }

    /**
     * Export
     *
     * @return void
     */
    public function export() {
        $elements = $this->object->getList();
        foreach ($elements as $element) {
            $instanceIds = $this->relObj->getinstance_idFromresource_id(trim($element[$this->object->getPrimaryKey()]));

            /* ADD action */
            $addStr = $this->action . $this->delim . "ADD";
            foreach ($this->insertParams as $param) {
                if ($param == 'instance_id') {
                    $instances = array();
                    foreach ($instanceIds as $instanceId) {
                        $instances[] = $this->instanceObj->getInstanceName($instanceId);
                    }
                    $element[$param] = implode('|', $instances);
                }
                $addStr .= $this->delim . $element[$param];
            }
            $addStr .= "\n";
            echo $addStr;

            /* SETPARAM action */
            foreach ($element as $parameter => $value) {
                if (!in_array($parameter, $this->exportExcludedParams) && !is_null($value) && $value != "") {
                    $value = str_replace("\n", "<br/>", $value);
                    $value = CentreonUtils::convertLineBreak($value);
                    echo $this->action . $this->delim . "setparam" . $this->delim . $element[$this->object->getUniqueLabelField()] . $this->delim . $parameter . $this->delim . $value . "\n";
                }
            }
        }
    }
}
