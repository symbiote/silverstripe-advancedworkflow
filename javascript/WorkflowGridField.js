(function($) {

	$.entwine('ss', function($) {
		
		// Disable clicking on each disabled table-row.
		$('.ss-gridfield .ss-gridfield-item').entwine({
			onmatch: function(e) {
				var ele = $(this);
				var row = ele.closest('tr');
				row.on('click', function(e) {
					/*
					 * Prevent a precursor POST to gridfield record URLs (all GridFields) when clicking on target-object's
					 * hyperlinks, which results in a 404.
					 */
					e.stopPropagation();
				});

				if(ele.find('.col-buttons.disabled').length) {
					row
						.addClass('disabled')
						// Disable any actions on the <tr> and edit icons, but do allow for target-object's hyperlink.
						.on('click', function(e) {
							return (e.target.nodeName === 'A' && e.target.className.match(/edit-link/) === null);
						});
						ele.find('a.edit-link').attr('title', '');
				}
			}
		});
		
		// Override GridField's method of providing cursor styles on hover.
		$('.AdvancedWorkflowAdmin .ss-gridfield-item.disabled').entwine({
			onmouseover: function() {
				this.css('cursor', 'default');
			}
		});	
		
	});
	
}(jQuery));
