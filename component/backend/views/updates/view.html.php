<?php
/**
 *  @package    AkeebaCMSUpdate
 *  @copyright  Copyright (c)2010-2014 Nicholas K. Dionysopoulos
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

class CmsupdateViewUpdates extends F0FViewHtml
{
	public function onBrowse($tpl = null)
	{
		$model = $this->getModel();

		$force = $model->getState('force', 0);

		$this->updateInfo 			= $model->getUpdateInfo($force);
		$this->lastCheckHR 			= $model->getHumanReadableLastCheck();
		$this->hasAkeebaBackup 		= $model->hasAkeebaBackup();
		$this->ftpOptions			= $model->getFTPOptions();
		$this->needsConfig			= $this->hasAkeebaBackup || ($this->ftpOptions['enable'] && (empty($this->ftpOptions['user']) || empty($this->ftpOptions['pass'])));
		$this->hasBackupOnUpdate	= $model->hasBackupOnUpdate();

		return true;
	}

	public function onDownload($tpl = null)
	{
		$this->setLayout('download');

		return true;
	}

	public function onExtract($tpl = null)
	{
		$this->setLayout('extract');

		$this->update_password = $this->getModel()->update_password;
		$this->hasAdmintools = $this->getModel()->hasAdminTools();

		return true;
	}
}