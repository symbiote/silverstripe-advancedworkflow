import jQuery from 'jquery';
import i18n from 'i18n';

jQuery.entwine('ss', ($) => {
  $('.advancedWorkflowTransition').entwine({
    onclick(e) {
      e.preventDefault();

      // get the stuff for it and show a dialog
      // eslint-disable-next-line no-alert
      const comments = prompt('Comments');
      const instanceId = this.parents('ul').attr('data-instance-id');
      const transitionId = this.attr('data-transition-id');
      const securityId = $('[name=SecurityID]').val();
      if (!securityId) {
        // eslint-disable-next-line no-alert
        alert('Invalid SecurityID field!');
        return false;
      }

      $.post(
        'AdvancedWorkflowActionController/transition',
        {
          SecurityID: securityId,
          comments,
          transition: transitionId,
          id: instanceId,
        },
        (data) => {
          if (data) {
            const parsedData = $.parseJSON(data);

            if (parsedData.success) {
              location.href = parsedData.link;
            } else {
              // eslint-disable-next-line no-alert
              alert(i18n._t('Workflow.ProcessError'));
            }
          }
        }
      );

      return false;
    },
  });
});
