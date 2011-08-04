<?php
/**
 * $Id: KTCorePlugin.php 7954 2008-01-25 05:56:52Z megan_w $
 *
 * KnowledgeTree Open Source Edition
 * Document Management Made Simple
 * Copyright (C) 2004 - 2008 The Jam Warehouse Software (Pty) Limited
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
 * You can contact The Jam Warehouse Software (Pty) Limited, Unit 1, Tramber Place,
 * Blake Street, Observatory, 7925 South Africa. or email info@knowledgetree.com.
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

require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/templating/templating.inc.php');
require_once(KT_LIB_DIR . '/util/ktVar.php');

class BaseConfigDispatcher extends KTAdminDispatcher
{
    protected $category = 'System Configuration';
    protected $name;

    public function check()
    {
        return parent::check();
    }

    public function do_main()
    {
	return $this->renderPage($this->getSettings());
    }

    public function do_save()
    {
	$settings = $this->getSettings();
	$settings = $this->saveSettings($settings);

	return $this->renderPage($settings);
    }

    private function renderPage($settings)
    {
	// Organise by group
        $groups = array();
        $groupList = array();
        foreach ($settings as $item) {
            $group_name = $item['group_display'];
            $groupList[$group_name]['id'] = $item['id'];
            $groupList[$group_name]['name'] = $group_name;
            $groupList[$group_name]['description'] = $item['group_description'];
            $groups[$group_name][] = $item;
        }

        $templating = KTTemplating::getSingleton();
        $template = $templating->loadTemplate('ktcore/configsettings');

        //set db config data being sent to template
        $template->setData(array(
            'context' => $this,
            'groupList' => $groupList,
            'groupSettings' => $groups,
            'section' => $this->name,
        ));

        return $template->render();
    }

    public function handleOutput($output)
    {
        print $output;
    }

    /**
     * Set an individual configuration setting (Used in global reasons setting to en-/disable other settings)
     *
     * @param $item
     * @param $value
     * @return unknown_type
     */
    private function setConfigSetting($item = null, $value = null)
    {
        $query = "SELECT item, value FROM config_settings WHERE item='{$item}' LIMIT 1";
        $results = DBUtil::getResultArray($query);
        if (count($results) <= 0) {
            throw new Exception("Config Setting '{$item}' could not be set because it could not be found.");
        }

        $row = $results[0];
        if ($item['value'] != $value) {
            if (!is_num($value) && !is_bool($value)) {
                $value = "'{$value}'";
            }
            $query = "UPDATE config_settings SET(value={$value} WHERE item='{$item}' LIMIT 1";
            $results = DBUtil::runQuery($query);
        }
    }

    /**
     * Get the configuration settings
     *
     * @return array
     */
    function getSettings()
    {
        $query = "SELECT g.display_name AS group_display, g.description AS group_description,
            s.id, s.item, s.display_name, s.description, s.value, s.default_value, s.type, s.options
            FROM config_groups g
            INNER JOIN config_settings s ON g.name = s.group_name
            WHERE category = '{$this->category}' AND s.can_edit = 1
            ORDER BY g.name, s.item";

        $results = DBUtil::getResultArray($query);

        if (PEAR::isError($results)) {
            $this->addErrorMessage(sprintf(_kt("The configuration settings could not be retrieved: %s") , $results->getMessage()));
            return array();
        }

        return $results;
    }

    /**
     * Render the form input for the given setting type.
     *
     * @param string $type
     * @param mixed $value
     * @param string $options
     * @return HTML
     */
    function renderInput($id, $type, $value, $defaultValue = '', $options = null)
    {
        if (!empty($options)) {
           $options = unserialize($options);
        }

        $input = '';
        if (!empty($defaultValue) && ($type == 'string' || $type == 'numeric_string' || empty($type))) {
            $pos = strpos($defaultValue, '${');

            if ($pos !== false) {
                $pos2 = strpos($defaultValue, '}', $pos);
                $var = substr($defaultValue, $pos + 2, $pos2 - ($pos + 2));

                global $default;
                $var = $default->$var;

                $defaultValue = preg_replace('/\$\{([^}]+)\}/', $var, $defaultValue);
            }

            $defaultValue = "<i>{$defaultValue}</i>";
            $input .= '<span class="descriptiveText">' . sprintf(_kt("The default value is %s") , $defaultValue) . '</span>';
        }

        /*
        The options array can contain a number of settings:
           - increment => the amount a numeric drop down will increment by
           - minimum => the minimum value of the numeric dropdown
           - maximum => the maximum value of the numeric dropdown
           - label => a word or sentence displayed before the input
           - append => a word or sentence displayed after the input
           - options
               => the values to be used in a dropdown, format: array(array('label' => 'xyz', 'value' => 'Xyz'), array('label' => 'abc', 'value' => 'Abc'));
               => the values to be used in a radio button, format: array('xyz', 'abc');
               => the values to be used in a numeric dropdown, format: array(array('label' => '10', 'value' => '10'), array('label' => '2', 'value' => '2'));
        */

        switch ($type) {
            case 'numeric':
                // If options aren't provided, create them
                if (!isset($options['options'])) {
                    $increment = isset($options['increment']) ? $options['increment'] : 5;
                    $minVal = isset($options['minimum']) ? $options['minimum'] : 0;
                    $maxVal = isset($options['maximum']) ? $options['maximum'] : 100;

                    $optionValues = array();
                    for ($i = $minVal; $i <= $maxVal; $i = $i + $increment) {
                        $optionValues[] = array('label' => $i, 'value' => $i);
                    }
                    $options['options'] = $optionValues;
                }

            case 'dropdown':
                $optionValues = array();
                $optionValues = $options['options'];

                $value = ($value == 'default') ? $defaultValue : $value;

                // Prepend a label if set
                $input .= isset($options['label']) ? "<label for='{$id}'>{$options['label']}</label>&nbsp;&nbsp;" : '';

                // Create dropdown
                $input .= "<select id='{$id}' name='configArray[{$id}]'>&nbsp;&nbsp;";
                foreach ($optionValues as $item) {
                    $selected = ($item['value'] == $value) ? 'selected' : '';
                    $input .= "<option value='{$item['value']}' $selected>{$item['label']}</option>";
                }
                $input .= '</select>';
                break;

            case 'boolean':
                $options['options'] = array('true', 'false');

                if ($value == 'true') {
                   $onChecked = 'checked="checked"';
                   $offChecked = '';
                   $onCssClass = 'selected';
                   $offCssClass = '';
                } else {
                   $onChecked = '';
                   $offChecked = 'checked="checked"';
                   $onCssClass = '';
                   $offCssClass = 'selected';
                }

                $input .= '<span class="switch">
                    <input type="radio" id="on_'.$id.'" name="configArray['.$id.']" value="true" '.$onChecked.' />
                    <input type="radio" id="off_'.$id.'" name="configArray['.$id.']" value="false" '.$offChecked.' />
                    <label for="on_'.$id.'" class="cb-enable '.$onCssClass.'"><span>ON</span></label>
                    <label for="off_'.$id.'" class="cb-disable '.$offCssClass.'"><span>OFF</span></label>
                </span>';

                break;

            case 'radio':
                $optionValues = array();
                $optionValues = $options['options'];

                $value = ($value == 'default') ? $defaultValue : $value;

                foreach ($optionValues as $item) {
                    $checked = ($item == $value) ? 'checked ' : '';

                    $input .= "<input type='radio' id='{$id}_{$item}' name='configArray[{$id}]' value='{$item}' {$checked}>&nbsp;&nbsp;";
                    $input .= "<label for={$id}>".ucwords($item).'</label>&nbsp;&nbsp;';
                }
                break;

            // Change this later to validate the numbers
            // For input where the number may be anything like a Port or the number may be a float instead of an integer
            case 'numeric_string':
                // Prepend a label if set
                $input .= isset($options['label']) ? "<label for='{$id}'>{$options['label']}</label>&nbsp;&nbsp;" : '';
                $input .= "<input name='configArray[{$id}]' value='{$value}' size = '5'>";
                break;

            case 'class':
            	if (!file_exists($options['file'])) { return ; }
                require_once($options['file']);
                $oClass = new $options['class']();
                $input = $oClass->getInputs($id, $type, $value, $defaultValue, $options);
                break;

            case 'string':
            default:
                // Prepend a label if set
                $input .= isset($options['label']) ? "<label for='{$id}'>{$options['label']}</label>&nbsp;&nbsp;" : '';
                $input .= "<input name='configArray[{$id}]' value='{$value}' size = '60'>";
        }

        // Append any text
        $input .= isset($options['append']) ? '&nbsp;&nbsp;'.sprintf(_kt('%s') , $options['append']) : '';

        return $input;
    }

    public function getStyle($options)
    {
        if (empty($options)) { return ''; }
        $options = unserialize($options);
    	if (!file_exists($options['file'])) { return ; }
        require_once($options['file']);
        $oClass = new $options['class']();
		if(method_exists($oClass, 'getStyle')) {
			return $oClass->getStyle();
		}

		return '';
    }

    /**
     * Save any modified settings, clear the cached settings and return the new settings
     *
     * @param array $currentSettings
     * @return array
     */
    function saveSettings($currentSettings, $log = false)
    {
        $newSettings = isset($_POST['configArray']) ? $_POST['configArray'] : '';
        if (!empty($newSettings)) {
            $this->addInfoMessage(_kt('The configuration settings have been updated.'));

            if ($log) {
                $comment = array();
            }

	    foreach ($currentSettings as $setting) {
		$new = $newSettings[$setting['id']];
		if ($setting['value'] != $new) {
		    $res = DBUtil::autoUpdate('config_settings', array('value' => $new), $setting['id']);
		    if (PEAR::isError($res)) {
			$this->addErrorMessage(sprintf(_kt("The setting %s could not be updated: %s") , $setting['display_name'], $res->getMessage()));
		    }

		    if ($log) {
			$comment[] = sprintf(_kt("%s from %s to %s") , $setting['display_name'], $setting['value'], $new);
		    }
		}
	    }

	    if ($log) {
		$this->logTransaction($comment);
	    }

	    // Clear the cached settings
	    $oKTConfig = new KTConfig();
	    $oKTConfig->clearCache();

	    // Get the new settings from the DB
	    $currentSettings = $this->getSettings();
        }

        return $currentSettings;
    }

    protected function logTransaction($aComment = null)
    {
        $comment = implode(', ', $aComment);
        $comment = _kt('Config settings modified: ').$comment;

        // log the transaction
        $date = date('Y-m-d H:i:s');

        require_once(KT_LIB_DIR . '/users/userhistory.inc.php');
        $params = array(
            'userid' => $_SESSION['userID'],
            'datetime' => $date,
            'actionnamespace' => 'ktcore.transactions.modifying_config_settings',
            'comments' => $comment,
            'sessionid' => $_SESSION['sessionID'],
        );

        KTUserHistory::createFromArray($params);
    }
}

class UIConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'User Interface Settings';
        $this->name = _kt('User Interface Settings');

        //$this->aBreadcrumbs[] = array(
        //    'url' => $_SERVER['PHP_SELF'],
        //    'name' => $this->name
        //);

        return parent::check();
    }
}

class ClientSettingsConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Client Tools Settings';
        $this->name = _kt('Client Tools Settings');

        //$this->aBreadcrumbs[] = array(
        //    'url' => $_SERVER['PHP_SELF'],
        //    'name' => _kt('Client Tools Settings'),
        //);

        return parent::check();
    }
}

class EmailConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Email Settings';
        $this->name = _kt('Email Settings');

        //$this->aBreadcrumbs[] = array(
        //    'url' => $_SERVER['PHP_SELF'],
        //    'name' => _kt('Email Settings'),
        //);

        return parent::check();
    }
}

class ActionReasonsDispatcher extends BaseConfigDispatcher
{
    function check()
    {
        $this->category = 'Document Action Settings';
        $this->name = _kt('Document Action Settings');

        //$this->aBreadcrumbs[] = array(
        //    'url' => $_SERVER['PHP_SELF'],
        //    'name' => _kt('Document Action Settings'),
        //);

        return parent::check();
    }

    /**
     * Extending the original saveSettings to intercept changes
     *
     * @see plugins/ktcore/admin/BaseConfigDispatcher#saveSettings($currentSettings, $log)
     */
    public function saveSettings($currentSettings, $log = false)
    {
        $currentSettings = parent::saveSettings($currentSettings, $log);
        $item = $this->getItemSettings('globalReasons', $currentSettings);
        $this->update($item['value']);

        return $currentSettings;
    }

