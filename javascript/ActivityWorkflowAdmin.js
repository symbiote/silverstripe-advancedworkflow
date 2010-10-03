;(function($) {
/**
 * A workaround for $.post not passing the XHR to the callback function.
 */
var __last_xhr;

$(function() {
	$('#left').fn({
		/**
		 * Updates the creation forms in the left area when the selected workflow item has changed.
		 *
		 * @param {DOMObject} item
		 */
		changeWorkflowItem: function(item) {
			var item = $(item);
			var type = item.attr('data-type');

			if(type == 'WorkflowDefinition') {
				$('#Form_CreateTransitionForm').fadeOut(400, function() {
					$('#Form_CreateActionForm').fadeIn().find('input[name=ParentID]').val(item.attr('data-id'));
				});
			} else if(type == 'WorkflowAction') {
				$('#Form_CreateActionForm').fadeOut(400, function() {
					$('#Form_CreateTransitionForm').fadeIn().find('input[name=ParentID]').val(item.attr('data-id'));
				});
			} else {
				$('#Form_CreateActionForm, #Form_CreateTransitionForm').fadeOut();
			}
		}
	});

	$('#ModelAdminPanel').fn({
		/**
		 * Loads a new form from a URL, and displays the status message attached to the response. This overloads the
		 * default ModelAdmin implementation to allow attaching data to the request.
		 *
		 * @param {String} url
		 * @param {Object} data
		 * @param {Function} callback
		 */
		loadForm: function(url, data, callback) {
			statusMessage(ss.i18n._t('ActivityWorkflow.LOADING', 'Loading...'));
			$(this).load(url, data, responseHandler(function(response, status, xhr) {
				$('#form_actions_right').remove();
				Behaviour.apply();
				if(window.onresize) window.onresize();

				if(callback) $(this).each(callback, [response, status, xhr]);
			}));
		}
	});

	$('#Workflows').jstree({
		plugins: [ 'themes', 'json_data', 'ui', 'crrm', 'dnd' ],
		json_data: {
			ajax: {
				url:  function() { return this.get_container().attr('href'); },
				data: function(n) { return { id : n.attr ? n.attr('data-id') : 0, class: n.attr ? n.attr('data-class') : '' }; }
			}
		},
		crrm: {move: {check_move: function(move) {
			var parent = this._get_parent(move.o);

			if(!parent) return false;
			if(parent == -1) parent = this.get_container();

			return (parent === move.np || parent[0] && move.np[0] && parent[0] === move.np[0]);
		}}},
		dnd: {
			drag_target: false,
			drop_target: false
		}
	});

	$('#Workflows').bind('move_node.jstree', function(event, args) {
		var link = $('#Workflows').attr('data-href-sort');
		var tree = args.inst;
		var obj  = $(args.rslt.o);
		var data = 'type=' + obj.attr('data-type');

		if(obj.parent().parent().is('li')) {
			data += '&parent_id=' + obj.parent().parent().attr('data-id');
		}

		obj.parent().children('li').each(function() {
			data += '&ids[]=' + $(this).attr('data-id');
		});

		statusMessage(ss.i18n._t('ActivityWorkflow.SAVINGORDER', 'Saving order...'));
		__last_xhr = $.post(link, data, responseHandler());
	});

	$('#Workflows').bind('select_node.jstree', function(event, args) {
		var self = $(args.args[0]);

		$('#ModelAdminPanel').fn('loadForm', self.attr('href'));
		$('#left').fn('changeWorkflowItem', self.parents('li'));

		return false;
	});

	$('#Workflows').bind('reopen.jstree', function(event, args) {
		$('#left').fn('changeWorkflowItem', args.inst.get_selected());
	});
});

$('#left form').live('submit', function() {
	var form = $(this);

	$('#ModelAdminPanel').fn('loadForm', form.attr('action'), form.formToArray(), function() {
		if(form.attr('id') == 'Form_CreateActionForm') $('#Workflows').jstree('refresh');
		$('input[type=submit]', form).removeClass('loading');
	});

	return false;
});

/**
 * Overloads the default ModelAdmin add/save/delete handler in order to refresh the tree when changes are made.
 *
 * @param {Event} event
 */
$('#form_actions_right input').live('click', function() {
	var button = $(this).addClass('loading');
	var form = $('#right form');
	var action = form.attr('action') + '?' + $(this).fieldSerialize();
	var isDelete = ($(this).attr('name') == 'action_doDelete');

	if(isDelete) {
		if(!confirm(ss.i18n._t('ActivityWorkflow.REALLYDELETE', 'Do you really want to delete this?'))) {
			button.removeClass('loading');
			return false;
		}
	} else {
		if(typeof tinyMCE != 'undefined') tinyMCE.triggerSave();
	}

	$('#ModelAdminPanel').fn('loadForm', action, form.formToArray(), responseHandler(function() {
		button.removeClass('loading');

		if(!isDelete) {
			if($('#right #ModelAdminPanel form').hasClass('validationerror')) {
				errorMessage(ss.i18n._t('ActivityWorkflow.VALIDATIONERROR', 'Validation Error'));
				return;
			} else {
				statusMessage(ss.i18n._t('ActivityWorkflow.SAVED', 'Saved'), 'good');
			}
		}

		$('#Workflows').jstree('refresh');
	}));

	return false;
});

/**
 * A simple wrapper around an AJAX request response handler function that also shows the status text attached to
 * the response as a status message.
 *
 * @param   {Function} callback
 * @returns Function
 */
function responseHandler(callback) {
	return function(response, status, xhr) {
		if(!xhr && __last_xhr) xhr = __last_xhr;

		if(status == 'success') {
			statusMessage(xhr.statusText, 'good');
		} else {
			errorMessage(xhr.statusText);
		}

		if(callback) $(this).each(callback, [response, status, xhr]);
	}
}
})(jQuery);