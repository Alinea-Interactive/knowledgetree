<?php
/**
 * Document API for KnowledgeTree
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

/**
 * @copyright 2008-2010, KnowledgeTree Inc.
 * @license GNU General Public License version 3
 * @author KnowledgeTree Team
 * @package KTAPI
 * @version Version 0.9
 */

require_once(KT_DIR . '/ktwebservice/KTDownloadManager.inc.php');

/**
 * API for the handling document operations within KnowledgeTree
 *
 * @author KnowledgeTree Team
 * @package KTAPI
 * @version 0.9
 */
class KTAPI_Document extends KTAPI_FolderItem
{
	/**
	 * This is a reference to the internal document object.
	 *
	 * @access protected
	 * @var object of Document
	 */
	var $document;

	/**
	 * This is the id of the document.
	 *
	 * @access protected
	 * @var int
	 */
	var $documentid;

	/**
	 * This is a reference to the parent folder.
	 *
	 * @access protected
	 * @var object of KTAPI_Folder
	 */
	var $ktapi_folder;

	/**
	 * Gets the id of the current document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return string The document id
	 */
	function get_documentid()
	{
		return $this->documentid;
	}

	/**
	 * This is used to get a document based on document id. Or a version of the document based on the metadata version id
	 *
	 * @author KnowledgeTree Team
	 * @static
	 * @access public
	 * @param KTAPI $ktapi The ktapi object
	 * @param int $documentid The document id
	 * @param int $iMetadataVersionId Optional. The metadata version id
	 * @return KTAPI_Document The document object
	 */
	function &get(&$ktapi, $documentid, $iMetadataVersionId = null)
	{
	    if (is_null($ktapi) || !($ktapi instanceof KTAPI)) {
	        return PEAR::raiseError('A valid KTAPI object is needed');
	    }

	    if (!is_numeric($documentid)) {
	        return PEAR::raiseError('A valid document id is required');
	    }

	    // ensure documentid is an integer
		$documentid += 0;

		$document = &Document::get($documentid, $iMetadataVersionId);
		if (is_null($document) || PEAR::isError($document))
		{
			return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_INVALID,$document );
		}

		$user = $ktapi->can_user_access_object_requiring_permission($document, KTAPI_PERMISSION_READ);

		if (is_null($user) || PEAR::isError($user))
		{
			return $user;
		}

		$folderid = $document->getParentID();

		if (!is_null($folderid))
		{
			$ktapi_folder = &KTAPI_Folder::get($ktapi, $folderid);
		}
		else
		{
			$ktapi_folder = null;
		}
		// We don't do any checks on this folder as it could possibly be deleted, and is not required right now.

