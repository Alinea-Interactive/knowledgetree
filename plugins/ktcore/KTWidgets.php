<?php
/**
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
 */

require_once(KT_LIB_DIR . '/widgets/basewidget.inc.php');
require_once(KT_LIB_DIR . '/templating/templating.inc.php');
require_once(KT_LIB_DIR . '/browse/DocumentCollection.inc.php');

class KTCoreInfoWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.info';
    public $sTemplate = 'ktcore/forms/widgets/info';
}

class KTCoreStringWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.string';
    public $sTemplate = 'ktcore/forms/widgets/string';
}

class KTCoreHiddenWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.hidden';
    public $sTemplate = 'ktcore/forms/widgets/hidden';
}

class KTCoreFileWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.file';
    public $sTemplate = 'ktcore/forms/widgets/file';

    function wrapName($outer) {
        $this->sName = sprintf('_kt_attempt_unique_%s', $this->sName);
        // we don't have access via "wrap" when processing, so we can't actually
        // wrap.  just don't use a lot of names
    }

    function process($data){
        $tname = sprintf('_kt_attempt_unique_%s', $this->sName);
        return array($this->sBasename => $_FILES[$tname]);
    }

    function getValidators() {
        if (!$this->bAutoValidate) {
            return null;
        }

        if (!$this->bRequired) {
            return null;
        }

        $oVF =& KTValidatorFactory::getSingleton();
       
        return $oVF->get('ktcore.validators.requiredfile', array(
            'test' => sprintf('_kt_attempt_unique_%s', $this->sName),
            'basename' => $this->sBasename,
        ));
    }
}


class KTCoreTextWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.text';
    public $sTemplate = 'ktcore/forms/widgets/text';
}

class KTCoreReasonWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.reason';
    public $sTemplate = 'ktcore/forms/widgets/text';

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        // FIXME make required *either* per-action property
        // FIXME or a global pref.
        $global_required_default = true;
        $this->bRequired = (KTUtil::arrayGet($aOptions, 'required', $global_required_default, false) == true);

        $this->aOptions['cols'] = KTUtil::arrayGet($aOptions, 'cols', 60);
        $this->aOptions['rows'] = KTUtil::arrayGet($aOptions, 'rows', 3);
    }
}

class KTCoreBooleanWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.boolean';
    public $sTemplate = 'ktcore/forms/widgets/boolean';

    function setDefault($mValue) {
        $this->value = ($mValue == true);
    }
}

class KTCorePasswordWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.password';
    public $sTemplate = 'ktcore/forms/widgets/password';

    public $bConfirm = false;
    public $sConfirmDescription;

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        $this->bConfirm = KTUtil::arrayGet($aOptions, 'confirm', false);
        $this->sConfirmDescription = KTUtil::arrayGet($aOptions, 'confirm_description');
    }

    function process($raw_data) {
        // since we're essentially a string, pass *that* out as the primary
        // but we also might want to confirm, and if so we use a private name
        $res = array();
        if ($this->bConfirm) {
            $res['_password_confirm_' . $this->sBasename] = array(
                'base' => $raw_data[$this->sBasename]['base'],
                'confirm' => $raw_data[$this->sBasename]['confirm'],
            );
            $res[$this->sBasename] = $raw_data[$this->sBasename]['base'];
        } else {
            $res[$this->sBasename] = $raw_data[$this->sName];
        }
        return $res;
    }

    function getValidators() {
        if (!$this->bAutoValidate) {
            return null;
        }
        $oVF =& KTValidatorFactory::getSingleton();

        $val = array();
        $val[] = parent::getValidators(); // required, etc.
        $val[] = $oVF->get('ktcore.validators.password', array(
            'test' => $this->sOrigname,
            'basename' => $this->sBasename
        ));

        return $val;
    }
}


class KTCoreSelectionWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.selection';
    public $bMulti = false;    // multiselection
    public $USE_SIMPLE = 5;   // point at which to switch to a dropdown/multiselect
    public $bUseSimple;    // only use checkboxes, regardless of size
    public $aVocab;
    public $sEmptyMessage;
	public $aEvents;
	
    private $_valuesearch;

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }
        
        $this->bUseSimple = KTUtil::arrayGet($aOptions, 'simple_select', null, false);
        $this->bMulti = KTUtil::arrayGet($aOptions, 'multi', false);

        $this->aVocab = (array) KTUtil::arrayGet($aOptions, 'vocab');
        $this->sEmptyMessage = KTUtil::arrayGet($aOptions, 'empty_message',
            _kt('No options available for this field.'));
		$this->aEvents = KTUtil::arrayGet($aOptions, 'events', false);
    }

    function getWidget() {
        // very simple, general purpose passthrough.  Chances are this is sufficient,
        // just override the template being used.
        $bHasErrors = false;
        if (count($this->aErrors) != 0) { $bHasErrors = true; }

        // at this last moment we pick the template to use
        $total = count($this->aVocab);
        if ($this->bUseSimple === true) {
            $this->sTemplate = 'ktcore/forms/widgets/simple_selection';
        } else if ($this->bUseSimple === false) {
            $this->sTemplate = 'ktcore/forms/widgets/selection';
        } else if (is_null($this->bUseSimple) && ($total <= $this->USE_SIMPLE)) {
            $this->sTemplate = 'ktcore/forms/widgets/simple_selection';
        } else {
            $this->sTemplate = 'ktcore/forms/widgets/selection';
        }

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate($this->sTemplate);

        // have to do this here, and not in "configure" since it breaks
        // entity-select.
        $unselected = KTUtil::arrayGet($this->aOptions, 'unselected_label');
        if (!empty($unselected)) {
            // NBM:  we get really, really nasty interactions if we try merge
            // NBM:  items with numeric (but important) key values and other
            // NBM:  numerically / null keyed items
            $vocab = array();
            $vocab[] = $unselected;
            foreach ($this->aVocab as $k => $v) {
                $vocab[$k] = $v;
            }

            $this->aVocab = $vocab;

            // make sure its the selected one if there's no value specified.
            if (empty($this->value)) {
                $this->value = '0';
            }
        }

        // performance optimisation for large selected sets.
        if ($this->bMulti) {
            $this->_valuesearch = array();
            $value = (array) $this->value;
            foreach ($value as $v) {
                $this->_valuesearch[$v] = true;
            }
        }
		
        $eventList = false;
        if ($this->aEvents)
        {
        	foreach ($this->aEvents as $event => $action)
        	{
        		$eventList .= "$event=$action";
        	}
        }
        $aTemplateData = array(
            'context' => $this,
            'name' => $this->sName,
            'has_id' => ($this->sId !== null),
            'id' => $this->sId,
            'has_value' => ($this->value !== null),
            'value' => $this->value,
            'options' => $this->aOptions,
            'vocab' => $this->aVocab,
            'eventList' => $eventList,
        );
        return $oTemplate->render($aTemplateData);
    }

    function selected($lookup) {
        if ($this->bMulti) {
            return $this->_valuesearch[$lookup];
        } else {
            return ($this->value == $lookup);
        }
    }

    function process($raw_data) {
        return array($this->sBasename => $raw_data[$this->sBasename]);
    }
}

