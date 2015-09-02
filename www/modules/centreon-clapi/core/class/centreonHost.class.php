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
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonHost.class.php $
 * SVN : $Id: centreonHost.class.php 343 2012-07-05 15:52:30Z shotamchay $
 *
 */
require_once "centreonObject.class.php";
require_once "centreonUtils.class.php";
require_once "centreonTimePeriod.class.php";
require_once "centreonACL.class.php";
require_once "centreonCommand.class.php";
require_once "centreonExported.class.php";
require_once "Centreon/Object/Instance/Instance.php";
require_once "Centreon/Object/Command/Command.php";
require_once "Centreon/Object/Timeperiod/Timeperiod.php";
require_once "Centreon/Object/Host/Host.php";
require_once "Centreon/Object/Host/Extended.php";
require_once "Centreon/Object/Host/Group.php";
require_once "Centreon/Object/Host/Category.php";
require_once "Centreon/Object/Host/Template.php";
require_once "Centreon/Object/Host/Macro/Custom.php";
require_once "Centreon/Object/Service/Service.php";
require_once "Centreon/Object/Service/Extended.php";
require_once "Centreon/Object/Contact/Contact.php";
require_once "Centreon/Object/Contact/Group.php";
require_once "Centreon/Object/Relation/Host/Template/Host.php";
require_once "Centreon/Object/Relation/Host/Parent/Host.php";
require_once "Centreon/Object/Relation/Host/Group/Host.php";
require_once "Centreon/Object/Relation/Host/Category/Host.php";
require_once "Centreon/Object/Relation/Instance/Host.php";
require_once "Centreon/Object/Relation/Contact/Host.php";
require_once "Centreon/Object/Relation/Contact/Group/Host.php";
require_once "Centreon/Object/Relation/Host/Service.php";

/**
 * Centreon Host objects
 *
 * @author sylvestre
 */
class CentreonHost extends CentreonObject {

    const ORDER_UNIQUENAME = 0;
    const ORDER_ALIAS = 1;
    const ORDER_ADDRESS = 2;
    const ORDER_TEMPLATE = 3;
    const ORDER_POLLER = 4;
    const ORDER_HOSTGROUP = 5;
    const MISSING_INSTANCE = "Instance name is mandatory";
    const UNKNOWN_NOTIFICATION_OPTIONS = "Invalid notifications options";

