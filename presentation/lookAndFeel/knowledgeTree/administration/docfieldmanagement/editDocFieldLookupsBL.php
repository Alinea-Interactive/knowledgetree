<?php
/**
* BL information for adding a User
*
* @author Mukhtar Dharsey
* @date 5 February 2003
* @package presentation.lookAndFeel.knowledgeTree.
*
*/
require_once("../../../../../config/dmsDefaults.php");

if (checkSession()) {
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternListBox.inc");
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternCreate.inc");
    require_once("editDocFieldLookupsUI.inc");
    require_once("$default->fileSystemRoot/lib/documentmanagement/DocumentField.inc");
    require_once("$default->fileSystemRoot/lib/documentmanagement/MetaData.inc");
    require_once("$default->fileSystemRoot/lib/users/User.inc");
    require_once("$default->fileSystemRoot/lib/groups/GroupUserLink.inc");
    require_once("$default->fileSystemRoot/lib/security/permission.inc");
    require_once("$default->fileSystemRoot/presentation/webpageTemplate.inc");
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternCustom.inc");    
    require_once("$default->fileSystemRoot/lib/foldermanagement/Folder.inc");
    require_once("$default->fileSystemRoot/presentation/lookAndFeel/knowledgeTree/foldermanagement/folderUI.inc");
    require_once("$default->fileSystemRoot/presentation/Html.inc");

    $oPatternCustom = & new PatternCustom();

    if(isset($fDocFieldID)) { 
        $oDocField = DocumentField::get($fDocFieldID);
        if ($oDocField->getHasLookup()){
	        // do a check to see both drop downs selected
	        if($fDocFieldID == -1) {
	            $oPatternCustom->setHtml(getPageNotSelected());
	        } else {
	            //$oMetaData = new MetaData();
	            //$faGroupID = GroupUserLink::getGroups($fUserID);
	            $oPatternCustom->setHtml(getGroupPage($fDocFieldID));
	            $main->setFormAction($_SERVER["PHP_SELF"] . "?fUserSet=1&fGroupSet=1");
	        }
        } else {
        	$oPatternCustom->setHtml(getLookupNotSet());
        }
    } else {
        // build first page
        $oPatternCustom->setHtml(getPage(null,null));
        $main->setFormAction($_SERVER["PHP_SELF"] . "?fUserSet=1");
    }

    if(isset($fGroupSet)) {
        if($fOtherGroupID) {
        	$oPatternCustom->setHtml("Add");
        } else {	                
	        $oPatternCustom->setHtml("Delete");
	        $main->setFormAction($_SERVER["PHP_SELF"] . "?fDeleteConfirmed=1&fGroupID=$fGroupID"); 		   
        }        
    }

    if (isset($fDeleteConfirmed)) {
        // else add to db and then goto page succes
        $oUserGroup = new GroupUserLink($fGroupID, $fUserID);
        $oUserGroup->setUserGroupID($fGroupID,$fUserID);
        if($oUserGroup->delete()) {
            $oPatternCustom->setHtml(getPageSuccess());
        } else {
            $oPatternCustom->setHtml(getPageFail());
        }
    }

    // render page
    $main->setCentralPayload($oPatternCustom);
    $main->render();
}
?>