// this happens so often, its worth creating a util function for it
class KTCoreEntitySelectionWidget extends KTCoreSelectionWidget {
    public $sNamespace = 'ktcore.widgets.entityselection';

    public $sIdMethod;
    public $sLabelMethod;

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        // the selection widget's configure method has already setup almost
        // all the vars we need.  we have one utility here, where you pass
        // in a list of existing entities that match the query, and we work
        // from there.

        $this->sIdMethod = KTUtil::arrayGet($aOptions, 'id_method', 'getId');
        $this->sLabelMethod = KTUtil::arrayGet($aOptions, 'label_method');
        if (empty($this->sLabelMethod)) {
            return PEAR::raiseError(_kt('No label method specified.'));
        }
        $existing_entities = (array) KTUtil::arrayGet($aOptions, 'existing_entities');

        // now we construct the "value" array from this set
        // BUT ONLY IF WE DON'T HAVE A "VALUE" array.
        if (empty($this->value)) {
            $this->value = array();
            foreach ($existing_entities as $oEntity) {
                $this->value[] = call_user_func(array(&$oEntity, $this->sIdMethod));
            }
        }

        // we next walk the "vocab" array, constructing a new one based on the
        // functions passed in so far.
        $new_vocab = array();
        foreach ($this->aVocab as $oEntity) {
            $id = call_user_func(array(&$oEntity, $this->sIdMethod));
            $label = call_user_func(array(&$oEntity, $this->sLabelMethod));
            $new_vocab[$id] = $label;
        }
        $this->aVocab = $new_vocab;
    }
}


class KTDescriptorSelectionWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.descriptorselection';
    public $sTemplate = 'ktcore/forms/widgets/descriptor';

    public $aJavascript = array('resources/js/jsonlookup.js');

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }


    }

    function getWidget() {
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate($this->sTemplate);

        $src_location = $this->aOptions['src'];
        $sJS = sprintf('addLoadEvent(initJSONLookup("%s", "%s"));', $this->sBasename, $src_location);


        // its bad, but that's life.
        $oPage =& $GLOBALS['main'];
        $oPage->requireJSStandalone($sJS);

        $this->aOptions['multi'] = true;

        $aTemplateData = array(
            'context' => $this,
            'label' => $this->sLabel,
            'description' => $this->sDescription,
            'name' => $this->sName,
            'required' => $this->bRequired,
            'has_id' => ($this->sId !== null),
            'id' => $this->sId,
            'has_value' => ($this->value !== null),
            'value' => $this->value,
            'has_errors' => $bHasErrors,
            'errors' => $this->aErrors,
            'short_name' => $this->sBasename,
            'options' => $this->aOptions,
        );
        return $oTemplate->render($aTemplateData);
    }
}

class KTCoreTreeMetadataWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.treemetadata';
    public $iFieldId;
    public $aCSS = array('resources/css/kt-treewidget.css');

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        $this->iFieldId = KTUtil::arrayGet($aOptions, 'field_id');
        if (is_null($this->iFieldId)) {
            return PEAR::raiseError(_kt('Tree metadata fields must be associated with a particular type.'));
        }
    }

    function getWidget() {
        // very simple, general purpose passthrough.  Chances are this is sufficient,
        // just override the template being used.
        $bHasErrors = false;

        require_once(KT_LIB_DIR . '/documentmanagement/MDTree.inc');

        $fieldTree = new MDTree();
        $fieldTree->buildForField($this->iFieldId);
        $fieldTree->setActiveItem($this->value);
        return $fieldTree->_evilTreeRenderer($fieldTree, $this->sName);
    }
}

