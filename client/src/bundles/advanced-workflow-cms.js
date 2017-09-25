import $ from 'jQuery';

;(function($) {
    $(function() {
        $.entwine('ss', function($) {

            $('.cms-edit-form').find('.Actions, .btn-toolbar').find('#ActionMenus_WorkflowOptions .action').entwine({
                onclick: function(e) {
                    var transitionId = $(this).attr('data-transitionid');
                    var buttonName = $(this).attr('name');

                    buttonName = buttonName.replace(/-\d+/, '');
                    $(this).attr('name', buttonName);

                    $('input[name=TransitionID]').val(transitionId);

                    this._super(e);
                }
            });

            $('.cms-edit-form').find('.Actions, .btn-toolbar').find('.action.start-workflow').entwine({
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
