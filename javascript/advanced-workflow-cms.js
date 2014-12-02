;(function($) {
	$(function() {

		$.entwine('ss', function($) {

			$('.cms-edit-form .Actions .action.start-workflow').entwine({
				onmouseup: function(e) {

					// Populate the hidden form field with the selected workflow definition.

					var action = $(this);
					$('input[name=TriggeredWorkflowID]').val(action.data('workflow'));

					// Update the element name to exclude the ID, therefore submitting correctly.

					var name = action.attr('name');
					action.attr('name', name.replace(/-\d+/, ''));
					this._super(e);
				}
			});
		});

	});
})(jQuery);
