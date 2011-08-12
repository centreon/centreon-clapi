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

define('PARAM', 1);
define('PARAM_NOTIF_CONTACT', 0);
define('PARAM_NOTIF_COMMAND', 2);
define('PARAM_NOTIF_PERIOD', 2);

require_once "./class/centreonACLResources.class.php";

class CentreonContact {
	/**
	 *
	 * @var CentreonDB
	 */
    protected $DB;

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
		$this->DB = $db;
		$this->_cmd = new CentreonCommand($this->DB);
		$this->_timeperiod = new CentreonTimePeriod($this->DB);
	}

	/**
	 *
	 * Check host existance
	 * @param unknown_type $name
	 */
	public function contactExists($name) {
		if (!isset($name))
			return 0;

		/*
		 * Get informations
		 */
		$DBRESULT =& $this->DB->query("SELECT contact_name, contact_id FROM contact WHERE contact_name = '".htmlentities($name, ENT_QUOTES)."'");
		if ($DBRESULT->numRows() >= 1) {
			$sg = $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $sg["contact_id"];
		} else {
			return 0;
		}
	}

	/**
	 *
	 * Get contact ID
	 * @param unknown_type $contact_name
	 */
	public function getContactID($contact_name = NULL) {
		if (!isset($contact_name))
			return;

		$request = "SELECT contact_id FROM contact WHERE contact_name LIKE '$contact_name'";
		$DBRESULT = $this->DB->query($request);
		$data = $DBRESULT->fetchRow();
		return $data["contact_id"];
	}

	/**
	 *
	 * Check if contact is admin user
	 * @param unknown_type $contact_name
	 */
	public function iscontactAdmin($contact_name = NULL) {
		if (!isset($contact_name))
			return;

		$request = "SELECT contact_admin FROM contact WHERE contact_name LIKE '$contact_name'";
		$DBRESULT = $this->DB->query($request);
		$data = $DBRESULT->fetchRow();
		return $data["contact_admin"];
	}

	/**
	 *
	 * Check that parameters is ok
	 * @param unknown_type $options
	 */
	protected function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined.\n";
			$this->return_code = 1;
			return 1;
		}
	}

	/**
	 *
	 * Validate Name format
	 * @param unknown_type $name
	 */
	protected function validateName($name) {
		if (preg_match('/^[0-9a-zA-Z\_\-\ \/\\\.]*$/', $name, $matches) && strlen($name)) {
			return $this->checkNameformat($name);
		} else {
			print "Name '$name' doesn't match with Centreon naming rules.\n";
			exit (1);
		}
	}

	/**
	 *
	 * Check name lengh
	 * @param unknown_type $name
	 */
	protected function checkNameformat($name) {
		if (strlen($name) > 40) {
			print "Warning: host name reduce to 40 caracters.\n";
		}
		return sprintf("%.40s", $name);
	}

	/**
	 *
	 * Delete action
	 * @param $name
	 */
	public function del($name) {
		$this->checkParameters($name);

		$request = "DELETE FROM contact WHERE contact_name LIKE '".htmlentities($name, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return 0;
	}

	/**
	 *
	 * Display all contact
	 * @param unknown_type $search
	 */
	public function show($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE contact_name LIKE '%".htmlentities($search, ENT_QUOTES)."%' OR contact_alias LIKE '%".htmlentities($search, ENT_QUOTES)."%' ";
		}
		$request = "SELECT contact_name, contact_alias, contact_email, contact_oreon, contact_admin, contact_activate FROM contact $searchStr ORDER BY contact_name";
		$DBRESULT =& $this->DB->query($request);
		$i = 0;
		while ($data =& $DBRESULT->fetchRow()) {
			if ($i == 0) {
				print "name;alias;email;reachInterface;isAdmin;enable\n";
			}
			$i++;
			print html_entity_decode($data["contact_name"], ENT_QUOTES).";".html_entity_decode($data["contact_alias"], ENT_QUOTES).";".html_entity_decode($data["contact_email"], ENT_QUOTES).";".html_entity_decode($data["contact_oreon"], ENT_QUOTES).";".html_entity_decode($data["contact_admin"], ENT_QUOTES).";".html_entity_decode($data["contact_activate"], ENT_QUOTES)."\n";
		}
		$DBRESULT->free();
		return 0;
	}

	/**
	 *
	 * Export all contacts
	 */
	public function export() {
		$request = "SELECT contact_id, contact_name, contact_alias, contact_email, contact_passwd, contact_admin, contact_oreon, contact_lang, contact_auth_type, contact_host_notification_options, contact_service_notification_options, timeperiod_tp_id, timeperiod_tp_id2 FROM contact ORDER BY contact_name";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			print "CONTACT;ADD;".html_entity_decode($data["contact_name"], ENT_QUOTES).";".html_entity_decode($data["contact_alias"], ENT_QUOTES).";".html_entity_decode($data["contact_email"], ENT_QUOTES).";{MD5}".html_entity_decode($data["contact_passwd"], ENT_QUOTES).";".html_entity_decode($data["contact_admin"], ENT_QUOTES).";".html_entity_decode($data["contact_oreon"], ENT_QUOTES).";".html_entity_decode($data["contact_lang"], ENT_QUOTES).";".html_entity_decode($data["contact_auth_type"], ENT_QUOTES)."\n";

			if (isset($data["timeperiod_tp_id"]))
				print "CONTACT;SETPARAM;".html_entity_decode($data["contact_name"], ENT_QUOTES).";hostnotifperiod;".html_entity_decode($this->_timeperiod->getTimeperiodName($data["timeperiod_tp_id"]), ENT_QUOTES)."\n";
			if (isset($data["timeperiod_tp_id2"]))
				print "CONTACT;SETPARAM;".html_entity_decode($data["contact_name"], ENT_QUOTES).";servicenotifperiod;".html_entity_decode($this->_timeperiod->getTimeperiodName($data["timeperiod_tp_id2"]), ENT_QUOTES)."\n";
			if (isset($data["contact_host_notification_options"]))
				print "CONTACT;SETPARAM;".html_entity_decode($data["contact_name"], ENT_QUOTES).";hostnotifoptions;".html_entity_decode($data["contact_host_notification_options"], ENT_QUOTES)."\n";
			if (isset($data["contact_host_notification_options"]))
				print "CONTACT;SETPARAM;".html_entity_decode($data["contact_name"], ENT_QUOTES).";servicenotifoptions;".html_entity_decode($data["contact_host_notification_options"], ENT_QUOTES)."\n";

			/*
			 * Host Command
			 */
			$request2 = "SELECT command_command_id FROM contact_hostcommands_relation WHERE contact_contact_id = '".$data["contact_id"]."'";
			$DBRESULT2 =& $this->DB->query($request2);
			while ($dataCMD =& $DBRESULT2->fetchRow()) {
				print "CONTACT;SETPARAM;".html_entity_decode($data["contact_name"], ENT_QUOTES).";hostnotifcmd;".html_entity_decode($this->_cmd->getCommandName($dataCMD["command_command_id"], ENT_QUOTES))."\n";
			}
			$DBRESULT2->free();
			/*
			 * Service Command
			 */
			$request2 = "SELECT command_command_id FROM contact_servicecommands_relation WHERE contact_contact_id = '".$data["contact_id"]."'";
			$DBRESULT2 =& $this->DB->query($request2);
			while ($dataCMD =& $DBRESULT2->fetchRow()) {
				print "CONTACT;SETPARAM;".html_entity_decode($data["contact_name"], ENT_QUOTES).";servicenotifcmd;".html_entity_decode($this->_cmd->getCommandName($dataCMD["command_command_id"], ENT_QUOTES))."\n";
			}
			$DBRESULT2->free();

		}
		$DBRESULT->free();
		return 0;
	}

	/**
	 *
	 * Add a contact
	 * @param $options
	 */
	public function add($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$info = split(";", $options);

		$info[0] = $this->validateName($info[0]);

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

	/**
	 *
	 * Add contact in DB.
	 * @param unknown_type $information
	 */
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

			if (isset($information["contact_passwd"]) && !strncmp("{MD5}", $information["contact_passwd"], 5)) {
				$password = str_replace("{MD5}", "", $information["contact_passwd"]);
			} else if (isset($information["contact_passwd"]) && !strncmp("{SHA1}", $information["contact_passwd"], 6)) {
				$password = str_replace("{SHA1}", "", $information["contact_passwd"]);
			} else {
				$password = md5($information["contact_passwd"]);
			}

			$request = 	"INSERT INTO contact " .
						"(contact_name, contact_alias, contact_email, contact_oreon, contact_admin, contact_lang, contact_auth_type, contact_passwd, contact_activate) VALUES " .
						"('".htmlentities($information["contact_name"], ENT_QUOTES)."', '".htmlentities($information["contact_alias"], ENT_QUOTES)."', '".htmlentities($information["contact_email"], ENT_QUOTES)."', " .
						" '".htmlentities($information["contact_oreon"], ENT_QUOTES)."', '".htmlentities($information["contact_admin"], ENT_QUOTES)."', '".htmlentities($information["contact_lang"], ENT_QUOTES)."', " .
						" '".htmlentities($information["contact_auth_type"], ENT_QUOTES)."', '".$password."', '1')";
			$DBRESULT = $this->DB->query($request);

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
        $this->DB->query($query);
        $query = "INSERT INTO contact_hostcommands_relation (contact_contact_id, command_command_id) " .
        		"VALUES ('".htmlentities($contactId, ENT_QUOTES)."', '".htmlentities($cmdId, ENT_QUOTES)."')";
        $this->DB->query($query);
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
        $this->DB->query($query);
        $query = "INSERT INTO contact_servicecommands_relation (contact_contact_id, command_command_id) " .
        		"VALUES ('".htmlentities($contactId, ENT_QUOTES)."', '".htmlentities($cmdId, ENT_QUOTES)."')";
        $this->DB->query($query);
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
        $this->DB->query($query);
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
        $this->DB->query($query);
	}

	/**
	 * Set standart parameter for contact
	 *
	 * @param string $options
	 * @return null|void
	 */
	protected function _setParamCommand($options = "") {
	    $data = split(';', $options);
	    if (!($contactId = $this->contactExists($data[PARAM_NOTIF_CONTACT]))) {
            print "Contact does not exist.\n";
            return 1;
        }

        $conversionTable = array();
        $conversionTable["name"] = "contact_name";
        $conversionTable["alias"] = "contact_alias";
        $conversionTable["email"] = "contact_email";
        $conversionTable["password"] = "contact_passwd";
        $conversionTable["access"] = "contact_oreon";
        $conversionTable["language"] = "contact_language";
        $conversionTable["admin"] = "contact_admin";
        $conversionTable["authtype"] = "contact_auth_type";

        $conversionTable["hostnotifopt"] = "contact_host_notification_options";
        $conversionTable["servicenotifopt"] = "contact_service_notification_options";

        if ($data[1] == "password") {
        	$data[2] = md5($data[2]);
        }

        /*
         * Update
         */
        $query = "UPDATE contact SET ".htmlentities($conversionTable[$data[1]], ENT_QUOTES)." = '".htmlentities($data[2], ENT_QUOTES)."' WHERE contact_id = '".htmlentities($contactId, ENT_QUOTES)."'";
		print $query;
        $this->DB->query($query);
        return 0;
	}

	/**
	 * Set parameter
	 *
	 * @param string $name
	 * @param array $params
	 * @return null|void
	 */
	public function setParam($options = null) {
	   	$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

	    $data = split(';', $options);
	    if (isset($data[PARAM])) {
    	    if (!$this->_checkNotifOptions($data)) {
                return null;
            }
            switch (strtolower($data[PARAM])) {
                case "name":
                    return $this->_setParamCommand($options);
                    break;
                case "alias":
                    return $this->_setParamCommand($options);
                    break;
                case "email":
                    return $this->_setParamCommand($options);
                    break;
                case "password":
                    return $this->_setParamCommand($options);
                    break;
                case "access":
                    return $this->_setParamCommand($options);
                    break;
                case "language":
                    return $this->_setParamCommand($options);
                    break;
                case "admin":
                    return $this->_setParamCommand($options);
                    break;
                case "authtype":
                    return $this->_setParamCommand($options);
                    break;
                case "hostnotifopt":
                    return $this->_setParamCommand($options);
                    break;
                case "servicenotifopt":
                    return $this->_setParamCommand($options);
                    break;
                case "hostnotifcmd":
                     return $this->_setHostNotificationCommand($options);
                    break;
                case "svcnotifcmd":
                     return $this->_setServiceNotificationCommand($options);
                    break;
                case "hostnotifperiod":
                    return $this->_setHostNotificationPeriod($options);
                    break;
                case "svcnotifperiod":
                    return  $this->_setServiceNotificationPeriod($options);
                    break;
                default:
                    print "Unknown parameter type.\n";
                    break;
            }
	    }
	}

	/**
	 * Enable contact
	 *
	 * @param string $name
	 * @return null|void
	 */
	public function enable($options = null) {
	    $check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

	    if (!($contactId = $this->contactExists($options))) {
            print "Contact does not exist.\n";
            return 1;
        }

        /*
         * enable user
         */
        $query = "UPDATE contact SET contact_activate = '1' WHERE contact_id = '".htmlentities($contactId, ENT_QUOTES)."'";
        $this->DB->query($query);
	}

	/**
	 * Disable contact
	 *
	 * @param string $name
	 * @return null|void
	 */
	public function disable($options = null) {
	    $check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

	    if (!($contactId = $this->contactExists($options))) {
            print "Contact does not exist.\n";
            return 1;
        }

        /*
         * enable user
         */
        $query = "UPDATE contact SET contact_activate = '0' WHERE contact_id = '".htmlentities($contactId, ENT_QUOTES)."'";
        $this->DB->query($query);
	}

	/**
	 * set ACL Resource
	 *
	 * @param string options
	 * @return int
	 */
	public function setACLGroup($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		/*
		 * Split parameters
		 */
		$data = split(';', $options);

		if (!($contactId = $this->contactExists($data[0]))) {
            print "Contact does not exist.\n";
            return 1;
        }

        $acl = new CentreonACLResources($this->DB);
        $aclid = $acl->getACLResourceID($data[1]);
        if ($aclid) {
        	if (!$this->iscontactAdmin($data[0])) {
        		return $acl->addContact($contactId, $aclid);
        	} else {
        		print "Contact '".$data[0]."' is admin. This contact cannot be added to an access list.\n";
        		return 1;
        	}
        } else {
        	print "ACL Group doesn't exists.\n";
        	return 1;
        }
	}

	/**
	 * unset ACL Resource
	 *
	 * @param string options
	 * @return int
	 */
	public function unsetACLGroup($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		/*
		 * Split parameters
		 */
		$data = split(';', $options);

		if (!($contactId = $this->contactExists($data[0]))) {
            print "Contact does not exist.\n";
            return 1;
        }

        $acl = new CentreonACLResources($this->DB);
        $aclid = $acl->getACLResourceID($data[1]);
        if ($aclid) {
        	return $acl->delContact($contactId, $aclid);
        } else {
        	print "ACL Group doesn't exists.\n";
        	return 1;
        }
	}
}
?>