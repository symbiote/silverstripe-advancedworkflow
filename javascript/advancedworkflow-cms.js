
;(function ($) {
	$(function () {
		$.entwine('ss', function($) {
			$('.cms-edit-form .Actions #ActionMenus_WorkflowOptions button.action').entwine({
				onclick: function(e) {
					var transitionId = $(this).attr('data-transitionid');
					var buttonName = $(this).attr('name');
					
					buttonName = buttonName.replace(/-\d+/, '');
					$(this).attr('name', buttonName);
					
					$('input[name=TransitionID]').val(transitionId);
					
					this._super(e);
				}
			});
		});
	});
})(jQuery);