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
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonContact.class.php $
 * SVN : $Id: centreonContact.class.php 25 2010-03-30 05:52:19Z jmathis $
 *
 */
 
class CentreonContactGroup {
	private $DB;
	private $nameLen;
	
	public function __construct($DB) {
		$this->DB = $DB;
		$this->nameLen = 40;
	}

	/*
	 * Check contact existance
	 */
	public function contactGroupExists($name) {
		if (!isset($name))
			return 0;
		
		/*
		 * Get informations
		 */
		$DBRESULT =& $this->DB->query("SELECT cg_name, cg_id FROM contactgroup WHERE cg_name = '".htmlentities($name, ENT_QUOTES)."'");
		if ($DBRESULT->numRows() >= 1) {
			$contact =& $DBRESULT->fetchRow();
			$DBRESULT->free();
			return $contact["cg_id"];
		} else {
			return 0;
		}
	}
	
	public function getContactGroupID($cg_name = NULL) {
		if (!isset($cg_name))
			return;
			
		$request = "SELECT cg_id FROM contactgroup WHERE cg_name LIKE '$cg_name'";
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["cg_id"];
	}
	
	private function checkParameters($options) {
		if (!isset($options) || $options == "") {
			print "No options defined. $str\n";
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
		if (strlen($name) > $this->nameLen) {
			print "Warning: contact group name reduce to 40 caracters.\n";
		}
		return sprintf("%.".$this->nameLen."s", $name);
	}
	
	private function checkRequestStatus() {
		if (PEAR::isError($this->privatePearDB)) {
			return 1;
		} else {
			return 0;
		}
	}
	
	/* *********************************************
	 * delete contact group functions
	 */
	
	public function delContactGroup($name) {
		$request = "DELETE FROM contactgroup WHERE cg_name LIKE '".htmlentities($name, ENT_QUOTES)."'";
		$DBRESULT =& $this->DB->query($request);
		$this->return_code = 0;
		return;
	}

	/* *********************************************
	 * show contacts
	 */
	public function show($search = NULL) {
		$searchStr = "";
		if (isset($search) && $search != "") {
			$searchStr = " WHERE cg_name LIKE '%".htmlentities($search, ENT_QUOTES)."%' OR cg_alias LIKE '%".htmlentities($search, ENT_QUOTES)."%' ";
		}
		$request = "SELECT cg_name, cg_alias FROM contactgroup $searchStr ORDER BY cg_name";
		$DBRESULT =& $this->DB->query($request);
		while ($data =& $DBRESULT->fetchRow()) {
			print html_entity_decode($data["cg_name"], ENT_QUOTES).";".html_entity_decode($data["cg_alias"], ENT_QUOTES)."\n";
		}
		$DBRESULT->free();
		
	}
	
	/* *********************************************
	 * Add functions
	 */
	public function add($options) {
		
		$info = split(";", $options);
		
		$info[0] = $this->validateName($info[0]);
		if (!$this->contactGroupExists($info[0])) {
			$convertionTable = array(0 => "cg_name", 1 => "cg_alias");
			$informations = array();
			foreach ($info as $key => $value) {
				$informations[$convertionTable[$key]] = $value;
			}
			$this->addContactGroup($informations);
		} else {
			print "Contactgroup ".$info[0]." already exists.\n";
			$this->return_code = 1;
			return;
		}
	} 
	
	public function addContactGroup($information) {
		if (!isset($information["cg_name"])) {
			return 0;
		} else {
			if (!isset($information["cg_alias"]) || $information["cg_alias"] == "")
				$information["cg_alias"] = $information["cg_name"];
			
			$request = "INSERT INTO contactgroup (cg_name, cg_alias, cg_activate) VALUES ('".htmlentities($information["cg_name"], ENT_QUOTES)."', '".htmlentities($information["cg_alias"], ENT_QUOTES)."', '1')";
			$DBRESULT =& $this->DB->query($request);
	
			$cg_id = $this->getContactGroupID($information["cg_name"]);
			return $cg_id;
		}
	}
	
	/* ***************************************
	 * Set contact child 
	 */
	public function setChild($options) {
		
		$info = split(";", $options);

		$contact = new CentreonContact($this->DB);
		
		$contact_id = $contact->getContactID($info[1]);
		$cg_id = $this->getContactGroupID($info[0]);
		
		$request = "SELECT * FROM contactgroup_contact_relation WHERE contact_contact_id = '".$contact_id."' AND contactgroup_cg_id = '".$cg_id."'";
		print $request;
		$DBRESULT =& $this->DB->query($request);
		if ($DBRESULT->numRows() == 0) {
			$request = "INSERT INTO contactgroup_contact_relation (contactgroup_cg_id, contact_contact_id) VALUES ('".$cg_id."', '".$contact_id."')";		
			$DBRESULT2 =& $this->DB->query($request);
			return $this->checkRequestStatus();
		}
	}
	
	public function unsetChild($options) {
		$info = split(";", $options);
		
		$contact = new CentreonContact($this->DB);
		
		$contact_id = $contact->getContactID($info[1]);
		$cg_id = $this->getContactGroupID($info[0]);
		
		$request = "DELETE FROM contactgroup_contact_relation WHERE contact_contact_id = '".$contact_id."' AND contactgroup_cg_id = '".$cg_id."'";
		$DBRESULT =& $this->DB->query($request);
		return $this->checkRequestStatus();	
	}
}
?>