    public static $aDepends = array(
        'CMD',
        'TP',
        'TRAP',
        'INSTANCE',
        'HTPL'
    );
    /**
     *
     * @var array 
     * Contains : list of authorized notifications_options for this object
     */
    public static $aAuthorizedNotificationsOptions = array(
        'd' => 'Down', 
        'u' => 'Unreachable', 
        'r' => 'Recovery', 
        'f' => 'Flapping', 
        's' => 'Downtime Scheduled'
    );

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
        $this->object = new Centreon_Object_Host();
        $this->params = array('host_active_checks_enabled' => '2',
            'host_passive_checks_enabled' => '2',
            'host_checks_enabled' => '2',
            'host_obsess_over_host' => '2',
            'host_check_freshness' => '2',
            'host_event_handler_enabled' => '2',
            'host_flap_detection_enabled' => '2',
            'host_process_perf_data' => '2',
            'host_retain_status_information' => '2',
            'host_retain_nonstatus_information' => '2',
            'host_notifications_enabled' => '2',
            'host_register' => '1',
            'host_activate' => '1'
        );
        $this->insertParams = array('host_name', 'host_alias', 'host_address', 'template', 'instance', 'hostgroup');
        $this->exportExcludedParams = array_merge($this->insertParams, array($this->object->getPrimaryKey()), array('host_template_model_htm_id'));
        $this->action = "HOST";
        $this->nbOfCompulsoryParams = count($this->insertParams);
        $this->register = 1;
        $this->activateField = 'host_activate';
    }

    /**
     * Get clapi action name from db column name
     *
     * @param string $columnName
     * @return string
     */
    protected function getClapiActionName($columnName) {
        static $table;

        if (!isset($table)) {
            $table = array("command_command_id" => "check_command",
                "command_command_id2" => "event_handler",
                "timeperiod_tp_id" => "check_period",
                "timeperiod_tp_id2" => "notification_period",
                "command_command_id_arg1" => "check_command_arguments",
                "command_command_id_arg2" => "event_handler_arguments");
        }
        if (preg_match("/^ehi_/", $columnName)) {
            return substr($columnName, strlen("ehi_"));
        }
        if (isset($table[$columnName])) {
            return $table[$columnName];
        }
        return $columnName;
    }

    /**
     * We keep this method for retro compatibility with other objects
     *
     * @param string $name
     * @return int
     */
    public function getHostID($name) {
        return $this->getObjectId($name);
    }

    /**
     * We keep this method for retro compatibility with other objects
     *
     * @param int $hostId
     * @return string
     */
    public function getHostName($hostId) {
        return $this->getObjectName($hostId);
    }

    /**
     * Display all hosts
     *
     * @param string $parameters
     * @return void
     */
    public function show($parameters = null) {
        $filters = array('host_register' => $this->register);
        if (isset($parameters)) {
            $filters[$this->object->getUniqueLabelField()] = "%" . $parameters . "%";
        }
        $params = array('host_id', 'host_name', 'host_alias', 'host_address', 'host_activate');
        $paramString = str_replace("host_", "", implode($this->delim, $params));
        echo $paramString . "\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters, "AND");
        foreach ($elements as $tab) {
            echo implode($this->delim, $tab) . "\n";
        }
    }

    /**
     * Add a contact
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function add($parameters) {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = array();
        $addParams[$this->object->getUniqueLabelField()] = $params[self::ORDER_UNIQUENAME];
        $addParams['host_alias'] = $params[self::ORDER_ALIAS];
        $addParams['host_address'] = $params[self::ORDER_ADDRESS];
        $templates = explode("|", $params[self::ORDER_TEMPLATE]);
        $templateIds = array();
        foreach ($templates as $template) {
            if ($template) {
                $tmp = $this->object->getIdByParameter($this->object->getUniqueLabelField(), $template);
                if (count($tmp)) {
                    $templateIds[] = $tmp[0];
                } else {
                    throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $template);
                }
            }
        }
        $instanceName = $params[self::ORDER_POLLER];
        $instanceObject = new Centreon_Object_Instance();
        if ($this->action == "HOST") {
            if ($instanceName) {
                $tmp = $instanceObject->getIdByParameter($instanceObject->getUniqueLabelField(), $instanceName);
                if (!count($tmp)) {
                    throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $instanceName);
                }
                $instanceId = $tmp[0];
            } else {
                throw new CentreonClapiException(self::MISSING_INSTANCE);
            }
        }
        $hostgroups = explode("|", $params[self::ORDER_HOSTGROUP]);
        $hostgroupIds = array();
        $hostgroupObject = new Centreon_Object_Host_Group();
        foreach ($hostgroups as $hostgroup) {
            if ($hostgroup) {
                $tmp = $hostgroupObject->getIdByParameter($hostgroupObject->getUniqueLabelField(), $hostgroup);
                if (count($tmp)) {
                    $hostgroupIds[] = $tmp[0];
                } else {
                    throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $hostgroup);
                }
            }
        }
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
        $hostId = parent::add();
        $i = 1;
        $templateRelationObject = new Centreon_Object_Relation_Host_Template_Host();
        foreach ($templateIds as $templateId) {
            $templateRelationObject->insert($templateId, $hostId, $i);
            $i++;
        }
        $hostgroupRelationObject = new Centreon_Object_Relation_Host_Group_Host();
        foreach ($hostgroupIds as $hostgroupId) {
            $hostgroupRelationObject->insert($hostgroupId, $hostId);
        }
        if (isset($instanceId)) {
            $instanceRelationObject = new Centreon_Object_Relation_Instance_Host();
            $instanceRelationObject->insert($instanceId, $hostId);
        }
        $extended = new Centreon_Object_Host_Extended();
        $extended->insert(array($extended->getUniqueLabelField() => $hostId));
    }

    /**
     * Del Action
     * Must delete services as well
     *
     * @param string $objectName
     * @return void
     * @throws CentreonClapiException
     */
    public function del($objectName) {
        parent::del($objectName);
        $this->db->query("DELETE FROM service WHERE service_register = '1' AND service_id NOT IN (SELECT service_service_id FROM host_service_relation)");
    }

    /**
     * Tie host to instance (poller)
     *
     * @param string $parameters
     * @throws CentreonClapiException
     */
    public function setinstance($parameters) {
        $params = explode($this->delim, $parameters);
        if (count($params) < 2) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $hostId = $this->getObjectId($params[self::ORDER_UNIQUENAME]);
        $instanceObj = new Centreon_Object_Instance();
        $tmp = $instanceObj->getIdByParameter($instanceObj->getUniqueLabelField(), $params[1]);
        if (!count($tmp)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[1]);
        }
        $instanceId = $tmp[0];
        $relationObj = new Centreon_Object_Relation_Instance_Host();
        $relationObj->delete(null, $hostId);
        $relationObj->insert($instanceId, $hostId);
    }

    /**
     * Set parameters
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function setparam($parameters = null) {
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (($objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) != 0) {
            $extended = false;
            $commandObject = new CentreonCommand();
            switch ($params[1]) {
                case "check_command":
                    $params[1] = "command_command_id";
                    $params[2] = $commandObject->getId($params[2]);
                    break;
                case "check_command_arguments":
                    $params[1] = "command_command_id_arg1";
                    break;
                case "event_handler":
                    $params[1] = "command_command_id2";
                    $params[2] = $commandObject->getId($params[2]);
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
                case "contact_additive_inheritance":
                    break;
                case "cg_additive_inheritance":
                    break;
                case "flap_detection_options":
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
                case "vrml_image":
                    $extended = true;
                    break;
                case "statusmap_image":
                    $extended = true;
                    break;
                case "2d_coords":
                    $extended = true;
                    break;
                case "3d_coords":
                    $extended = true;
                    break;
                case "host_notification_options" :
                    $aNotifs = explode(",", $params[2]);
                    foreach ($aNotifs as $notif) {
                        if (!array_key_exists($notif, self::$aAuthorizedNotificationsOptions)) {
                            throw new CentreonClapiException(self::UNKNOWN_NOTIFICATION_OPTIONS);
                        }
                    }
                break;
                default:
                    if (!preg_match("/^host_/", $params[1])) {
                        $params[1] = "host_" . $params[1];
                    }
                    break;
            }
            if ($extended == false) {
                $updateParams = array($params[1] => $params[2]);
                parent::setparam($objectId, $updateParams);
            } else {
                $params[1] = "ehi_" . $params[1];
                if ($params[1] == "ehi_icon_image" || $params[1] == "ehi_statusmap_image" || $params[1] == "ehi_vrml_image") {
                    if ($params[2]) {
                        $id = CentreonUtils::getImageId($params[2]);
                        if (is_null($id)) {
                            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[2]);
                        }
                        $params[2] = $id;
                    } else {
                        $params[2] = null;
                    }
                }
                $extended = new Centreon_Object_Host_Extended();
                $extended->update($objectId, array($params[1] => $params[2]));
            }
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }
    }

    /**
     * Set severity
     * 
     * @param string $parameters
     */
    public function setseverity($parameters) {
        $params = explode($this->delim, $parameters);
        if (count($params) < 2) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (($hostId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) == 0) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }
        $severityObj = new Centreon_Object_Host_Category();
        $severity = $severityObj->getIdByParameter(
                $severityObj->getUniqueLabelField(), $params[1]
        );
        if (!isset($severity[0])) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[1]);
        }
        $k = $severityObj->getPrimaryKey();
        $severityId = $severity[0][$k];
        $severity = $severityObj->getParameters(
                $severityId, array('level')
        );
        if ($severity['level']) {
            // can't delete with generic method
            $this->db->query("DELETE FROM hostcategories_relation 
                WHERE host_host_id = ? 
                AND hostcategories_hc_id IN (SELECT hc_id FROM hostcategories WHERE level > 0)", $hostId
            );
            $rel = new Centreon_Object_Relation_Host_Category_Host();
            $rel->insert($severityId, $hostId);
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[1]);
        }
    }

    /**
     * Unset severity
     * 
     * @param string $parameters
     */
    public function unsetseverity($parameters) {
        $params = explode($this->delim, $parameters);
        if (count($params) < 1) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (($hostId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) == 0) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }
        // can't delete with generic method
        $this->db->query("DELETE FROM hostcategories_relation 
                WHERE host_host_id = ? 
                AND hostcategories_hc_id IN (SELECT hc_id FROM hostcategories WHERE level > 0)", $hostId
        );
    }

    /**
     * Wrap macro
     *
     * @param string $macroName
     * @return string
     */
    protected function wrapMacro($macroName) {
        $wrappedMacro = "\$_HOST" . strtoupper($macroName) . "\$";
        return $wrappedMacro;
    }

    /**
     * Strip macro
     *
     * @param string $macroName
     * @return string
     */
    protected function stripMacro($macroName) {
        $strippedMacro = $macroName;
        if (preg_match('/\$_HOST([a-zA-Z0-9_-]+)\$/', $strippedMacro, $matches)) {
            $strippedMacro = $matches[1];
        }
        return strtolower($strippedMacro);
    }

    /**
     * Get macro list of a host
     *
     * @param string $hostName
     * @return void
     * @throws CentreonClapiException
     */
    public function getmacro($hostName) {
        if (($hostId = $this->getObjectId($hostName)) == 0) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $hostName);
        }
        $macroObj = new Centreon_Object_Host_Macro_Custom();
        $macroList = $macroObj->getList(array("host_macro_name", "host_macro_value", "is_password"), -1, 0, null, null, array("host_host_id" => $hostId));
        echo "macro name;macro value;is_password\n";
        foreach ($macroList as $macro) {
            echo $macro['host_macro_name'] . $this->delim . $macro['host_macro_value'] . $this->delim . $macro['is_password'] . "\n";
        }
    }

    /**
     * Inserts/updates custom macro
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function setmacro($parameters) {
        $params = explode($this->delim, $parameters);
        if (count($params) == 3) {
            $params[3] = 0;
        }

        if (($hostId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) == 0) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $macroObj = new Centreon_Object_Host_Macro_Custom();
        $macroList = $macroObj->getList($macroObj->getPrimaryKey(), -1, 0, null, null, array("host_host_id" => $hostId,
            "host_macro_name" => $this->wrapMacro($params[1])), "AND");
        if (count($macroList)) {
            $macroObj->update($macroList[0][$macroObj->getPrimaryKey()], array('host_macro_value' => $params[2], 'is_password' => $params[3]));
        } else {
            $macroObj->insert(array('host_host_id' => $hostId,
                'host_macro_name' => $this->wrapMacro($params[1]),
                'host_macro_value' => $params[2],
                'is_password' => $params[3]));
        }
        $this->addAuditLog(
                'c', $hostId, $params[self::ORDER_UNIQUENAME], array($params[1] => $params[2])
        );
    }

    /**
     * Delete custom macro
     *
     * @param string $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function delmacro($parameters) {
        $params = explode($this->delim, $parameters);
        if (($hostId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) == 0) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }
        if (count($params) < 2) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $macroObj = new Centreon_Object_Host_Macro_Custom();
        $macroList = $macroObj->getList($macroObj->getPrimaryKey(), -1, 0, null, null, array("host_host_id" => $hostId,
            "host_macro_name" => $this->wrapMacro($params[1])), "AND");
        if (count($macroList)) {
            $macroObj->delete($macroList[0][$macroObj->getPrimaryKey()]);
        }
        $this->addAuditLog(
                'c', $hostId, $params[self::ORDER_UNIQUENAME], array($params[1] => '')
        );
    }

    /**
     * Deploy services
     * Recursive method
     *
     * @param int $hostId
     * @param mixed $hostTemplateId
     * @return void
     */
    protected function deployServices($hostId, $hostTemplateId = null) {
        static $tmplRel;
        static $svcObj;
        static $hostSvcRel;
        static $svcExtended;

        if (!isset($tmplRel) && !isset($svcObj) && !isset($hostSvcRel)) {
            $tmplRel = new Centreon_Object_Relation_Host_Template_Host();
            $svcObj = new Centreon_Object_Service();
            $hostSvcRel = new Centreon_Object_Relation_Host_Service();
            $svcExtended = new Centreon_Object_Service_Extended();
        }

        if (!isset($hostTemplateId)) {
            $id = $hostId;
        } else {
            $id = $hostTemplateId;
        }
        $templates = $tmplRel->gethost_tpl_idFromhost_host_id($id);
        foreach ($templates as $templateId) {
            $serviceTemplates = $hostSvcRel->getservice_service_idFromhost_host_id($templateId);
            foreach ($serviceTemplates as $serviceTemplateId) {
                $params = $svcObj->getParameters($serviceTemplateId, array('service_alias'));
                $sql = "SELECT service_id
                		FROM service s, host_service_relation hsr
                		WHERE s.service_id = hsr.service_service_id
                		AND s.service_description = :servicedescription
                		AND hsr.host_host_id = :hostid
                		UNION
                		SELECT service_id
                		FROM service s, host_service_relation hsr
                		WHERE s.service_id = hsr.service_service_id
                		AND s.service_description = :servicedescription
                		AND hsr.hostgroup_hg_id IN (SELECT hostgroup_hg_id FROM hostgroup_relation WHERE host_host_id = :hostid)";
                $res = $this->db->query($sql, array(':servicedescription' => $params['service_alias'], ':hostid' => $hostId));
                $result = $res->fetchAll();
                if (!count($result)) {
                    $svcId = $svcObj->insert(array('service_description' => $params['service_alias'],
                        'service_activate' => '1',
                        'service_register' => '1',
                        'service_template_model_stm_id' => $serviceTemplateId));
                    $hostSvcRel->insert($hostId, $svcId);
                    $svcExtended->insert(array($svcExtended->getUniqueLabelField() => $svcId));
                }
                unset($res);
            }
            $this->deployServices($hostId, $templateId);
        }
    }

    /**
     * Apply template in order to deploy services
     *
     * @param string $hostName
     * @return void
     * @throws CentreonClapiException
     */
    public function applytpl($hostName) {
        if (!$this->register) {
            throw new CentreonClapiException(self::UNKNOWN_METHOD);
        }
        $params = explode($this->delim, $hostName);
        if (($hostId = $this->getObjectId($hostName)) == 0) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $hostName);
        }
        $this->deployServices($hostId);
    }

    /**
     * Magic method
     *
     * @param string $name
     * @param array $args
     * @return void
     * @throws CentreonClapiException
     */
    public function __call($name, $arg) {
        $name = strtolower($name);
        if (!isset($arg[0])) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $args = explode($this->delim, $arg[0]);
        $hostIds = $this->object->getIdByParameter($this->object->getUniqueLabelField(), array($args[0]));
        if (!count($hostIds)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $args[0]);
        }
        $hostId = $hostIds[0];
        if (preg_match("/^(get|set|add|del)([a-zA-Z_]+)/", $name, $matches)) {
            switch ($matches[2]) {
                case "contact":
                    $class = "Centreon_Object_Contact";
                    $relclass = "Centreon_Object_Relation_Contact_Host";
                    break;
                case "contactgroup":
                    $class = "Centreon_Object_Contact_Group";
                    $relclass = "Centreon_Object_Relation_Contact_Group_Host";
                    break;
                case "hostgroup":
                    $class = "Centreon_Object_Host_Group";
                    $relclass = "Centreon_Object_Relation_Host_Group_Host";
                    break;
                case "template":
                    $class = "Centreon_Object_Host_Tempalte";
                    $relclass = "Centreon_Object_Relation_Host_Template_Host";
                    break;
                case "parent":
                    $class = "Centreon_Object_Host";
                    $relclass = "Centreon_Object_Relation_Host_Parent_Host";
                    break;
                case "hostcategory":
                    $class = "Centreon_Object_Host_Category";
                    $relclass = "Centreon_Object_Relation_Host_Category_Host";
                    break;
                default:
                    throw new CentreonClapiException(self::UNKNOWN_METHOD);
                    break;
            }
            if (class_exists($relclass) && class_exists($class)) {
                $relobj = new $relclass();
                $obj = new $class();
                if ($matches[1] == "get") {
                    $tab = $relobj->getTargetIdFromSourceId($relobj->getFirstKey(), $relobj->getSecondKey(), $hostId);
                    echo "id" . $this->delim . "name" . "\n";
                    foreach ($tab as $value) {
                        $tmp = $obj->getParameters($value, array($obj->getUniqueLabelField()));
                        echo $value . $this->delim . $tmp[$obj->getUniqueLabelField()] . "\n";
                    }
                } else {
                    if (!isset($args[1])) {
                        throw new CentreonClapiException(self::MISSINGPARAMETER);
                    }
                    $relation = $args[1];
                    $relations = explode("|", $relation);
                    $relationTable = array();
                    foreach ($relations as $rel) {
                        if ($matches[2] == "contact") {
                            $tab = $obj->getIdByParameter("contact_name", array($rel));
                        } else {
                            $tab = $obj->getIdByParameter($obj->getUniqueLabelField(), array($rel));
                        }
                        
                        if (!count($tab)) {
                            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $rel);
                        }
                        $relationTable[] = $tab[0];
                    }
                    if ($matches[1] == "set") {
                        $relobj->delete(null, $hostId);
                    }
                    $existingRelationIds = $relobj->getTargetIdFromSourceId($relobj->getFirstKey(), $relobj->getSecondKey(), $hostId);
                    foreach ($relationTable as $relationId) {
                        if ($matches[1] == "del") {
                            $relobj->delete($relationId, $hostId);
                        } elseif ($matches[1] == "set" || $matches[1] == "add") {
                            if (!in_array($relationId, $existingRelationIds)) {
                                $relobj->insert($relationId, $hostId);
                            }
                        }
                    }
                    if ($matches[2] == "hostgroup") {
                        $aclObj = new CentreonACL();
                        $aclObj->reload(true);
                    }
                }
            } else {
                throw new CentreonClapiException(self::UNKNOWN_METHOD);
            }
        } else {
            throw new CentreonClapiException(self::UNKNOWN_METHOD);
        }
    }

    /**
     * Export
     *
     * @return void
     */
    public function export($filter_id=null, $filter_name=null) {
        $filters = array("host_register" => $this->register);
        if (!is_null($filter_id)) {
            $filters['host_id'] = $filter_id;
        }
    
        $elements = $this->object->getList("*", -1, 0, null, null, $filters, "AND");
        $extendedObj = new Centreon_Object_Host_Extended();
        $commandObj = new Centreon_Object_Command();
        $tpObj = new Centreon_Object_Timeperiod();
        $macroObj = new Centreon_Object_Host_Macro_Custom();
        $instanceRel = new Centreon_Object_Relation_Instance_Host();
        if ($this->register) {
            $instElements = $instanceRel->getMergedParameters(array("name"), array("host_name"), -1, 0, null, null, array("host_register" => $this->register), "AND");
        }
        foreach ($elements as $element) {
            $addStr = $this->action . $this->delim . "ADD";
            foreach ($this->insertParams as $param) {
                $addStr .= $this->delim;
                if ($param == 'instance') {
                    if ($this->register) {
                        foreach ($instElements as $instElem) {
                            if ($element['host_name'] == $instElem['host_name']) {
                                $addStr .= $instElem['name'];
                            }
                        }
                    }
                }
                if ($param != "hostgroup" && $param != "template") {
                    $addStr .= $element[$param];
                }
            }
            $addStr .= "\n";
            echo $addStr;
            foreach ($element as $parameter => $value) {
                if (!in_array($parameter, $this->exportExcludedParams) && !is_null($value) && $value != "") {
                    $action_tmp = null;
                    if ($parameter == "timeperiod_tp_id" || $parameter == "timeperiod_tp_id2") {
                        $action_tmp = 'TP';
                        $tmpObj = $tpObj;
                    } elseif ($parameter == "command_command_id" || $parameter == "command_command_id2") {
                        $action_tmp = 'CMD';
                        $tmpObj = $commandObj;
                    }
                    if (isset($tmpObj)) {
                        $tmp = $tmpObj->getParameters($value, $tmpObj->getUniqueLabelField());
                        if (isset($tmp) && isset($tmp[$tmpObj->getUniqueLabelField()])) {
                            $tmp_id = $value;
                            $value = $tmp[$tmpObj->getUniqueLabelField()];
                            if (!is_null($filter_id) && !is_null($action_tmp)) {
                                $this->api->export_filter($action_tmp, $tmp_id, $value);
                            }
                        }
                        unset($tmpObj);
                    }
                    $value = CentreonUtils::convertLineBreak($value);
                    echo $this->action . $this->delim . "setparam" . $this->delim . $element[$this->object->getUniqueLabelField()] . $this->delim . $this->getClapiActionName($parameter) . $this->delim . $value . "\n";
                }
            }
            $params = $extendedObj->getParameters($element[$this->object->getPrimaryKey()], array("ehi_notes", "ehi_notes_url", "ehi_action_url", "ehi_icon_image",
                "ehi_icon_image_alt", "ehi_vrml_image", "ehi_statusmap_image", "ehi_2d_coords", "ehi_3d_coords"));
            if (isset($params) && is_array($params)) {
                foreach ($params as $k => $v) {
                    if (!is_null($v) && $v != "") {
                        $v = CentreonUtils::convertLineBreak($v);
                        echo $this->action . $this->delim . "setparam" . $this->delim . $element[$this->object->getUniqueLabelField()] . $this->delim . $this->getClapiActionName($k) . $this->delim . $v . "\n";
                    }
                }
            }
            $macros = $macroObj->getList("*", -1, 0, null, null, array('host_host_id' => $element[$this->object->getPrimaryKey()]), "AND");
            foreach ($macros as $macro) {
                echo $this->action . $this->delim . "setmacro" . $this->delim . $element[$this->object->getUniqueLabelField()] . $this->delim . $this->stripMacro($macro['host_macro_name']) . $this->delim . $macro['host_macro_value'] . "\n";
            }
        }
        $cgRel = new Centreon_Object_Relation_Contact_Group_Host();
        $filters_cgRel = array("host_register" => $this->register);
        if (!is_null($filter_id)) {
            $filters_cgRel['host_id'] = $filter_id;
        }
        $elements = $cgRel->getMergedParameters(array("cg_name", "cg_id"), array($this->object->getUniqueLabelField()), -1, 0, null, null, $filters_cgRel, "AND");
        foreach ($elements as $element) {
            $this->api->export_filter('CG', $element['cg_id'], $element['cg_name']);
            echo $this->action . $this->delim . "addcontactgroup" . $this->delim . $element[$this->object->getUniqueLabelField()] . $this->delim . $element['cg_name'] . "\n";
        }
        
        $contactRel = new Centreon_Object_Relation_Contact_Host();
        $filters_contactRel = array("host_register" => $this->register);
        if (!is_null($filter_id)) {
            $filters_contactRel['host_id'] = $filter_id;
        }
        $elements = $contactRel->getMergedParameters(array("contact_name", "contact_id"), array($this->object->getUniqueLabelField()), -1, 0, null, null, $filters_contactRel, "AND");
        foreach ($elements as $element) {
            $this->api->export_filter('CONTACT', $element['contact_id'], $element['contact_name']);
            echo $this->action . $this->delim . "addcontact" . $this->delim . $element[$this->object->getUniqueLabelField()] . $this->delim . $element['contact_name'] . "\n";
        }
        
        $htplRel = new Centreon_Object_Relation_Host_Template_Host();
        $filters_htplRel = array("h.host_register" => $this->register);
        if (!is_null($filter_id)) {
            $filters_htplRel['h.host_id'] = $filter_id;
        }
        $elements = $htplRel->getMergedParameters(array("host_name as host"), array("host_name as template", "host_id as tpl_id"), -1, 0, "host,`order`", "ASC", $filters_htplRel, "AND");
        foreach ($elements as $element) {
            $this->api->export_filter('HTPL', $element['tpl_id'], $element['template']);
            echo $this->action . $this->delim . "addtemplate" . $this->delim . $element['host'] . $this->delim . $element['template'] . "\n";
        }
        
        // Filter only
        if (!is_null($filter_id)) {            
            # service templates linked
            $hostRel = new Centreon_Object_Relation_Host_Service();
            $helements = $hostRel->getMergedParameters(array("host_name"), array('service_description', 'service_id'), -1, 0, null, null, array("service_register" => 0, "host_id" => $filter_id), "AND");
            foreach ($helements as $helement) {
                $this->api->export_filter('STPL', $helement['service_id'], $helement['service_description']);
            }
            
            # service linked
            $hostRel = new Centreon_Object_Relation_Host_Service();
            $helements = $hostRel->getMergedParameters(array("host_name"), array('service_description', 'service_id'), -1, 0, null, null, array("service_register" => 1, "host_id" => $filter_id), "AND");
            foreach ($helements as $helement) {
                $this->api->export_filter('SERVICE', $helement['service_id'], $helement['service_description']);
            }
            
            # service hg linked and hostgroups
            $hostRel = new Centreon_Object_Relation_Host_Group_Host();
            $helements = $hostRel->getMergedParameters(array("hg_name", "hg_id"), array('*'), -1, 0, null, null, array("host_id" => $filter_id), "AND");
            foreach ($helements as $helement) {
                $this->api->export_filter('HG', $helement['hg_id'], $helement['hg_name']);
                $this->api->export_filter('HGSERVICE', $helement['hg_id'], $helement['hg_name']);
            }            
        }
    }

}