// wrap a set of fields into a core, basic one.
//
// this *also* subdivides the form data output namespace.
// to do this, it encapsulates a *large* amount of the KTWidget API
class KTCoreFieldsetWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.fieldset';

    private $_widgets;
    public $sDescription;
    public $sLabel;

    function configure($aOptions) {
        // do NOT use parent.
        $this->sLabel = KTUtil::arrayGet($aOptions, 'label');
        $this->sDescription = KTUtil::arrayGet($aOptions, 'description');
        $this->sName = KTUtil::arrayGet($aOptions, 'name');
        $this->sBasename = $this->sName;

        $aWidgets = (array) KTUtil::arrayGet($aOptions, 'widgets');
        // very similar to the one in forms.inc.php
        if (is_null($this->_oWF)) {
            $this->_oWF =& KTWidgetFactory::getSingleton();
        }

        $this->_widgets = array();
        // we don't want to expose the factory stuff to the user - its an
        // arbitrary distinction to the user.  Good point from NBM ;)
        foreach ($aWidgets as $aInfo) {
            if (is_null($aInfo)) {
                continue;
            } else if (is_object($aInfo)) {
                // assume this is a fully configured object
                $this->_widgets[] = $aInfo;
            } else {
                $namespaceOrObject = $aInfo[0];
                $config = (array) $aInfo[1];

                $this->_widgets[] = $this->_oWF->get($namespaceOrObject, $config);
            }
        }

    }

    function render() {
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/fieldset');

        $aTemplateData = array(
            'context' => $this,
            'label' => $this->sLabel,
            'description' => $this->sDescription,
            'widgets' => $this->renderWidgets(),
        );
        return $oTemplate->render($aTemplateData);
    }

    function renderWidgets() {
        $rendered = array();

        foreach ($this->_widgets as $v) {
            if (PEAR::isError($v)) {
                $rendered[] = sprintf(_kt('<div class="ktError"><p>Unable to show widget &mdash; %s</p></div>'), $v->getMessage());
            } else {
                $rendered[] = $v->render();
            }
        }

        return implode(' ', $rendered);
    }

    function getDefault() {
        // we need to do a little more admin here
        // to obtain the default
        // annoyingly
        $d = array();
        foreach ($this->_widgets as $w) {
            if (PEAR::isError($w)) {
                continue;
            }
            $d[$w->getBasename()] = $w->getDefault();
        }
        return $d;
    }

    function setDefault($aValue) {
        $d = (array) $aValue;
        foreach ($this->_widgets as $k => $w) {
            $oWidget =& $this->_widgets[$k];
            $oWidget->setDefault(KTUtil::arrayGet($d, $oWidget->getBasename(), $oWidget->getDefault()));
        }
    }

    function wrapName($sOuter) {
        $this->sName = sprintf('%s[%s]', $sOuter, $this->sBasename);
        // now, chain to our children
        foreach ($this->_widgets as $k => $v) {
            $oWidget =& $this->_widgets[$k];
            if (PEAR::isError($oWidget)) {
                continue;
            }
            $oWidget->wrapName($this->sName);
        }
    }

    function setErrors($aErrors = null) {
        if (is_array($aErrors)) {
            $this->aErrors = $aErrors;
        }

        foreach ($this->_widgets as $k => $w) {
            $oWidget =& $this->_widgets[$k];
            $oWidget->setErrors(KTUtil::arrayGet($aErrors, $oWidget->getBasename()));
        }
    }


    function getValidators() {
        // we use a fieldsetValidator here.
        $extra_validators = array();

        foreach ($this->_widgets as $oWidget) {
            $res = $oWidget->getValidators();

            if (!is_null($res)) {
                if (is_array($res)) {
                    $extra_validators = kt_array_merge($extra_validators, $res);
                } else {
                    $extra_validators[] = $res;
                }
            }
        }

        $oVF =& KTValidatorFactory::getSingleton();
        return array($oVF->get('ktcore.validators.fieldset', array(
            'test' => $this->sBasename,
            'validators' => &$extra_validators,
        )));
    }

    function process($raw_data) {
        $d = (array) KTUtil::arrayGet($raw_data, $this->sBasename);
        $o = array();

        // we now need to recombine the process
        foreach ($this->_widgets as $oWidget) {
            $o =& kt_array_merge($o, $oWidget->process($d));
        }

        return array($this->sBasename => $o);
    }

}

class KTCoreTransparentFieldsetWidget extends KTCoreFieldsetWidget {
    public $sNamespace = 'ktcore.widgets.transparentfieldset';

    function render() {
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/transparent_fieldset');

        $aTemplateData = array(
            'widgets' => $this->renderWidgets(),
        );
        return $oTemplate->render($aTemplateData);
    }
}



class KTExtraConditionalFieldsetWidget extends KTCoreFieldsetWidget {
    public $sNamespace = 'ktextra.conditionalmetadata.fieldset';

    function render() {
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/conditionalfieldset');

        $aTemplateData = array(
            'context' => $this,
            'label' => $this->sLabel,
            'description' => $this->sDescription,
        );
        return $oTemplate->render($aTemplateData);
    }
}


class KTCoreCollectionWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.collection';
    public $sTemplate = 'ktcore/forms/widgets/collectionframe';

    public $oCollection;
    public $sCode;

    function configure($aOptions) {
        $aOptions['broken_name'] = KTUtil::arrayGet($aOptions, 'broken_name', true, false);

        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        $this->oCollection = KTUtil::arrayGet($aOptions, 'collection');
        if(empty($this->oCollection)) return PEAR::raiseError(_kt('No collection specified.'));

        $this->iFolderId = KTUtil::arrayGet($aOptions, 'folder_id');
        if(empty($this->iFolderId)) return PEAR::raiseError(_kt('No initial folder specified specified.'));

        $this->aBCUrlParams = KTUtil::arrayGet($aOptions, 'bcurl_params', array());

        $this->aCols = array();
        foreach($this->oCollection->columns as $oCol) {
            $this->aCols[] = $oCol->namespace;
        }

        $this->sCode = KTUtil::randomString();
        $this->sCollection = serialize($this->oCollection);
        $_SESSION['collection_widgets'][$this->sCode] = serialize($this);

        $this->requireJSResource('resources/js/collectionframe.js');


    }

    function getTargetURL() {
        $oPluginRegistry =& KTPluginRegistry::getSingleton();
        $oPlugin =& $oPluginRegistry->getPlugin('ktcore.plugin');
        $sPath = $oPlugin->getPagePath('collection');
        $oKTConfig =& KTConfig::getSingleton();

        $sName = $this->sName;
        if (KTUtil::arrayGet($this->aOptions, 'broken_name', false)) {
            $this->sName = 'fFolderId';
        }

        $sPath = KTUtil::addQueryString($sPath, array('code'=>$this->sCode,
                                                      'fFolderId'=>$this->iFolderId,
                                                      'varname' => $sName));

        return $sPath;
    }

    function getCollection() {
        $oCR =& KTColumnRegistry::getSingleton();
        //print '<pre>';
        foreach($this->aCols as $ns) {

            $oCR->getColumn($ns);
        }
        $this->oCollection = unserialize($this->sCollection);
        return $this->oCollection;
    }
}


class KTCoreFolderCollectionWidget extends KTCoreCollectionWidget {
    public $sNamespace = 'ktcore.widgets.foldercollection';
    public $sTemplate = 'ktcore/forms/widgets/collectionframe';


    function configure($aOptions) {

        if (!isset($aOptions['value'])) {
            $aOptions['value'] = KTUtil::arrayGet($aOptions,'folder_id', 1);
        }
        $this->value = $aOptions['value'];


        $collection = new AdvancedCollection();
        $oCR =& KTColumnRegistry::getSingleton();
        $col = $oCR->getColumn('ktcore.columns.title');
        $col->setOptions(array('qs_params'=>array('fMoveCode'=>$sMoveCode,
                                                  'fFolderId'=> $this->value,
                                                  'action'=>'startMove')));
        $collection->addColumn($col);

        $qObj = new FolderBrowseQuery(KTUtil::arrayGet($aOptions,'value'));
        $collection->setQueryObject($qObj);

        $aO = $collection->getEnvironOptions();
        $collection->setOptions($aO);

        $aOptions['collection'] = $collection;
        $aOptions['broken_name'] = $false;

        return parent::configure($aOptions);
    }

