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

defined('_JEXEC') or die();

// Load FOF
include_once JPATH_LIBRARIES . '/fof/include.php';
if(!defined('FOF_INCLUDED') || !class_exists('FOFForm', true))
{
	JError::raiseError ('500', 'Your Akeeba CMS Update installation is broken; please re-install. Alternatively, extract the installation archive and copy the fof directory inside your site\'s libraries directory.');

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
	require_once FOFTemplateUtils::parsePath('media://akeeba_strapper/strapper.php', true);

	AkeebaStrapper::jQuery();
	AkeebaStrapper::bootstrap();
}

// Access check, Joomla! 1.6 style.
if (!JFactory::getUser()->authorise('core.manage', 'com_cmsupdate'))
{
	return JError::raiseError(403, JText::_('JERROR_ALERTNOAUTHOR'));
}

FOFDispatcher::getTmpInstance('com_cmsupdate')->dispatch();