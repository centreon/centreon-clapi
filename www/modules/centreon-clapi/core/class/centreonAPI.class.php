<?php
/*
 * Copyright 2005-2009 MERETHIS
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
 *
 */

/*
 * Include Centreon Class
 */
require_once "../../../class/centreonDB.class.php";
require_once "../../../class/centreonXML.class.php";
if (file_exists("../../../class/centreonSession.class.php"))
	require_once "../../../class/centreonSession.class.php";
else
	require_once "../../../class/Session.class.php";
/*
 * General Centeon Management
 */
require_once "./class/centreon.Config.Poller.class.php";

/*
 * Centreon Object Management
 */
require_once "./class/centreonCommand.class.php";
require_once "./class/centreonContact.class.php";
require_once "./class/centreonContactGroup.class.php";
require_once "./class/centreonHost.class.php";
require_once "./class/centreonHostGroup.class.php";
require_once "./class/centreonService.class.php";
require_once "./class/centreonServiceGroup.class.php";

/*
 * Declare Centreon API
 * 
 */
class CentreonAPI {
	public $dateStart;
	public $login;
	public $password;
	public $action;
	public $object; 
	public $options; 
	public $args;
	public $DB;
	public $DBC;
	public $DBN;
	public $format;
	public $xmlObj;
	public $debug;
	public $variables;
	public $centreon_path;
	private $return_code;
	private $relationObject;
	
	public function CentreonAPI($user, $password, $action, $centreon_path, $options) {
		/*
		 * Set variables
		 */
		$this->debug 	= 0;
		$this->return_code = 0;
		$this->login 	= htmlentities($user, ENT_QUOTES);
		$this->password = htmlentities($password, ENT_QUOTES);
		$this->action 	= htmlentities(strtoupper($action), ENT_QUOTES);
		$this->options 	= $options;
		$this->centreon_path = $centreon_path;
		
		if (isset($options["v"])) {
			$this->variables = $options["v"];
		} else {
			$this->variables = "";
		}
		if (isset($options["o"])) {
			$this->object =  htmlentities(strtoupper($options["o"]), ENT_QUOTES);
		} else {
			$this->object = "";
		}				
		
		/*
  		 * Centreon DB Connexion
		 */ 
		$this->DB = new CentreonDB();
		$this->dateStart = time();
	
		$this->relationObject = array();
		$this->relationObject["CMD"] = "Command";
		$this->relationObject["HOST"] = "Host";
		$this->relationObject["SERVICE"] = "Service";
		$this->relationObject["HG"] = "HostGroup";
		$this->relationObject["SG"] = "ServiceGroup";
		$this->relationObject["CONTACT"] = "Contact";
		$this->relationObject["CG"] = "ContactGroup";
	}

	public function setLogin($login) {
		$this->login = $login;
	}

	public function setPassword($password) {
		$this->password = $password;
	}
	
	public function checkUser() {
		if (!isset($this->login) || $this->login == "") {
			print "ERROR: Can not connect to centreon without login.\n";
			$this->printHelp();
			exit();
		}
		if (!isset($this->password) || $this->password == "") {
			print "ERROR: Can not connect to centreon without password.";
			$this->printHelp();
		}
		
		/*
		 * Check Login / Password
		 */
		$DBRESULT =& $this->DB->query("SELECT contact_id FROM contact WHERE contact_alias = '".$this->login."' AND contact_passwd = MD5('".$this->password."') AND contact_activate = '1' AND contact_oreon = '1'");
		if ($DBRESULT->numRows()) {
			return 1;
		} else {
			print "Can not found user '".$this->login."'. User cannot connect\n";
			exit(1);
		}
	}
	
	public function endOfLine() {
		print "\n";
	}
	
	public function close() {
		print "\n";
		exit ($this->exitcode);
	}

	public function setFormat($format) {
		$this->format = format;
	}

