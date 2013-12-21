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

JHtmlBehavior::framework(true);
JHtml::_('bootstrap.framework');
JHtml::_('bootstrap.tooltip');

FOFTemplateUtils::addJS('media://com_cmsupdate/js/common.js');
FOFTemplateUtils::addJS('media://com_cmsupdate/js/encryption.js');

JFactory::getDocument()->addScript('//yandex.st/json2/2011-10-19/json2.min.js');

?>

<div id="extractProgress">
	<div class="alert alert-warning">
		<span><?php echo JText::_('COM_CMSUPDATE_EXTRACT_LBL_DONTCLOSEEXTRACT')?></span>
	</div>

	<div id="extractProgressBarContainer" class="progress progress-striped active">
		<div id="extractProgressBar" class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
			<span class="sr-only" id="extractProgressBarInfo">0%</span>
		</div>
	</div>
	<div class="alert alert-info" id="extractProgressInfo">
		<h4>
			<?php echo JText::_('COM_CMSUPDATE_EXTRACT_LBL_EXTRACTPROGRESS') ?>
		</h4>
		<div class="panel-body" id="extractProgressBarText">
			<span class="icon icon-signal"></span>
			<span id="extractProgressBarTextPercent">0</span> %
			<br/>
			<span class="icon icon-folder-open"></span>
			<span id="extractProgressBarTextIn">0 KiB</span>
			<br/>
			<span class="icon icon-hdd"></span>
			<span id="extractProgressBarTextOut">0 KiB</span>
			<br/>
			<span class="icon icon-file"></span>
			<span id="extractProgressBarTextFile"></span>
		</div>
	</div>
</div>

<div id="extractError" style="display: none">
	<div class="alert alert-danger">
		<h4>
			<?php echo JText::_('COM_CMSUPDATE_EXTRACT_ERR_EXTRACTERROR_HEADER') ?>
		</h4>
		<div class="panel-body" id="extractErrorText"></div>
	</div>
</div>

<script type="text/javascript">
	(function($){
		extractErrorHandler = function(msg)
		{
			(function($){
				$('#extractProgressInfo').hide();
				$('#extractProgressBarContainer').addClass('progress-danger');
				$('#extractProgressBarContainer').removeClass('active');
				$('#extractProgressBarContainer').removeClass('progress-striped');
				$('#extractErrorText').html(msg);
				$('#extractError').show('fast');
			})(cmsupdate.jQuery);
		}

		cmsupdate.nextStep = function()
		{
			window.location = 'index.php?option=com_cmsupdate&view=update&task=extract&<?php echo JFactory::getSession()->getFormToken() ?>=1';
		}

		$(document).ready(function(){
			cmsupdate.ajax_url = '<?php echo JUri::base() ?>components/com_cmsupdate/restore.php';
			cmsupdate.error_callback = extractErrorHandler;
			cmsupdate.update_password = '<?php echo $this->update_password ?>';
			cmsupdate.startExtract();
		});
	})(akeeba.jQuery);
</script>