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

// Only define the cmsupdate namespace if not defined.
if (typeof(cmsupdate) === 'undefined') {
    var cmsupdate = {
        ajax_url:           '',
        error_callback:     ''
    };
}

if(typeof(cmsupdate.jQuery) == 'undefined') {
    if (typeof(akeeba) != 'undefined') {
        if (typeof(akeeba.jQuery) != 'undefined') {
            cmsupdate.jQuery = akeeba.jQuery;
        }
    }
    if(typeof(cmsupdate.jQuery) == 'undefined') {
        cmsupdate.jQuery = jQuery.noConflict();
    }
}

/**
 * Generic submit form
 *
 * @param   string  task     The task to use when submitting the form
 * @param   object  options  Any other form fields you want to modify. Empty = ignored.
 * @param   string  form     The form DOM object or id. Empty = use the one with id='adminForm'.
 *
 * @return  void
 */
cmsupdate.submitform = function(task, options, form)
{
    if (typeof(form) === 'undefined')
    {
        form = document.getElementById('adminForm');
    }
    else if (typeof(form) === 'string')
    {
        form = document.getElementById(form);
    }

    if ((typeof(task) !== 'undefined') && (task !== ""))
    {
        form.task.value = task;
    }

    if ((typeof(options) == 'object') && (options !== ""))
    {
        for (var key in options)
        {
            form.elements[key].value = options[key];
        }
    }

    // Submit the form.
    if (typeof form.onsubmit == 'function')
    {
        form.onsubmit();
    }

    if (typeof form.fireEvent == "function")
    {
        form.fireEvent('submit');
    }

    form.submit();
}

/**
 * Toggles the update options pane in the main updates view
 *
 * @returns  boolean
 */
cmsupdate.toogleUpdateOptions = function()
{
    (function($){
        var display = $('#updateOptions').css('display');

        $('#updateOptions').toggle('fast');
    })(cmsupdate.jQuery);

    return false;
}

/**
 * Performs an AJAX request and returns the parsed JSON output.
 * The cmsupdate.ajax_url is used as the AJAX proxy URL.
 * If there is no errorCallback, the cmsupdate.error_callback is used.
 *
 * @param   object    data             An object with the query data, e.g. a serialized form
 * @param   function  successCallback  A function accepting a single object parameter, called on success
 * @param   function  errorCallback    A function accepting a single string parameter, called on failure
 */
cmsupdate.doAjax = function(data, successCallback, errorCallback)
{
    var structure =
    {
        type: "POST",
        url: this.ajax_url,
        cache: false,
        data: data,
        timeout: 600000,

        success: function(msg) {
            // Initialize
            var junk = null;
            var message = "";

            // Get rid of junk before the data
            var valid_pos = msg.indexOf('###');

            if( valid_pos == -1 ) {
                // Valid data not found in the response
                msg = 'Invalid AJAX data received:<br/>' + msg;

                if(errorCallback == null)
                {
                    if(cmsupdate.error_callback != null)
                    {
                        cmsupdate.error_callback(msg);
                    }
                }
                else
                {
                    errorCallback(msg);
                }

                return;
            }
            else if( valid_pos != 0 )
            {
                // Data is prefixed with junk
                junk = msg.substr(0, valid_pos);
                message = msg.substr(valid_pos);
            }
            else
            {
                message = msg;
            }
            message = message.substr(3); // Remove triple hash in the beginning

            // Get of rid of junk after the data
            var valid_pos = message.lastIndexOf('###');
            message = message.substr(0, valid_pos); // Remove triple hash in the end

            try
            {
                var data = eval('('+message+')');
            }
            catch(err)
            {
                var msg = err.message + "<br/><pre>\\n" + message + "\\n</pre>";

                if(errorCallback == null)
                {
                    if(cmsupdate.error_callback != null)
                    {
                        cmsupdate.error_callback(msg);
                    }
                }
                else
                {
                    errorCallback(msg);
                }
                return;
            }

            // Call the callback function
            successCallback(data);
        },

        error: function(Request, textStatus, errorThrown) {
            var message = '<strong>AJAX Loading Error</strong><br/>HTTP Status: ' + Request.status + ' (' + Request.statusText + ')<br/>';
            message = message + 'Internal status: ' + textStatus + '<br/>';
            message = message + 'XHR ReadyState: ' + Response.readyState + '<br/>';
            message = message + 'Raw server response:<br/>' + Request.responseText;

            if(errorCallback == null)
            {
                if(cmsupdate.error_callback != null)
                {
                    cmsupdate.error_callback(message);
                }
            }
            else
            {
                errorCallback(message);
            }
        }
    };

    cmsupdate.jQuery.ajax( structure );
}

