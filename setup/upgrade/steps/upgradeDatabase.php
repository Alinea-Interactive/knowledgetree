<?php
/**
* Upgrade Step Controller. 
*
* KnowledgeTree Community Edition
* Document Management Made Simple
* Copyright(C) 2008,2009 KnowledgeTree Inc.
* Portions copyright The Jam Warehouse Software(Pty) Limited
*
* This program is free software; you can redistribute it and/or modify it under
* the terms of the GNU General Public License version 3 as published by the
* Free Software Foundation.
*
* This program is distributed in the hope that it will be useful, but WITHOUT
* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
* details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* You can contact KnowledgeTree Inc., PO Box 7775 #87847, San Francisco,
* California 94120-7775, or email info@knowledgetree.com.
*
* The interactive user interfaces in modified source and object code versions
* of this program must display Appropriate Legal Notices, as required under
* Section 5 of the GNU General Public License version 3.
*
* In accordance with Section 7(b) of the GNU General Public License version 3,
* these Appropriate Legal Notices must retain the display of the "Powered by
* KnowledgeTree" logo and retain the original copyright notice. If the display of the
* logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
* must display the words "Powered by KnowledgeTree" and retain the original
* copyright notice.
*
* @copyright 2008-2009, KnowledgeTree Inc.
* @license GNU General Public License version 3
* @author KnowledgeTree Team
* @package Upgrader
* @version Version 0.1
*/

require '../../config/dmsDefaults.php';
require_once KT_LIB_DIR . '/config/config.inc.php';
require_once KT_LIB_DIR . '/plugins/pluginutil.inc.php';
include KT_LIB_DIR . '/upgrades/upgrade.inc.php';

class upgradeDatabase extends Step 
{
	/**
	* Reference to Database object
	*
	* @author KnowledgeTree Team
	* @access public
	* @var object
	*/	
    public $_dbhandler = null;
    	
	/**
	* Reference to Database object
	*
	* @author KnowledgeTree Team
	* @access public
	* @var object
	*/	
    public $_util = null;
    
	/**
	* Location of database binaries.
	*
	* @author KnowledgeTree Team
	* @access private
	* @var string
	*/
    private $mysqlDir; // TODO:multiple databases
    
	/**
	* Name of database binary.
	*
	* @author KnowledgeTree Team
	* @access private
	* @var string
	*/
    private $dbBinary = ''; // TODO:multiple databases
    
	/**
	* List of errors encountered
	*
	* @author KnowledgeTree Team
	* @access public
	* @var array
	*/
    public $error = array();
    
	/**
	* List of errors used in template
	*
	* @author KnowledgeTree Team
	* @access public
	* @var array
	*/
    public $templateErrors = array('dmspassword', 'dmsuserpassword', 'con', 'dname', 'dtype', 'duname', 'dpassword');
    
	/**
	* Flag to store class information in session
	*
	* @author KnowledgeTree Team
	* @access public
	* @var array
	*/
    public $storeInSession = true;
    
    protected $silent = false;
    protected $temp_variables = array();
    
	/**
	* Constructs database upgrade object
	*
	* @author KnowledgeTree Team
	* @access public
	* @param none
 	*/
    public function __construct() {
        $this->temp_variables = array("step_name"=>"database", "silent"=>$this->silent, 
                                      "loadingText"=>"The database upgrade is under way.  Please wait until it completes");
    	$this->_dbhandler = new UpgradedbUtil();
        $this->_util = new UpgradeUtil();
    }

	/**
	* Main control of database setup
	*
	* @author KnowledgeTree Team
	* @param none
	* @access public
	* @return string
	*/
    public function doStep() {
        parent::doStep();
    	$this->initErrors();
    	if(!$this->inStep("database")) {
    	    $this->doRun();
    		return 'landing';
    	}
		if($this->next()) {
		    $this->doRun('preview');
			return 'next';
		} else if($this->previous()) {
			return 'previous';
		}
        else if ($this->confirmUpgrade()) {
            $this->doRun('confirm');
            return 'next';
        }
        else if ($this->upgrading()) {
            if ($this->doRun('runUpgrade')) {
                return 'next';
            }
            return 'error';
        }
        
        $this->doRun();
        return 'landing';
    }
    
    private function confirmUpgrade() {
        return isset($_POST['ConfirmUpgrade']);
    }
    
    private function upgrading() {
        return isset($_POST['RunUpgrade']);
    } 
    
