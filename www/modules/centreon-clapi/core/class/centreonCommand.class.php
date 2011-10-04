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

/**
 *
 * Centreon Command Class
 * @author jmathis
 *
 */
class CentreonCommand {
	private $DB;
	private $maxLen;
	private $type;
	private $params;
	private $graphTemplates;
	private $version;
	private $debug;

	/**
	 *
	 * Centreon command Constructor
	 * @param unknown_type $DB
	 */
	public function __construct($DB) {
		$this->DB = $DB;
		$this->maxLen = 50;
		$this->type = array("notif" => 1, "check" => 2, "misc" => 3, 1 => "notif", 2 => "check", 3 => "misc");
		$this->params = array("name" => 1, "line" => 1, "example" => 1, "type" => 1, "template" => 1);
		$this->graphTemplates = array('id' => array(0 => "", NULL => ""), 'name' => array(0 => "", NULL => ""));
		$this->version = $this->getVersion();
		$this->debug = 0;
	}

	/**
	 *
	 * Get Version of Centreon
	 */
	protected function getVersion() {
		$request = "SELECT * FROM informations";
		$DBRESULT = $this->DB->query($request);
		$info = $DBRESULT->fetchRow();
		return $info["value"];
	}

	/**
	 * Check command existance
	 */
	public function commandExists($name) {
		if (!isset($name))
			return 0;

		/**
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

	/**
	 *
	 * Decode a specific string
	 * @param unknown_type $str
	 */
	protected function decode($str) {
		if (!strncmp($this->version, "2.1", 3)) {
			$str = str_replace("#S#", "/", $str);
			$str = str_replace("#BS#", "\\", $str);
			//$str = str_replace("#BR#", "\n", $str);
			$str = str_replace("#R#", "\t", $str);
		}
		return $str;
	}

	/**
	 *
	 * Encode a specific string
	 * @param unknown_type $name
	 */
	protected function encode($name) {
		if (!strncmp($this->version, "2.1", 3)) {
			$name = str_replace("$", "\$", $name);
			$name = str_replace("/", "#S#", htmlentities($name, ENT_QUOTES));
			$name = str_replace("\\", "#BS#", $name);
			//$name = str_replace("\n", "#BR#", $name);
			$name = str_replace("\t", "#R#", $name);
		}
		return $name;
	}

	public function getCommandID($command_name = NULL) {
		if (!isset($command_name))
			return 0;

		$request = "SELECT command_id FROM command WHERE command_name LIKE '$command_name'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["command_id"];
	}

	public function getCommandName($command_id = NULL) {
		if (!isset($command_id))
			return 0;

		$request = "SELECT command_name FROM command WHERE command_id = '$command_id'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["command_name"];
	}

	private function checkParameters($options) {
		if (!isset($options) || (isset($options) && $options == "")) {
			print "No options defined.\n";
			return 1;
		}
	}

	private function validateName($name) {
		if (preg_match('/^[0-9a-zA-Z\_\-\ \/\\\.]*$/', $name, $matches)) {
			return $this->checkNameformat($name);
		} else {
			print "Name '$name' doesn't match with Centreon naming rules.\n";
			exit (1);
		}
	}

	private function checkNameformat($name) {
		if (strlen($name) > $this->maxLen) {
			print "Warning: host name reduce to ".$this->maxLen." caracters.\n";
		}
		return sprintf("%.".$this->maxLen."s", $name);
	}

	private function setDefaultType($information) {
		if (!isset($information["command_type"]) || $information["command_type"] == "") {
			$information["command_type"] = 2;
		}
		return $information;
	}

	/** *****************************************
	 * Delete
	 */
	public function del($name) {

		$check = $this->checkParameters($name);
		if ($check) {
			return $check;
		}

		$request = "DELETE FROM command WHERE command_name LIKE '".htmlentities($name, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return 0;
	}

	/** *****************************************
	 * display all commands
	 */
	public function show($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE command_name LIKE '%".htmlentities($search, ENT_QUOTES)."%' ";
		}

		$request = "SELECT command_id, command_name, command_type, command_line FROM command $searchStr ORDER BY command_name";
		$DBRESULT =& $this->DB->query($request);
		$i = 0;
		while ($data =& $DBRESULT->fetchRow()) {
			if ($i == 0) {
				print "id;name;type;line\n";
			}
			print html_entity_decode($data["command_id"], ENT_QUOTES).";".html_entity_decode($this->decode($data["command_name"]), ENT_QUOTES).";".$this->type[html_entity_decode($data["command_type"], ENT_QUOTES)].";".html_entity_decode($this->decode($data["command_line"]), ENT_QUOTES)."\n";
			$i++;
		}
		$DBRESULT->free();
		return 0;
	}

	/** *****************************************
	 * exoirt all commands
	 */
	public function export() {

		$this->getTemplateGraph();

		$request = "SELECT command_name, command_type, command_line, command_example, graph_id FROM command ORDER BY command_name";
		$DBRESULT =& $this->DB->query($request);
		$i = 0;
		while ($data =& $DBRESULT->fetchRow()) {
			print "CMD;ADD;".html_entity_decode($this->decode($data["command_name"]), ENT_QUOTES).";".$this->type[html_entity_decode($data["command_type"], ENT_QUOTES)].";".html_entity_decode($this->decode($data["command_line"]), ENT_QUOTES).";".html_entity_decode($this->decode($data["command_example"]), ENT_QUOTES).";".html_entity_decode($this->decode($this->graphTemplates['id'][$data["graph_id"]]), ENT_QUOTES)."\n";
			$i++;
		}
		$DBRESULT->free();
		return 0;
	}

	/**
	 *
	 * Get the full list of graph templates
	 */
	private function getTemplateGraph() {
		$request = "SELECT name, graph_id FROM giv_graphs_template";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			$this->graphTemplates["id"][$data["graph_id"]] = $data['name'];
			$this->graphTemplates["name"][$data["name"]] = $data['graph_id'];
		}
		$DBRESULT->free();
		return 0;
	}