	public function printHelp() {
		$this->printLegals();
		print "This software comes with ABSOLUTELY NO WARRANTY. This is free software,\n";
		print "and you are welcome to modify and redistribute it under the GPL license\n\n";
		print "usage: ./centreon -u <LOGIN> -p <PASSWORD> -o <OBJECT> -a <ACTION> [-v]\n";
		print "  -v 	variables \n";
		print "  -h 	Print help \n";
		print "  -V 	Print version \n";
		print "  -o 	Object type \n";
		print "  -a 	Launch action on Centreon\n";
		print "     Actions are the followings :\n";
		print "       - POLLERGENERATE: Build nagios configuration for a poller (poller id in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERGENERATE -v 1 \n";
		print "       - POLLERTEST: Test nagios configuration for a poller (poller id in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERTEST -v 1 \n";
		print "       - CFGMOVE: move nagios configuration for a poller to final directory (poller id in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a CFGMOVE -v 1 \n";
		print "       - POLLERRESTART: Restart a poller (poller id in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERRESTART -v 1 \n";
		print "       - POLLERRELOAD: Reload a poller (poller id in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERRELOAD -v 1 \n";
		print "       - POLLERLIST: list all pollers\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERRELOAD -v 1 \n";
		print "       - ADDHOST: Add an host (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHOST -v \"host;host;127.0.0.1;2;1\" \n";
		print "       - LISTHOST: List all hosts in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTHOST \n";
		print "       - DELHOST: Delete an host (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELHOST -v \"host\" \n";
		print "       - SETHMACRO: Add an host macro (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHMACRO -v \"host;macroname;macrovalue\" \n";
		print "       - DELHMACRO: Delete an host macro (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHMACRO -v \"host;macroname\" \n";
		print "       - ADDHTPL: Add an host template (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHOST -v \"host;host;127.0.0.1;2;1\" \n";
		print "       - LISTHTPL: List all host templates in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTHOST \n";
		print "       - DELHTPL: Delete an host template (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELHOST -v \"host\" \n";
		print "       - SETHTPLMACRO: Add an host template macro (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHMACRO -v \"host;macroname;macrovalue\" \n";
		print "       - DELHTPLMACRO: Delete an host template macro (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHMACRO -v \"host;macroname\" \n";
		print "       - ADDHG: Add an hostgroup (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHOSTGROUP -v \"name;alias\" \n";
		print "       - LISTHG: List all hostgroups in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTHOSTGROUP \n";
		print "       - DELHG: Delete an hostgroup (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELHOSTGROUP -v \"hostgroup_name\" \n";
		print "       - ADDSG: Add a servicegroup (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDSG -v \"name;alias\" \n";
		print "       - LISTSG: List all servicegroups in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTSG \n";
		print "       - DELSG: Delete a servicegroup (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELSG -v \"servicegroup_name\" \n";
		print "       - ADDCCT: Add a contact (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDCCT -v \"name;alias\" \n";
		print "       - LISTCCT: List all contacts in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTCCT \n";
		print "       - DELCCT: Delete a contact (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELCCT -v \"contact_name\" \n";
		print "       - ADDCG: Add a contactgroup (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDCG -v \"name;alias\" \n";
		print "       - LISTCG: List all contactgroups in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTCG \n";
		print "       - DELCG: Delete a contactgroup (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELCG -v \"contactgroup_name\" \n";
		print "       - ADDCMD: Add a command (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDCMD -v \"name;alias\" \n";
		print "       - LISTCMD: List all commands in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTCMD \n";
		print "       - DELCMD: Delete a command (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELCMD -v \"command_name\" \n";
		print "\n";
		print "Notes:\n";
		print "  - Actions can be written in lowercase chars\n";
		print "  - LOGIN and PASSWORD is an admin account of Centreon\n";
		print "\n";
		$this->return_code = 0;
		exit($this->return_code);
	}

	public function getVar($str) {
		$res = split("=", $str);
 		return $res[1];
	}

	private function checkParameters($str) {
		if (!isset($this->options["v"]) || $this->options["v"] == "") {
			print "No options defined. $str\n";
			$this->return_code = 1;
			return 1;
		}
	}

