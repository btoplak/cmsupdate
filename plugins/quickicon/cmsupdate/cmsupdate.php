<?php
/**
 *  @package    AkeebaCMSUpdate
 *  @copyright  Copyright (c)2010-2013 Nicholas K. Dionysopoulos
 *  @license    GNU General Public License version 3, or later
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

defined('_JEXEC') or die;

// PHP version check
if(defined('PHP_VERSION')) {
	$version = PHP_VERSION;
} elseif(function_exists('phpversion')) {
	$version = phpversion();
} else {
	$version = '5.0.0'; // all bets are off!
}
if(!version_compare($version, '5.3.1', 'ge')) return;

JLoader::import('joomla.application.plugin');

// Make sure CMS Update is installed, otherwise bail out
if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_cmsupdate/cmsupdate.php'))
{
	return;
}

// Crude ACL check, mainly to eliminate complaints by cognitively challenged
// individuals dealing with sites with crashed sessions...
$user = JFactory::getUser();
if (!$user->authorise('core.manage', 'com_cmsupdate'))
{
	return;
}

/**
 * CMS Update notification plugin for Joomla! updates
 *
 * It's funny how I had written the original plugin used in Joomla! 1.7 and
 * now find myself writing a new plugin to do the same thing, for a much
 * better Joomla! CMS updater :)
 */
class plgQuickiconCmsupdate extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 *
	 * @since       2.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		$this->loadLanguage();
	}

	/**
	 * This method is called when the Quick Icons module is constructing its set
	 * of icons. You can return an array which defines a single icon and it will
	 * be rendered right after the stock Quick Icons.
	 *
	 * @param   string  $context  The calling context
	 *
	 * @return  array  A list of icon definition associative arrays, consisting of the
	 *                 keys link, image, text and access.
	 *
	 * @since   2.5
	 */
	public function onGetIcons($context)
	{
		if ($context != $this->params->get('context', 'mod_quickicon') || !JFactory::getUser()->authorise('core.manage', 'com_cmsupdate'))
		{
			return;
		}

		// Load FOF
		include_once JPATH_LIBRARIES . '/fof/include.php';

		if(!defined('FOF_INCLUDED') || !class_exists('FOFForm', true))
		{
			return;
		}

		// Load the ACU library's autoloader
		if (!class_exists('AcuAutoloader', false))
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_cmsupdate/lib/autoloader.php';
			AcuAutoloader::init();
		}

		// Get the updates
		$model = FOFModel::getTmpInstance('Updates', 'CmsupdateModel');
		$updates = $model->getUpdateInfo();

		$ret = array(
			'link' => 'index.php?option=com_cmsupdate',
			'image' => 'joomla',
			'icon' => 'header/icon-48-download.png',
			'text' => JText::_('PLG_QUICKICON_CMSUPDATE_OK'),
			'id' => 'plg_quickicon_cmsupdate',
			'group' => 'MOD_QUICKICON_MAINTENANCE',
		);

		if ($updates->status)
		{
			// Change the icon text
			$source = $updates->source;
			$update = $updates->$source;

			$ret['text'] = JText::sprintf('PLG_QUICKICON_CMSUPDATE_UPDATEAVAILABLE', $update['version']);
			$ret['icon'] = 'header/icon-48-jupdate-updatefound.png';

			// Add a prominent notification on the Control Panel page
			JHtml::_('jquery.framework');

			$buttonText = JText::sprintf('PLG_QUICKICON_CMSUPDATE_BUTTONTEXT', $update['version']);
			$alertText = JText::_('PLG_QUICKICON_CMSUPDATE_UPDATEAVAILABLE', true);
			$alertText = str_replace('%s', $update['version'], $alertText);
			$script = <<< JS
jQuery(document).ready(function()
{
	jQuery('#system-message-container').prepend(
		'<div class="alert alert-error alert-joomlaupdate">'
		+ '$alertText'
		+ ' <button class="btn btn-primary btn-small" onclick="document.location=\'index.php?option=com_cmsupdate\'">'
		+ '$buttonText</button>'
		+ '</div>');
});

JS;

			JFactory::getDocument()->addScriptDeclaration($script);
		}

		return array($ret);
	}
}
