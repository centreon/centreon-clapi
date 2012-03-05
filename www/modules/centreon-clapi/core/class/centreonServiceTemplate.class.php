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
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonHost.class.php $
 * SVN : $Id: centreonHost.class.php 241 2012-01-16 21:48:49Z jmathis $
 *
 */

require_once "centreonService.class.php";

/**
 * Class for managing service templates
 *
 * @author sylvestre
 */
class CentreonServiceTemplate extends CentreonObject
{
    const ORDER_SVCDESC  = 0;
    const ORDER_SVCALIAS = 1;
    const ORDER_SVCTPL   = 2;
    const NB_UPDATE_PARAMS = 3;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->object = new Centreon_Object_Service();
        $this->params = array('service_is_volatile'         		   => '2',
                              'service_active_checks_enabled'          => '2',
                              'service_passive_checks_enabled'         => '2',
                              'service_parallelize_check'              => '2',
                              'service_obsess_over_service'            => '2',
                              'service_check_freshness'                => '2',
                              'service_event_handler_enabled'          => '2',
                              'service_flap_detection_enabled'         => '2',
                              'service_process_perf_data'		       => '2',
                              'service_retain_status_information'	   => '2',
        					  'service_retain_nonstatus_information'   => '2',
                              'service_notifications_enabled'		   => '2',
                              'service_register'					   => '0',
                              'service_activate'				       => '1'
                              );
        $this->nbOfCompulsoryParams = 3;
        $this->register = 0;
        $this->activateField = 'service_activate';
    }

    /**
     * Check parameters
     *
     * @param string $serviceDescription
     * @return bool
     */
    protected function serviceExists($serviceDescription)
    {
        $elements = $this->object->getList("service_description", -1, 0, null, null, array('service_description' => $serviceDescription,
                                                                                           'service_register' => 0), "AND");
        if (count($elements)) {
            return true;
        }
        return false;
    }

    /**
     * Display all service templates
     *
     * @param string $parameters
     * @return void
     */
    public function show($parameters = null)
    {
        $filters = array('service_register' => $this->register);
        if (isset($parameters)) {
            $filters["service_description"] = "%".$parameters."%";
        }
        $commandObject = new Centreon_Object_Command();
        $paramsSvc = array('service_id', 'service_description', 'service_alias', 'command_command_id', 'command_command_id_arg',
                        'service_normal_check_interval', 'service_retry_check_interval', 'service_max_check_attempts',
                        'service_active_checks_enabled', 'service_passive_checks_enabled');
        $elements = $this->object->getList($paramsSvc, -1, 0, null, null, $filters, "AND");
        $paramSvcString = str_replace("service_", "", implode($this->delim, $paramsSvc));
        $paramSvcString = str_replace("command_command_id", "check command", $paramSvcString);
        $paramSvcString = str_replace("command_command_id_arg", "check command arguments", $paramSvcString);
        $paramSvcString = str_replace("_", " ", $paramSvcString);
        echo $paramSvcString."\n";
        foreach ($elements as $tab) {
            if (isset($tab['command_command_id']) && $tab['command_command_id']) {
                $tmp = $commandObject->getParameters($tab['command_command_id'], array($commandObject->getUniqueLabelField()));
                if (isset($tmp[$commandObject->getUniqueLabelField()])) {
                    $tab['command_command_id'] = $tmp[$commandObject->getUniqueLabelField()];
                }
            }
            echo implode($this->delim, $tab) . "\n";
        }
    }

	/**
     * Add a service template
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function add($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if ($this->serviceExists($params[self::ORDER_SVCDESC]) == true) {
            throw new CentreonClapiException(self::OBJECTALREADYEXISTS);
        }
        $addParams = array();
        $addParams['service_description'] = $params[self::ORDER_SVCDESC];
        $addParams['service_alias'] = $params[self::ORDER_SVCALIAS];
        $template = $params[self::ORDER_SVCTPL];
        $tmp = $this->object->getList($this->object->getPrimaryKey(), -1, 0, null, null, array('service_description' => $template, 'service_register' => '0'), "AND");
        if (!count($tmp)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $template);
        }
        $addParams['service_template_model_stm_id'] = $tmp[0][$this->object->getPrimaryKey()];
        $this->params = array_merge($this->params, $addParams);
        $serviceId = parent::add();

        $extended = new Centreon_Object_Service_Extended();
        $extended->insert(array($extended->getUniqueLabelField() => $serviceId));
    }

    /**
     * Delete service template
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function del($parameters)
    {
        $serviceDesc = $parameters;
        $elements = $this->object->getList("service_id", -1, 0, null, null, array('service_description' => $serviceDesc,
                                                                                  'service_register' => 0), "AND");
        if (!count($elements)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
        $this->object->delete($elements[0]['service_id']);
    }

    /**
     * Returns command id
     *
     * @param string $commandName
     * @return int
     * @throws CentreonClapiException
     */
    protected function getCommandId($commandName)
    {
        $obj = new Centreon_Object_Command();
        $tmp = $obj->getIdByParameter($obj->getUniqueLabelField(), $commandName);
        if (count($tmp)) {
            $id = $tmp[0];
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $commandName);
        }
        return $id;
    }

	/**
     * Set parameters
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function setparam($parameters = null)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $serviceDesc = $params[0];
        $elements = $this->object->getList("service_id", -1, 0, null, null, array('service_description' => $serviceDesc,
                                                                                  'service_register' => 0), "AND");
        if (!count($elements)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
        $objectId = $elements[0]['service_id'];
        $extended = false;
        switch ($params[1]) {
            case "check_command":
                $params[1] = "command_command_id";
                $params[2] = $this->getCommandId($params[2]);
                break;
            case "check_command_arguments":
                $params[1] = "command_command_id_arg";
                break;
            case "event_handler":
                $params[1] = "command_command_id2";
                $params[2] = $this->getCommandId($params[2]);
                break;
            case "event_handler_arguments":
                $params[1] = "command_command_id_arg2";
                break;
            case "check_period":
                $params[1] = "timeperiod_tp_id";
                $tpObj = new CentreonTimePeriod();
                $params[2] = $tpObj->getTimeperiodId($params[2]);
                break;
            case "notification_period":
                $params[1] = "timeperiod_tp_id2";
                $tpObj = new CentreonTimePeriod();
                $params[2] = $tpObj->getTimeperiodId($params[2]);
                break;
            case "flap_detection_options":
                break;
            case "template":
                $params[1] = "service_template_model_stm_id";
                $tmp = $this->object->getList($this->object->getPrimaryKey(), -1, 0, null, null, array('service_description' => $params[2], 'service_register' => '0'), "AND");
                if (!count($tmp)) {
                    throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[2]);
                }
                $params[2] = $tmp[0][$this->object->getPrimaryKey()];
                break;
            case "notes":
                $extended = true;
                break;
            case "notes_url":
                $extended = true;
                break;
            case "action_url":
                $extended = true;
                break;
            case "icon_image":
                $extended = true;
                break;
            case "icon_image_alt":
                $extended = true;
                break;
            default:
                $params[1] = "service_".$params[1];
                break;
        }
        if ($extended == false) {
            $updateParams = array($params[1] => $params[2]);
            parent::setparam($objectId, $updateParams);
        } else {
            $params[1] = "esi_".$params[1];
            $extended = new Centreon_Object_Service_Extended();
            $extended->update($objectId, array($params[1] => $params[2]));
        }
    }

    /**
     * Wrap macro
     *
     * @param string $macroName
     * @return string
     */
    protected function wrapMacro($macroName)
    {
        $wrappedMacro = "\$_SERVICE".strtoupper($macroName)."\$";
        return $wrappedMacro;
    }

    /**
     * Get macro list of a service template
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function getmacro($parameters)
    {
        $serviceDesc = $parameters;
        $elements = $this->object->getList("service_id", -1, 0, null, null, array('service_description' => $serviceDesc,
                                                                                  'service_register' => 0), "AND");
        if (!count($elements)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
        $macroObj = new Centreon_Object_Service_Macro_Custom();
        $macroList = $macroObj->getList(array("svc_macro_name", "svc_macro_value"), -1, 0, null, null, array("svc_svc_id" => $elements[0]['service_id']));
        echo "macro name;macro value\n";
        foreach ($macroList as $macro) {
            echo $macro['svc_macro_name'] . $this->delim . $macro['svc_macro_value'] . "\n";
        }
    }

    /**
     * Inserts/updates custom macro
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function setmacro($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < 3) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $elements = $this->object->getList("service_id", -1, 0, null, null, array('service_description' => $params[0],
                                                                                  'service_register' => 0), "AND");
        if (!count($elements)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
        $macroObj = new Centreon_Object_Service_Macro_Custom();
        $macroList = $macroObj->getList($macroObj->getPrimaryKey(), -1, 0, null, null, array("svc_svc_id"      => $elements[0]['service_id'],
                                                                                			 "svc_macro_name" => $this->wrapMacro($params[1])),
                                                                                		"AND");
        if (count($macroList)) {
            $macroObj->update($macroList[0][$macroObj->getPrimaryKey()], array('svc_macro_value' => $params[2]));
        } else {
            $macroObj->insert(array('svc_svc_id'       => $elements[0]['service_id'],
                                    'svc_macro_name'  => $this->wrapMacro($params[1]),
                                    'svc_macro_value' => $params[2]));
        }
    }

    /**
     * Delete custom macro
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function delmacro($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < 2) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $elements = $this->object->getList("service_id", -1, 0, null, null, array('service_description' => $params[0],
                                                                                  'service_register' => 0), "AND");
        if (!count($elements)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
        $macroObj = new Centreon_Object_Service_Macro_Custom();
        $macroList = $macroObj->getList($macroObj->getPrimaryKey(), -1, 0, null, null, array("svc_svc_id"      => $elements[0]['service_id'],
                                                                                			 "svc_macro_name" => $this->wrapMacro($params[1])),
                                                                                		"AND");
        if (count($macroList)) {
            $macroObj->delete($macroList[0][$macroObj->getPrimaryKey()]);
        }
    }

    /**
     * Magic method
     *
     * @param string $name
     * @param array $args
     * @return void
     * @throws CentreonClapiException
     */
    public function __call($name, $arg)
    {
        $name = strtolower($name);
        if (!isset($arg[0]) || !$arg[0]) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $args = explode($this->delim, $arg[0]);
        $elements = $this->object->getList("service_id", -1, 0, null, null, array('service_description' => $args[0],
                                                                                  'service_register' => 0), "AND");
        if (!count($elements)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
        $serviceId = $elements[0]['service_id'];
        if (preg_match("/^(get|set|add|del)([a-zA-Z_]+)/", $name, $matches)) {
            switch ($matches[2]) {
                case "host":
                    $class = "Centreon_Object_Host";
                    $relclass = "Centreon_Object_Relation_Host_Service";
                    break;
                case "contact":
                    $class = "Centreon_Object_Contact";
                    $relclass = "Centreon_Object_Relation_Contact_Service";
                    break;
                case "contactgroup":
                    $class = "Centreon_Object_Contact_Group";
                    $relclass = "Centreon_Object_Relation_Contact_Group_Service";
                    break;
                default:
                    throw new CentreonClapiException(self::UNKNOWN_METHOD);
                    break;
            }
            if (class_exists($relclass) && class_exists($class)) {
                $relobj = new $relclass();
                $obj = new $class();
                if ($matches[1] == "get") {
                    $tab = $relobj->getTargetIdFromSourceId($relobj->getFirstKey(), $relobj->getSecondKey(), $serviceId);
                    echo "id".$this->delim."name"."\n";
                    foreach($tab as $value) {
                        $tmp = $obj->getParameters($value, array($obj->getUniqueLabelField()));
                        echo $value . $this->delim . $tmp[$obj->getUniqueLabelField()] . "\n";
                    }
                } else {
                    if (!isset($args[1])) {
                        throw new CentreonClapiException(self::MISSINGPARAMETER);
                    }
                    if ($matches[2] == "contact") {
                        $args[1] = str_replace(" ", "_", $args[1]);
                    }
                    $relation = $args[1];
                    $relations = explode("|", $relation);
                    $relationTable = array();
                    foreach($relations as $rel) {
                        $tab = $obj->getIdByParameter($obj->getUniqueLabelField(), array($rel));
                        if (!count($tab)) {
                            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":".$rel);
                        }
                        $relationTable[] = $tab[0];
                    }
                    if ($matches[1] == "set") {
                        $relobj->delete(null, $serviceId);
                    }
                    $existingRelationIds = $relobj->getTargetIdFromSourceId($relobj->getFirstKey(), $relobj->getSecondKey(), $serviceId);
                    foreach($relationTable as $relationId) {
                        if ($matches[1] == "del") {
                            $relobj->delete($relationId, $serviceId);
                        } elseif ($matches[1] == "set" || $matches[1] == "add") {
                            if (!in_array($relationId, $existingRelationIds)) {
                                $relobj->insert($relationId, $serviceId);
                            }
                        }
                    }
                }
            } else {
                throw new CentreonClapiException(self::UNKNOWN_METHOD);
            }
        } else {
            throw new CentreonClapiException(self::UNKNOWN_METHOD);
        }
    }
}