/**
 * Generic error handler
 *
 * @param   string  msg  The error message to display
 */
cmsupdate.onGenericError = function(msg)
{
    alert(msg);
}

/**
 * Update a progress bar
 *
 * @param   integer  percent        The percent to set the progress bar to, must be 0 to 100
 * @param   string   progressBarId  The ID of the progress bar to set
 */
cmsupdate.setProgressBar = function(percent, progressBarId)
{
    (function($){
        if (progressBarId == undefined)
        {
            progressBarId = 'downloadProgressBar';
        }

        var newValue = 0;

        if(percent <= 1)
        {
            newValue = 100 * percent;
        }
        else
        {
            newValue = percent;
        }

        if (newValue < 0)
        {
            newValue = 0;
        }

        if (newValue > 100)
        {
            newValue = 100;
        }

        if (newValue < 100)
        {
            $('#' + progressBarId + 'Container').addClass('active');
            $('#' + progressBarId + 'Container').addClass('progress-striped');
            $('#' + progressBarId + 'Container').removeClass('progress-danger');
            $('#' + progressBarId + 'Container').removeClass('progress-success');
        }
        else
        {
            $('#' + progressBarId + 'Container').removeClass('active');
            $('#' + progressBarId + 'Container').removeClass('progress-striped');
            $('#' + progressBarId + 'Container').removeClass('progress-danger');
            $('#' + progressBarId + 'Container').addClass('progress-success');
        }

        $('#' + progressBarId).css('width',newValue + '%');
        $('#' + progressBarId + 'Info').text(newValue + '%');
    })(cmsupdate.jQuery);
}

/**
 * Converts a bytes value to a human readable form (e.g. KiB, MiB etc)
 *
 * @param   integer  bytes  The number of bytes, e.g. 1124
 * @param   boolean  si     Set to true to use base 10 for unit conversion, otherwise base 2 is used
 *
 * @returns  string The human readable size with maximum 1 decimal precision, e.g. 1.1 Kib
 */
cmsupdate.humanFileSize = function (bytes, si)
{
    var thresh = si ? 1000 : 1024;
    if(bytes < thresh) return bytes + ' B';
    var units = si ? ['kB','MB','GB','TB','PB','EB','ZB','YB'] : ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
    var u = -1;
    do {
        bytes /= thresh;
        ++u;
    } while(bytes >= thresh);
    return bytes.toFixed(1)+' '+units[u];
};

cmsupdate.startDownload = function()
{
    (function($){
        cmsupdate.setProgressBar(0, 'downloadProgressBar');
        $('#downloadProgressBarText').html('');

        var jsonObject = {
            frag:       -1,
            totalSize:  -1,
        };

        var data = {
            'task' : 'downloader',
            'json' : JSON.stringify(jsonObject)
        };

        cmsupdate.doAjax(data, function(ret){
            cmsupdate.stepDownload(ret);
        });
    })(cmsupdate.jQuery);
}

