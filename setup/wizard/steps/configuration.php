<?php
/**
* Configuration Step Controller.
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
* @package Installer
* @version Version 0.1
*/
require_once(WIZARD_DIR.'step.php');

class configuration extends Step
{
    private $host;
    private $port;
    private $root_url;
    private $file_system_root;
    private $ssl_enabled;
    private $done;
	public $temp_variables = array("step_name"=>"configuration");
	/**
	* Flag to store class information in session
	*
	* @author KnowledgeTree Team
	* @access public
	* @var array
	*/
    protected $storeInSession = true;
	/**
	* Flag if step needs to be installed
	*
	* @author KnowledgeTree Team
	* @access public
	* @var array
	*/
    protected $runInstall = true;
    
    public function __construct()
    {
        $this->done = true;
    }

	private function setDetails() {
		$conf = $this->getDataFromSession("configuration");
		if($conf) {
			$this->temp_variables['server'] = $conf['server'];
			$this->temp_variables['paths'] = $conf['paths'];
		}
	}

    public function doStep() {
        if($this->next()) {
            if($this->doRun()){
                return 'confirm';
            }
            return 'error';
            /*
            if($this->doRun())
                return 'next';
            else
                return 'error';
            */
        } else if($this->previous()) {
        	$this->setDetails();
            return 'previous';
        } else if($this->confirm()) {
            return 'next';
        } else if($this->edit()) {
        	$this->setDetails();
        	return 'landing';
        }

        $this->doRun();
        return 'landing';
    }

    public function doRun()
    {
        $server = $this->getServerInfo();
        $this->temp_variables['server'] = $server;

        $paths = $this->getPathInfo($server['file_system_root']['value']);
        $this->temp_variables['paths'] = $paths;

        // Running user
        // Logging

        return $this->done;
    }

    public function installStep()
    {
        include_once('database.inc');

        $iniClass = realpath('../../lib/upgrades/Ini.inc.php');
        include_once($iniClass);

        // get data from the server
        $conf = $this->getDataFromSession("configuration");
        $server = $conf['server'];
        $paths = $conf['paths'];

        // initialise writing to config.ini
        $configPath = realpath('../../config/config.ini');

        $ini = false;
        if(file_exists($configPath)) {
            $ini = new Ini($configPath);
        }

        // initialise the db connection
        $db = new DBUtil();
        // retrieve database information from session
        $dbconf = $this->getDataFromSession("database");
        // make db connection
        $db->DBUtil($dbconf['dhost'], $dbconf['duname'], $dbconf['dpassword'], $dbconf['dname']);
        $table = 'config_settings';
        // write server settings to config_settings table and config.ini
        foreach($server as $item){
            switch($item['where']){
                case 'file':
                    $value = $item['value'];
                    if($value == 'yes'){
                        $value = 'true';
                    }
                    if($value == 'no'){
                        $value = 'false';
                    }
                    if(!$ini === false){
                        $ini->updateItem($item['section'], $item['setting'], $value);
                    }
                    break;

                case 'db':
//                	echo '<pre>';print_r($item);echo '</pre>';
                    $value = mysql_real_escape_string($item['value']);
                    $setting = mysql_real_escape_string($item['setting']);
                    $sql = "UPDATE {$table} SET value = '{$value}' WHERE item = '{$setting}'";
//                    echo "$sql <br/>";
                    $db->query($sql);
                    break;
            }
        }

        // write the paths to the config_settings table
        foreach ($paths as $item){
            if(empty($item['setting'])){
                continue;
            }

            $value = mysql_real_escape_string($item['path']);
            $setting = mysql_real_escape_string($item['setting']);

            $sql = "UPDATE {$table} SET value = '{$value}' WHERE item = '{$setting}'";
//            echo "$sql <br/>";
            $db->query($sql);
        }

        // write out the config.ini file
        if(!$ini === false){
            $ini->write();
        }

        // close the database connection
        $db->close();
    }