    function getDefault() { return $this->value; }
    function setDefault($mValue) {
        if ($mValue != $this->value) {
            $this->oCollection->setQueryObject(new FolderBrowseQuery($mValue));
            $this->value = $mValue;
            $this->aOptions['folder_id'] = $this->value;
            $this->iFolderId = $this->value;
            $this->sCollection = serialize($this->oCollection);
            $_SESSION['collection_widgets'][$this->sCode] = serialize($this);
        }
    }
}

class KTCoreCollectionPage extends KTStandardDispatcher {

    function _generate_breadcrumbs(&$oFolder, $sCode, $aURLParams, $sName = 'fFolderId') {
        $aBreadcrumbs = array();
        $folder_path_names = $oFolder->getPathArray();
        $folder_path_ids = explode(',', $oFolder->getParentFolderIds());
        $folder_path_ids[] = $oFolder->getId();

        if (!empty($folder_path_ids) && empty($folder_path_ids[0]))
        {
			array_shift($folder_path_ids);
        }

        $oRoot = Folder::get(1);
        $folder_path_names = array_merge(array($oRoot->getName()), $folder_path_names);


        foreach (range(0, count($folder_path_ids) - 1) as $index) {
            $id = $folder_path_ids[$index];

            $aParams = kt_array_merge($aURLParams, array('fFolderId'=>$id, 'code'=>$sCode, 'varname'=>$sName));
            $url = KTUtil::addQueryString($_SERVER['PHP_SELF'], $aParams);
            $aBreadcrumbs[] = array('url' => $url, 'name' => $folder_path_names[$index]);
        }

        return $aBreadcrumbs;
    }



    function do_main() {

        $sCode = KTUtil::arrayGet($_REQUEST, 'code');
        $sName = KTUtil::arrayGet($_REQUEST, 'varname','fFolderId');
        $oWidget = unserialize($_SESSION['collection_widgets'][$sCode]);

        $oCollection = $oWidget->getCollection();

        $oFolder = Folder::get(KTUtil::arrayGet($_REQUEST, 'fFolderId', 1));
        if (PEAR::isError($oFolder)) {
            $this->errorRedirectToMain(_kt('Invalid folder selected.'));
            exit(0);
        }

        $aOptions = array('ignorepermissions' => KTBrowseUtil::inAdminMode($this->oUser, $oFolder));
        $oCollection->_queryObj->folder_id = $oFolder->getId();

        $aOptions = $oCollection->getEnvironOptions();
        $aOptions['return_url'] = KTUtil::addQueryString($_SERVER['PHP_SELF'], array('code'=>$sCode, 'varname' => $sName, 'fFolderId' => $oFolder->getId()));

        $oCollection->setOptions($aOptions);

        // add the collection code to the title column QS params

        foreach($oWidget->aCols as $ns) {
            $aColOpts = $oCollection->getColumnOptions($ns);
            $aColOpts['qs_params'] = kt_array_merge(KTUtil::arrayGet($aColOpts, 'qs_params', array()),
                                                    array('code' => $sCode, 'varname' => $sName));
            $oCollection->setColumnOptions($ns, $aColOpts);
        }

        // make the breadcrumbs
        $aBreadcrumbs = $this->_generate_breadcrumbs($oFolder, $sCode, $oWidget->aBCUrlParams, $sName);

        print KTTemplating::renderTemplate('ktcore/forms/widgets/collection',
            array(
                'collection'=> $oCollection,
                'targetfolderid' => $oFolder->getId(),
                'breadcrumbs' => $aBreadcrumbs,
                'targetname' => $sName,
            )
        );

        exit(0);
    }
}



// based on the selection widget, this carries a mapping array,
// which is converted to JSON and inserted into the output. javascript
// enforces the various relationships between conditional fields.

class KTCoreConditionalSelectionWidget extends KTCoreSelectionWidget {
    public $sNamespace = 'ktcore.widgets.conditionalselection';

    public $sIdMethod;
    public $sLabelMethod;

    public $bIsMaster;
    public $bMappings;

    function _getFieldIdForMetadataId($iMetadata) {
	$sTable = 'metadata_lookup';
	$sQuery = "SELECT document_field_id FROM " . $sTable . " WHERE id = ?";
	$aParams = array($iMetadata);

	$res = DBUtil::getOneResultKey(array($sQuery, $aParams), 'document_field_id');
	if (PEAR::isError($res)) {
	    return false;
	}
	return $res;
    }


    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        $this->sIdMethod = KTUtil::arrayGet($aOptions, 'id_method', 'getId');
        $this->sLabelMethod = KTUtil::arrayGet($aOptions, 'label_method');
        if (empty($this->sLabelMethod)) {
            return PEAR::raiseError(_kt('No label method specified.'));
        }
        $existing_entities = (array) KTUtil::arrayGet($aOptions, 'existing_entities');

        if (empty($this->value)) {
            $this->value = array();
            foreach ($existing_entities as $oEntity) {
                $this->value[] = call_user_func(array(&$oEntity, $this->sIdMethod));
            }
        }

	$this->iField = KTUtil::arrayGet($aOptions, 'field');
	$this->iMasterId = KTUtil::arrayGet($aOptions, 'masterid');

	// if we're the master, we have to build the dependancy array and store it as JSON
	// also, include the javascript
	if(KTUtil::arrayGet($aOptions, 'master', false)) {
	    $this->bMaster = true;
	    $this->aJavascript = array('resources/js/conditional_selection.js');

	    $oFieldset = KTFieldset::get(KTUtil::arrayGet($aOptions, 'fieldset'));
	    $aLookups = array();
	    $aConnections = array();

	    foreach($oFieldset->getFields() as $oField) {
		$c = array();

		foreach($oField->getEnabledValues() as $oMetadata) {
		    $a = array();
		    // print '<pre>';

		    $nvals = KTMetadataUtil::getNextValuesForLookup($oMetadata->getId());
		    if($nvals) {
			foreach($nvals as $i=>$aVals) {
			    $a = array_merge($a, $aVals);

			    foreach($aVals as $id) {
			      $field = $this->_getFieldIdForMetadataId($id);
			      // print 'id ' . $id . ' is in field ' . $field . "<br/>";
			      if(!in_array($field, $c)) {
				$c[] = $field;
			      }
			    }
			}
		    }

		    $aLookups[$oMetadata->getId()] = $a;
		}
		$aConnections[$oField->getId()] = $c;
	    }

	    //exit(0);

	    $oJSON = new Services_JSON;
	    $this->sLookupsJSON = $oJSON->encode($aLookups);
	    $this->sConnectionsJSON = $oJSON->encode($aConnections);
	}


