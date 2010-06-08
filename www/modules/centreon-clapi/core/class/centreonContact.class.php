<?php
/*
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
 * SVN : $Id: centreonHost.class.php 25 2010-03-30 05:52:19Z jmathis $
 *
 */

define('PARAM', 0);
define('PARAM_NOTIF_CONTACT', 1);
define('PARAM_NOTIF_COMMAND', 2);
define('PARAM_NOTIF_PERIOD', 2);

class CentreonContact {
	/**
	 *
	 * @var CentreonDB
	 */
    protected $_db;

    /**
     *
     * @var CentreonCommand
     */
	protected $_cmd;

	/**
	 *
	 * @var CentreonTimePeriod
	 */
	protected $_timeperiod;

	public function __construct($db) {
		$this->_db = $db;
		$this->_cmd = new CentreonCommand($this->_db);
		$this->_timeperiod = new CentreonTimePeriod($this->_db);
	}

	/*
	 * Check host existance
	 */
	public function contactExists($name) {
		if (!isset($name))
			return 0;

		/*
		 * Get informations
		 */
		$DBRESULT =& $this->_db->query("SELECT contact_name, contact_id FROM contact WHERE contact_name = '".htmlentities($name, ENT_QUOTES)."'");
		if ($DBRESULT->numRows() >= 1) {
			$sg = $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $sg["contact_id"];
		} else {
			return 0;
		}
	}

	public function getContactID($contact_name = NULL) {
		if (!isset($contact_name))
			return;

		$request = "SELECT contact_id FROM contact WHERE contact_name LIKE '$contact_name'";
		$DBRESULT = $this->_db->query($request);
		$data = $DBRESULT->fetchRow();
		return $data["contact_id"];
	}

