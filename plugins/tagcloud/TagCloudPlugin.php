<?php

/*
 * $Id$
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
 *
 */

require_once(KT_LIB_DIR . '/plugins/plugin.inc.php');
require_once(KT_LIB_DIR . '/plugins/pluginregistry.inc.php');
require_once('TagCloudRedirectPage.php');
require_once(KT_LIB_DIR . '/templating/templating.inc.php');

/**
 * Tag Cloud Plugin class
 */
class TagCloudPlugin extends KTPlugin {

    var $sNamespace = 'ktcore.tagcloud.plugin';
    var $iVersion = 1;

    /**
      * Constructor method for plugin
      *
      * @param string $sFilename
      * @return TagCloudPlugin
      */
    function TagCloudPlugin($filename = null)
    {
        $res = parent::KTPlugin($filename);
        $this->sFriendlyName = _kt('Tag Cloud Plugin');

        $dir = $this->_fixFilename(__FILE__);
        $this->pluginDir = dirname($dir) . DIRECTORY_SEPARATOR;

        return $res;
    }

    /**
     * Setup function for plugin
     */
    function setup()
    {
        // Register plugin components
        $this->registerCriterion('TagCloudCriterion', 'ktcore.criteria.tagcloud', KT_LIB_DIR . '/browse/Criteria.inc');
        $this->registerDashlet('TagCloudDashlet', 'ktcore.tagcloud.feed.dashlet', 'TagCloudDashlet.php');
        $this->registerPage('TagCloudRedirection', 'TagCloudRedirectPage', __FILE__);
        $this->registerPortlet(array(), 'TagCloudPortlet', 'tagcloud.portlet', 'TagCloudPortlet.php');

        // Check if the tagcloud fielset entry exists, if not, create it
        $fieldsetId = TagCloudPlugin::tagFieldsetExists();
        if ($fieldsetId === false) {
            $fieldset = TagCloudPlugin::createFieldset();
            if (PEAR::isError($fieldset) || is_null($fieldset)) {
                return false;
            }
            // make the fieldset id viewable
            $fieldsetId = $fieldset->iId;
        }

        // Check if the tagcloud document field entry exists, if not, create it
        $exists = TagCloudPlugin::tagFieldExists();
        if ($exists === false) {
            $field = TagCloudPlugin::createDocumentField($fieldsetId);
            if (PEAR::isError($field) || is_null($field)) {
                return false;
            }
        }

        $templating =& KTTemplating::getSingleton();
        $templating->addLocation('Tag Cloud Plugin', $this->pluginDir . 'templates');
    }

    /**
     * function to add fieldset entry to fieldsets table
     *
     * @return unknown
     */
    function createFieldset()
    {
        // create the fieldsets entry
        $fieldset = KTFieldset::createFromArray(array(
            'name' => _kt('Tag Cloud'),
            'description' => _kt('The following tags are associated with your document'),
            'namespace' => 'tagcloud',
            'mandatory' => false,
            'isConditional' => false,
            'isGeneric' => true,
            'isComplete' => false,
            'isComplex' => false,
            'isSystem' => false,
        ));

        return $fieldset;
    }

    /**
     * function to add the tagcloud entry to the document_fields table
     *
     * @param int $parentId
     * @return int $id
     */
    function createDocumentField($parentId)
    {
        // create the document_field entry
        $id = DocumentField::createFromArray(array(
            'Name' => 'Tag',
            'Description' => 'Tag Words',
            'DataType' => 'STRING',
            'IsGeneric' => false,
            'HasLookup' => false,
            'HasLookupTree' => false,
            'ParentFieldset' => $parentId,
            'IsMandatory' => false,
        ));

        return $id;
    }

    /**
     * function to check if the Tag field exists in the document_fields table
     *
     * @return boolean
     */
    function tagFieldExists()
    {
        $query = 'SELECT df.id AS id FROM document_fields AS df WHERE df.name = \'Tag\'';
        $tag = DBUtil::getOneResultKey(array($query), 'id');

        if (PEAR::isError($tag)) {
            global $default;
            $default->log->error('Tag Cloud plugin - error checking tag field: ' . $tag->getMessage());
            return $tag;
        }

        return is_numeric($tag)? $tag : false;
    }

    /**
     * function to check if the fieldset exists in the database
     *
     * @return boolean
     */
    function tagFieldsetExists()
    {
        $query = 'SELECT fs.id AS id FROM fieldsets AS fs WHERE namespace = \'tagcloud\'';
        $fieldsetId = DBUtil::getOneResultKey(array($query), 'id');

        if (PEAR::isError($fieldsetId)) {
            global $default;
            $default->log->error('Tag Cloud plugin - error checking tag fieldset: ' . $fieldsetId->getMessage());
            return $fieldsetId;
        }

        return is_numeric($fieldsetId) ? $fieldsetId : false;
    }

}

$pluginRegistry =& KTPluginRegistry::getSingleton();
$pluginRegistry->registerPlugin('TagCloudPlugin', 'ktcore.tagcloud.plugin', __FILE__);

?>