	/** ******************************
	 * add a command
	 */
	public function add($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$info = split(";", $options);
		$info[0] = $this->validateName($info[0]);

		if ($this->debug) {
			print "INFO : ".$options."\n";
		}

		$this->getTemplateGraph();

		if (!$this->commandExists($info[0])) {

			$convertionTable = array(0 => "command_name", 1 => "command_type", 2 => "command_line", 3 => "command_example", 4 => "graph_template");
			$informations = array();
			foreach ($info as $key => $value) {
				if ($this->debug) {
					print "VALUES : ".$key. "=>" . $value . "\n";
				}

				if ($key != 1) {
					$informations[$convertionTable[$key]] = $value;
				} else {
					$informations[$convertionTable[$key]] = $this->type[$value];
				}
			}
			$this->addCommand($informations);
		} else {
			print "Command ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	}

	/**
	 *
	 * Add a command
	 * @param unknown_type $information
	 */
	private function addCommand($information) {
		if (!isset($information["command_name"])) {
			return 0;
		} else {
			$information = $this->setDefaultType($information);

			if (count($information) == 3) {
				$information["command_name"] = $this->encode($information["command_name"]);
				$information["command_line"] = $this->encode($information["command_line"]);

				$request = 	"INSERT INTO command " .
							"(command_name, command_line, command_type) VALUES " .
							"('".htmlentities($information["command_name"], ENT_QUOTES)."', '".$information["command_line"]."'" .
							", '".htmlentities($information["command_type"], ENT_QUOTES)."')";

				$DBRESULT =& $this->DB->query($request);
				$command_id = $this->getCommandID($information["command_name"]);
			} else if (count($information) == 5) {
				$information["command_name"] = $this->encode($information["command_name"]);
				$information["command_line"] = $this->encode($information["command_line"]);
				$information["command_example"] = $this->encode($information["command_example"]);
				$information["graph_id"] = $this->encode($information["graph_template"]);

				$request = 	"INSERT INTO command " .
							"(command_name, command_line, command_type, command_example, graph_id) VALUES " .
							"('".htmlentities($information["command_name"], ENT_QUOTES)."', '".$information["command_line"]."'" .
							", '".htmlentities($information["command_type"], ENT_QUOTES)."', '".htmlentities($information["command_example"], ENT_QUOTES)."'" .
							", '".htmlentities($this->graphTemplates['name'][$information["graph_template"]], ENT_QUOTES)."' )";
				$DBRESULT =& $this->DB->query($request);
				$command_id = $this->getCommandID($information["command_name"]);
			}
			return $command_id;
		}
	}

	/** ****************************************
	 * Set parameters
	 */
	public function setParam($options) {

		$check = $this->checkParameters($options);
		if ($check) {
			return $check;
		}

		$info = split(";", $options);
		if ($this->commandExists($info[0])) {
			if ($info[1] != "template" && $info[1] != "type") {
				$request = "UPDATE command SET command_".$info[1]." = '".$info[2]."' WHERE command_name LIKE '".$info[0]."'";
				$DBRESULT =& $this->DB->query($request);
				return 0;
			} else if ($info[1] == "type") {
				$request = "UPDATE command SET command_".$info[1]." = '".$this->type[$info[2]]."' WHERE command_name LIKE '".$info[0]."'";
				$DBRESULT =& $this->DB->query($request);
				return 0;
			} else {
				$request = "UPDATE command SET graph_id = (SELECT graph_id FROM giv_graphs_template WHERE name LIKE '".htmlentities($info[2], ENT_QUOTES)."') WHERE command_name = '".htmlentities($info[0], ENT_QUOTES)."'";
				$DBRESULT =& $this->DB->query($request);
				return 0;
			}
		} else {
			print "Command '".$info[0]."' doesn't exists.\n";
			return 1;
		}
	}
}
?>