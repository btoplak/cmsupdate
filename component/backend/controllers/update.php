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

class CmsupdateControllerUpdate extends FOFController
{
	/**
	 * Executes a given controller task. The onBefore<task> and onAfter<task>
	 * methods are called automatically if they exist.
	 *
	 * @param   string $task The task to execute, e.g. "browse"
	 *
	 * @throws  Exception   Exception thrown if the onBefore<task> returns false
	 *
	 * @return  null|bool  False on execution failure
	 */
	public function execute($task)
	{
		$allowedTasks = array('browse', 'init', 'download', 'extract', 'finalise');

		if (!in_array($task, $allowedTasks))
		{
			$task = 'browse';
		}

		return parent::execute($task);
	}

	public function onBeforeBrowse()
	{
		$result = parent::onBeforeBrowse();

		if ($result)
		{
			$force = $this->input->getInt('force', 0);
			$this->getThisModel()->setState('force', $force);
		}

		return $result;
	}

	public function init()
	{
		if ($this->csrfProtection)
		{
			$this->_csrfProtection();
		}

		// @todo
	}
} 