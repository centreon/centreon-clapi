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

require_once "./class/centreon.Config.Poller.class.php";

/*
 * Declare Centreon API
 * 
 */
class CentreonAPI {
	public $dateStart;
	public $login;
	public $password;
	public $action; 
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
	
	public function CentreonAPI($user, $password, $action, $centreon_path, $options) {
		/*
		 * Set variables
		 */
		$this->debug 	= 0;
		$this->return_code = 0;
		$this->login 	= htmlentities($user, ENT_QUOTES);
		$this->password = htmlentities($password, ENT_QUOTES);
		$this->action 	= htmlentities($action, ENT_QUOTES);
		$this->options 	= $options;
		$this->centreon_path = $centreon_path;
		if (isset($options["v"]))
			$this->variables= $options["v"];
		else
			$this->variables= "";
				
		/*
  		 * Centreon DB Connexion
		 */ 
		$this->DB = new CentreonDB();
		$this->dateStart = time();
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
	}

	public function setFormat($format) {
		$this->format = format;
	}

	public function printHelp() {
		$this->printLegals();
		print "This software comes with ABSOLUTELY NO WARRANTY. This is free software,\n";
		print "and you are welcome to modify and redistribute it under the GPL license\n\n";
		print "usage: ./centreon -u <LOGIN> -p <PASSWORD> -a <ACTION> [-v]\n";
		print "  -v 	variables \n";
		print "  -h 	Print help \n";
		print "  -V 	Print version \n";
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
		print "       - POLLERRELOAD: Reload a poller poller id in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERRELOAD -v 1 \n";
		print "       - ADDHOST: Add an host (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHOST -v \"host:host:127.0.0.1:2:1\" \n";
		print "       - LISTHOST: List all hosts in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTHOST \n";
		print "       - DELHOST: Delete an host (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELHOST -v \"host\" \n";
		print "       - ADDHOSTGROUP: Add an hostgroup (need -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a ADDHOSTGROUP -v \"hostgroup:hostgroup\" \n";
		print "       - LISTHOSTGROUP: List all hostgroups in configuration\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a LISTHOSTGROUP \n";
		print "       - DELHOSTGROUP: Delete an hostgroup (name in -v parameters)\n";
		print "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a DELHOSTGROUP -v \"hostgroup\" \n";
		print "\n\n";
		print "Notes:\n";
		print "  - Actions can be writed in lowercase chars\n";
		print "  - LOGIN and PASSWORD is an admin account of Centreon\n";
		print "\n";
		$this->return_code = 0;
	}

	public function getVar($str) {
		$res = split("=", $str);
 		return $res[1];
	}

	public function initXML() {
		$this->xmlObj = new CentreonXML();
	}

	public function launchAction() {
		$action = strtoupper($this->action);
 		if ($this->debug)
 			print "DEBUG : $action\n";
 		if (method_exists($this, $action)) {
 			$this->$action();
 		} else {
			print "Sorry unavailable fonction.";
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

	/*
	 * Add Hosts
	 */
	public function ADDHOST() {
		require_once "./class/centreonHost.class.php";
		require_once "./class/centreonService.class.php";
		
		if (!isset($this->options["v"]) || $this->options["v"] == "") {
			print "No options. Cannot create host.\n";
			$this->eeturn_code = 1;
			return;
		}
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
	 * Add Hosts
	 */
	public function DELHOST() {
		
		require_once "./class/centreonHost.class.php";
		
		if (!isset($this->options["v"]) || $this->options["v"] == "") {
			print "No options. Cannot create host.\n";
			$this->return_code = 1;
			return;
		}
		$host = new CentreonHost($this->DB);
		$host->delHost($this->options["v"]);
	}	

	/*
	 * Add Hosts
	 */
	public function LISTHOST() {
		
		require_once "./class/centreonHost.class.php";
		
		/*
		if (!isset($this->options["v"]) || $this->options["v"] == "") {
			print "No options. Cannot create host.\n";
			$this->return_code = 1;
			return;
		}
		*/
		$host = new CentreonHost($this->DB);
		$host->listHost();
	}	
	
	public function ADDHOSTGROUP() {
		require_once "./class/centreonHostGroup.class.php";
		
		$hostgroup = new CentreonHostGroup($this->DB);
		
		$info = split(":", $this->options["v"]);
		
		if (!$hostgroup->hostGroupExists($info[0])) {
			$convertionTable = array(0 => "hg_name", 1 => "hg_alias");
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			$hostgroup->addHostGroup($informations);
		} else {
			print "Hostgroup ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}
	
	public function LISTHOSTGROUP() {
		require_once "./class/centreonHostGroup.class.php";
		
		$hostgroup = new CentreonHostGroup($this->DB);
		$hostgroup->listHostGroup();
	}
	
	public function DELHOSTGROUP() {
		require_once "./class/centreonHostGroup.class.php";
		
		if (!isset($this->options["v"]) || $this->options["v"] == "") {
			print "No options. Cannot create hostgroup.\n";
			$this->return_code = 1;
			return;
		}
		
		$hostgroup = new CentreonHostGroup($this->DB);
		$hostgroup->delHostGroup($this->options["v"]);
	}
}
?>