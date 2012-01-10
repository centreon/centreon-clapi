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
 *
 */

/**
 * Include Centreon Class
 */
require_once "../../../class/centreonDB.class.php";
require_once "../../../class/centreonXML.class.php";

if (file_exists("../../../class/centreonSession.class.php")) {
	require_once "../../../class/centreonSession.class.php";
} else {
	require_once "../../../class/Session.class.php";
}

/**
 * General Centeon Management
 */
require_once "./class/centreon.Config.Poller.class.php";

/**
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
	public $optGen;
	private $return_code;
	private $relationObject;

	private $objectTable;

	public function CentreonAPI($user, $password, $action, $centreon_path, $options) {
		global $version;

		/**
		 * Set variables
		 */
		$this->debug 	= 0;
		$this->return_code = 0;

		if (isset($user)) {
			$this->login 	= htmlentities($user, ENT_QUOTES);
		}
		if (isset($password)) {
			$this->password = htmlentities($password, ENT_QUOTES);
		}
		if (isset($action)) {
			$this->action 	= htmlentities(strtoupper($action), ENT_QUOTES);
		}

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

		$this->objectTable = array();

		/**
  		 * Centreon DB Connexion
		 */
		$this->DB = new CentreonDB();
		$this->dateStart = time();

		$this->relationObject = array();
		$this->relationObject["CMD"] = "Command";
		$this->relationObject["COMMAND"] = "Command";
		$this->relationObject["HOST"] = "Host";
		$this->relationObject["SERVICE"] = "Service";

		$this->relationObject["HG"] = "HostGroup";
		$this->relationObject["HC"] = "HostCategory";

		$this->relationObject["SG"] = "ServiceGroup";
		$this->relationObject["SC"] = "ServiceCategory";

		$this->relationObject["CONTACT"] = "Contact";
		$this->relationObject["CG"] = "ContactGroup";

		/* Templates */
		$this->relationObject["HTPL"] = "Host";
		$this->relationObject["STPL"] = "Service";

		$this->relationObject["TIMEPERIOD"] = "TimePeriod";
		$this->relationObject["TP"] = "TimePeriod";

		/*
		 * Manage version
		 */
		$this->optGen = $this->getOptGen();
		$version = $this->optGen["version"];
	}

	/**
	 * Centreon Object Management
	 */
	protected function requireLibs($object) {
		if ($object != "") {
			if (isset($this->relationObject[$object]) && !class_exists("Centreon".$this->relationObject[$object])) {
				require_once "./class/centreon".$this->relationObject[$object].".class.php";
			}
			if (isset($this->relationObject[$object]) && $this->relationObject[$object] == "Host") {
				require_once "./class/centreonService.class.php";
				require_once "./class/centreonHostGroup.class.php";
				require_once "./class/centreonContact.class.php";
				require_once "./class/centreonContactGroup.class.php";
			}
			if (isset($this->relationObject[$object]) && $this->relationObject[$object] == "Service") {
				require_once "./class/centreonHost.class.php";
			}
			if (isset($this->relationObject[$object]) && $this->relationObject[$object] == "Contact") {
				require_once "./class/centreonCommand.class.php";
			}
		    if (isset($this->relationObject[$object]) && $this->relationObject[$object] == "TimePeriod") {
				require_once "./class/centreonTimePeriod.class.php";
			}
		}

		/**
		 * Default class needed
		 */
		require_once "./class/centreonTimePeriod.class.php";
		require_once "./class/centreonACLResources.class.php";
	}

	/**
	 * Get General option of Centreon
	 */
	private function getOptGen() {
		$DBRESULT =& $this->DB->query("SELECT * FROM options");
		while ($row =& $DBRESULT->fetchRow()) {
			$this->optGen[$row["key"]] = $row["value"];
		}
		$DBRESULT->free();
		unset($row);
	}

	/**
	 *
	 * Set user login
	 * @param varchar $login
	 */
	public function setLogin($login) {
		$this->login = $login;
	}

	/**
	 *
	 * Set password of the user
	 * @param varchar $password
	 */
	public function setPassword($password) {
		$this->password = trim($password);
	}

	/**
	 *
	 * check user access ...
	 * @return return bool 1 if user can login
	 */
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

		/**
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

	/**
	 *
	 * return (print) a "\n"
	 */
	public function endOfLine() {
		print "\n";
	}

	/**
	 *
	 * close the current action
	 */
	public function close() {
		print "\n";
		exit ($this->return_code);
	}

	/**
	 *
	 * Print usage for using CLAPI ...
	 */
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
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERLIST\n";
		print "\n";
		print "   For more information about configuration objects, please refer to CLAPI wiki:\n";
		print "      - http://forge.centreon.com/wiki/centreon-clapi/Use \n";
		print "\n";
		print "Notes:\n";
		print "  - Actions can be written in lowercase chars\n";
		print "  - LOGIN and PASSWORD is an admin account of Centreon\n";
		print "\n";
		$this->return_code = 0;
		exit($this->return_code);
	}

	/**
	 *
	 * Get variable passed in parameters
	 * @param varchar $str
	 */
	public function getVar($str) {
		$res = split("=", $str);
 		return $res[1];
	}

	/**
	 *
	 * Check that parameters are not empty
	 * @param varchar $str
	 */
	private function checkParameters($str) {
		if (!isset($this->options["v"]) || $this->options["v"] == "") {
			print "No options defined.\n";
			$this->return_code = 1;
			return 1;
		}
	}

	/**
	 *
	 * Init XML Flow
	 */
	public function initXML() {
		$this->xmlObj = new CentreonXML();
	}

	/**
	 *
	 * Main function : Launch action
	 */
	public function launchAction() {
		$action = strtoupper($this->action);
 		/**
 		 * Debug
 		 */
 		if ($this->debug) {
 			print "DEBUG : $action\n";
 		}

 		/**
 		 * Check method availability before using it.
 		 */
 		if ($this->object) {
			/**
			 * Require needed class
			 */
			$this->requireLibs($this->object);

			/**
			 * Check class declaration
			 */
			if (isset($this->relationObject[$this->object])) {
           		$objName = "centreon".$this->relationObject[$this->object];
			} else {
            	$objName = "";
            }
            if (!isset($this->relationObject[$this->object]) || !class_exists($objName)) {
            	print "Object not found in Centreon API.\n";
           		return 1;
            }
			$obj = new $objName($this->DB, $this->object);
			if (method_exists($obj, $action)) {
				$this->return_code = $obj->$action($this->variables);
			} else {
				print "Method not implemented into Centreon API.\n";
				return 1;
			}
		} else {
			if (method_exists($this, $action)) {
				$this->return_code = $this->$action();
				print "Return code end : ".$this->return_code;
			} else {
				print "Method not implemented into Centreon API.\n";
				$this->return_code = 1;
			}
		}
		exit($this->return_code);
	}

	/**
	 * Import Scenario file
	 */
	public function import($filename) {
		$globalReturn = 0;

		$this->fileExists($filename);

		/*
		 * Open File in order to read it.
		 */
		$handle = fopen($filename, 'r');
		if ($handle) {
			while ($string = fgets($handle)) {
				$tab = preg_split('/;/', $string);
				if (strlen(trim($string)) != 0) {
					$this->object = trim($tab[0]);
					$this->action = trim($tab[1]);
					$this->variables = trim(substr($string, strlen($tab[0].";".$tab[1].";")));
					if ($this->debug == 1) {
						print "Object : ".$this->object."\n";
						print "Action : ".$this->action."\n";
						print "VARIABLES : ".$this->variables."\n\n";
					}
					$this->launchActionForImport();
					print "RETURN : ".$this->return_code."\n";
					if ($this->return_code) {
						$globalReturn = 1;
					}
				}
			}
			fclose($handle);
		}
		return $globalReturn;
	}

	public function launchActionForImport() {
		$action = strtoupper($this->action);
 		/**
 		 * Debug
 		 */
 		if ($this->debug) {
 			print "DEBUG : $action\n";
 		}

 		/**
 		 * Check method availability before using it.
 		 */
 		if ($this->object) {
			/**
			 * Require needed class
			 */
			$this->requireLibs($this->object);

			/**
			 * Check class declaration
			 */
			if (isset($this->relationObject[$this->object])) {
           		$objName = "centreon".$this->relationObject[$this->object];
			} else {
            	$objName = "";
            }
            if (!isset($this->relationObject[$this->object]) || !class_exists($objName)) {
            	print "Object not found in Centreon API.\n";
           		return 1;
            }
			$obj = new $objName($this->DB, $this->object);
			if (method_exists($obj, $action)) {
				$this->return_code = $obj->$action($this->variables);
				print "TEST : ".$this->return_code."\n";
			} else {
				print "Method not implemented into Centreon API.\n";
				return 1;
			}
		} else {
			if (method_exists($this, $action)) {
				$this->return_code = $this->$action();
			} else {
				print "Method not implemented into Centreon API.\n";
				$this->return_code = 1;
			}
		}
	}

	/**
	 * Export All configuration
	 */
	public function export() {
		$this->initAllObjects();
		$this->objectTable['CMD']->export();
		$this->objectTable['CONTACT']->export();
		$this->objectTable['CG']->export();
		$this->objectTable['HG']->export();
		$this->objectTable['HTPL']->export();
		$this->objectTable['HOST']->export();
		$this->objectTable['STPL']->export();
		$this->objectTable['SERVICE']->export();
		$this->objectTable['SG']->export();
		$this->objectTable['SC']->export();
	}

	/**
	 *
	 * Init an object
	 * @param unknown_type $DB
	 * @param unknown_type $objname
	 */
	private function iniObject($objname) {
		$className = 'centreon'.$this->relationObject[$objname];
		$this->requireLibs($objname);
		$this->objectTable[$objname] = new $className($this->DB, $objname);
	}

	/**
	 * Init All object instance in order to export all informations
	 */
	private function initAllObjects() {
		$this->iniObject('TP');
		$this->iniObject('CMD');
		$this->iniObject('HOST');
		$this->iniObject('SERVICE');
		$this->iniObject('HG');
		$this->iniObject('HC');
		$this->iniObject('SG');
		$this->iniObject('SC');
		$this->iniObject('CONTACT');
		$this->iniObject('CG');
		$this->iniObject('HTPL');
		$this->iniObject('STPL');
	}

	/**
	 * Check if file exists
	 */
	private function fileExists($filename) {
		if (!file_exists($filename)) {
			print "$filename : File doesn't exists\n";
			exit(1);
		}
	}

	/**
	 *
	 * Print centreon version and legal use
	 */
	public function printLegals() {
		$DBRESULT =& $this->DB->query("SELECT * FROM informations WHERE `key` = 'version'");
 		$data =& $DBRESULT->fetchRow();
 		print "Centreon API version ".$data["value"]." - ";
 		print "Copyright Merethis - www.centreon.com\n";
		unset($data);
	}

	/**
	 *
	 * Print centreon version
	 */
	public function printVersion() {
		$DBRESULT =& $this->DB->query("SELECT * FROM informations WHERE `key` = 'version'");
 		$data =& $DBRESULT->fetchRow();
 		print "version ".$data["value"]."\n";
 		unset($data);
	}

	/** ******************************************************
	 *
	 * API Possibilities
	 */

	/**
	 *
	 * List all poller declared in Centreon
	 */
	public function POLLERLIST() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		$this->return_code = $poller->getPollerList($this->format);
	}

	/**
	 *
	 * Launch poller restart
	 */
	public function POLLERRESTART() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		return $poller->pollerRestart($this->variables);
	}

	/**
	 *
	 * Launch poller reload
	 */
	public function POLLERRELOAD() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		return $poller->pollerReload($this->variables);
	}

	/**
	 *
	 * Launch poller configuration files generation
	 */
	public function POLLERGENERATE() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		return $poller->pollerGenerate($this->variables, $this->login, $this->password);
	}

	/**
	 *
	 * Launch poller configuration test
	 */
	public function POLLERTEST() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		return $poller->pollerTest($this->format, $this->variables);
	}

	/**
	 *
	 * move configuration files into final directory
	 */
	public function CFGMOVE() {
		$poller = new CentreonConfigPoller($this->DB, $this->centreon_path);
		return $poller->cfgMove($this->variables);
	}

	/**
	 *
	 * Apply configuration Generation + move + restart
	 */
	public function APPLYCFG() {
		/**
		 * Display time for logs
		 */
		print date("Y-m-d H:i:s") . " - APPLYCFG\n";

		/**
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
		return $this->return_code;
	}
}
?>