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
 * For more information : command@centreon.com
 *
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonHost.class.php $
 * SVN : $Id: centreonHost.class.php 25 2010-03-30 05:52:19Z jmathis $
 *
 */

require_once "centreonObject.class.php";
require_once "centreonUtils.class.php";
require_once "Centreon/Object/Command/Command.php";
require_once "Centreon/Object/Graph/Template/Template.php";

/**
 *
 * Centreon Command Class
 * @author jmathis
 *
 */
class CentreonCommand extends CentreonObject
{
    const ORDER_UNIQUENAME        = 0;
    const ORDER_TYPE              = 1;
    const ORDER_COMMAND           = 2;
    const UNKNOWN_CMD_TYPE        = "Unknown command type";
    protected $typeConversion;

    /**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
        $this->object = new Centreon_Object_Command();
        $this->params = array();
        $this->nbOfCompulsoryParams = 3;
		$this->typeConversion = array("notif" => 1, "check" => 2, "misc" => 3, 1 => "notif", 2 => "check", 3 => "misc");
	}

	/**
	 *
	 * Get command id
	 *
	 * @param unknown_type $command_name
	 * @todo gotta remove this, but it is called in CentreonContact, fix it first
	 */
	public function getCommandID($command_name = NULL)
	{
	    $filters = array();
        if (isset($parameters)) {
            $filters = array($this->object->getUniqueLabelField() => "%".$parameters."%");
        }
        $params = array('hc_id', 'hc_name', 'hc_alias');
        $paramString = str_replace("hc_", "", implode($this->delim, $params));
        echo $paramString . "\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters);
        foreach ($elements as $tab) {
            echo implode($this->delim, $tab) . "\n";
        }
	}

    /**
     * Display all commands
     *
     * @param string $parameters
     */
	public function show($parameters = null)
	{
	    $filters = array();
        if (isset($parameters)) {
            $filters = array($this->object->getUniqueLabelField() => "%".$parameters."%");
        }
        $params = array('command_id', 'command_name', 'command_type', 'command_line');
        $paramString = str_replace("command_", "", implode($this->delim, $params));
        echo $paramString . "\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters);
        foreach ($elements as $tab) {
            $tab['command_line'] = CentreonUtils::convertSpecialPattern(html_entity_decode($tab['command_line']));
            $tab['command_type'] = $this->typeConversion[$tab['command_type']];
            echo implode($this->delim, $tab) . "\n";
        }
	}

	/**
	 * Add a command
	 *
	 * @param string $parameters
	 * @throws CentreonClapiException
	 */
	public function add($parameters)
	{
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = array();
        $addParams[$this->object->getUniqueLabelField()] = $params[self::ORDER_UNIQUENAME];
        if (!isset($this->typeConversion[$params[self::ORDER_TYPE]])) {
            throw new CentreonClapiException(self::UNKNOWN_CMD_TYPE . ":" . $params[self::ORDER_TYPE]);
        }
        $addParams['command_type'] = is_numeric($params[self::ORDER_TYPE]) ? $params[self::ORDER_TYPE] : $this->typeConversion[$params[self::ORDER_TYPE]];
        $addParams['command_line'] = $params[self::ORDER_COMMAND];
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
        parent::add();
	}

	/**
	 * Set parameters
	 *
	 * @param string $parameters
	 * @throws CentreonClapiException
	 */
	public function setparam($parameters)
	{
	    $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (($objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) != 0) {
            if (!preg_match("/^command_/", $params[1])) {
                if ($params[1] != "graph") {
                    $params[1] = "command_".$params[1];
                } else {
                    $params[1] = "graph_id";
                }
            }
            if ($params[1] == "command_type") {
                if (!isset($this->typeConversion[$params[2]])) {
                    throw new CentreonClapiException(self::UNKNOWN_CMD_TYPE . ":" . $params[2]);
                }
                if (!is_numeric($params[2])) {
                    $params[2] = $this->typeConversion[$params[2]];
                }
            } elseif ($params[1] == "graph_id") {
                $graphObject = new Centreon_Object_Graph_Template();
                $tmp = $graphObject->getIdByParameter($graphObject->getUniqueLabelField(), $params[2]);
                if (!count($tmp)) {
                    throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[2]);
                }
                $params[2] = $tmp[0];
            }
            $updateParams = array($params[1] => $params[2]);
            parent::setparam($objectId, $updateParams);
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }
	}
}
?>