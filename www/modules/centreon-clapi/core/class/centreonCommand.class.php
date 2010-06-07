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
 * For more information : command@centreon.com
 *
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonHost.class.php $
 * SVN : $Id: centreonHost.class.php 25 2010-03-30 05:52:19Z jmathis $
 *
 */
 
class CentreonCommand {
	private $DB;
	
	public function __construct($DB) {
		$this->DB = $DB;
	}

	/*
	 * Check host existance
	 */
	public function commandExists($name) {
		if (!isset($name))
			return 0;
		
		/*
		 * Get informations
		 */
		$DBRESULT =& $this->DB->query("SELECT command_name, command_id FROM command WHERE command_name = '".htmlentities($name, ENT_QUOTES)."'");
		if ($DBRESULT->numRows() >= 1) {
			$sg =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $sg["command_id"];
		} else {
			return 0;
		}
	}
	
	public function getCommandID($command_name = NULL) {
		if (!isset($command_name))
			return;
			
		$request = "SELECT command_id FROM command WHERE command_name LIKE '$command_name'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["command_id"];
	}
	
	private function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined. $str\n";
			return 1;
		}
	}
	
	/* ******************************
	 * Delete
	 */
	
	public function del($name) {
		$this->checkParameters($name);
		$request = "DELETE FROM command WHERE command_name LIKE '".htmlentities($name, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return 0;
	}

	/* ******************************
	 * display all commands
	 */
	
	public function show($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE command_name LIKE '%".htmlentities($search, ENT_QUOTES)."%' ";
		}
		$request = "SELECT command_id, command_name, command_line FROM command $searchStr ORDER BY command_name";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			$data["command_line"] = str_replace("#S#", "/", $data["command_line"]);
			$data["command_line"] = str_replace("#BS#", "\\", $data["command_line"]);
			$data["command_line"] = str_replace("#BR#", "\n", $data["command_line"]);
			$data["command_line"] = str_replace("#R#", "\t", $data["command_line"]);
			print html_entity_decode($data["command_id"], ENT_QUOTES).";".html_entity_decode($data["command_name"], ENT_QUOTES).";".html_entity_decode($data["command_line"], ENT_QUOTES)."\n";
		}
		$DBRESULT->free();
		return 0;
	}

	/* ******************************
	 * add a command
	 */
	
	public function add($options) {
		
		$this->checkParameters($options);
		
		$info = split(";", $options);
		
		if (!$this->commandExists($info[0])) {
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
			$this->addCommand($informations);
		} else {
			print "Command ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}
	
	public function addCommand($information) {
		if (!isset($information["command_name"])) {
			return 0;
		} else {
			
			$request = 	"INSERT INTO command " .
						"(command_name, command_line, command_type) VALUES " .
						"('".htmlentities($information["command_name"], ENT_QUOTES)."', '".htmlentities($information["command_line"], ENT_QUOTES)."'" .
						", '".htmlentities($information["command_type"], ENT_QUOTES)."')";
			$DBRESULT =& $this->DB->query($request);	
			$command_id = $this->getCommandID($information["command_name"]);
			return $command_id;
		}
	}

	public function setParamCmd() {
		
	}
}
?>