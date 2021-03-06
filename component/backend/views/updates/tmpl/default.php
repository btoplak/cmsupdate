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

JHtml::_('behavior.framework');
JHtml::_('bootstrap.framework');
JHtml::_('bootstrap.tooltip');

F0FTemplateUtils::addJS('media://com_cmsupdate/js/common.js?'.ACU_VERSIONHASH);
?>
<form action="index.php" method="POST" id="adminForm">
	<input type="hidden" name="option" value="com_cmsupdate" />
	<input type="hidden" name="view" value="update" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="source" value="" />
	<input type="hidden" name="<?php echo JFactory::getSession()->getFormToken() ?>" value="1" />


	<ul class="nav nav-tabs" id="cmsupdateTabs">
		<li class="active">
			<a href="#cmsupdateMain" data-toggle="tab">
				<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_QUICKUPDATE_HEADER') ?>
			</a>
		</li>
		<li>
			<a href="#cmsupdateAll" data-toggle="tab">
				<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_ALLVERSIONS_HEADER') ?>
			</a></li>
	</ul>

	<div class="tab-content">
		<div class="tab-pane active" id="cmsupdateMain">
			<?php if (!$this->updateInfo->status): ?>
				<div class="alert alert-success">
					<a class="close" data-dismiss="alert" href="#">&times;</a>
					<h3>
						<?php echo JText::_('COM_CMSUPDATE_UPDATES_MSG_NOUPDATES_HEADER'); ?>
					</h3>
					<p>
						<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_MSG_NOUPDATES', JVERSION); ?>
					</p>
				</div>
			<?php else: ?>
				<div class="alert alert-danger">
					<a class="close" data-dismiss="alert" href="#">&times;</a>
					<h3>
						<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_MSG_UPDATES_HEADER', JVERSION); ?>
					</h3>
					<p>
						<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_MSG_UPDATES_CURRENT', JVERSION); ?>
					</p>
					<p>
						<strong>
						<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_MSG_UPDATETYPE_' . $this->updateInfo->source, $this->updateInfo->{$this->updateInfo->source}['version']); ?>
						</strong>
					</p>
					<p>
						<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_MSG_UPDATESOURCE', $this->updateInfo->{$this->updateInfo->source}['package']); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if($this->needsConfig): ?>
				<div id="updateOptions" class="well well-small" style="display: none">
					<h4>
						<?php echo JText::_('COM_CMSUPDATE_UPDATES_MSG_UPDATEOPTIONS_HEADER'); ?>
					</h4>
					<div class="form form-horizontal">
						<?php if ($this->hasAkeebaBackup): ?>
							<div class="control-group">
								<label class="control-label" for="backupOnUpdate">
									<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_UPDATEOPTIONS_BACKUPONUPDATE') ?>
								</label>
								<div class="control-group">
									<?php if (version_compare(JVERSION, '3.0', 'ge')): ?>
										<div class="btn-group radio btn-group-yesno">
											<input type="radio" id="backupOnUpdateYes" name="backupOnUpdate" value="1" <?php echo $this->hasBackupOnUpdate ? 'checked="checked"' : '' ?> class="btn active btn-success" />
											<label for="backupOnUpdateYes"><?php echo JText::_('JYES') ?></label>
											<input type="radio" id="backupOnUpdateNo" name="backupOnUpdate" value="0" <?php echo $this->hasBackupOnUpdate ? '' : 'checked="checked"' ?> class="btn" />
											<label for="backupOnUpdateNo"><?php echo JText::_('JNO') ?></label>
										</div>
									<?php else: ?>
										<select name="backupOnUpdate" id="backupOnUpdate" class="input-medium">
											<option value="1" <?php echo $this->hasBackupOnUpdate ? 'selected="selected"' : '' ?>><?php echo JText::_('JYES');?></option>
											<option value="0" <?php echo $this->hasBackupOnUpdate ? '' : 'selected="selected"' ?>><?php echo JText::_('JNO');?></option>
										</select>
									<?php endif; ?>
								</div>
							</div>
						<?php endif;?>
						<?php if ($this->ftpOptions['enable'] && (empty($this->ftpOptions['user']) || empty($this->ftpOptions['pass']))): ?>
							<div class="control-group">
								<label class="control-label" for="ftp_user">
									<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_UPDATEOPTIONS_FTPUSER') ?>
								</label>
								<div class="control-group">
									<input type="text" name="user" id="ftp_user" value="<?php echo $this->ftpOptions['user'] ?>" />
								</div>
							</div>
							<div class="control-group">
								<label class="control-label" for="ftp_pass">
									<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_UPDATEOPTIONS_FTPPASS') ?>
								</label>
								<div class="control-group">
									<input type="password" name="pass" id="ftp_pass" value="<?php echo $this->ftpOptions['pass'] ?>" />
								</div>
							</div>
						<?php endif;?>
					</div>
				</div>
			<?php endif;?>

			<p class="form-actions">
				<span style="display: none">
				<?php if (!empty($this->updateInfo->installed['version'])): ?>
					<button onclick="cmsupdate.submitform('init', {source: 'installed'}); return false;"
							class="btn btn-small hasTooltip" title="<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_REINSTALL_TOOLTIP') ?>">
						<span class="icon icon-play-circle"></span>
						<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_BTN_REINSTALL', $this->updateInfo->installed['version']); ?>
					</button>
				<?php endif; ?>
				</span>

				<?php if ($this->updateInfo->status): ?>
					<button onclick="cmsupdate.submitform('init', {source: '<?php echo $this->updateInfo->source ?>'}); return false;"
							class="btn btn-primary hasTooltip" title="<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_UPDATEMAIN_TOOLTIP') ?>">
						<span class="icon icon-play icon-white"></span>
						<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_BTN_UPDATEMAIN', $this->updateInfo->{$this->updateInfo->source}['version']); ?>
					</button>
				<?php endif; ?>

				<?php if($this->needsConfig): ?>
					<button class="btn btn-small hasTooltip" onclick="cmsupdate.toggleUpdateOptions(); return false;" title="<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_UPDATEOPTIONS_TOOLTIP') ?>">
						<span class="icon icon-signal"></span>
						<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_UPDATEOPTIONS') ?>
					</button>
				<?php endif; ?>
			</p>

			<p class="form-inline">
				<a href="index.php?option=com_cmsupdate&force=1" class="btn btn-small hasTooltip"
				   title="<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_RELOAD_TOOLTIP') ?>">
					<span class="icon icon-refresh"></span>
					<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_RELOAD'); ?>
				</a>

				<span class="help-inline">
					<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_LBL_LASTCHECK', $this->lastCheckHR) ?>
				</span>
			</p>
		</div>

		<div class="tab-pane" id="cmsupdateAll">
			<h3>
				<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_ALLVERSIONS_HEADER'); ?>
			</h3>

			<div class="alert alert-warning">
				<a class="close" data-dismiss="alert" href="#">&times;</a>
				<p>
					<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_ALLVERSIONS_INFO'); ?>
				</p>
			</div>

			<table class="table table-striped">
				<?php foreach($this->updateInfo as $type => $update):
					if (!is_array($update)) continue;
					if (empty($update['version'])) continue;
					?>
					<tr>
						<td>
			<span class="hasTooltip" title="<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_TYPE_' . $type . '_TOOLTIP') ?>">
				<span class="icon icon-question-sign"></span>
				<?php echo JText::_('COM_CMSUPDATE_UPDATES_LBL_TYPE_' . $type) ?>
			</span>
						</td>
						<td>
							<?php echo $this->escape($update['version']) ?>
						</td>
						<td>
							<button class="btn btn-small btn-inverse hasTooltip"
									onclick="cmsupdate.submitform('init', {source: '<?php echo $type ?>'}); return false;"
									title="<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_UPDATE_TOOLTIP') ?>">
								<span class="icon icon-play icon-white"></span>
								<?php echo JText::sprintf('COM_CMSUPDATE_UPDATES_BTN_UPDATE', $update['version']); ?>
							</button>
						</td>
						<td>
							<a href="<?php echo $this->escape($update['package']) ?>" class="btn btn-small hasTooltip" title="<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_DOWNLOAD_TOOLTIP'); ?>">
								<span class="icon icon-download"></span>
								<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_DOWNLOAD'); ?>

							</a>
							<a href="<?php echo $this->escape($update['infourl']) ?>" class="btn btn-small btn-info hasTooltip" title="<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_INFO_TOOLTIP'); ?>" target="_blank">
								<span class="icon icon-comment icon-white"></span>
								<?php echo JText::_('COM_CMSUPDATE_UPDATES_BTN_INFO'); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
	</div>
</form>
<?php
if($this->statsIframe)
{
    echo $this->statsIframe;
}
?>