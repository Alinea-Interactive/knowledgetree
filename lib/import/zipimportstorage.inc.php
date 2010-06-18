<?php
/**
 * $Id$
 *
 * Manages listing and contents for documents uploaded from a zip file
 *
 * KnowledgeTree Community Edition
 * Document Management Made Simple
 * Copyright (C) 2008, 2009, 2010 KnowledgeTree Inc.
 * 
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
 * Contributor( s): ______________________________________
 */

require_once(KT_LIB_DIR . '/filelike/fsfilelike.inc.php');
require_once(KT_LIB_DIR . '/import/fsimportstorage.inc.php');

require_once('File/Archive.php');
require_once(KT_LIB_DIR . '/util/ktpclzip.inc.php');


class KTZipImportStorage extends KTFSImportStorage {

    /**
     * The archive extension.
     * @var string
     */
    var $sExtension = 'zip';

    var $sZipPath = '';

    var $sBasePath = '';

    var $aFile = array();

    var $sFileName = 'file';

    var $allowed_extensions = array('tgz', 'tar', 'gz', 'zip', 'deb', 'ar');

    // TODO : Added $fileName = '', so singleton could be used.
    function __construct($fileName = '', $fileData = null) {
        $this->sFileName = $fileName;
        if(empty($fileData)){
            $this->aFile = $_FILES[$fileName];
        }else{
            $this->aFile = $fileData;
        }
        $this->sZipPath = $this->aFile['tmp_name'];

        // Check the bzip2 lib functions are available
        if(function_exists('bzopen')){
            $this->allowed_extensions = array_merge($this->allowed_extensions, array('bz2', 'tbz'));
        }
    }

    function CheckFormat(){
        // Get the file extension
        $aFilename = explode('.', $this->aFile['name']);
        $cnt = count($aFilename);
        $sExtension = $aFilename[$cnt - 1];

        // check if its in the list of supported extensions
        if(!in_array($sExtension, $this->allowed_extensions)){
            return false;
        }

        $this->sExtension = (!empty($sExtension)) ? $sExtension : 'zip';

        // Check if the archive is a .tar.gz or .tar.bz, etc
        if($cnt > 2){
            if($aFilename[$cnt-2] == 'tar'){
                switch($this->sExtension){
                    case 'gz':
                        $this->sExtension = 'tgz';
                        break;
                    case 'bz2':
                        $this->sExtension = 'tbz';
                        break;
                }
            }
        }

        return true;
    }

    function getFormats(){
        return implode(', ', $this->allowed_extensions);
    }

    function init() {
    	$oStorage = KTStorageManagerUtil::getSingleton();
        $oKTConfig =& KTConfig::getSingleton();
        $sBasedir = $oKTConfig->get("urls/tmpDirectory");

        $sTmpPath = $oStorage->tempnam($sBasedir, 'archiveimportstorage');
        if ($sTmpPath === false) {
            return PEAR::raiseError(_kt("Could not create temporary directory for archive storage"));
        }
        if (!$oStorage->file_exists($this->sZipPath)) {
            return PEAR::raiseError(_kt("Archive file given does not exist"));
        }
        $oStorage->unlink($sTmpPath);
        $oStorage->mkdir($sTmpPath, 0777);
        $this->sBasePath = $sTmpPath;

        // Set environment language to output character encoding
        $sOutputEncoding = $oKTConfig->get('export/encoding', 'UTF-8');
        $loc = $sOutputEncoding;
        putenv("LANG=$loc");
        putenv("LANGUAGE=$loc");
        $loc = setlocale(LC_ALL, $loc);

        // File Archive doesn't unzip properly - using peclzip for zip files
        // todo: replace file archive for tar, etc
        if($this->sExtension == 'zip'){

        	$archive = new KTPclZip($this->sZipPath);
        	// before extraction, test for directory traversal
        	if (!$archive->checkDirectoryTraversal($sTmpPath)) {
        	   $archive->extractZipFile($sTmpPath);
        	}
        	else {
        	    return new PEAR_Error('Zip file contains potential directory traversal and will not be extracted');
        	}

            /* ** Original zip functionality using the unzip binary ** *
            $sUnzipCommand = KTUtil::findCommand("import/unzip", "unzip");
            if (empty($sUnzipCommand)) {
                return PEAR::raiseError(_kt("unzip command not found on system"));
            }
            $aArgs = array(
                $sUnzipCommand,
                "-q", "-n",
                "-d", $sTmpPath,
                $this->sZipPath,
            );
            $aRes = KTUtil::pexec($aArgs);

            if ($aRes['ret'] !== 0) {
                return PEAR::raiseError(_kt("Could not retrieve contents from zip storage"));
            }
            /* ** End original zip functionality ** */
        }else{
            File_Archive::extract(
                File_Archive::readArchive(
                    $this->sExtension, File_Archive::readUploadedFile($this->sFileName)
                ),
                $dst = $sTmpPath
            );
        }
    }

    function cleanup() {
    	$oStorage = KTStorageManagerUtil::getSingleton();
        if ($this->sBasePath && $oStorage->file_exists($this->sBasePath)) {
            KTUtil::deleteDirectory($this->sBasePath);
            $this->sBasePath = null;
        }
        if ($this->sZipPath && $oStorage->file_exists($this->sZipPath)) {
            KTUtil::deleteDirectory($this->sZipPath);
            $this->sZipPath = null;
        }
    }
}

class KTZipImportStorageManager 
{
    static function getSingleton() 
    {
    	static $singleton = null;
    	if (is_null($singleton))
    	{
    		$oConfig =& KTConfig::getSingleton();
        	$sDefault = 'KTZipImportStorage';
        	$klass = $oConfig->get('importstorage/manager', $sDefault);
        	// TODO : Remove after config settings upgrade
        	$klass = "KTAmazonS3ZipImportStorage";
        	if (!class_exists($klass)) {
            	$klass = $sDefault;
        	}
        	$singleton = new $klass;
    	}

    	return $singleton;
    }
}

?>