cmsupdate.stepDownload = function(data)
{
    (function($){
        // Look for errors
        if(!data.status)
        {
            cmsupdate.downloadErrorHandler(data.error);
            return;
        }

        var totalSize = 0;
        var doneSize = 0;
        var percent = 0;
        var frag = -1;

        // get running stats
        if(data.totalSize != undefined)
        {
            totalSize = data.totalSize;
        }

        if(data.doneSize != undefined)
        {
            doneSize = data.doneSize;
        }

        if(data.percent != undefined)
        {
            percent = data.percent;
        }

        if(data.frag != undefined)
        {
            frag = data.frag;
        }

        // Update GUI
        cmsupdate.setProgressBar(percent, 'downloadProgressBar');

        $('#downloadProgressBarText').text( percent.toFixed(1) + '%');

        var jsonObject = {
            frag:       frag,
            totalSize:  totalSize,
            doneSize:   doneSize
        }

        post = {
            'task'	: 'downloader',
            'json'	: JSON.stringify(jsonObject)
        };

        if(percent < 100)
        {
            // More work to do
            doAjax(post, function(ret){
                cmsupdate.startDownload(ret);
            });
        } else {
            // Done!
            cmsupdate.setProgressBar(100, 'downloadProgressBar');
            cmsupdate.nextStep();
        }
    })(cmsupdate.jQuery);
}

/**
 * Is a variable empty?
 *
 * Part of php.js
 *
 * @see  http://phpjs.org/
 *
 * @param   mixed  mixed_var  The variable
 *
 * @returns  boolean  True if empty
 */
function empty (mixed_var)
{
    var key;

    if (mixed_var === "" ||
        mixed_var === 0 ||
        mixed_var === "0" ||
        mixed_var === null ||
        mixed_var === false ||
        typeof mixed_var === 'undefined'
        ){
        return true;
    }

    if (typeof mixed_var == 'object')
    {
        for (key in mixed_var)
        {
            return false;
        }

        return true;
    }

    return false;
}

/**
 * Is the variable an array?
 *
 * Part of php.js
 *
 * @see  http://phpjs.org/
 *
 * @param   mixed  mixed_var  The variable
 *
 * @returns  boolean  True if it is an array or an object
 */
function is_array (mixed_var)
{
    var key = '';
    var getFuncName = function (fn) {
        var name = (/\W*function\s+([\w\$]+)\s*\(/).exec(fn);

        if (!name) {
            return '(Anonymous)';
        }

        return name[1];
    };

    if (!mixed_var)
    {
        return false;
    }

    // BEGIN REDUNDANT
    this.php_js = this.php_js || {};
    this.php_js.ini = this.php_js.ini || {};
    // END REDUNDANT

    if (typeof mixed_var === 'object')
    {
        if (this.php_js.ini['phpjs.objectsAsArrays'] &&  // Strict checking for being a JavaScript array (only check this way if call ini_set('phpjs.objectsAsArrays', 0) to disallow objects as arrays)
            (
                (this.php_js.ini['phpjs.objectsAsArrays'].local_value.toLowerCase &&
                    this.php_js.ini['phpjs.objectsAsArrays'].local_value.toLowerCase() === 'off') ||
                    parseInt(this.php_js.ini['phpjs.objectsAsArrays'].local_value, 10) === 0)
            ) {
            return mixed_var.hasOwnProperty('length') && // Not non-enumerable because of being on parent class
                !mixed_var.propertyIsEnumerable('length') && // Since is own property, if not enumerable, it must be a built-in function
                getFuncName(mixed_var.constructor) !== 'String'; // exclude String()
        }

        if (mixed_var.hasOwnProperty)
        {
            for (key in mixed_var) {
                // Checks whether the object has the specified property
                // if not, we figure it's not an object in the sense of a php-associative-array.
                if (false === mixed_var.hasOwnProperty(key)) {
                    return false;
                }
            }
        }

        // Read discussion at: http://kevin.vanzonneveld.net/techblog/article/javascript_equivalent_for_phps_is_array/
        return true;
    }

    return false;
}
