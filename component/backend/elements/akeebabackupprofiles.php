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

/**
 * A field to display Akeeba Backup profiles
 */
class JFormFieldAkeebabackupprofiles extends JFormFieldList
{
	/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$_name = 'akeebabackupprofiles';

	protected function getOptions()
	{
		$options = parent::getOptions();

		if (!is_array($options))
		{
			$options = array();
		}

		$db = JFactory::getDbo();

		$tables = $db->getTableList();
		$tableName = $db->replacePrefix('#__ak_profiles');

		if (!in_array($tableName, $tables))
		{
			return $options;
		}

		$query = $db->getQuery(true)
			->select(array(
				$db->qn('id'),
				$db->qn('description'),
			))
			->from($db->qn('#__ak_profiles'));
		$db->setQuery($query);
		$profiles = $db->loadAssocList();

		if (!empty($profiles))
		{
			foreach ($profiles as $profile)
			{
				$options[] = JHtml::_(
					'select.option', $profile['id'], $profile['description'], 'value', 'text'
				);
			}
		}

		return $options;
	}
}