
;(function ($) {
	$(function () {

		$('.advancedWorkflowTransition').live('click', function (e) {
			e.preventDefault();
			// get the stuff for it and show a dialog
			
			var comments = prompt("Comments");
			
			var instanceId = $(this).parents('ul').attr('data-instance-id');
			var transitionId = $(this).attr('data-transition-id');
			var securityId = $('[name=SecurityID]').val();
			if (!securityId) {
				alert("Invalid SecurityID field!");
				return false;
			}

			$.post('AdvancedWorkflowActionController/transition', {SecurityID: securityId, comments: comments, transition: transitionId, id: instanceId}, function (data) {
				if (data) {
					data = $.parseJSON(data);
					if (data.success) {
						location.href = data.link;
					} else {
						alert(ss.i18n._t('Workflow.ProcessError'));
					}
				}
			})

			return false;
		})
	})
})(jQuery);