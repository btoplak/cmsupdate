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

F0FTemplateUtils::addJS('media://com_cmsupdate/js/common.js');

JFactory::getDocument()->addScript('//yandex.st/json2/2011-10-19/json2.min.js');
?>

<div id="downloadProgress">
	<div class="alert alert-warning">
		<span><?php echo JText::_('COM_CMSUPDATE_DOWNLOAD_LBL_DONTCLOSEDOWNLOAD')?></span>
	</div>

	<div id="downloadProgressBarContainer" class="progress progress-striped active">
		<div id="downloadProgressBar" class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
			<span class="sr-only" id="downloadProgressBarInfo">0%</span>
		</div>
	</div>
	<div class="alert alert-info" id="downloadProgressInfo">
		<h4>
			<?php echo JText::_('COM_CMSUPDATE_DOWNLOAD_LBL_DOWNLOADPROGRESS') ?>
		<h4>
		<div class="panel-body" id="downloadProgressBarText"></div>
	</div>
</div>

<div id="downloadError" style="display: none">
	<div class="alert alert-danger">
		<h4>
			<?php echo JText::_('COM_CMSUPDATE_DOWNLOAD_ERR_DOWNLOADERROR_HEADER') ?>
		</h4>
		<div class="panel-body" id="downloadErrorText"></div>
	</div>
</div>

<script type="text/javascript">
	(function($){
		downloadErrorHandler = function(msg)
		{
			(function($){
				$('#downloadProgressInfo').hide();
				$('#downloadProgressBarContainer').addClass('progress-danger');
				$('#downloadProgressBarContainer').removeClass('active');
				$('#downloadProgressBarContainer').removeClass('progress-striped');
				$('#downloadErrorText').html(msg);
				$('#downloadError').show('fast');
			})(cmsupdate.jQuery);
		}

		cmsupdate.nextStep = function()
		{
			window.location = 'index.php?option=com_cmsupdate&view=update&task=extract&<?php echo JFactory::getSession()->getFormToken() ?>=1';
		}

		$(document).ready(function(){
			cmsupdate.ajax_url = 'index.php?option=com_cmsupdate&view=update&task=downloader&format=raw';
			cmsupdate.error_callback = downloadErrorHandler;
			cmsupdate.startDownload();
		});
	})(akeeba.jQuery);
</script>