    /**
     * Get configuration settings for a single item
     *
     * @param $itemName
     * @return array()
     */
    private function getItemSettings($itemName = null, $currentSettings = null)
    {
        $settings = is_array($currentSettings) ? $currentSettings : $this->getSettings();
        foreach ($settings as $item) {
            if ($item['item'] == $itemName) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Update all other touched settings
     *
     * @param $enabled
     * @return void
     */
    private function update($enabled = null)
    {
        $affected_settings = array(
            'clientToolPolicies/captureReasonsDelete',
            'clientToolPolicies/captureReasonsCheckin',
            'clientToolPolicies/captureReasonsCheckout',
            'clientToolPolicies/captureReasonsCancelCheckout',
            'clientToolPolicies/captureReasonsCopyInKT',
            'clientToolPolicies/captureReasonsMoveInKT',
            'addInPolicies/captureReasonsCheckin',
            'addInPolicies/captureReasonsCheckout'
        );

        $oConfig = KTConfig::getSingleton();
        foreach ($affected_settings as $setting) {
            $oConfig->set($setting, $enabled);
        }

        $oConfig->readConfig();
    }
}

class GeneralConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'General Settings';
        $this->name = _kt('General Settings');

        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'name' => _kt('General Settings'),
        );

        return parent::check();
    }
}

