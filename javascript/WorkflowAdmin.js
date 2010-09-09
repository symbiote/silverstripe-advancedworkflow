var _HANDLER_FORMS = {
	addpage : 'Form_CreateWorkflowForm'
};

addworkflow = Class.create();
addworkflow.applyTo('#addworkflow');
addworkflow.prototype = {
	initialize: function () {
		Observable.applyTo($('Form_CreateWorkflowForm'));
		$('Form_CreateWorkflowForm').onsubmit = this.form_submit;
	},

	onclick : function() {
		statusMessage('Creating new connector...');
		this.form_submit();
		return false;
	},

	form_submit : function() {
		Ajax.SubmitForm('Form_CreateWorkflowForm', null, {
			onSuccess : this.onSuccess,
			onFailure : this.showAddPageError
		});
		return false;
	},
	onSuccess: function(response) {
		Ajax.Evaluator(response);
	},
	showAddPageError: function(response) {
		errorMessage('Error adding connector', response);
	}
}


Behaviour.register({
	'#Form_EditForm' : {
		onkeypress : function(event) {
			event = (event) ? event : window.event;
			var kc = event.keyCode ? event.keyCode : event.charCode;
			if(kc == 13) {
				return false;
			}
		}
	}
	
});