	protected function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined. $str\n";
			$this->return_code = 1;
			return 1;
		}
	}

	/* **************************************
	 * Delete action
	 */

	public function del($name) {
		$this->checkParameters($name);

		$request = "DELETE FROM contact WHERE contact_name LIKE '".htmlentities($name, ENT_QUOTES)."'";
		$DBRESULT =& $this->_db->query($request);
		$this->return_code = 0;
		return 0;
	}

	/* **************************************
	 * Display all contact
	 */

	public function show($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE contact_name LIKE '%".htmlentities($search, ENT_QUOTES)."%' OR contact_alias LIKE '%".htmlentities($search, ENT_QUOTES)."%' ";
		}
		$request = "SELECT contact_name, contact_alias, contact_email, contact_pager, contact_oreon, contact_admin, contact_activate FROM contact $searchStr ORDER BY contact_name";
		$DBRESULT =& $this->_db->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			print html_entity_decode($data["contact_name"], ENT_QUOTES).";".html_entity_decode($data["contact_alias"], ENT_QUOTES).";".html_entity_decode($data["contact_email"], ENT_QUOTES).";".html_entity_decode($data["contact_pager"], ENT_QUOTES).";".html_entity_decode($data["contact_oreon"], ENT_QUOTES).";".html_entity_decode($data["contact_admin"], ENT_QUOTES).";".html_entity_decode($data["contact_activate"], ENT_QUOTES)."\n";
		}
		$DBRESULT->free();
		return 0;
	}

	/* **************************************
	 * Add
	 */

	public function add($options) {

		$this->checkParameters($options);

		$info = split(";", $options);

		if (!$this->contactExists($info[0])) {
			// contact_name, contact_alias, contact_email, contact_oreon, contact_admin, contact_lang, contact_auth_type, contact_passwd
			//test;test;jmathis@merethis.com;test;1;1;en_US;local
			$convertionTable = array(
				0 => "contact_name", 1 => "contact_alias",
				2 => "contact_email", 3 => "contact_passwd",
				4 => "contact_admin", 5 => "contact_oreon",
				6 => "contact_lang", 7 => "contact_auth_type"
			);
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			$this->addContact($informations);
		} else {
			print "Contact ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}

	protected function addContact($information) {
		if (!isset($information["contact_name"])) {
			return 0;
		} else {
			if (!isset($information["contact_alias"]) || $information["contact_alias"] == "")
				$information["contact_alias"] = $information["contact_name"];
			if (!isset($information["contact_activate"]) || $information["contact_activate"] == "")
				$information["contact_activate"] = 1;
			if (!isset($information["contact_auth_type"]) || $information["contact_auth_type"] == "")
				$information["contact_auth_type"] = "local";

			$request = 	"INSERT INTO contact " .
						"(contact_name, contact_alias, contact_email, contact_oreon, contact_admin, contact_lang, contact_auth_type, contact_passwd, contact_activate) VALUES " .
						"('".htmlentities($information["contact_name"], ENT_QUOTES)."', '".htmlentities($information["contact_alias"], ENT_QUOTES)."', '".htmlentities($information["contact_email"], ENT_QUOTES)."', " .
						" '".htmlentities($information["contact_oreon"], ENT_QUOTES)."', '".htmlentities($information["contact_admin"], ENT_QUOTES)."', '".htmlentities($information["contact_lang"], ENT_QUOTES)."', " .
						" '".htmlentities($information["contact_auth_type"], ENT_QUOTES)."', '".htmlentities(md5($information["contact_passwd"]), ENT_QUOTES)."', '1')";
			$DBRESULT = $this->_db->query($request);

			$contact_id = $this->getContactID($information["contact_name"]);
			return $contact_id;
		}
	}

	/**
	 * Checks if options are valid
	 *
	 * @param array $data
	 * @return boolean
	 */
	protected function _checkNotifOptions($data = "")
	{
        if (count($data) < 3) {
            print "Invalid options.\n";
            return false;
        }
        return true;
	}

	/**
	 * Set host notification command
	 * options format : contactName;notificationCommandName
	 *
	 * @param string $options
	 * @return null|void
	 */
	protected function _setHostNotificationCommand($options = "")
	{
        $data = split(';', $options);
	    if (!($contactId = $this->contactExists($data[PARAM_NOTIF_CONTACT]))) {
            print "Contact does not exist.\n";
            return null;
        }
	    if (!($cmdId = $this->_cmd->commandExists($data[PARAM_NOTIF_COMMAND]))) {
            print "Command does not exist.\n";
            return null;
        }
        $query = "DELETE FROM contact_hostcommands_relation WHERE contact_contact_id = '".htmlentities($contactId, ENT_QUOTES)."'";
        $this->_db->query($query);
        $query = "INSERT INTO contact_hostcommands_relation (contact_contact_id, command_command_id) " .
        		"VALUES ('".htmlentities($contactId, ENT_QUOTES)."', '".htmlentities($cmdId, ENT_QUOTES)."')";
        $this->_db->query($query);
	}

	/**
	 * Set service notification command
	 *
	 * @param string $options
	 * @return null|void
	 */
	protected function _setServiceNotificationCommand($options = "")
	{
	    $data = split(';', $options);
	    if (!($contactId = $this->contactExists($data[PARAM_NOTIF_CONTACT]))) {
            print "Contact does not exist.\n";
            return null;
        }
        if (!($cmdId = $this->_cmd->commandExists($data[PARAM_NOTIF_COMMAND]))) {
            print "Command does not exist.\n";
            return null;
        }
        $query = "DELETE FROM contact_servicecommands_relation WHERE contact_contact_id = '".htmlentities($contactId, ENT_QUOTES)."'";
        $this->_db->query($query);
        $query = "INSERT INTO contact_servicecommands_relation (contact_contact_id, command_command_id) " .
        		"VALUES ('".htmlentities($contactId, ENT_QUOTES)."', '".htmlentities($cmdId, ENT_QUOTES)."')";
        $this->_db->query($query);
	}

	/**
	 * Set host notification period
	 *
	 * @param string $options
	 * @return null|void
	 */
	protected function _setHostNotificationPeriod($options = "")
	{
	    $data = split(';', $options);
	    if (!($contactId = $this->contactExists($data[PARAM_NOTIF_CONTACT]))) {
            print "Contact does not exist.\n";
            return null;
        }
        if (!($timeperiodId = $this->_timeperiod->getTimeperiodId($data[PARAM_NOTIF_PERIOD]))) {
            print "Timeperiod does not exist.\n";
            return null;
        }
        $query = "UPDATE contact SET timeperiod_tp_id = '".htmlentities($timeperiodId, ENT_QUOTES)."' WHERE contact_id = '".htmlentities($contactId, ENT_QUOTES)."'";
        $this->_db->query($query);
	}

	/**
	 * Set service notification period
	 *
	 * @param string $options
	 * @return null|void
	 */
	protected function _setServiceNotificationPeriod($options = "")
	{
	    $data = split(';', $options);
	    if (!($contactId = $this->contactExists($data[PARAM_NOTIF_CONTACT]))) {
            print "Contact does not exist.\n";
            return null;
        }
        if (!($timeperiodId = $this->_timeperiod->getTimeperiodId($data[PARAM_NOTIF_PERIOD]))) {
            print "Timeperiod does not exist.\n";
            return null;
        }
        $query = "UPDATE contact SET timeperiod_tp_id2 = '".htmlentities($timeperiodId, ENT_QUOTES)."' WHERE contact_id = '".htmlentities($contactId, ENT_QUOTES)."'";
        $this->_db->query($query);
	}

	/**
	 * Set parameter
	 *
	 * @param string $name
	 * @param array $params
	 * @return null|void
	 */
	public function setParam($options = null)
	{
	    $data = split(';', $options);
	    if (isset($data[PARAM])) {
    	    if (!$this->_checkNotifOptions($data)) {
                return null;
            }
            switch ($data[PARAM]) {
                case "hostnotifcmd":
                    $this->_setHostNotificationCommand($options);
                    break;
                case "svcnotifcmd":
                    $this->_setServiceNotificationCommand($options);
                    break;
                case "hostnotifperiod":
                    $this->_setHostNotificationPeriod($options);
                    break;
                case "svcnotifperiod":
                    $this->_setServiceNotificationPeriod($options);
                    break;
                default:
                    print "Unknown parameter type.\n"
                    break;
            }
	    }
	}
}
?>