	public function initXML() {
		$this->xmlObj = new CentreonXML();
	}

	/*
	 * Main function : Launch action 
	 */
	public function launchAction() {
		$action = strtoupper($this->action);
 		/*
 		 * Debug
 		 */
 		if ($this->debug) {
 			print "DEBUG : $action\n";
 		}
 		 		
 		/* 
 		 * Check method availability before using it.
 		 */
 		if ($this->object) {
			$objName = "Centreon".$this->relationObject[$this->object];
			$obj = new $objName($this->DB);
			if (method_exists($obj, $action)) {
				$obj->$action($this->options["v"]);			
			} else {
				print "Method not implemented into Centreon API.\n";
				return 1;	
			}
		} else {
			if (method_exists($this, $action)) {
				$this->$action();			
			} else {
				print "Method not implemented into Centreon API.\n";
			}
		}
		exit($this->return_code);
	}

	public function printLegals() {
		$DBRESULT =& $this->DB->query("SELECT * FROM informations WHERE `key` = 'version'");
 		$data =& $DBRESULT->fetchRow();
 		print "Centreon API version ".$data["value"]." - ";
 		print "Copyright Merethis - www.centreon.com\n";
		unset($data);
	}

	public function printVersion() {
		$DBRESULT =& $this->DB->query("SELECT * FROM informations WHERE `key` = 'version'");
 		$data =& $DBRESULT->fetchRow();
 		print "version ".$data["value"]."\n";
 		unset($data);
	}

	/*
	 * API Possibilities
	 */

