<?php
/**
 * @package    AkeebaCMSUpdate
 * @copyright  Copyright (c)2010-2014 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 3, or later
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

defined('_JEXEC') or die();

// Load F0F
include_once JPATH_LIBRARIES . '/f0f/include.php';
if (!defined('F0F_INCLUDED') || !class_exists('F0FForm', true))
{
	JError::raiseError('500', 'Your Akeeba CMS Update installation is broken; please re-install. Alternatively, extract the installation archive and copy the f0f directory to your site\'s libraries/f0f directory.');

	return;
}

// Make sure we have Joomla! 2.5.15 or later
if (version_compare(JVERSION, '2.5.15', 'lt'))
{
	JError::raiseError('500', 'Akeeba CMS Update requires Joomla! 2.5.15 or later.');

	return;
}

// Load the ACU library's autoloader
if (!class_exists('AcuAutoloader', false))
{
	require_once __DIR__ . '/lib/autoloader.php';
	AcuAutoloader::init();
}

// Load Akeeba Strapper
if (!class_exists('AkeebaStrapper'))
{
	require_once F0FTemplateUtils::parsePath('media://akeeba_strapper/strapper.php', true);

	AkeebaStrapper::jQuery();
	AkeebaStrapper::bootstrap();
}

// Access check, Joomla! 1.6 style.
if (!JFactory::getUser()->authorise('core.manage', 'com_cmsupdate'))
{
	return JError::raiseError(403, JText::_('JERROR_ALERTNOAUTHOR'));
}

require_once __DIR__.'/version.php';

define('ACU_VERSIONHASH', md5(ACU_VERSION.ACU_DATE));

F0FDispatcher::getTmpInstance('com_cmsupdate')->dispatch();