class i18nConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Internationalisation Settings';
        $this->name = _kt('Internationalisation Settings');

        //$this->aBreadcrumbs[] = array(
        //    'url' => $_SERVER['PHP_SELF'],
        //    'name' => _kt('Internationalisation Settings'),
        //);

        return parent::check();
    }
}

class SearchAndIndexingConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Search and Indexing Settings';
        $this->name = _kt('Search and Indexing Settings');

        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'name' => _kt('Search and Indexing Settings'),
        );

        return parent::check();
    }
}

class SecurityConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Security Settings';
        $this->name = _kt('Security Settings');

        return parent::check();
    }

    function saveSettings($currentSettings)
    {
        return parent::saveSettings($currentSettings, true);
    }
}

class KtToolsConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'KnowledgeTree Tools Settings';
        $this->name = _kt('KnowledgeTree Tools Settings');

        return parent::check();
    }

    function saveSettings($currentSettings)
    {
        return parent::saveSettings($currentSettings, true);
    }
}

class KtWebdavConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'WebDAV Settings';
        $this->name = _kt('WebDAV Settings');

        return parent::check();
    }

    function saveSettings($currentSettings)
    {
        return parent::saveSettings($currentSettings, true);
    }
}

class ExplorerConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Explorer CP Settings';
        $this->name = _kt('Explorer CP');

        return parent::check();
    }

    function saveSettings($currentSettings)
    {
        return parent::saveSettings($currentSettings, true);
    }
}

class WebservicesConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Web Services Settings';
        $this->name = _kt('Web Services');

        return parent::check();
    }

    function saveSettings($currentSettings)
    {
        return parent::saveSettings($currentSettings, true);
    }
}


class SessionConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Session Management Settings';
        $this->name = _kt('Session Management');

        return parent::check();
    }
}

class TimezoneConfigPageDispatcher extends BaseConfigDispatcher
{
    public function check()
    {
        $this->category = 'Timezone Settings';
        $this->name = _kt('Timezone');

        return parent::check();
    }
}
?>
