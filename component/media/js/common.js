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
    var cmsupdate = {};
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

cmsupdate.toogleUpdateOptions = function()
{
    (function($){
        var display = $('#updateOptions').css('display');

        $('#updateOptions').toggle('fast');
    })(cmsupdate.jQuery);

    return false;
}