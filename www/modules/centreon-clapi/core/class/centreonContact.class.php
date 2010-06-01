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
 * SVN : $URL: http://svn.modules.centreon.com/centreon-clapi/trunk/www/modules/centreon-clapi/core/class/centreonHost.class.php $
 * SVN : $Id: centreonHost.class.php 25 2010-03-30 05:52:19Z jmathis $
 *
 */
 
class CentreonContact {
	private $DB;
	
	public function __construct($DB) {
		$this->DB = $DB;
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
		$DBRESULT =& $this->DB->query("SELECT contact_name, contact_id FROM contact WHERE contact_name = '".htmlentities($name, ENT_QUOTES)."'");
		if ($DBRESULT->numRows() >= 1) {
			$sg =& $DBRESULT->fetchRow();
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
		$DBRESULT =& $this->DB->query($request);
		$data =& $DBRESULT->fetchRow();
		return $data["contact_id"];
	}
	
	private function checkParameters($options) {
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
		$DBRESULT =& $this->DB->query($request);
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
		$DBRESULT =& $this->DB->query($request);
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
	
	public function addContact($information) {
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
			$DBRESULT =& $this->DB->query($request);
	
			$contact_id = $this->getContactID($information["contact_name"]);
			return $contact_id;
		}
	}
}
?>