        $new_vocab = array();
        foreach ($this->aVocab as $oEntity) {
            $id = call_user_func(array(&$oEntity, $this->sIdMethod));
            $label = call_user_func(array(&$oEntity, $this->sLabelMethod));
            $new_vocab[$id] = array($label, $oEntity->getId());
        }
        $this->aVocab = $new_vocab;
    }

    function getWidget() {
        $bHasErrors = false;
        if (count($this->aErrors) != 0) { $bHasErrors = true; }

	$this->sTemplate = 'ktcore/forms/widgets/conditional_selection';

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate($this->sTemplate);

        $unselected = KTUtil::arrayGet($this->aOptions, 'unselected_label');
        if (!empty($unselected)) {
            $vocab = array();
            $vocab[] = $unselected;
            foreach ($this->aVocab as $k => $v) {
                $vocab[$k] = $v;
            }
            $this->aVocab = $vocab;
            if (empty($this->value)) {
                $this->value = '0';
            }
        }

        if ($this->bMulti) {
            $this->_valuesearch = array();
            $value = (array) $this->value;
            foreach ($value as $v) {
                $this->_valuesearch[$v] = true;
            }
        }

        $aTemplateData = array(
            'context' => $this,
            'name' => $this->sName,
            'has_id' => ($this->sId !== null),
            'id' => $this->sId,
            'has_value' => ($this->value !== null),
            'value' => $this->value,
            'options' => $this->aOptions,
            'vocab' => $this->aVocab,
	    'lookups' => $this->sLookupsJSON,
	    'connections' => $this->sConnectionsJSON,
	    'master' => $this->bMaster,
	    'masterid' => $this->iMasterId,
	    'field' => $this->iField,
        );
        return $oTemplate->render($aTemplateData);
    }
}

class KTCoreTextAreaWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.textarea';
    public $sTemplate = 'ktcore/forms/widgets/textarea';

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        // FIXME make required *either* per-action property
        // FIXME or a global pref.
        $global_required_default = true;
        $this->bRequired = (KTUtil::arrayGet($aOptions, 'required', $global_required_default, false) == true);

		// Part of the space on the mce editor is taken up by the toolbars, so make the plain text field slightly smaller (if using the default size)
        $default_rows = 20;
        if(isset($this->aOptions['field'])){
            $oField = $this->aOptions['field'];
            if(!$oField->getIsHTML()){
                $default_rows = 15;
            }
        }

        $this->aOptions['cols'] = KTUtil::arrayGet($aOptions, 'cols', 80);
        $this->aOptions['rows'] = KTUtil::arrayGet($aOptions, 'rows', $default_rows);
        $this->aOptions['field'] = KTUtil::arrayGet($aOptions, 'field');
    }

    function render() {
        // very simple, general purpose passthrough.  Chances are this is sufficient,
        // just override the template being used.
        $bHasErrors = false;       
        if (count($this->aErrors) != 0) { $bHasErrors = true; }
        //var_dump($this->aErrors);
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/base');
		
      	$this->aJavascript[] = 'thirdpartyjs/jquery/jquery-1.4.2.js';
      	$this->aJavascript[] = 'thirdpartyjs/jquery/jquery_noconflict.js';
      	$this->aJavascript[] = 'thirdpartyjs/tinymce/jscripts/tiny_mce/tiny_mce.js';
    	$this->aJavascript[] = 'resources/js/kt_tinymce_init.js';
    	
        if (!empty($this->aJavascript)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireJSResources($this->aJavascript);
        }
        if (!empty($this->aCSS)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireCSSResources($this->aCSS);
        }
        
        $widget_content = $this->getWidget();
        
        $aTemplateData = array(
            "context" => $this,
            "label" => $this->sLabel,
            "description" => $this->sDescription,
            "name" => $this->sName,
            "required" => $this->bRequired,
            "has_id" => ($this->sId !== null),
            "id" => $this->sId,
            "has_value" => ($this->value !== null),
            "value" => $this->value,
            "has_errors" => $bHasErrors,
            "errors" => $this->aErrors,
            "options" => $this->aOptions,
            "widget" => $widget_content,
        );
        return $oTemplate->render($aTemplateData);   
    }    
    
}

class KTCoreDateWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.date';
    public $sTemplate = 'ktcore/forms/widgets/date';

    function getValidators() {
        if (!$this->bAutoValidate) {
            return null;
        }
        $validators = parent::getValidators(); // required, etc.
		
        $oVF =& KTValidatorFactory::getSingleton();

        $val = array();
        if(!empty($validators) && !PEAR::isError($validators)) $val[] = $validators;
        $val[] = $oVF->get('ktcore.validators.date', array(
            'test' => $this->sOrigname,
            'basename' => $this->sBasename
        ));

        return $val;
    }
}

class KTCoreButtonWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.button';
    public $sTemplate = 'ktcore/forms/widgets/button';
}

class KTCoreLayerWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.layer';
    public $sTemplate = 'ktcore/forms/widgets/layer';
    
    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        $this->aOptions['class'] = KTUtil::arrayGet($aOptions, 'class', '');
    }    
}

class KTCoreImageCropWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.imagecrop';
    public $sTemplate = 'ktcore/forms/widgets/imagecrop';

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        $this->aOptions['init_width'] = KTUtil::arrayGet($aOptions, 'init_width', '313');
        $this->aOptions['init_height'] = KTUtil::arrayGet($aOptions, 'init_height', '50');
    }

    function render() {
        // very simple, general purpose passthrough.  Chances are this is sufficient,
        // just override the template being used.
        $bHasErrors = false;       
        if (count($this->aErrors) != 0) { $bHasErrors = true; }
        //var_dump($this->aErrors);
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/base');
		
      	$this->aJavascript[] = 'thirdpartyjs/jquery/jquery-1.4.2.js';
        $this->aJavascript[] = 'thirdpartyjs/jquery/plugins/imageareaselect/scripts/jquery.imgareaselect.pack.js';
    	//$this->aJavascript[] = 'resources/js/kt_image_crop.js';
    	
        if (!empty($this->aJavascript)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireJSResources($this->aJavascript);
        }
        
    	$this->aCSS[] = 'thirdpartyjs/jquery/plugins/imageareaselect/css/imgareaselect-default.css';
        
        if (!empty($this->aCSS)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireCSSResources($this->aCSS);
        }
        
        $widget_content = $this->getWidget();

        $aTemplateData = array(
            "context" => $this,
            "label" => $this->sLabel,
            "description" => $this->sDescription,
            "name" => $this->sName,
            "has_value" => ($this->value !== null),
            "value" => $this->value,
            "has_errors" => $bHasErrors,
            "errors" => $this->aErrors,
            "options" => $this->aOptions,
            "widget" => $widget_content,
        );
        return $oTemplate->render($aTemplateData);   
    }    
}

class KTCoreImageWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.image';
    public $sTemplate = 'ktcore/forms/widgets/image';

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }

        $this->aOptions['src'] = KTUtil::arrayGet($aOptions, 'src', '');
        $this->aOptions['alt'] = KTUtil::arrayGet($aOptions, 'alt', '');
        $this->aOptions['title'] = KTUtil::arrayGet($aOptions, 'title', '');
        $this->aOptions['width'] = KTUtil::arrayGet($aOptions, 'width', '');
        $this->aOptions['height'] = KTUtil::arrayGet($aOptions, 'height', '');
        $this->aOptions['has_width'] = ($this->aOptions['height'] !== null);
        $this->aOptions['has_height'] = ($this->aOptions['height'] !== null);
        $this->aOptions['div_border'] = KTUtil::arrayGet($aOptions, 'div_border', '');
        $this->aOptions['has_div_border'] = ($this->aOptions['div_border'] !== null);
        
    }

    function render() {
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/base');
		
        $widget_content = $this->getWidget();

        $aTemplateData = array(
            "context" => $this,
            "label" => $this->sLabel,
            "description" => $this->sDescription,
            "name" => $this->sName,
            "has_value" => ($this->value !== null),
            "value" => $this->value,
            "has_errors" => $bHasErrors,
            "errors" => $this->aErrors,
            "options" => $this->aOptions,
            "widget" => $widget_content,
        );
        return $oTemplate->render($aTemplateData);   
    }    

}

class KTCoreImageSelectWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.imageselect';
    public $sTemplate = 'ktcore/forms/widgets/imageselect';

    public $width;
    public $height;

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }
    }

    function render() {
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/base');
        
      	$this->aJavascript[] = 'thirdpartyjs/jquery/jquery-1.4.2.js';
      	$this->aJavascript[] = 'thirdpartyjs/jquery/plugins/selectimage/jquery.selectimage.js';
      	$this->aJavascript[] = 'resources/js/kt_selectimage.js';
	
        if (!empty($this->aJavascript)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireJSResources($this->aJavascript);
        }

    	//$this->aCSS[] = 'resources/css/kt_imageselect.css';
	    $this->aCSS[] = 'thirdpartyjs/jquery/plugins/selectimage/css/selectimage.css';
        
        if (!empty($this->aCSS)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireCSSResources($this->aCSS);
        }
	
        $widget_content = $this->getWidget();

        $aTemplateData = array(
            "context" => $this,
            "label" => $this->sLabel,
            "description" => $this->sDescription,
            "name" => $this->sName,
            "has_value" => ($this->value !== null),
            "value" => $this->value,
            "has_errors" => $bHasErrors,
            "errors" => $this->aErrors,
            "options" => $this->aOptions,
            "widget" => $widget_content,
        );
        return $oTemplate->render($aTemplateData);   
    }    
    
}

class KTCoreSWFFileSelectWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.swffileselect';
    public $sTemplate = 'ktcore/forms/widgets/swffileselect';

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }
        
        $this->aOptions['fFolderId'] = KTUtil::arrayGet($aOptions, 'fFolderId', '');
        $this->aOptions['field_id'] = KTUtil::arrayGet($aOptions, 'field_id', '');
    }

    function render() {
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/base');
        
      	$this->aJavascript[] = 'thirdpartyjs/jquery/jquery-1.4.2.js';
      	
      	//TODO: abstract handlers and config from javascript to enable these
      	//      to be set in php.
      	
      	$this->aJavascript[] = 'thirdpartyjs/swfupload/swfupload.js';
        $this->aJavascript[] = 'thirdpartyjs/swfupload/swfupload.queue.js';      	
        $this->aJavascript[] = 'thirdpartyjs/swfupload/fileprogress.js';      	
        $this->aJavascript[] = 'thirdpartyjs/swfupload/handlers.js';
      	$this->aJavascript[] = 'resources/js/kt_upload.js';
        
        if (!empty($this->aJavascript)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireJSResources($this->aJavascript);
            $oPage->requireJSStandalone($this->getConfiguration());
        }
        
        $this->aCSS[] = 'resources/css/upload.css';
        
        if (!empty($this->aCSS)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireCSSResources($this->aCSS);
        }
        
        $widget_content = $this->getWidget();

        $aTemplateData = array(
            "context" => $this,
            "label" => $this->sLabel,
            "description" => $this->sDescription,
            "name" => $this->sName,
            "has_value" => ($this->value !== null),
            "value" => $this->value,
            "has_errors" => $bHasErrors,
            "errors" => $this->aErrors,
            "options" => $this->aOptions,
            "widget" => $widget_content,
        );
        return $oTemplate->render($aTemplateData);   
    }    
    
    /**
     * This function dynamically generates the init configuration script required by 
     * swfupload for the particular session.
     * 
     * @param $folderId The id of the folder to upload the document to.	If none is provided
     *		  the following will be sniffed: get params and widget options.
     *
     * @return String configuration script.
     */
    private function getConfiguration($folderId = null){
        
        if (is_null($folderId)) {
            $folderId = $_GET['fFolderId'];
        }
        
        if ($folderId == '') {
            $folderId = $_POST['fFolderId'];    
        }
        
        if ($folderId == '') {
            $folderId = $this->aOptions['fFolderId'];
        }
        
        ob_start();
        ?>
window.onload = function() {
	public swfu;

		public settings = {
			flash_url : "thirdpartyjs/swfupload/swfupload.swf",
			upload_url: "action.php?kt_path_info=ktlive.actions.folder.bulkupload&_kt_form_name=SWFUPLOAD&fFolderId=<?php print $folderId ?>&action=liveDocumentUpload",
			//upload_url: "upload/upload.php",
			post_params: {"PHPSESSID" : "<?php print session_id(); ?>"},
			file_size_limit : "4096 MB",
			file_types : "*.*",
			file_types_description : "All Files",
			file_upload_limit : 1,
			file_queue_limit : 1,
			custom_settings : {
				progressTarget : "fsUploadProgress",
				cancelButtonId : "btnCancel"
			},
			debug: false,

			// Button settings
			//button_image_url: "resources/graphics/newui/swfupload.png",
			button_width: jQuery("#fakeflashbutton").width(),
			button_height: jQuery("#fakeflashbutton").height()+5,
			button_placeholder_id: "spanButtonPlaceHolder",
			//button_text: '<span class="button">Upload</span>',
			//button_text_style: ".theFont { font-size: 16; }",
			//button_text_left_padding: 12,
			//button_text_top_padding: 3,

			button_action : SWFUpload.BUTTON_ACTION.SELECT_FILES,
			button_disabled : false,
			button_cursor : SWFUpload.CURSOR.HAND,
			button_window_mode: SWFUpload.WINDOW_MODE.TRANSPARENT,
			
			// The event handler functions are defined in handlers.js
			file_queued_handler : fileQueued,
			file_queue_error_handler : fileQueueError,
			file_dialog_complete_handler : fileDialogComplete,
			upload_start_handler : uploadStart,
			upload_progress_handler : uploadProgress,
			upload_error_handler : uploadError,
			upload_success_handler : uploadSuccess,
			upload_complete_handler : uploadComplete,
			queue_complete_handler : queueComplete	// Queue plugin event
		};

		swfu = new SWFUpload(settings);
};
        <?PHP
        $script = ob_get_contents();
        ob_end_clean();
        
        return $script;
    }
    
}

class KTCoreAjaxUploadWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.ajaxupload';
    public $sTemplate = 'ktcore/forms/widgets/ajaxupload';
    
    

    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }
        $this->aOptions['name']      = KTUtil::arrayGet($aOptions, 'name', '');
        $this->aOptions['fFolderId'] = KTUtil::arrayGet($aOptions, 'fFolderId', '');
        $this->aOptions['field_id']  = KTUtil::arrayGet($aOptions, 'field_id', '');
        $this->aOptions['amazonsettings'] = KTUtil::arrayGet($aOptions, 'amazonsettings', '');
        $this->aOptions['awstmppath'] = KTUtil::arrayGet($aOptions, 'awstmppath', '');
        
    }

    function render() {
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/forms/widgets/base');
        
      	$this->aJavascript[] = 'thirdpartyjs/jquery/plugins/ajaxupload/ajaxupload.js';
      	
        
        if (!empty($this->aJavascript)) {
            // grab our inner page.
            $oPage =& $GLOBALS['main'];            
            $oPage->requireJSResources($this->aJavascript);
            $oPage->requireJSStandalone($this->getConfiguration());
        }
        
        
        $widget_content = $this->getWidget();

        $aTemplateData = array(
            "context" => $this,
            "label" => $this->sLabel,
            "description" => $this->sDescription,
            "name" => $this->sName,
            "has_value" => ($this->value !== null),
            "value" => $this->value,
            "has_errors" => $bHasErrors,
            "errors" => $this->aErrors,
            "options" => $this->aOptions,
            "widget" => $widget_content,
        );
        return $oTemplate->render($aTemplateData);   
    }    
    
    /**
     * This function dynamically generates the init configuration script required by 
     * swfupload for the particular session.
     * 
     * @param $folderId The id of the folder to upload the document to.	If none is provided
     *		  the following will be sniffed: get params and widget options.
     *
     * @return String configuration script.
     */
    private function getConfiguration($folderId = null){
        // TODO : This needs to be in a javascript file, and get passed the variable.
        if (is_null($folderId)) {
            $folderId = $_GET['fFolderId'];
        }
        
        if ($folderId == '') {
            $folderId = $_POST['fFolderId'];    
        }
        
        if ($folderId == '') {
            $folderId = $this->aOptions['fFolderId'];
        }
        
        ob_start();
        ?>
jQuery(document).ready(function(){
    jQuery('#extract-documents').hide();
    jQuery('#document_type_field').hide();
    jQuery('#type_metadata_fields').hide();
    jQuery('#advanced_settings_metadata_button').hide();
    jQuery('#successful_upload_files_ul').hide();
	jQuery('form .form_actions').hide();
    jQuery('#uploadbuttondiv').show();
    public button = jQuery('#button1'), interval;
	public newran = Math.random();
	newran = Math.ceil(newran * 100000);
	jQuery('#file_random_name').attr('value', newran);
	//swapElementFromRequest('advanced_settings_metadata','presentation/lookAndFeel/knowledgeTree/documentmanagement/getTypeMetadataFields.php?fDocumentTypeID=' + '1', '1');
    new AjaxUpload(button, 
    {
			action: '<?php echo $this->aOptions['amazonsettings']['formAction']; ?>', 
			name: 'file',
			onSubmit : function(file, ext)
			{
				public title = xtractFileTitle(file);
				sameNameFile(file);
				if(jQuery('#file_exists').val() == 1)
				{
					return;
				}
				jQuery('#button1').hide();
				jQuery('#cancelButton').show();
				ranfilename = jQuery('#file_random_name').val();
				detectArchiveFile(file);
                this.setData({
                    'AWSAccessKeyId' : '<?php echo $this->aOptions['amazonsettings']['AWSAccessKeyId']; ?>',
                    'acl'            : '<?php echo $this->aOptions['amazonsettings']['acl']; ?>',
                    'key'            : '<?php echo $this->aOptions['awstmppath']; ?>'+ranfilename,
                    'policy'         : '<?php echo $this->aOptions['amazonsettings']['policy']; ?>',
                    'Content-Type'   : 'binary/octet-stream',
                    'signature'      : '<?php echo $this->aOptions['amazonsettings']['signature']; ?>',
                    'success_action_redirect'      : '<?php echo $this->aOptions['amazonsettings']['success_action_redirect']; ?>'
                });
                button.hide();
				jQuery('#uploading_spinner').css({visibility: 'visible'});
                Img = document.getElementById('spinner');
                Img.style.display="inline";
                Img.src = "resources/graphics/thirdparty/loader.gif";
			},
			onComplete: function(file, response){
				if(jQuery('#file_exists').val() == 1)
				{
					return;
				}
				ranfilename = jQuery('#file_random_name').val();
				public title = xtractFileTitle(file);
				button.show();
                jQuery('#uploading_spinner').css({visibility: 'hidden'});
                jQuery('#cancelButton').hide();
                jQuery('#document_type_field').show();
                jQuery('#type_metadata_fields').show();
                //jQuery('#advanced_settings_metadata_button').show();
                jQuery('#successful_upload_files_ul').show();
				public listitem = '<li>';
				listitem += '<span id="'+ranfilename+'_title">'+title+'</span>';
				listitem += '<input id="'+ranfilename+'_htitle" name="file['+ranfilename+'][tmp_and_filename]" type="hidden" value="'+ranfilename+'<?php echo '_'; ?>'+file+'" />';
				listitem += '<input class="xtitles" id="'+ranfilename+'_xtitle" name="file['+ranfilename+'][title]" type="hidden" value="'+title+'" />';
				listitem += '<input class="hfilenames" id="'+ranfilename+'_hfilenames" name="filename" type="hidden" value="'+file+'" />';
				listitem += '<span onclick="removeFile(this)" style="cursor:pointer;"> <img src="resources/graphics/delete.png" /> </span>';
				listitem += '<span onclick="editTitle(\''+ranfilename+'\', \''+title+'\')" style="cursor:pointer;"> <img src="thirdparty/icon-theme/16x16/actions/document-properties.png" /> </span>';
				listitem += '</li>';
				public newran = Math.random();
				newran = Math.ceil(newran * 100000);
				jQuery('#file_random_name').attr('value', newran);
				jQuery('#successful_upload_files').show().append(listitem);
                jQuery('#kt_swf_upload_percent').val('100');
                jQuery('form .form_actions').show();
			}
		});
        cancelUpload = function() {
            window.stop();
            button.show();
            jQuery('#uploading_spinner').css({visibility: 'hidden'});
            jQuery('#cancelButton').hide();
            jQuery('#extract-documents').hide();
        },
		removeFile = function(spanObj) {
			jQuery(spanObj).parent().remove();
			if (jQuery('#successful_upload_files').children().length == 0)
			{
				jQuery('#extract-documents').hide();
				jQuery('#document_type_field').hide();
				jQuery('#type_metadata_fields').hide();
				jQuery('#advanced_settings_metadata_button').hide();
				jQuery('#successful_upload_files_ul').hide();
				jQuery('form .form_actions').hide();
				jQuery('#uploadbuttondiv').show();
			}
		},
		detectArchiveFile = function(fileName) {
			// TODO : This information should come from server
			isSupported = fileName.match(/\.(tgz|tar|gz|zip|deb|ar|bz|bz2|rar|tbz)$/i);
			isSupported = (isSupported != null)? true : false;
			if (isSupported) {
				jQuery('#extract-documents').show();
			}
		},
		sameNameFile = function(fileName) {
			jQuery('#file_exists').attr('value', '0');
			//public children = jQuery('#successful_upload_files').children().length;
			if(jQuery('.xtitles').size() > 0)
			{
				//jQuery('#successful_upload_files').children().each(function()
				//jQuery('.xtitles').each(function()
				jQuery('.hfilenames').each(function()
				{
					//if(jQuery(this).text() != '')
					if(jQuery(this).attr('value') != '')
					{
						//if (fileName == jQuery(this).text().trim())
						public newName = jQuery(this).attr('value');
						if (fileName == newName.trim())
						{
							alert('A file with the same name exists.');
							jQuery('#file_exists').attr('value', '1');
						}
					}
				})
			}    
		},
		xtractFileTitle = function (data) {
 			public m = data.match(/([^\/\\]+)\.(\w+)$/)
 			if(m == null)
 			{
 				return data;
 			}
 			else
 			{
 				return m[1];
 			}
        },
		renameFile = function(ranfilename) {
			public oldTitle = jQuery('#' + ranfilename + '_xtitle');
			public newTitleValue = jQuery('#' + ranfilename + '_etitle').attr('value');
			jQuery(oldTitle).attr('value', newTitleValue);
			jQuery('#' + ranfilename + '_submit').remove();
			jQuery('#' + ranfilename + '_etitle').remove();
			public listItem = '<span id="'+ranfilename+'_title">'+newTitleValue+'</span>';
			jQuery('#' + ranfilename + '_title').append(listItem);
		},
		editTitle = function(ranfilename, title) {
			public titleField = '<input id="'+ranfilename+'_etitle" name="title[]" type="text" value="'+title+'" />';
			public saveField = '<input id="'+ranfilename+'_submit" type="submit" value="Save" name="'+title+'" onclick="renameFile('+ranfilename+');return false;"/>';
			jQuery('#' + ranfilename + '_title').html(titleField + '&nbsp&nbsp' + saveField);
		}
});
        <?PHP
        $script = ob_get_contents();
        ob_end_clean();
        
        return $script;
    }
    
}

class KTCoreDivWidget extends KTWidget {
    public $sNamespace = 'ktcore.widgets.div';
	
    function configure($aOptions) {
        $res = parent::configure($aOptions);
        if (PEAR::isError($res)) {
            return $res;
        }
        
    }

    function getWidget() {
        $this->sTemplate = 'ktcore/forms/widgets/simple_div';
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate($this->sTemplate);
		$this->sClass = KTUtil::arrayGet($aOptions, 'class', false);
		
        $aTemplateData = array(
            'context' => $this,
            'has_id' => ($this->sId !== null),
            'id' => $this->sId,
        );

        return $oTemplate->render($aTemplateData);
    }

    

    
}