		return new KTAPI_Document($ktapi, $ktapi_folder, $document);
	}

	/**
	 * This is used to get a document based on the document id and the metadata version
	 *
	 * @author KnowledgeTree Team
	 * @static
	 * @access public
	 * @param KTAPI $ktapi The ktapi object
	 * @param int $documentid The document id
	 * @param int $metadataVersion The metadata version (0,1,2)
	 * @return KTAPI_Document The document object
	 */
	function &get_by_metadata_version(&$ktapi, $documentid, $metadataVersion)
	{
	    // get the metadata version id
	    $iMetadataVersionId = Document::getMetadataVersionIdFromVersion($documentid, $metadataVersion);
		if (is_null($iMetadataVersionId) || PEAR::isError($iMetadataVersionId))
		{
			return new KTAPI_Error(KTAPI_ERROR_VERSION_INVALID, $iMetadataVersionId );
		}

	    // get the KTAPI_Document object
	    return self::get($ktapi, $documentid, $iMetadataVersionId);
	}

	/**
	 * Checks if a document has been deleted
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return boolean TRUE if deleted | FALSE if in a different state
	 */
	function is_deleted()
	{
		return ($this->document->getStatusID() == 3);
	}

	/**
	 * Checks if the document is a shortcut
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return boolean TRUE if it is a shortcut | FALSE if not
	 */
	function is_shortcut()
	{
		return $this->document->isSymbolicLink();
	}

	/**
	 * Retrieves the shortcuts linking to this document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return array The shortcuts linked to the document
	 */
	function get_shortcuts()
	{
		return $this->document->getSymbolicLinks();
	}


	/**
	 * This is the constructor for the KTAPI_Document
	 *
	 * @author KnowledgeTree Team
	 * @access private
	 * @param KTAPI $ktapi The KTAPI object
	 * @param KTAPI_Folder $ktapi_folder The parent folder object
	 * @param Document $document The internal document object
	 * @return KTAPI_Document
	 */
	function KTAPI_Document(&$ktapi, &$ktapi_folder, &$document)
	{
		assert($ktapi instanceof KTAPI);   //$ktapi instanceof KTAPI);
		assert(is_null($ktapi_folder) || $ktapi_folder instanceof KTAPI_Folder); //$ktapi_folder instanceof KTAPI_Folder);

		$this->ktapi = &$ktapi;
		$this->ktapi_folder = &$ktapi_folder;
		$this->document = &$document;
		$this->documentid = $document->getId();
	}

	/**
	 * This checks a document into the repository.
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
     * if ($document->is_checked_out()) {
     *     $document->checkin('filename.txt', 'Reason for checkin', '/tmp/filename');
     * }
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $filename The name of the file
	 * @param string $reason The reason for checking the document in
	 * @param string $tempfilename The location of the temporary file
	 * @param bool $major_update Determines if the version number should have a major increment (+1) or a minor increment (+0.1)
         */
        function checkin($filename, $reason, $tempfilename, $major_update=false)
        {
            $storage = KTStorageManagerUtil::getSingleton();
            if (!$storage->isFile($tempfilename))
            {
                return new PEAR_Error('File does not exist.');
            }

            $user = $this->can_user_access_object_requiring_permission($this->document, KTAPI_PERMISSION_WRITE);

            if (PEAR::isError($user))
            {
                return $user;
            }

            if (!$this->document->getIsCheckedOut())
            {
                return new PEAR_Error(KTAPI_ERROR_DOCUMENT_NOT_CHECKED_OUT);
            }

            $filename = KTUtil::replaceInvalidCharacters($filename);

            $options = array('major_update' => $major_update);

            $currentfilename = $this->document->getFileName();
            if ($filename != $currentfilename)
            {
                $options['newfilename'] = $filename;
            }

            DBUtil::startTransaction();

            $result = KTDocumentUtil::checkin($this->document, $tempfilename, $reason, $user, $options);
            if (PEAR::isError($result))
            {
                DBUtil::rollback();
                return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$result);
            }

            DBUtil::commit();

            KTUploadManager::temporary_file_imported($tempfilename);
	}

	/**
	 * Removes the update notification for the document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	function removeUpdateNotification()
	{
		$sql =  "DELETE FROM notifications WHERE data_int_1=$this->documentid AND data_str_1='ModifyDocument'";
		DBUtil::runQuery($sql);
	}

	/**
	 * Creates a link between two documents
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param KTAPI_Document $document The document object
	 * @param string $type The link relationship type: depended on|Attachment|Reference|Copy|Default
	 * @return PEAR_Error|void Returns nothing on success | a PEAR_Error object on failure
	 *
	 */
	function link_document($document, $type)
	{
		$typeid = $this->ktapi->get_link_type_id($type);
		if (PEAR::isError($typeid))
		{
			return $typeid;
		}

		$link = new DocumentLink($this->get_documentid(), $document->get_documentid(), $typeid );
		$created = $link->create();
		if ($created === false || PEAR::isError($created))
		{
			return new PEAR_Error(_kt('Could not create link'));
		}
	}

	/**
	 * Removes the link between two documents
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param KTAPI_Document $document The document object
	 * @return PEAR_Error|void Returns nothing on success | a PEAR_Error object on failure
	 */
	function unlink_document($document)
	{
		$sql = "DELETE FROM document_link WHERE parent_document_id=$this->documentid AND child_document_id=$document->documentid";
		$result = DBUtil::runQuery($sql);
		if (empty($result) || PEAR::isError($created))
		{
			return new PEAR_Error(_kt('Could not remove link'));
		}
	}


	/**
	 * Checks whether the document has been checked out.
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
     * if ($document->is_checked_out()) {
     *     continue;
     * }else{
     *     $document->checkout('Reason for document checkout');
     * }
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return boolean TRUE if checked out | FALSE if not
	 */
	function is_checked_out()
	{
		return ($this->document->getIsCheckedOut());
	}

	/**
	 * Cancels the checkout on a document
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
     * if ($document->is_checked_out()) {
     *     $document->undo_checkout('Reason for cancelling checkout');
     * }
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $reason The reason for cancelling
	 * @return PEAR_Error|void Returns nothing on success | a PEAR_Error on failure
	 */
	function undo_checkout($reason)
	{
		$user = $this->can_user_access_object_requiring_permission($this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		if (!$this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_NOT_CHECKED_OUT);
		}

		DBUtil::startTransaction();

		$this->document->setIsCheckedOut(0);
		$this->document->setCheckedOutUserID(-1);
		$res = $this->document->update();
		if (($res === false) || PEAR::isError($res))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res);
		}

		$oDocumentTransaction = new DocumentTransaction($this->document, $reason, 'ktcore.transactions.force_checkin');

		$res = $oDocumentTransaction->create();
		if (($res === false) || PEAR::isError($res)) {
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res);
		}
		DBUtil::commit();
	}

	/**
	 * Gets a list of linked documents
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return array|PEAR_Error Returns a list of linked documents on success | a PEAR_Error on failure
	 */

	function get_linked_documents()
	{
		$sql = "
		SELECT
			dl.child_document_id as document_id,
			dmv.name as title,
			dcv.size,
			w.name as workflow,
			ws.name as workflow_state,
			dlt.name as link_type, dtl.name as document_type,
			dcv.major_version, dcv.minor_version, d.oem_no
		FROM
			document_link dl
			INNER JOIN document_link_types dlt ON dl.link_type_id=dlt.id
			INNER JOIN documents d ON dl.child_document_id=d.id
			INNER JOIN document_metadata_version dmv ON d.metadata_version_id=dmv.id
			INNER JOIN document_content_version dcv ON dmv.content_version_id=dcv.id
			INNER JOIN document_types_lookup dtl ON dtl.id=dmv.document_type_id
			LEFT OUTER JOIN workflow_documents wd ON d.id=wd.document_id
			LEFT OUTER JOIN workflows w ON w.id=wd.workflow_id
			LEFT OUTER JOIN workflow_states ws ON wd.state_id=ws.id
		WHERE
			dl.parent_document_id=$this->documentid

		";
		$rows = DBUtil::getResultArray($sql);
		if (PEAR::isError($rows))
		{
			return $rows;
		}
		$result=array();
		$read_permission = &KTPermission::getByName(KTAPI_PERMISSION_READ);
		$user = $this->ktapi->get_user();

		foreach($rows as $row)
		{
			$document = Document::get($row['document_id']);
			if (PEAR::isError($document) || is_null($document))
			{
				continue;
			}
			if (!KTPermissionUtil::userHasPermissionOnItem($user, $read_permission, $document))
			{
				continue;
			}

			$oem_no = $row['oem_no'];
			if (empty($oem_no)) $oem_no = 'n/a';

			$result[] = array(
					'document_id' => (int)$row['document_id'],
					'custom_document_no' => 'n/a',
					'oem_document_no' => $oem_no,
					'title' => $row['title'],
					'document_type' => $row['document_type'],
					'version' => (float)($row['major_version'] . '.' . $row['minor_version']),
					'filesize' =>(int)$row['size'],
					'workflow' => empty($row['workflow']) ? 'n/a' : $row['workflow'],
					'workflow_state' => empty($row['workflow_state']) ? 'n/a' : $row['workflow_state'],
					'link_type' => empty($row['link_type']) ? 'unknown' : $row['link_type'],
				);
		}

		return $result;
	}

	/**
	 * Checks out a document
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
     * $document->checkout('Reason for document checkout');
     * if ($document->is_checked_out()) {
     *     continue;
     * }
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $reason The reason for checking out the document
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function checkout($reason)
	{
		$document_status = $this->document->getStatusID();

		switch ($document_status) {
			case LIVE:
				//just ignore
				break;
			case PUBLISHED:
				//just ignore
				break;
			case DELETED:
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_DELETED);
				break;
			case ARCHIVED:
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_ARCHIVED);
				break;
			case STATUS_INCOMPLETE:
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_UNAVAILABLE);
				break;
			case VERSION_DELETED:
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_DELETED);
				break;
		}

		$user = $this->can_user_access_object_requiring_permission($this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		//if the document is checked-out by the current user, just return
		//as no need to check-out again BUT we do need to download
		//returning here will allow download, but skip check-out
		if ( ($this->document->getIsCheckedOut()) &&
			($this->document->getCheckedOutUserID() == $_SESSION['userID']) )
		{
			return;
		}

		DBUtil::startTransaction();
		$res = KTDocumentUtil::checkout($this->document, $reason, $user);
		if (PEAR::isError($res))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res);
		}

		DBUtil::commit();
	}

	/**
	 * Deletes a document from the folder.
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
	 * $document->delete('Reason for deletion');
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $reason The reason for deleting the document
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function delete($reason)
	{
		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_DELETE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		if ($this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_CHECKED_OUT);
		}

		DBUtil::startTransaction();
		$res = KTDocumentUtil::delete($this->document, $reason);
		if (PEAR::isError($res))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res);
		}

		DBUtil::commit();
	}

	/**
	 * Changes the owner of a document and updates its permissions.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $newusername The username of the new owner
	 * @param string $reason The reason for changing the owner
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function change_owner($newusername, $reason='Changing of owner.')
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_CHANGE_OWNERSHIP);

		if (PEAR::isError($user))
		{
			return $user;
		}

        DBUtil::startTransaction();

        $user = &User::getByUserName($newusername);
        if (is_null($user) || PEAR::isError($user))
        {
        	return new KTAPI_Error('User could not be found',$user);
        }

        $newuserid = $user->getId();

        $this->document->setOwnerID($newuserid);

        $res = $this->document->update();

        if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR ,$res );
        }

        $res = KTPermissionUtil::updatePermissionLookup($this->document);
        if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
        }

		$oDocumentTransaction = new DocumentTransaction($this->document, $reason, 'ktcore.transactions.permissions_change');

		$res = $oDocumentTransaction->create();
		if (($res === false) || PEAR::isError($res)) {
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
		}

		DBUtil::commit();
	}

	/**
	 * Copies the document from one folder to another.
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
	 * $newFolder = $this->root->add_folder("New folder");
	 * $copyOfDoc = $document->copy($newFolder, 'Reason for copying document');
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param KTAPI_Folder $ktapi_target_folder The new parent folder where the document is being copied into
	 * @param string $reason The reason for the copy
	 * @param string $newname Optional. The title of the document to be used in the case of a name clash
	 * @param string $newfilename Optional. The filename of the document to be used in the case of a name clash
	 * @return KTAPI_Document|PEAR_Error Returns the new KTAPI Document object | a PEAR_Error on failure
	 */
	function copy(&$ktapi_target_folder, $reason, $newname=null, $newfilename=null)
	{
		assert(!is_null($ktapi_target_folder));
		assert($ktapi_target_folder instanceof KTAPI_FOLDER);    //$ktapi_target_folder instanceof KTAPI_Folder);

		if (empty($newname))
		{
			$newname=null;
		}
		if (empty($newfilename))
		{
			$newfilename=null;
		}

		$user = $this->ktapi->get_user();

		if ($this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_CHECKED_OUT);
		}

		$target_folder = &$ktapi_target_folder->get_folder();

		$result = $this->can_user_access_object_requiring_permission($target_folder, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($result))
		{
			return $result;
		}

		$name = $this->document->getName();
		$clash = KTDocumentUtil::nameExists($target_folder, $name);
        if ($clash && !is_null($newname))
        {
        	$name = $newname;
        	$clash = KTDocumentUtil::nameExists($target_folder, $name);

        	if ($clash) {
        		return new PEAR_Error('A document with this title already exists in your chosen folder.  '
                                    . 'Please choose a different folder, or specify a new title for the copied document.');
        	}
        }

        $filename=$this->document->getFilename();
        $clash = KTDocumentUtil::fileExists($target_folder, $filename);

        if ($clash && !is_null($newfilename))
        {
			$filename = $newfilename;
            $clash = KTDocumentUtil::fileExists($target_folder, $filename);

            if ($clash) {
            	return new PEAR_Error('A document with this filename already exists in your chosen folder.  '
                                   . 'Please choose a different folder, or specify a new filename for the copied document.');
            }
        }

		DBUtil::startTransaction();

        $new_document = KTDocumentUtil::copy($this->document, $target_folder, $reason);
        if (PEAR::isError($new_document))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $new_document );
        }

        if (!is_null($newname)) {
        	$new_document->setName($name);
        }
        if (!is_null($newfilename)) {
        	$new_document->setFilename($filename);
        }

        $res = $new_document->update();

        if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
        }

        DBUtil::commit();

        /*
        // FIXME do we need to refactor all trigger usage into the util function?
        $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $aTriggers = $oKTTriggerRegistry->getTriggers('copyDocument', 'postValidate');
        foreach ($aTriggers as $aTrigger) {
            $sTrigger = $aTrigger[0];
            $oTrigger = new $sTrigger;
            $aInfo = array(
                'document' => $new_document,
                'old_folder' => $this->ktapi_folder->get_folder(),
                'new_folder' => $target_folder,
            );
            $oTrigger->setInfo($aInfo);
            $ret = $oTrigger->postValidate();
        }
        */

        return KTAPI_Document::get($this->ktapi, $new_document->getId());
	}

	/**
	 * Moves the document from one folder to another.
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
	 * $newFolder = $this->root->add_folder("New folder");
	 * $document->move($newFolder, 'Reason for moving the document');
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param KTAPI_Folder $ktapi_target_folder The folder object where the document is being moved into
	 * @param string $reason The reason for the move
	 * @param string $newname Optional. The title of the document to be used in the case of a name clash
	 * @param string $newfilename Optional. The filename of the document to be used in the case of a name clash
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function move(&$ktapi_target_folder, $reason, $newname=null, $newfilename=null)
	{
		assert(!is_null($ktapi_target_folder));
		assert($ktapi_target_folder instanceof KTAPI_Folder);  // $ktapi_target_folder instanceof KTAPI_Folder);

		if (empty($newname)) {
			$newname=null;
		}
		if (empty($newfilename)) {
			$newfilename=null;
		}

		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_DOCUMENT_MOVE);

		if (PEAR::isError($user)) {
			return $user;
		}

		if ($this->document->getIsCheckedOut()) {
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_CHECKED_OUT);
		}

		$target_folder = $ktapi_target_folder->get_folder();
		$result = $this->can_user_access_object_requiring_permission(  $target_folder, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($result)) {
			return $result;
		}

		if (!KTDocumentUtil::canBeMoved($this->document, $error)) {
            if (PEAR::isError($error)) {
                return $error;
            }
            else {
                return new PEAR_Error('Document cannot be moved.');
            }
		}

		$fileRenamed = false;
		$options = array();
		$name = $this->document->getName();
		$nameClash = KTDocumentUtil::nameExists($target_folder, $name);

		if ($nameClash && !is_null($newname)) {
        	$nameClash = KTDocumentUtil::nameExists($target_folder, $newname);
        	$name = $newname;
        	$options['name'] = $name;
		}

		$filename=$this->document->getFilename();
		$filenameClash = KTDocumentUtil::fileExists($target_folder, $filename);

		if ($filenameClash && !is_null($newfilename)) {
            $filenameClash = KTDocumentUtil::fileExists($target_folder, $newfilename);
            $filename = $newfilename;
        	$options['filename'] = $filename;
        }

        if ($nameClash) {
        	if ($filenameClash) {
        		return new PEAR_Error('A document with this title and filename already exists in your chosen folder.  '
                                . 'Please choose a different folder, or specify a new title and filename for the moved document.');
        	}

        	return new PEAR_Error('A document with this title already exists in your chosen folder.  '
                                . 'Please choose a different folder, or specify a new title for the moved document.');
        }

        if ($filenameClash) {
        	return new PEAR_Error('A document with this filename already exists in your chosen folder.  '
                                . 'Please choose a different folder, or specify a new filename for the moved document.');
        }

		DBUtil::startTransaction();

        $res = KTDocumentUtil::move($this->document, $target_folder, $user, $reason, false, $options);
        if (PEAR::isError($res)) {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res );
        }

        DBUtil::commit();
	}

	/**
	 * Changes the filename of the document.
	 * If the filename contains any invalid characters they are replaced with a dash (-). For example: ?, *, %, \, /
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $newname The new filename
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function renameFile($newname)
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}
		$newname = KTUtil::replaceInvalidCharacters($newname);

		DBUtil::startTransaction();
		$res = KTDocumentUtil::rename($this->document, $newname, $user);
		if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
        }
        DBUtil::commit();
	}

	/**
	 * Changes the document type of the document.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $documenttype The new document type
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function change_document_type($documenttype)
	{
		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$doctypeid = KTAPI::get_documenttypeid($documenttype);
		if (PEAR::isError($doctypeid))
		{
			return $doctypeid;
		}

		if ($this->document->getDocumentTypeId() != $doctypeid)
		{
			// Get the current document type, fieldsets and metadata
			$iOldDocTypeID = $this->document->getDocumentTypeID();
			$fieldsets = KTMetadataUtil::fieldsetsForDocument($this->document, $iOldDocTypeID);
			$mdlist = DocumentFieldLink::getByDocument($this->document);

			$field_values = array();
			foreach ($mdlist as $oFieldLink) {
				$field_values[$oFieldLink->getDocumentFieldID()] = $oFieldLink->getValue();
			}

			DBUtil::startTransaction();
			$this->document->startNewMetadataVersion($user);
			$this->document->setDocumentTypeId($doctypeid);
			$res = $this->document->update();

			if (PEAR::isError($res))
			{
				DBUtil::rollback();
				return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
			}


			// Ensure all values for fieldsets common to both document types are retained
			$fs_ids = array();

			$doctype_fieldsets = KTFieldSet::getForDocumentType($doctypeid);
			foreach($doctype_fieldsets as $fieldset)
			{
				$fs_ids[] = $fieldset->getId();
			}

			$MDPack = array();
			foreach ($fieldsets as $oFieldset)
			{
				if ($oFieldset->getIsGeneric() || in_array($oFieldset->getId(), $fs_ids))
				{
					$fields = $oFieldset->getFields();

					foreach ($fields as $oField)
					{
						$val = isset($field_values[$oField->getId()]) ? $field_values[$oField->getId()] : '';

						if (!empty($val))
						{
							$MDPack[] = array($oField, $val);
						}
					}
				}
			}

			$core_res = KTDocumentUtil::saveMetadata($this->document, $MDPack, array('novalidate' => true));

			if (PEAR::isError($core_res)) {
				DBUtil::rollback();
				return $core_res;
			}



		    $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
            $aTriggers = $oKTTriggerRegistry->getTriggers('edit', 'postValidate');

            foreach ($aTriggers as $aTrigger)
            {
                $sTrigger = $aTrigger[0];
                $oTrigger = new $sTrigger;
                $aInfo = array(
                    "document" => $this->document,
                    "aOptions" => $packed,
                );
                $oTrigger->setInfo($aInfo);
                $ret = $oTrigger->postValidate();
            }

            DBUtil::commit();

		}
	}

	/**
	 * Changes the title of the document.
	 * If the title contains any invalid characters they are replaced with a dash (-). For example: ?, *, %, \, /
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $newname The new document title
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function rename($newname)
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}
		$newname = KTUtil::replaceInvalidCharacters($newname);

		if ($this->document->getName() != $newname)
		{

			DBUtil::startTransaction();
			$this->document->setName($newname);
			$res = $this->document->update();

			if (PEAR::isError($res))
			{
				DBUtil::rollback();
				return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res);
			}
			DBUtil::commit();
		}
	}

	/**
	 * Sets the status of the document to 'archived'.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $reason The reason for archiving the document
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function archive($reason)
	{
		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		DBUtil::startTransaction();
		$res = KTDocumentUtil::archive($this->document, $reason);

        if (PEAR::isError($res)) {
            DBUtil::rollback();
            return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res);
        }
        DBUtil::commit();
	}

	/**
	 * Starts a workflow on a document.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $workflow The workflow being applied
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function start_workflow($workflow)
	{
		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid = $this->document->getWorkflowId();

		if (!empty($workflowid))
		{
			return new PEAR_Error('A workflow is already defined.');
		}

		$workflow = KTWorkflow::getByName($workflow);
		if (is_null($workflow) || PEAR::isError($workflow))
		{
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $workflow);
		}

		DBUtil::startTransaction();
		$result = KTWorkflowUtil::startWorkflowOnDocument($workflow, $this->document);
		if (is_null($result) || PEAR::isError($result))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $result);
		}
		DBUtil::commit();
	}

	/**
	 * This deletes the workflow on the document.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function delete_workflow()
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid=$this->document->getWorkflowId();
		if (empty($workflowid))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_NOT_IN_PROGRESS);
		}

		DBUtil::startTransaction();
		$result = KTWorkflowUtil::startWorkflowOnDocument(null, $this->document);
		if (is_null($result) || PEAR::isError($result))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID,$result);
		}
		DBUtil::commit();
	}

	/**
	 * This performs a transition to a new state of the workflow on the document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $transition The transition to perform
	 * @param string $reason The reason for transitioning the document to a new state
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function perform_workflow_transition($transition, $reason)
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid=$this->document->getWorkflowId();
		if (empty($workflowid))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_NOT_IN_PROGRESS);
		}

		$transition = &KTWorkflowTransition::getByName($transition);
		if (is_null($transition) || PEAR::isError($transition))
		{
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $transition);
		}

		DBUtil::startTransaction();
		$result = KTWorkflowUtil::performTransitionOnDocument($transition, $this->document, $user, $reason);
		if (is_null($result) || PEAR::isError($result))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $transition);
		}
		DBUtil::commit();
	}

	/**
	 * This returns all metadata for the document.
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
	 * $metadata = $document->get_metadata();
	 * foreach($metadata as $fieldset) {
	 *     echo '<br><br>Fieldset: '.$fieldset['fieldset'];
	 *
	 *     foreach($fieldset['fields'] as $field) {
	 *         echo '<br>Field name: '.$field['name'] . ' Value: '. $field['value'];
	 *     }
	 * }
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return array An array of metadata fieldsets and fields
	 */
	function get_metadata()
	{
		 $doctypeid = $this->document->getDocumentTypeID();
		 $fieldsets = (array) KTMetadataUtil::fieldsetsForDocument($this->document, $doctypeid);
		 if (is_null($fieldsets) || PEAR::isError($fieldsets))
		 {
		     return array();
		 }

		 $results = array();

		 foreach ($fieldsets as $fieldset)
		 {
		    // this line caused conditional metadata to not be present, and it is there when this is commented out;
		    // if there are problems with conditional metadata in future, check here to make sure this is not the cause
//		 	if ($fieldset->getIsConditional()) {	/* this is not implemented...*/	continue;	}

		 	$fields = $fieldset->getFields();
		 	$result = array('fieldset' => $fieldset->getName(),
		 					'description' => $fieldset->getDescription());

		 	$fieldsresult = array();

            foreach ($fields as $field)
            {
                $value = '';

				$fieldvalue = DocumentFieldLink::getByDocumentAndField($this->document, $field);
                if (!is_null($fieldvalue) && (!PEAR::isError($fieldvalue)))
                {
                	$value = $fieldvalue->getValue();
                }

                // Old
                //$controltype = 'string';
                // Replace with true
                $controltype = strtolower($field->getDataType());

                if ($field->getHasLookup())
                {
                	$controltype = 'lookup';
                    if ($field->getHasLookupTree())
                    {
                    	$controltype = 'tree';
                    }
                }

                // Options - Required for Custom Properties
                $options = array();

                if ($field->getInetLookupType() == 'multiwithcheckboxes' || $field->getInetLookupType() == 'multiwithlist') {
                    $controltype = 'multiselect';
                }

                switch ($controltype)
                {
                	case 'lookup':
                		$selection = KTAPI::get_metadata_lookup($field->getId());
                		break;
                	case 'tree':
                		$selection = KTAPI::get_metadata_tree($field->getId());
                		break;
                    case 'large text':
                        $options = array(
                                'ishtml' => $field->getIsHTML(),
                                'maxlength' => $field->getMaxLength()
                            );
                        $selection= array();
                        break;
                    case 'multiselect':
                        $selection = KTAPI::get_metadata_lookup($field->getId());
                        $options = array(
                                'type' => $field->getInetLookupType()
                            );
                        break;
                	default:
                		$selection= array();
                }


                $fieldsresult[] = array(
                	'fieldid' => $field->getId(),
                	'name' => $field->getName(),
                	'required' => $field->getIsMandatory(),
                    'value' => $value == '' ? 'n/a' : $value,
                    'blankvalue' => $value=='' ? '1' : '0',
                    'description' => $field->getDescription(),
                    'control_type' => $controltype,
                    'selection' => $selection,
                    'options' => $options,

                );

            }
            $result['fields'] = $fieldsresult;
            $results [] = $result;
		 }

		 return $results;
	}

	/**
	 * This returns all tags for the document.
	 *
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return array An array of metadata fieldsets for tags
	 */
	function get_tag($sTagCloudFieldsetName = 'tag cloud')
	{
		$fieldsets = (array) KTMetadataUtil::fieldsetsByNameForDocument($this->document, $sTagCloudFieldsetName);

		 if (is_null($fieldsets) || PEAR::isError($fieldsets))
		 {
		     return array();
		 }

		 $results = array();

		 foreach ($fieldsets as $fieldset)
		 {
		    // this line caused conditional metadata to not be present, and it is there when this is commented out;
		    // if there are problems with conditional metadata in future, check here to make sure this is not the cause
//		 	if ($fieldset->getIsConditional()) {	/* this is not implemented...*/	continue;	}

		 	$fields = $fieldset->getFields();
		 	$result = array('fieldset' => $fieldset->getName(),
		 					'description' => $fieldset->getDescription());

		 	$fieldsresult = array();

            foreach ($fields as $field)
            {
                $value = '';

				$fieldvalue = DocumentFieldLink::getByDocumentAndField($this->document, $field);
                if (!is_null($fieldvalue) && (!PEAR::isError($fieldvalue)))
                {
                	$value = $fieldvalue->getValue();
                }

                // Old
                //$controltype = 'string';
                // Replace with true
                $controltype = strtolower($field->getDataType());

                if ($field->getHasLookup())
                {
                	$controltype = 'lookup';
                    if ($field->getHasLookupTree())
                    {
                    	$controltype = 'tree';
                    }
                }

                // Options - Required for Custom Properties
                $options = array();

                if ($field->getInetLookupType() == 'multiwithcheckboxes' || $field->getInetLookupType() == 'multiwithlist') {
                    $controltype = 'multiselect';
                }

                switch ($controltype)
                {
                	case 'lookup':
                		$selection = KTAPI::get_metadata_lookup($field->getId());
                		break;
                	case 'tree':
                		$selection = KTAPI::get_metadata_tree($field->getId());
                		break;
                    case 'large text':
                        $options = array(
                                'ishtml' => $field->getIsHTML(),
                                'maxlength' => $field->getMaxLength()
                            );
                        $selection= array();
                        break;
                    case 'multiselect':
                        $selection = KTAPI::get_metadata_lookup($field->getId());
                        $options = array(
                                'type' => $field->getInetLookupType()
                            );
                        break;
                	default:
                		$selection= array();
                }


                $fieldsresult[] = array(
                	'fieldid' => $field->getId(),
                	'name' => $field->getName(),
                	'required' => $field->getIsMandatory(),
                    'value' => $value == '' ? 'n/a' : $value,
                    'blankvalue' => $value=='' ? '1' : '0',
                    'description' => $field->getDescription(),
                    'control_type' => $controltype,
                    'selection' => $selection,
                    'options' => $options,

                );

            }
            $result['fields'] = $fieldsresult;
            $results [] = $result;
		 }

		 return $results;
	}

    /**
     * Gets a simple array of document metadata fields
     *
     * <code>
     * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
     * $document = $ktapi->get_document_by_id($documentid);
     * $metadata = $document->get_packed_metadata();
     * foreach($metadata as $field) {
     *     echo '<br><br>Fieldset: ' . $field[0]->getParentFieldset();
     *     echo '<br>Field name: ' .$field[0]->getName();
     *     echo ' Value: ' . $field[1];
     * }
     * </code>
     *
     * @author KnowledgeTree Team
     * @access public
     * @param array $metadata The full metadata fieldsets and fields
     * @return array An array of metadata field object and value pairs
     */
    function get_packed_metadata($metadata = null)
    {
        global $default;

        if (is_null($metadata)) {
            $metadata = $this->get_metadata();
        }

        $packed = array();

        foreach($metadata as $fieldset_metadata) {
           if (is_array($fieldset_metadata)) {
               $fieldsetname = $fieldset_metadata['fieldset'];
               $fields = $fieldset_metadata['fields'];
           }
           else if ($fieldset_metadata instanceof stdClass) {
               $fieldsetname = $fieldset_metadata->fieldset;
               $fields = $fieldset_metadata->fields;
           }
           else {
               $default->log->debug("unexpected fieldset type");
               continue;
           }

           $fieldset = KTFieldset::getByName($fieldsetname);
           if (is_null($fieldset) || PEAR::isError($fieldset) || $fieldset instanceof KTEntityNoObjects) {
               $default->log->debug("could not resolve fieldset: $fieldsetname for document id: $this->documentid");
               // exit graciously
               continue;
           }

           foreach($fields as $fieldinfo) {
               if (is_array($fieldinfo)) {
                   $fieldname = $fieldinfo['name'];
                   // if the 'blankvalue' argument was set to 1 (true) then do not use the current value;
                   // this prevents the 'n/a' values set for blank fields on get_metadata from being saved as such
                   // while allowing user entered values of 'n/a' to be saved
                   $value = ($fieldinfo['value'] == 'n/a' && $fieldinfo['blankvalue']) ? '' : $fieldinfo['value'];
               }
               else if ($fieldinfo instanceof stdClass) {
                   $fieldname = $fieldinfo->name;
                   $value = $fieldinfo->value;
               }
               else {
                   $default->log->debug("unexpected fieldinfo type");
                   continue;
               }

               $field = DocumentField::getByFieldsetAndName($fieldset, $fieldname);
               if (is_null($field) || PEAR::isError($field) || $field instanceof KTEntityNoObjects) {
                   $default->log->debug("Could not resolve field: $fieldname on fieldset $fieldsetname for document id: $this->documentid");
                   // exit graciously
                   continue;
               }

               $packed[] = array($field, $value);
           }
        }

        return $packed;
    }

    /**
     * This updates the metadata on the document. This includes the 'title'.
     *
     * <code>
     * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
     * $document = $ktapi->get_document_by_id($documentid);
     * $metadata = $document->get_metadata();
     * foreach($metadata as $key => $fieldset) {
     *     if ($fieldset['fieldset'] == 'XYZ') {
     *
     *         foreach($fieldset['fields'] as $k => $field) {
     *             if ($field['name'] == 'ABC') {
     *                 $metadata[$key][fields][$k]['value'] = 'new value';
     *             }
     *         }
     *     }
     * }
     *
     * $res = $document->update_metadata($metadata);
     * </code>
     *
     * @author KnowledgeTree Team
     * @access public
     * @param array This is an array containing the metadata to be associated with the document.
     * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
     */
    function update_metadata($metadata)
    {
        global $default;

        if (empty($metadata)) {
            return;
        }

        $packed = $this->get_packed_metadata($metadata);

        DBUtil::startTransaction();

        $user = $this->ktapi->get_user();
        $this->document->setLastModifiedDate(getCurrentDateTime());
        $this->document->setModifiedUserId($user->getId());

        // Update the content version / document version
        if ($default->updateContentVersion) {
            $this->document->startNewContentVersion($user);
            $this->document->setMinorVersionNumber($this->document->getMinorVersionNumber()+1);
        }
        else {
            $this->document->startNewMetadataVersion($user);
        }

        $res = $this->document->update();
        if (PEAR::isError($res)) {
            DBUtil::rollback();
            return new KTAPI_Error('Unexpected failure updating document', $res);
        }

        $result = KTDocumentUtil::saveMetadata($this->document, $packed, array('novalidate' => true));

        if (is_null($result)) {
            DBUtil::rollback();
            return new PEAR_Error(KTAPI_ERROR_INTERNAL_ERROR . ': Null result returned but not expected.');
        }

        if (PEAR::isError($result)) {
            DBUtil::rollback();
            return new KTAPI_Error('Unexpected validation failure', $result);
        }

        DBUtil::commit();

        $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $aTriggers = $oKTTriggerRegistry->getTriggers('edit', 'postValidate');

        foreach ($aTriggers as $aTrigger) {
            $sTrigger = $aTrigger[0];
            $oTrigger = new $sTrigger;
            $aInfo = array(
                "document" => $this->document,
                "aOptions" => $packed,
            );
            $oTrigger->setInfo($aInfo);
            $ret = $oTrigger->postValidate();
        }

        // update document object with additional fields / data from the triggers
        $this->document = Document::get($this->document->iId);
        $folder = Folder::get($this->document->getFolderID());

        // create the document transaction record
        $documentTransaction = new DocumentTransaction($this->document, _kt('Document metadata updated'), 'ktcore.transactions.update');
        $documentTransaction->create();

        // Check if there are any dynamic conditions / permissions that need to be updated on the document
        // If there are dynamic conditions then update the permissions on the document
        // The dynamic condition test fails unless the document exists in the DB therefore update permissions after committing the transaction.
        include_once(KT_LIB_DIR.'/permissions/permissiondynamiccondition.inc.php');
        $iPermissionObjectId = $folder->getPermissionObjectID();
        $dynamicCondition = KTPermissionDynamicCondition::getByPermissionObjectId($iPermissionObjectId);

        if (!PEAR::isError($dynamicCondition) && !empty($dynamicCondition)) {
            $res = KTPermissionUtil::updatePermissionLookup($this->document);
            KTPermissionUtil::clearCache();
        }
    }

	/**
	 * This updates the system metadata on the document.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param array $sysdata The system metadata to be applied
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function update_sysdata($sysdata)
	{
		global $default;
		if (empty($sysdata))
		{
			return;
		}
		$owner_mapping = array(
						'created_by'=>'creator_id',
						'modified_by'=>'modified_user_id',
						'owner'=>'owner_id'
						);

		$documents = array();
		$document_content = array();
		$indexContent = null;
		$uniqueOemNo = false;

		foreach($sysdata as $rec)
		{
			if (is_object($rec))
			{
				$name = $rec->name;
				$value = sanitizeForSQL($rec->value);
			}
			elseif (is_array($rec))
			{
				$name = $rec['name'];
				$value = sanitizeForSQL($rec['value']);
			}
			else
			{
				// just ignore
				continue;
			}
			switch(strtolower($name))
			{
				case 'unique_oem_document_no':
					$documents['oem_no'] = $value;
					$uniqueOemNo = true;
					break;
				case 'oem_document_no':
					$documents['oem_no'] = $value;
					break;
				case 'index_content':
					$indexContent = $value;
					break;
				case 'created_date':
					if (!empty($value)) $documents['created'] = $value;
					break;
				case 'modified_date':
					if (!empty($value)) $documents['modified'] = $value;
					break;
				case 'is_immutable':
					$documents['immutable'] = in_array(strtolower($value), array('1','true','on','yes'))?'1':'0';
					break;
				case 'filename':
					$value = KTUtil::replaceInvalidCharacters($value);
					$document_content['filename'] = $value;
					break;
				case 'major_version':
					$document_content['major_version'] = $value;
					break;
				case 'minor_version':
					$document_content['minor_version'] = $value;
					break;
				case 'version':
					list($major_version, $minor_version) = explode('.', $value);
					$document_content['major_version'] = $major_version;
					$document_content['minor_version'] = $minor_version;
					break;
				case 'mime_type':
					$sql = "select id from mime_types where mimetypes='$value'";
					$value = DBUtil::getResultArray($sql);
					if (PEAR::isError($value))
					{
						$default->log->error("Problem resolving mime type '$value' for document id $this->documentid. Reason: " . $value->getMessage());
						return $value;
					}
					if (count($value) == 0)
					{
						$default->log->error("Problem resolving mime type '$value' for document id $this->documentid. None found.");
						break;
					}
					$value = $value[0]['id'];
					$document_content['mime_id'] = $value;
					break;
				case 'owner':
				case 'created_by':
				case 'modified_by':
					$sql = "select id from users where name='$value'";
					$userId = DBUtil::getResultArray($sql);
					if (PEAR::isError($userId))
					{
						$default->log->error("Problem resolving user '$value' for document id $this->documentid. Reason: " . $userId->getMessage());
						return $userId;
					}
					if (empty($userId))
					{
						$sql = "select id from users where username='$value'";
						$userId = DBUtil::getResultArray($sql);
						if (PEAR::isError($userId))
						{
							$default->log->error("Problem resolving username '$value' for document id $this->documentid. Reason: " . $userId->getMessage());
							return $userId;
						}
					}
					if (empty($userId))
					{
						$default->log->error("Problem resolving user based on '$value' for document id $this->documentid. No user found");
						// if not found, not much we can do
						break;
					}
					$userId=$userId[0];
					$userId=$userId['id'];

					$name = $owner_mapping[$name];
					$documents[$name] = $userId;
					break;
				default:
					$default->log->error("Problem updating field '$name' with value '$value' for document id $this->documentid. Field is unknown.");
					// TODO: we should do some logging
					//return new PEAR_Error('Unexpected field: ' . $name);
			}
		}

		if (count($documents) > 0)
		{
			$sql = "UPDATE documents SET ";
			$i=0;
			foreach($documents as $name=>$value)
			{
				if ($i++ > 0) $sql .= ",";
				$value = sanitizeForSQL($value);
				$sql .= "$name='$value'";
			}
			$sql .= " WHERE id=$this->documentid";
			$result = DBUtil::runQuery($sql);
			if (PEAR::isError($result))
			{
				return $result;
			}

			if ($uniqueOemNo)
			{
				$oem_no = sanitizeForSQL($documents['oem_no']);
				$sql = "UPDATE documents SET oem_no=null WHERE oem_no = '$oem_no' AND id != $this->documentid";
				$result = DBUtil::runQuery($sql);
			}

		}
		if (count($document_content) > 0)
		{
			$content_id = $this->document->getContentVersionId();
			$sql = "UPDATE document_content_version SET ";
			$i=0;
			foreach($document_content as $name=>$value)
			{
				if ($i++ > 0) $sql .= ",";
				$value = sanitizeForSQL($value);
				$sql .= "$name='$value'";
			}
			$sql .= " WHERE id=$content_id";
			$result = DBUtil::runQuery($sql);
			if (PEAR::isError($result))
			{
				return $result;
			}
		}
		if (!is_null($indexContent))
		{
			$indexer = Indexer::get();
			$result = $indexer->diagnose();
			if (empty($result))
			{
				$indexer->updateDocumentIndex($this->documentid, $indexContent);
			}
			else
			{
				$default->log->error("Problem updating index with value '$value' for document id $this->documentid. Problem with indexer.");
			}
		}
	}

	/**
	 * This updates the tag on the document.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param string $tag_word The tag to be added
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function update_tag($tag_word)
	{
		$metadata = $this->get_metadata();

		$num_metadata = count($metadata++);
		for ($i = 0; $i < $num_metadata; $i++)
		{
			//look for the "Tag Cloud" fieldset
			if (strtolower($metadata[$i]['fieldset']) == "tag cloud")
			{
				$metadata[$i]['fields'][0][value] = $tag_word;
			}
		}

		return ($this->update_metadata($metadata));
	}

	/**
	 * Clears the cached data on the document and refreshes the document object.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	function clearCache()
	{
		// TODO: we should only clear the cache for the document we are working on
		// this is a quick fix but not optimal!!


		$metadataid = $this->document->getMetadataVersionId();
		$contentid = $this->document->getContentVersionId();

		$cache = KTCache::getSingleton();

		$cache->remove('KTDocumentMetadataVersion/id', $metadataid);
		$cache->remove('KTDocumentContentVersion/id', $contentid);
		$cache->remove('KTDocumentCore/id', $this->documentid);
		$cache->remove('Document/id', $this->documentid);
		unset($GLOBALS['_OBJECTCACHE']['KTDocumentMetadataVersion'][$metadataid]);
		unset($GLOBALS['_OBJECTCACHE']['KTDocumentContentVersion'][$contentid]);
		unset($GLOBALS['_OBJECTCACHE']['KTDocumentCore'][$this->documentid]);

		$this->document = &Document::get($this->documentid);
	}

	/**
	 * Merge new metadata with previous metadata version
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function mergeWithLastMetadataVersion()
	{
		// keep latest metadata version
		$metadata_version = $this->document->getMetadataVersion();
		if ($metadata_version == 0)
		{
			// this could theoretically happen in the case we are updating metadata and sysdata, but no metadata fields are specified.
			return;
		}

		$metadata_id = $this->document->getMetadataVersionId();

		// get previous version
		$sql = "SELECT id, metadata_version FROM document_metadata_version WHERE id<$metadata_id AND document_id=$this->documentid order by id desc";
		$old = DBUtil::getResultArray($sql);
		if (is_null($old) || PEAR::isError($old))
		{
			return new PEAR_Error('Previous version could not be resolved');
		}
		// only interested in the first one
		$old=$old[0];
		$old_metadata_id = $old['id'];
		$old_metadata_version = $old['metadata_version'];

		DBUtil::startTransaction();

		// delete previous metadata version

		$sql = "DELETE FROM document_metadata_version WHERE id=$old_metadata_id";
		$rs = DBUtil::runQuery($sql);
		if (PEAR::isError($rs))
		{
			DBUtil::rollback();
			return $rs;
		}

		// make latest equal to previous
		$sql = "UPDATE document_metadata_version SET metadata_version=$old_metadata_version WHERE id=$metadata_id";
		$rs = DBUtil::runQuery($sql);
		if (PEAR::isError($rs))
		{
			DBUtil::rollback();
			return $rs;
		}
		$sql = "UPDATE documents SET metadata_version=$old_metadata_version WHERE id=$this->documentid";
		$rs = DBUtil::runQuery($sql);
		if (PEAR::isError($rs))
		{
			DBUtil::rollback();
			return $rs;
		}
		DBUtil::commit();

		$this->clearCache();
	}

	/**
	 * This returns the workflow transitions available for the user on the document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return array|PEAR_Error Array of the workflow transitions | a PEAR_Error on failure
	 */
	function get_workflow_transitions()
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid=$this->document->getWorkflowId();
		if (empty($workflowid))
		{
			return array();
		}

		$result = array();

		$transitions = KTWorkflowUtil::getTransitionsForDocumentUser($this->document, $user);
		if (is_null($transitions) || PEAR::isError($transitions))
		{
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $transitions);
		}
		foreach($transitions as $transition)
		{
			$result[] = $transition->getName();
		}

		return $result;
	}

	/**
	 * This returns the current workflow state
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return string Returns the name of the state | a PEAR_Error on failure
	 */
	function get_workflow_state()
	{
		$user = $this->can_user_access_object_requiring_permission($this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid=$this->document->getWorkflowId();
		if (empty($workflowid))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_NOT_IN_PROGRESS);
		}

		$result = array();

		$state = KTWorkflowUtil::getWorkflowStateForDocument($this->document);
		if (is_null($state) || PEAR::isError($state))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_INVALID);
		}

		$statename = $state->getName();

		return $statename;

	}

	/**
	 * Get the available permissions on the document.
	 * R = read, W = write, E = edit - if the document is checked out by the user.
	 * The method assumes read permissions is available.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param Document $document The internal document object
	 * @return string The available permissions
	 */
	function get_permission_string($document)
	{
		$perms = 'R';

		if (Permission::userHasDocumentWritePermission($document)) {
			$perms .= 'W';

			$user_id = $_SESSION['userID'];
			$co_user_id = $document->getCheckedOutUserID();

			if (!empty($co_user_id) && ($user_id == $co_user_id)) {
				$perms .= 'E';
			}
		}

		// delete document is a separate permission to the write permission
		if (Permission::userHasDeleteDocumentPermission($document)) {
		    $perms .= 'D';
		}

		if (Permission::userHasSecurityFolderPermission($document)) {
		    $perms .= 'S';
		}

		return $perms;
	}

	/**
	 * This returns detailed information on the document.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return array The document information
	 */
	function get_detail()
	{
		global $default;
		// make sure we ge tthe latest
		$this->clearCache();

		$wsversion = $this->ktapi->getVersion();

		$detail = array();
		$document = $this->document;

		// get the document id
		$detail['document_id'] = (int) $document->getId();

		$oem_document_no = null;
		if ($wsversion >= 2)
		{
			$oem_document_no = $document->getOemNo();
		}
		if (empty($oem_document_no))
		{
			$oem_document_no = 'n/a';
		}

		$detail['custom_document_no'] = 'n/a';
		$detail['oem_document_no'] = $oem_document_no;

		// get the title
		$detail['title'] = $document->getName();

		// get the document type
		$documenttypeid=$document->getDocumentTypeID();
		$documenttype = '* unknown *';
		if (is_numeric($documenttypeid))
		{
			$dt = DocumentType::get($documenttypeid);

			if (!is_null($dt) && !PEAR::isError($dt))
			{
				$documenttype=$dt->getName();
			}
		}
		$detail['document_type'] = $documenttype;

		// get the filename
		$detail['filename'] = $document->getFilename();

		// get the filesize
		$detail['filesize'] = (int) $document->getFileSize();

		// get the folder id
		$detail['folder_id'] = (int) $document->getFolderID();

		// get the creator
		$userid = $document->getCreatorID();
		$user_name = $user_username = 'n/a';
		if (is_numeric($userid))
		{
			$user_name = $user_username = '* unknown *';
			$user = User::get($userid);
			if (!is_null($user) && !PEAR::isError($user))
			{
				$user_name = $user->getName();
				if ($wsversion >= 3)
				{
					$user_username = $user->getUserName();
				}
			}
		}
		$detail['created_by'] = $user_name;
		if ($wsversion >= 3)
		{
			$detail['created_by_user_name'] = $user_username;
		}

		// get the creation date
		$detail['created_date'] = $document->getDisplayCreatedDateTime();

		// get the checked out user
		$userid = $document->getCheckedOutUserID();
		$user_name = $user_username = 'n/a';
		if (is_numeric($userid))
		{
			$user_name = $user_username = '* unknown *';
			$user = User::get($userid);
			if (!is_null($user) && !PEAR::isError($user))
			{
				$user_name = $user->getName();
				if ($wsversion >= 3)
				{
					$user_username = $user->getUserName();
				}
			}
		}
		$detail['checked_out_by'] = $user_name;
		if ($wsversion >= 3)
		{
			$detail['checked_out_by_user_name'] = $user_username;
		}

		// get the checked out date
		list($major, $minor, $fix) = explode('.', $default->systemVersion);
		if ($major == 3 && $minor >= 5)
		{
			$detail['checked_out_date'] = $document->getDisplayCheckedOutDate();
		}
		else
		{
			$detail['checked_out_date'] = $detail['modified_date'];
		}
		if (is_null($detail['checked_out_date'])) $detail['checked_out_date'] = 'n/a';

		// get the modified user
		$userid = $document->getModifiedUserId();
		$user_name = $user_username = 'n/a';
		if (is_numeric($userid))
		{
			$user_name = $user_username = '* unknown *';
			$user = User::get($userid);
			if (!is_null($user) && !PEAR::isError($user))
			{
				$user_name = $user->getName();
				if ($wsversion >= 3)
				{
					$user_username = $user->getUserName();
				}
			}
		}
		$detail['modified_by'] = $detail['updated_by'] = $user_name;
		if ($wsversion >= 3)
		{
			$detail['modified_by_user_name'] = $user_username;
		}

		// get the modified date
		$detail['updated_date'] = $detail['modified_date'] = $document->getDisplayLastModifiedDate();

		// get the owner
		$userid = $document->getOwnerID();
		$user_name = $user_username = 'n/a';
		if (is_numeric($userid))
		{
			$user_name = $user_username = '* unknown *';
			$user = User::get($userid);
			if (!is_null($user) && !PEAR::isError($user))
			{
				$user_name = $user->getName();
				if ($wsversion >= 3)
				{
					$user_username = $user->getUserName();
				}
			}
		}
		$detail['owned_by'] = $user_name;
		if ($wsversion >= 3)
		{
			$detail['owned_by_user_name'] = $user_username;
		}

		// get the version
		$detail['version'] = $document->getVersion();
		if ($wsversion >= 2)
		{
			$detail['version'] = (float) $detail['version'];
		}
		//get the content_id
        $detail['content_id'] = $document->getContentVersionId();

		//might be unset at the bottom in case of old webservice version
		//make sure we're using the real document for this one
		$this->document->switchToRealCore();
		$detail['linked_document_id'] = $document->getLinkedDocumentId();
		$this->document->switchToLinkedCore();

		// check immutability
		$detail['is_immutable'] = (bool) $document->getImmutable();

		// check permissions
		$detail['permissions'] = KTAPI_Document::get_permission_string($document);

		// get workflow name
		$workflowid = $document->getWorkflowId();
		$workflowname='n/a';
		if (is_numeric($workflowid))
		{
			$workflow = KTWorkflow::get($workflowid);
			if (!is_null($workflow) && !PEAR::isError($workflow))
			{
				$workflowname = $workflow->getName();
			}
		}
		$detail['workflow'] = $workflowname;

		// get the workflow state
		$stateid = $document->getWorkflowStateId();
		$workflowstate = 'n/a';
		if (is_numeric($stateid))
		{
			$state = KTWorkflowState::get($stateid);
			if (!is_null($state) && !PEAR::isError($state))
			{
				$workflowstate = $state->getName();
			}
		}
		$detail['workflow_state']=$workflowstate;

		// get the full path
		$detail['full_path'] = '/' . $this->document->getFullPath();

		// get mime info
		$mimetypeid = $document->getMimeTypeID();
		$detail['extension'] =KTMime::getFileType($mimetypeid);
		$detail['mime_type'] =KTMime::getMimeTypeName($mimetypeid);
		$detail['mime_icon_path'] =KTMime::getIconPath($mimetypeid);
		$detail['mime_display'] =KTMime::getFriendlyNameForString($detail['mime_type']);

		// get the storage path
		$detail['storage_path'] = $document->getStoragePath();

		if ($wsversion >= 2)
		{
			unset($detail['updated_by']);
			unset($detail['updated_date']);
		}
		if ($wsversion < 3) {
			unset($detail['linked_document_id']);
		}

		if ($wsversion >= 3)
		{
			//clean URI
			$url = KTBrowseUtil::getUrlForDocument($document);
			$detail['clean_uri'] = $url;

			$document_status_id = $document->getStatusID();
			$detail['document_status'] = Document::getStatusString($document_status_id);

			//need to get latest check-in date
			$aTransactionsByDocument = DocumentTransaction::getByDocumentFilterByNamespace($document, 'ktcore.transactions.check_in');

			$newest_date_so_far = null;
			$newest_date_as_string = 'n/a';

			//look for the latest date
			foreach($aTransactionsByDocument as $oTransaction)
			{
				$date = strtotime($oTransaction->getDate());

				if ($date > $newest_date_so_far)
				{
					$newest_date_so_far = $date;
					$newest_date_as_string = $oTransaction->getDate();
				}
			}

			$detail['checked_in_date'] = $newest_date_as_string;
		}

		return $detail;
	}

	/**
	 * Gets the title of the document
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
	 * $title = $document->get_title();
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return string The document title
	 */
	function get_title()
	{
		return $this->document->getDescription();
	}

	/**
	 * Gets the content version id of the document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return integer the content version id
	*/
	function get_content_version()
	{
		return $this->document->getContentVersionId();
	}

	/**
	 * Gets the url which can be used to download the document.
	 *
	 * @param int $version Not implemented. The content version of the document
	 */
	function get_download_url($version = null)
	{
	    $session = $this->ktapi->get_session();

	    // Create the url that can be used to download the document
    	$download_manager = new KTDownloadManager();
    	$download_manager->set_session($session->session);
    	$download_manager->cleanup();
    	$url = $download_manager->allow_download($this);

    	// Log the transaction
    	$this->download();

    	return $url;
	}

	/**
	 * Logs the document transaction for a download.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	function download($version = null)
	{
		$document_status = $this->document->getStatusID();

		switch ($document_status) {
			case LIVE:
				//just ignore
				break;
			case PUBLISHED:
				//just ignore
				break;
			case DELETED:
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_DELETED);
				break;
			case ARCHIVED:
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_ARCHIVED);
				break;
			case STATUS_INCOMPLETE:
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_UNAVAILABLE);
				break;
			case VERSION_DELETED:
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_VERSION_DELETED);
				break;
		}

		if (isset($version) && $version !== '')
		{
			$content_version_status_id = $this->document->getContentVersionStatus($version);

			if ($content_version_status_id == VERSION_DELETED)
			{
				return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_VERSION_DELETED);
			}
		}

		$oStorage = KTStorageManagerUtil::getSingleton();
        $options = array();

        $comment = (!is_null($version)) ? 'Document version '.$version.' downloaded' : 'Document downloaded';
        $oDocumentTransaction = new DocumentTransaction($this->document, 'Document downloaded', 'ktcore.transactions.download', $aOptions);
        return $oDocumentTransaction->create();
	}

    /**
     * Function to fetch the actual file content of a document
     *
     * @return $content the document file content
     */
    function get_document_content()
    {
        // fetch the content
        $content = KTDocumentUtil::getDocumentContent($this->document);

        // TODO what if the file could not be found?

    	// Log the transaction
    	$this->download();

        // return the document content
        return $content;
    }

	/**
	 * This returns the transaction history for the document.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return array The list of transactions | a PEAR_Error on failure
	 */
	function get_transaction_history()
	{
        $sQuery = 'SELECT DTT.name AS transaction_name, U.name AS username, U.username AS user_username, DT.version AS version, DT.comment AS comment, DT.datetime AS datetime ' .
            'FROM ' . KTUtil::getTableName('document_transactions') . ' AS DT INNER JOIN ' . KTUtil::getTableName('users') . ' AS U ON DT.user_id = U.id ' .
            'INNER JOIN ' . KTUtil::getTableName('transaction_types') . ' AS DTT ON DTT.namespace = DT.transaction_namespace ' .
            'WHERE DT.document_id = ? ORDER BY DT.datetime DESC';
        $aParams = array($this->documentid);

        $transactions = DBUtil::getResultArray(array($sQuery, $aParams));
        if (is_null($transactions) || PEAR::isError($transactions))
        {
        	return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $transactions  );
        }

		foreach($transactions as $key=>$transaction)
		{
			$transactions[$key]['version'] = (float) $transaction['version'];
			$transactions[$key]['datetime'] = datetimeutil::getLocaleDate($transactions[$key]['datetime']);
		}


        return $transactions;
	}

    /**
     * This returns the version history on the document.
     *
     * @author KnowledgeTree Team
     * @access public
     * @return array The version history
     */
    function get_version_history()
    {
        $metadata_versions = KTDocumentMetadataVersion::getByDocument($this->document);
        $wsversion = $this->ktapi->getVersion();
        $versions = array();

        foreach ($metadata_versions as $version)
        {
            $versionInfo = array();

            $document = &Document::get($this->documentid, $version->getId());
            $userid = $document->getModifiedUserId();
            $user = User::get($userid);
            $username = $user_username = 'Unknown';
            if (!PEAR::isError($user))
            {
                $username = is_null($user) ? 'n/a' : $user->getName();
                $user_username = is_null($user) ? 'n/a' : $user->getUserName();
            }

            $versionInfo['user'] = $username;
            $versionInfo['metadata_version'] = $document->getMetadataVersion();
            $versionInfo['content_version'] = $document->getVersion();
            $versionInfo['datetime'] = $document->getDisplayVersionCreated();

            if ($wsversion >= 2)
            {
                $versionInfo['metadata_version'] = (int)$versionInfo['metadata_version'];
                $versionInfo['content_version'] = (float)$versionInfo['content_version'];
            }

            if ($wsversion >= 3)
            {
                $versionInfo['user_username'] = $user_username;
                $versionInfo['major_version'] = (int)$document->getMajorVersionNumber();
                $versionInfo['minor_version'] = (int)$document->getMinorVersionNumber();
            }

            $versions[] = $versionInfo;
        }

        return $versions;
    }

	/**
	 * Get the content version id using the document (content) version - major/minor version
	 *
	 * @param string $version
	 * @return int
	 */
	function get_content_version_id_from_version($version)
	{
	    return $this->document->getContentVersionIdFromVersion($version);
	}

	/**
	 * This expunges a document from the system.
	 *
	 * <code>
	 * $ktapi = new KTAPI();
     * $session = $ktapi->start_system_session();
	 * $document = $ktapi->get_document_by_id($documentid);
	 * if ($document->is_deleted()) {
     *     $document->expunge();
     * }
	 * </code>
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return void|PEAR_Error Returns nothing on success | a PEAR_Error on failure
	 */
	function expunge()
	{
		$oStorage =& KTStorageManagerUtil::getSingleton();

		if ($this->document->getStatusID() != 3)
		{
			return new PEAR_Error('You should not purge this');
		}

		DBUtil::startTransaction();

		$filename = $this->document->getFileName();
		$full_path = $this->document->getFullPath();
		$comment = sprintf(_kt("Document expunged: %s/%s"), $full_path, $filename);

		$transaction = new DocumentTransaction($this->document, $comment, 'ktcore.transactions.expunge');
        $transaction->create();
        $this->document->cleanupDocumentData($this->documentid);
		$result = $oStorage->expunge($this->document);
        $this->document->delete();

		DBUtil::commit();
	}

	/**
	 * Restores a deleted document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	function restore()
	{
		DBUtil::startTransaction();

		$oStorage = KTStorageManagerUtil::getSingleton();

		$folder = Folder::get($this->document->getRestoreFolderId());
		if (PEAR::isError($folder))
		{
			$this->document->setFolderId(1);
			$folder = Folder::get(1);
		}
		else
		{
			$this->document->setFolderId($this->document->getRestoreFolderId());
		}

		$oStorage->restore($this->document);

		$this->document->setStatusId(LIVE);
		$this->document->setPermissionObjectId($folder->getPermissionObjectId());
		$res = $this->document->update();

		$res = KTPermissionUtil::updatePermissionLookup($this->document);

		$user = $this->ktapi->get_user();

		$oTransaction = new DocumentTransaction($this->document, 'Restored from deleted state by ' . $user->getName(), 'ktcore.transactions.update');
		$oTransaction->create();

		DBUtil::commit();
	}

	/**
	 * Returns the internal document object
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return Document The document object
	 */
	public function getObject()
	{
	    return $this->document;
	}

	/**
	 * Get the role allocation for the document
	 *
	 * @return KTAPI_RoleAllocation Instance of the role allocation object
	 */
	public function getRoleAllocation()
	{
	    $allocation = KTAPI_RoleAllocation::getAllocation($this->ktapi, $this);

	    return $allocation;
	}

	/**
	 * Get the permission allocation for the document
	 *
	 * @return KTAPI_PermissionAllocation Instance of the permission allocation object
	 */
	public function getPermissionAllocation()
	{
	    $allocation = KTAPI_PermissionAllocation::getAllocation($this->ktapi, $this);

	    return $allocation;
	}

	/**
	 * Checks if the user is subscribed to the document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return bool $result TRUE if subscribed | FALSE if not
	 */
	public function isSubscribed()
	{
        $subscriptionType = SubscriptionEvent::subTypes('Document');
        $user = $this->ktapi->get_user();
        $document = $this->document;

        $result = Subscription::exists($user->getId(), $document->getId(), $subscriptionType);
        return $result;
	}

	/**
	 * Removes the users subscription to the document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return boolean|object $result SUCCESS Boolean result of operation | FAILURE - a pear error object
	 */
	public function unsubscribe()
	{
        if (!$this->isSubscribed())
        {
            return TRUE;
        }

        $subscriptionType = SubscriptionEvent::subTypes('Document');
        $user = $this->ktapi->get_user();
        $document = $this->document;

        $subscription = & Subscription::getByIDs($user->getId(), $document->getId(), $subscriptionType);
        $result = $subscription->delete();

        if (PEAR::isError($result)) {
            return $result->getMessage();
        }
        if ($result) {
            return $result;
        }

        return $_SESSION['errorMessage'];
	}

	/**
	 * Subscribes the user to the document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return boolean|object $result SUCCESS Boolean result of operation | FAILURE - a pear error object
	 */
	public function subscribe()
	{
        if ($this->isSubscribed())
        {
            return TRUE;
        }

        $subscriptionType = SubscriptionEvent::subTypes('Document');
        $user = $this->ktapi->get_user();
        $document = $this->document;

        $subscription = new Subscription($user->getId(), $document->getId(), $subscriptionType);
        $result = $subscription->create();

        if (PEAR::isError($result)) {
            return $result->getMessage();
        }
        if ($result) {
            return $result;
        }

        return $_SESSION['errorMessage'];
	}

	/**
	 * Checks if the document is immutable
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @return bool TRUE if it is immutable | FALSE if not
	 */
	public function isImmutable()
	{
	    return $this->document->getImmutable();
	}

	/**
	 * Sets a document to be immutable
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	public function immute()
	{
	    if ($this->is_checked_out()) {
	        return new PEAR_Error('Document is checked out and can\'t be finalized.');
	    }
        $this->document->setImmutable(true);
        $this->document->update();
	}

	/**
	 * Removes the immutability of a document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	public function unimmute()
	{
        $this->document->setImmutable(false);
        $this->document->update();
	}

	/**
	 * Emails a document as an attachment or hyperlink to a list of users, groups or external email addresses.
	 * In the case of external addresses, if a hyperlink is used then a timed download link (via webservices) is sent
	 * allowing the recipient a window period in which to download the document.
	 * The period is set through the webservices config option webservice/downloadExpiry. Defaults to 30 minutes.
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 * @param array $members The email recipients - KTPAI_Users, KTAPI_Groups or email addresses
	 * @param string $comment Content to be appended to the email
	 * @param bool $attachDocument TRUE if document is an attachment | FALSE if using a hyperlink to the document
	 *
	 * NOTE this function requires that the Email Plugin be active.
	 *      It seems that it is possible for this to be unintentionally turned off during a plugin re-read.
	 */
	public function email($members, $comment, $attachDocument = true)
	{
	    // check for active email plugin
        if (!KTPluginUtil::pluginIsActive('ktstandard.email.plugin')) {
            return new PEAR_Error('Email Plugin is not active');
        }
	    if (empty($members)) {
	        return new PEAR_Error('No recipients specified');
	    }

	    $userIds = array();
	    $groupIds = array();
	    $emailAddrs = array();

	    foreach($members as $member)
	    {
	        if ($member instanceof KTAPI_User) {
	            $userIds[] = $member->Id;
	        }
	        else if ($member instanceof KTAPI_Group) {
	            $groupIds[] = $member->Id;
	        }
	        else if (is_string($member)) {
	            $emailAddrs[] = $member;
	        }
	    }

        $config = KTConfig::getSingleton();
        $allowAttachment = $config->get('email/allowAttachment', false);
        $allowEmailAddresses = $config->get('email/allowEmailAddresses', false);

        // if attachments aren't allowed, set $attachDocument to false
        if (!$allowAttachment) {
            $attachDocument = false;
        }

        // If sending to external email addresses is not allowed - set array of external recipients to empty
        if (!$allowEmailAddresses) {
            $emailAddrs = array();
        }

        $emailErrors = array();
        $userEmails = array();
        $listEmails = array();

        sendGroupEmails($groupIds, $userEmails, $emailErrors);
        sendUserEmails($userIds, $userEmails, $emailErrors);

        if ($attachDocument) {
            sendManualEmails($emailAddrs, $userEmails, $emailErrors);
        }
        else {
            sendExternalEmails($emailAddrs, $this->documentid, $this->get_title(), $comment, $emailErrors);
        }

        if (empty($userEmails)) {
            return;
        }

        $listEmails = array_keys($userEmails);
        sendEmail($listEmails, $this->documentid, $this->get_title(), $comment, (boolean)$attachDocument, $emailErrors);

    }

	/**
	 * Method to add a Document to the User's History
	 *
	 * This integrates with the User History commercial plugin
	 * @author KnowledgeTree Team
	 * @access public
	 */
	public function addDocumentToUserHistory()
	{
		if (KTPluginUtil::pluginIsActive('brad.UserHistory.plugin')) {
			$path = KTPluginUtil::getPluginPath('brad.UserHistory.plugin');
            require_once($path .  'UserHistoryActions.php');

			$docAction = new UserHistoryDocumentAction($this->document, $this->ktapi->get_user());
			$docAction->_show();
		}
	}

    /**
     * Get a list of Documents
     *
     * @param  String  Where clause (not required)
     * @return Array array of Documents objects, false otherwise.
     */
    static public function getList($whereClause = null)
    {
        return Document::getList($whereClause);
    }


	/**
	 * Method to check whether the thumbnail preview of a document exists
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	public function thumbnailExists()
	{
		$oStorage = KTStorageManagerUtil::getSingleton();

		global $default;

		$varDir = $default->varDirectory;

		$thumbnailCheck = $varDir . '/thumbnails/'.$this->documentid.'.jpg';

		return $oStorage->file_exists($thumbnailCheck);
	}

	/**
	 * Method to generate a thumbnail for a document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	public function generateThumbnail()
	{
		// If thumbnail exists, return it
		if ($this->thumbnailExists()) {
			return TRUE;
		} else {
			require_once(KT_LIB_DIR . '/plugins/pluginutil.inc.php');

			// Check that plugin is enabled
			if (KTPluginUtil::pluginIsActive('thumbnails.generator.processor.plugin')) {
				$path = KTPluginUtil::getPluginPath('thumbnails.generator.processor.plugin');

				require_once($path .  'thumbnails.php');

				// Get mimetype
				$mimeType = KTMime::getMimeTypeName($this->document->getMimeTypeID());

				$thumbnailGenerator = new thumbnailGenerator();

				// Check that mimetype is valid
				if (in_array($mimeType, $thumbnailGenerator->getSupportedMimeTypes())) {

					// Setup
					$thumbnailGenerator->setDocument($this->document);

					// Process
					$thumbnailGenerator->processDocument();

					// return another (final) check for the thumbnail
					return $this->thumbnailExists();

				} else {
					return FALSE;
				}

			} else {
				return FALSE;
			}
		}
	}

	/**
	 * Method to check whether the instantview file of a document exists
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	public function instantViewExists()
	{
		$oStorage = KTStorageManagerUtil::getSingleton();

		global $default;

		$varDir = $default->varDirectory;

		$thumbnailCheck = $varDir . '/flash/'.$this->documentid.'.swf';

		return $oStorage->file_exists($thumbnailCheck);
	}

	/**
	 * Method to generate an instaView for a document
	 *
	 * @author KnowledgeTree Team
	 * @access public
	 */
	public function generateInstantView()
	{
		// If thumbnail exists, return it
		if ($this->instantViewExists()) {
			return TRUE;
		} else {
			require_once(KT_LIB_DIR . '/plugins/pluginutil.inc.php');

			// Check that plugin is enabled
			if (KTPluginUtil::pluginIsActive('instaview.processor.plugin')) {
				$path = KTPluginUtil::getPluginPath('instaview.processor.plugin');

				require_once($path .  'instaView.php');

				// Get mimetype
				$mimeType = KTMime::getMimeTypeName($this->document->getMimeTypeID());

				$instaView = new instaView();

				// Check that mimetype is valid
				if (in_array($mimeType, $instaView->getSupportedMimeTypes())) {

					// Setup
					$instaView->setDocument($this->document);

					// Process
					$instaView->processDocument();

					// return another (final) check for the thumbnail
					return $this->instantViewExists();

				} else {
					return FALSE;
				}

			} else {
				return FALSE;
			}
		}
	}

	/**
	 *
	 * Determines whether a document has "binary changes", i.e. if it truly has content changes
	 * (since rename etc also increase the content version). The only way to determine this is by
	 * checking whether there has been a check-in in the given version range
	 *
	 * @param float $from_version
	 * @param float $to_version
	 */
	public function hasBinaryChanges($from_version, $to_version)
	{
		$sSQL = 'SELECT DT.document_id FROM '.KTUtil::getTableName('document_transactions').' AS DT '.
			'WHERE DT.document_id = '.$this->documentid.' AND DT.version > '.$from_version.' AND DT.version <= '.$to_version.
			' AND DT.transaction_namespace LIKE \'ktcore.transactions.check_in\' ';

        $results = DBUtil::getResultArray($sSQL);

        if (is_null($results) || PEAR::isError($results))
        {
        	return false;
        }

        return (count($results) > 0);
	}
}

?>
