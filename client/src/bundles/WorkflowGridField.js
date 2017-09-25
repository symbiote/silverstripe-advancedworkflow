import jQuery from 'jquery';

jQuery.entwine('ss', ($) => {
  // Disable clicking on each disabled table-row.
  $('.ss-gridfield .ss-gridfield-item').entwine({
    onmatch() {
      const row = this.closest('tr');

      if (this.find('.col-buttons.disabled').length) {
        row
          .addClass('disabled')
          // Disable any actions on the <tr> and edit icons, but do allow
          // for target-object's hyperlink.
          .on('click', (e) => {
            if (e.target.nodeName === 'A' && e.target.className.match(/edit-link/) === null) {
              return true;
            }
            return false;
          });

        this.find('a.edit-link').attr('title', '');
      }
    },
  });

  // Override GridField's method of providing cursor styles on hover.
  $('.AdvancedWorkflowAdmin .ss-gridfield-item.disabled').entwine({
    onmouseover() {
      this.css('cursor', 'default');
    },
  });

  /*
   * Prevent a precursor POST to gridfield record URLs (all Pending/Submitted GridFields)
   * when clicking on target-object's hyperlinks, which results in a 404.
   */
  $('.ss-gridfield .ss-gridfield-item td.col-Title a').entwine({
    onclick(e) {
      e.stopPropagation();
    },
  });

  /*
   * Reload the current (central) CMS pane, to ensure that previously related content
   * objects, are visually cleared from the UI immediately.
   */
  /* eslint-disable max-len */
  $('.ss-gridfield .col-buttons .action.gridfield-button-delete, .cms-edit-form .Actions button.action.action-delete').entwine({
    /* eslint-enable max-len */
    onclick(e) {
      this._super(e);
      $('.cms-container').reloadCurrentPanel();
      e.preventDefault();
    },
  });
});