    private function doRun($action = null) {
        $this->readConfig(KTConfig::getConfigFilename());
        
        if($this->dbSettings['dbPort'] == '')  {
            $con = $this->_dbhandler->load($this->dbSettings['dbHost'], $this->dbSettings['dbUser'],  
                                           $this->dbSettings['dbPass'], $this->dbSettings['dbName']);
        } else {
            $con = $this->_dbhandler->load($this->dbSettings['dbHost'].":".$this->dbSettings['dbPort'], $this->dbSettings['dbUser'],  
                                           $this->dbSettings['dbPass'], $this->dbSettings['dbName']);
        }
        
        $this->temp_variables['action'] = $action;
        if (is_null($action) || ($action == 'preview')) {
            $this->temp_variables['title'] = 'Preview Upgrade';
            $this->temp_variables['upgradeTable'] = $this->generateUpgradeTable();
        }
        else if ($action == 'confirm') {
            $this->temp_variables['title'] = 'Confirm Upgrade';
            $this->temp_variables['upgradeTable'] = $this->upgradeConfirm();
        }
        else if ($action == 'runUpgrade') {
            $this->temp_variables['title'] = 'Upgrade In Progress';
            if (!$this->upgradeDatabase()) {
                $this->temp_variables['backupSuccessful'] = false;
                return false;
            }
            $this->temp_variables['backupSuccessful'] = true;
        }
        
        return true;
    }
    
    private function generateUpgradeTable() {
        global $default;

        $this->temp_variables['systemVersion'] = $default->systemVersion;
        $query = sprintf('SELECT value FROM %s WHERE name = "databaseVersion"', $default->system_settings_table);

        $result = $this->_dbhandler->query($query);
        if ($result) {
            $lastVersionObj = $this->_dbhandler->fetchNextObject($result);
            $lastVersion = $lastVersionObj->value;
        }
        $currentVersion = $default->systemVersion;
    
        $upgrades = describeUpgrade($lastVersion, $currentVersion);
    
        $ret = "<table border=1 cellpadding=1 cellspacing=1 width='100%'>\n";
        $ret .= "<tr bgcolor='darkgrey'><th width='10'>Code</th><th width='100%'>Description</th><th width='30'>Applied</th></tr>\n";
        $i=0;
        foreach ($upgrades as $upgrade) {
            $color = ((($i++)%2)==0) ? 'white' : 'lightgrey';
            $ret .= sprintf("<tr bgcolor='$color'><td>%s</td><td>%s</td><td>%s</td></tr>\n",
            htmlspecialchars($upgrade->getDescriptor()),
            htmlspecialchars($upgrade->getDescription()),
                $upgrade->isAlreadyApplied() ? "Yes" : "No"
            );
        }
        $ret .= '</table>';
        return $ret;
    }

	/**
	* Stores varibles used by template
	*
	* @author KnowledgeTree Team
	* @params none
	* @access public
	* @return array
	*/
    public function getStepVars() {
        return $this->temp_variables;
    }

	/**
	* Returns database errors
	*
	* @author KnowledgeTree Team
	* @access public
	* @params none
	* @return array
	*/
    public function getErrors() {

        return $this->error;
    }

	/**
	* Initialize errors to false
	*
	* @author KnowledgeTree Team
	* @param none
	* @access private
	* @return boolean
	*/
    private function initErrors() {
    	foreach ($this->templateErrors as $e) {
    		$this->error[$e] = false;
    	}
    }
    
     private function readConfig($path) {
        $ini = new UpgradeIni($path);
        $dbSettings = $ini->getSection('db');
        $this->dbSettings = array('dbHost'=> $dbSettings['dbHost'],
                                    'dbName'=> $dbSettings['dbName'],
                                    'dbUser'=> $dbSettings['dbUser'],
                                    'dbPass'=> $dbSettings['dbPass'],
                                    'dbPort'=> $dbSettings['dbPort'],
                                    'dbAdminUser'=> $dbSettings['dbAdminUser'],
                                    'dbAdminPass'=> $dbSettings['dbAdminPass'],
        );
        $this->temp_variables['dbSettings'] = $this->dbSettings;
    }
    
    private function upgradeConfirm()
    {
        if (!isset($_SESSION['backupStatus']) || $_SESSION['backupStatus'] === false) {
            $this->temp_variables['backupStatus'] = false;
        }
        else {
            $this->temp_variables['backupStatus'] = true;
        }
    }