    private function getServerInfo()
    {
        $script = $_SERVER['SCRIPT_NAME'];
        $file_system_root = $_SERVER['DOCUMENT_ROOT'];
        $host = $_SERVER['SERVER_NAME'];
        $port = $_SERVER['SERVER_PORT'];
        $ssl_enabled = isset($_SERVER['HTTPS']) ? (strtolower($_SERVER['HTTPS']) === 'on' ? 'yes' : 'no') : true;

        $pos = strpos($script, '/setup/wizard/');
        $root_url = substr($script, 0, $pos);

        $root_url = (isset($_POST['root_url'])) ? $_POST['root_url'] : $root_url;
        $file_system_root = (isset($_POST['file_system_root'])) ? $_POST['file_system_root'] : $file_system_root.$root_url;
        $host = (isset($_POST['host'])) ? $_POST['host'] : $host;
        $port = (isset($_POST['port'])) ? $_POST['port'] : $port;
        $ssl_enabled = (isset($_POST['ssl_enabled'])) ? $_POST['ssl_enabled'] : $ssl_enabled;

        $server = array();
        $server['root_url'] = array('name' => 'Root Url', 'setting' => 'rootUrl', 'where' => 'db', 'value' => $root_url);
        $server['file_system_root'] = array('name' => 'File System Root', 'section' => 'KnowledgeTree', 'setting' => 'fileSystemRoot', 'where' => 'file', 'value' => $file_system_root);
        $server['host'] = array('name' => 'Host', 'setting' => 'server_host', 'where' => 'db', 'value' => $host);
        $server['port'] = array('name' => 'Port', 'setting' => 'server_port', 'where' => 'db', 'value' => $port);
        $server['ssl_enabled'] = array('name' => 'SSL Enabled', 'section' => 'KnowledgeTree', 'setting' => 'sslEnabled', 'where' => 'file', 'value' => $ssl_enabled);

        if(empty($server['host']['value']))
            $this->error[] = 'Please enter the server\'s host name';

        if(empty($server['port']['value']))
            $this->error[] = 'Please enter the server\'s port';

        if(empty($server['file_system_root']['value']))
            $this->error[] = 'Please enter the file system root';

        return $server;
    }

    private function getPathInfo($fileSystemRoot)
    {
        $dirs = $this->getDirectories();
        $varDirectory = $fileSystemRoot . DIRECTORY_SEPARATOR . 'var';

        foreach ($dirs as $key => $dir){
            $path = (isset($_POST[$dir['setting']])) ? $_POST[$dir['setting']] : $dir['path'];

            while(preg_match('/\$\{([^}]+)\}/', $path, $matches)){
                $path = str_replace($matches[0], $$matches[1], $path);
            }

            $dirs[$key]['path'] = $path;
            $class = $this->checkPermission($path, $dir['create']);
            $dirs[$key] = array_merge($dirs[$key], $class);
        }

        return $dirs;
    }

    private function checkPermission($dir, $create=false)
    {
        $exist = 'Directory does not exist';
        $write = 'Directory is not writable';
        $ret = array('class' => 'cross');

        if(!file_exists($dir)){
            if($create === false){
                $this->done = false;
                $ret['msg'] = $exist;
                return $ret;
            }
            $par_dir = dirname($dir);
            if(!file_exists($par_dir)){
                $this->done = false;
                $ret['msg'] = $exist;
                return $ret;
            }
            if(!is_writable($par_dir)){
                $this->done = false;
                $ret['msg'] = $exist;
                return $ret;
            }
            @mkdir($dir, '0755');
        }

        if(is_writable($dir)){
            $ret['class'] = 'tick';
            return $ret;
        }

        $this->done = false;
        $ret['msg'] = $write;
        return $ret;
    }

    private function getDirectories()
    {
        return array(
                array('name' => 'Var Directory', 'setting' => 'varDirectory', 'path' => '${fileSystemRoot}/var', 'create' => false),
                array('name' => 'Document Directory', 'setting' => 'documentRoot', 'path' => '${varDirectory}/Documents', 'create' => true),
                array('name' => 'Log Directory', 'setting' => 'logDirectory', 'path' => '${varDirectory}/log', 'create' => true),
                array('name' => 'Temporary Directory', 'setting' => 'tmpDirectory', 'path' => '${varDirectory}/tmp', 'create' => true),
                array('name' => 'Uploads Directory', 'setting' => 'uploadDirectory', 'path' => '${varDirectory}/uploads', 'create' => true),
                array('name' => 'Configuration File', 'setting' => '', 'path' => '${fileSystemRoot}/config/config.ini', 'create' => false),
            );
    }
}
?>