	/*
	 * List all poller declared in Centreon
	 */
	public function POLLERLIST() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		$this->return_code = $poller->getPollerList($this->format);
	}

	/*
	 * Launch poller restart
	 */
	public function POLLERRESTART() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		$this->return_code = $poller->pollerRestart($this->variables);
	}
	
	/*
	 * Launch poller reload
	 */
	public function POLLERRELOAD() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		$this->return_code = $poller->pollerReload($this->variables);
	}

	/*
	 * Launch poller configuration files generation
	 */
	public function POLLERGENERATE() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		$this->return_code = $poller->pollerGenerate($this->variables, $this->login, $this->password);
	}

	/*
	 * Launch poller configuration test
	 */	
	public function POLLERTEST() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		$this->return_code = $poller->pollerTest($this->format, $this->variables);
	}
	
	/*
	 * move configuration files into final directory
	 */	
	public function CFGMOVE() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		$this->return_code = $poller->cfgMove($this->variables);
	}

	/*
	 * Apply configuration Generation + move + restart 
	 */
	public function APPLYCFG() {
		/*
		 * Display time for logs
		 */
		print date("Y-m-d H:i:s") . " - APPLYCFG\n";
		
		/*
		 * Launch Actions
		 */
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		$this->return_code = $poller->pollerGenerate($this->variables, $this->login, $this->password);
		$this->endOfLine();
		if ($this->return_code == 0) {
			$this->return_code = $poller->pollerTest($this->format, $this->variables);
			$this->endOfLine();
		}
		if ($this->return_code == 0) {
			$this->return_code = $poller->cfgMove($this->variables);
			$this->endOfLine();
		}
		if ($this->return_code == 0) {
			$this->return_code = $poller->pollerRestart($this->variables);
		}	
	}

	/* ********************************************************************
	 * Host Functions
	 */

	/*
	 * Add Hosts
	 */
	public function ADDHOST() {
		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";
		
		$this->checkParameters("Cannot create host.");
		
		$host = new CentreonHost($this->DB);
		$svc = new CentreonService($this->DB);
		$info = split(":", $this->options["v"]);
		if (!$host->hostExists($info[0])) {
			$convertionTable = array(0 => "host_name", 1 => "host_alias", 2 => "host_address", 3 => "host_template", 4 => "host_poller", 5 => "hostgroup");
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			$host_id = $host->addHost($informations);
			$host->deployServiceTemplates($host_id, $svc);
		} else {
			print "Host ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}

	/*
	 * Delete Hosts
	 */
	public function DELHOST() {
		require_once "./class/centreonHost.class.php";
		
		$this->checkParameters("Cannot delete host.");
		
		$host = new CentreonHost($this->DB);
		$host->delHost($this->options["v"]);
	}	

	/*
	 * Add Hosts
	 */
	public function LISTHOST() {
		
		require_once "./class/centreonHost.class.php";
		
		$host = new CentreonHost($this->DB);
		$host->listHost($this->options["v"]);
	}	
	
	/*
	 * Set host Macro
	 */
	public function SETMACROHOST() {
		require_once "./class/centreonHost.class.php";

		$host = new CentreonHost($this->DB);
		$info = split(";", $this->options["v"]);
		$host->setMacroHost($info[0], $info[1], $info[2]);
	}
	
	public function DELHMACRO() {
		require_once "./class/centreonHost.class.php";

		$host = new CentreonHost($this->DB);
		$info = split(";", $this->options["v"]);
		$host->delMacroHost($info[0], $info[1]);
	}

	public function SETPARAMHOST() {
		require_once "./class/centreonHost.class.php";
		
		$this->checkParameters("Cannot set parameter for host.");
		
		$host = new CentreonHost($this->DB);
		$elem = split(";", $this->options["v"]);
		$exitcode = $host->setParameterHost($elem[0], $elem[1], $elem[2]);
		return $exitcode;
	}
	
	/*
	 * Set Parents
	 */
	public function SETPARENTHOST() {
		require_once "./class/centreonHost.class.php";
		
		$this->checkParameters("Cannot set parents for host.");
		
		$host = new CentreonHost($this->DB);
		
		$elem = split(";", $this->options["v"]);
		if (strstr($elem[1], ",")) {
			$elem2 = split(",", $elem[1]);
			foreach ($elem2 as $value) {
				$exitcode = $host->setParent($elem[0], $value);
				if ($exitcode != 0) {
					return $exitcode;
				}
			}			
		} else {
			$exitcode = $host->setParentHost($elem[0], $elem[1]);		
		}
		return $exitcode;
	}

	
	/*
	 * Host Template function
	 */
	/*
	 * Add Host template
	 */
	public function ADDHTPL() {
		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";
		
		$this->checkParameters("Cannot create host.");
		
		$host = new CentreonHost($this->DB);
		$host->setTemplateFlag();
		
		$svc = new CentreonService($this->DB);
		$info = split(":", $this->options["v"]);
		if (!$host->hostExists($info[0])) {
			$convertionTable = array(0 => "host_name", 1 => "host_alias", 2 => "host_address", 3 => "host_template", 4 => "host_poller", 5 => "hostgroup");
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			$host_id = $host->addHost($informations);
			$host->deployServiceTemplates($host_id, $svc);
		} else {
			print "Host ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}

	/*
	 * Delete Hosts
	 */
	public function DELHTPL() {
		require_once "./class/centreonHost.class.php";
		
		$this->checkParameters("Cannot delete host.");
		
		$host = new CentreonHost($this->DB);
		$host->setTemplateFlag();
		$host->delHost($this->options["v"]);
	}	

	/*
	 * Add Hosts
	 */
	public function LISTHTPL() {
		
		require_once "./class/centreonHost.class.php";
		
		$host = new CentreonHost($this->DB);
		$host->setTemplateFlag();
		$host->listHost();
	}	
	
	/*
	 * Set host Macro
	 */
	public function SETMACROHTPL() {
		require_once "./class/centreonHost.class.php";

		$host = new CentreonHost($this->DB);
		$host->setTemplateFlag();
		$info = split(";", $this->options["v"]);
		$host->setMacro($info[0], $info[1], $info[2]);
	}
	
	public function DELMACROHTPL() {
		require_once "./class/centreonHost.class.php";

		$host = new CentreonHost($this->DB);
		$host->setTemplateFlag();
		$info = split(";", $this->options["v"]);
		$host->delMacro($info[0], $info[1]);
	}
		
	/* ***********************************************************
	 * Service group Launch Function
	 * 
	 */

	
	
	/* ***********************************************************
	 * Contact Launch Function
	 * 
	 */
	 
	public function ADDCCT() {
		require_once "./class/centreonContact.class.php";
	
		$contact = new CentreonContact($this->DB);
		
		$info = split(";", $this->options["v"]);
		
		if (!$contact->contactExists($info[0])) {
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
			$contact->addContact($informations);
		} else {
			print "Contact ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	
	}
	
	/*
	 * List all contact in Centreon configuration
	 */
	public function LISTCCT() {
		require_once "./class/centreonContact.class.php";
	
		$search = "";
		if (isset($this->options["v"]) && $this->options["v"] != "") {
			$search = $this->options["v"];
		}
	
		$contact = new CentreonContact($this->DB);
		$contact->listContact($search);
	}
	
	/*
	 * Delete a contact in centreon configuration panel
	 */
	public function DELCCT() {
		require_once "./class/centreonContact.class.php";
	
		$this->checkParameters("Cannot create contact.");
		
		$contact = new CentreonContact($this->DB);
		$exitcode = $contact->delContact($this->options["v"]);
		return $exitcode;
	}
	
	/* ***********************************************************
	 * Contact groups functions
	 */
	 
	/*
	 * Add a contactgroup 
	 */
	public function ADDCG() {
		require_once "./class/centreonContactGroup.class.php";
		
		$contactgroup = new CentreonContactGroup($this->DB);
		
		$info = split(";", $this->options["v"]);
		
		if (!$contactgroup->contactGroupExists($info[0])) {
			$convertionTable = array(0 => "cg_name", 1 => "cg_alias");
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			$contactgroup->addContactGroup($informations);
		} else {
			print "Contactgroup ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}
	
	/*
	 * List all contactgroup
	 */
	public function LISTCG() {
		require_once "./class/centreonContactGroup.class.php";
		
		$search = "";
		if (isset($this->options["v"]) && $this->options["v"] != "") {
			$search = $this->options["v"];
		}
		
		$contactgroup = new CentreonContactGroup($this->DB);
		$contactgroup->listContactGroup($search);
	}
	
	/*
	 * Del a contactgroup
	 */
	public function DELCG() {
		require_once "./class/centreonContactGroup.class.php";
		
		$this->checkParameters("Cannot create contactgroup.");
		
		$contactgroup = new CentreonContactGroup($this->DB);
		$exitcode = $contactgroup->delContactGroup($this->options["v"]);
		return $exitcode;
	}
	
	/* ***********************************************************
	 * Commands functions
	 */
	 
	/*
	 * Add a command
	 */
	public function ADDCMD() {
		require_once "./class/centreonCommand.class.php";
		
		$command = new CentreonCommand($this->DB);
		
		$info = split(";", $this->options["v"]);
		
		if (!$command->commandExists($info[0])) {
			$type = array("notif" => 1, "check" => 2, "misc" => 3);
			$convertionTable = array(0 => "command_name", 1 => "command_line", 2 => "command_type");
			$informations = array();
			foreach ($info as $key => $value) {
				if ($key != 2) {
					$informations[$convertionTable[$key]] = $value;
				} else {
					$informations[$convertionTable[$key]] = $type[$value];
				}
			}
			$command->addCommand($informations);
		} else {
			print "Command ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}
	
	/*
	 * List all contactgroup
	 */
	public function LISTCMD() {
		require_once "./class/centreonCommand.class.php";
		
		$search = "";
		if (isset($this->options["v"]) && $this->options["v"] != "") {
			$search = $this->options["v"];
		}
		
		$command = new CentreonCommand($this->DB);
		$command->listCommand($search);
	}
	
	/*
	 * Del a contactgroup
	 */
	public function DELCMD() {
		require_once "./class/centreonCommand.class.php";
		
		$this->checkParameters("Cannot create command.");
		
		$command = new CentreonCommand($this->DB);
		$exitcode = $command->delCommand($this->options["v"]);
		return $exitcode;
	}
}
?>