    private function upgradeDatabase()
    {
        global $default;
        
        $errors = false;
        
        $this->temp_variables['detail'] = '<p>The table below describes the upgrades that have occurred to
            upgrade your KnowledgeTree installation to <strong>' . $default->systemVersion . '</strong>';
      
        $pre_res = $this->performPreUpgradeActions();
        if (PEAR::isError($pre_res)) {
            $errors = true;
            $this->temp_variables['preUpgrade'] = '<font color="red">Pre-Upgrade actions failed.</font>';
        }
        else {
            $this->temp_variables['preUpgrade'] = '<font color="green">Pre-Upgrade actions succeeded.</font>';
    
        }
        
        $res = $this->performAllUpgrades();
        if (PEAR::isError($res) || PEAR::isError($pres)) {
            $errors = true;
            // TODO instantiate error details hideable section?
            $this->temp_variables['upgradeStatus'] = '<font color="red">Database upgrade failed</font>
                                                      <br/><br/>
                                                      Please restore from your backup and ensure that the database does not contain 
                                                      any unsupported modifications and try the upgrade process again.
                                                      <br/><br/>
                                                      If the problem persists, contact KnowledgeTree Support.';
        }
        else {
            $this->temp_variables['upgradeStatus'] = '<font color="green">Upgrade succeeded.</font>';
        }
    
        $post_pres = $this->performPostUpgradeActions();
        if (PEAR::isError($post_res)) {
            $errors = true;
            $this->temp_variables['postUpgrade'] = '<font color="red">Post-Upgrade actions failed.</font>';
        }
        else {
            $this->temp_variables['postUpgrade'] = '<font color="green">Post-Upgrade actions succeeded.</font>';
        }
        
        return !$errors;
    }

    private function performPreUpgradeActions() {
    
        // This is just to test and needs to be updated to a more sane and error resistent architrcture if it works.
        // It should idealy work the same as the upgrades.
    
        global $default;
    
        // Lock the scheduler
        $lockFile = $default->cacheDirectory . DIRECTORY_SEPARATOR . 'scheduler.lock';
        touch($lockFile);
        return true;
    
    }
    
    private function performPostUpgradeActions() {
    
        // This is just to test and needs to be updated to a more sane and error resistent architrcture if it works.
        // It should idealy work the same as the upgrades.
    
        global $default;
    
        // Ensure all plugins are re-registered.
        $sql = "TRUNCATE plugin_helper";
        $res = DBUtil::runQuery($sql);
    
        // Clear out all caches and proxies - they need to be regenerated with the new code
        $proxyDir = $default->proxyCacheDirectory;
        KTUtil::deleteDirectory($proxyDir);
    
        $oKTCache = new KTCache();
        $oKTCache->deleteAllCaches();
    
        // Clear the configuration cache, it'll regenerate on next load
        $oKTConfig = new KTConfig();
        $oKTConfig->clearCache();
    
        // Unlock the scheduler
        $lockFile = $default->cacheDirectory . DIRECTORY_SEPARATOR . 'scheduler.lock';
        if(file_exists($lockFile)){
            @unlink($lockFile);
        }
    
        return true;
    
    }

    private function performAllUpgrades () {
        global $default;
        
        $row = 1;
        
        $query = sprintf('SELECT value FROM %s WHERE name = "databaseVersion"', $default->system_settings_table);
        $lastVersion = DBUtil::getOneResultKey($query, 'value');
        $currentVersion = $default->systemVersion;
    
        $upgrades = describeUpgrade($lastVersion, $currentVersion);
        
        $this->temp_variables['upgradeTable'] = '';
    
        foreach ($upgrades as $upgrade) {
            if (($row % 2) == 1) {
                $class = "odd";
            } else {
                $class = "even";
            }
            $this->temp_variables['upgradeTable'] .= sprintf('<div class="row %s"><div class="foo">%s</div>' . "\n", $class, 
                                                             htmlspecialchars($upgrade->getDescription()));
            ++$row;
            $res = $upgrade->performUpgrade();
            $this->temp_variables['upgradeTable'] .= sprintf('<div class="bar">%s</div>', $this->showResult($res));
            $this->temp_variables['upgradeTable'] .= '<br>' . "\n";
            $this->temp_variables['upgradeTable'] .= "</div>\n";
            if (PEAR::isError($res)) {
                if (!is_a($res, 'Upgrade_Already_Applied')) {
                    break;
                } else {
                    $res = true;
                }
            }
            if ($res === false) {
                $res = PEAR::raiseError("Upgrade returned false");
                break;
            }
        }
    
        return $res;
    }
    
    private function showResult($res) {
        if (PEAR::isError($res)) {
            if (is_a($res, 'Upgrade_Already_Applied')) {
                return '<span style="color: orange">Already applied</span>';
            }
            return sprintf('<span style="color: red">%s</span>', htmlspecialchars($res->toString()));
        }
        if ($res === true) {
            return '<span style="color: green">Success</span>';
        }
        if ($res === false) {
            return '<span style="color: red">Failure</span>';
        }
        return $res;
    }

}
?>