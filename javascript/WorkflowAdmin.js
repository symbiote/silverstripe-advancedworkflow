if(typeof SiteTreeHandlers == 'undefined') SiteTreeHandlers = {};
SiteTreeHandlers.parentChanged_url = 'admin/workflowadmin/ajaxupdateparent';
SiteTreeHandlers.orderChanged_url = 'admin/workflowadmin/ajaxupdatesort';
SiteTreeHandlers.loadPage_url = 'admin/workflowadmin/getitem';
SiteTreeHandlers.loadTree_url = 'admin/workflowadmin/getsubtree';
SiteTreeHandlers.showRecord_url = 'admin/workflowadmin/show/';
SiteTreeHandlers.controller_url = 'admin/workflowadmin';

var _HANDLER_FORMS = {
	addpage : 'Form_CreateWorkflowForm',
	deleteworkflow : 'Form_DeleteItemsForm',
	sortitems : 'sortitems_options'
};

addworkflow = Class.create();
addworkflow.applyTo('#addworkflow');
addworkflow.prototype = {
	initialize: function () {
		Observable.applyTo($('Form_CreateWorkflowForm'));
		$('Form_CreateWorkflowForm').onsubmit = this.form_submit;
		this.o1 = $('sitetree').observeMethod('SelectionChanged', this.treeSelectionChanged);
		addworkflow.selectedNodes = {};
	},

	onclick : function() {
		statusMessage('Creating new connector...');
		this.form_submit();
		return false;
	},

	treeSelectionChanged : function(selectedNode) {
		var idx = $('sitetree').getIdxOf(selectedNode);
		if(selectedNode.selected) {
			selectedNode.removeNodeClass('selected');
			selectedNode.selected = false;
			addworkflow.selectedNodes[idx] = false;

		} else {
			selectedNode.addNodeClass('selected');
			selectedNode.selected = true;
			addworkflow.selectedNodes[idx] = true;
		}

		return true;
	},

	form_submit : function() {
		var st = $('sitetree');

//		$('Form_CreateWorkflowForm').elements.ParentID.value = st.getIdxOf(st.firstSelected());
		Ajax.SubmitForm('Form_CreateWorkflowForm', null, {
			onSuccess : this.onSuccess,
			onFailure : this.showAddPageError
		});
		return false;
	},
	onSuccess: function(response) {
		Ajax.Evaluator(response);
		// Make it possible to drop files into the new folder
//		DropFileItem.applyTo('#sitetree li');
	},
	showAddPageError: function(response) {
		errorMessage('Error adding connector', response);
	}
}


/**
 * Delete folder action
 */
deleteworkflow = {
	button_onclick : function() {
		if(treeactions.toggleSelection(this)) {
			deleteworkflow.o1 = $('sitetree').observeMethod('SelectionChanged', deleteworkflow.treeSelectionChanged);
			deleteworkflow.o2 = $('Form_DeleteItemsForm').observeMethod('Close', deleteworkflow.popupClosed);

			addClass($('sitetree'),'multiselect');

			deleteworkflow.selectedNodes = { };

			var sel = $('sitetree').firstSelected()
			if(sel) {
				var selIdx = $('sitetree').getIdxOf(sel);
				deleteworkflow.selectedNodes[selIdx] = true;
				sel.removeNodeClass('current');
				sel.addNodeClass('selected');
			}
		}
		return false;
	},

	treeSelectionChanged : function(selectedNode) {
		var idx = $('sitetree').getIdxOf(selectedNode);

		if(selectedNode.selected) {
			selectedNode.removeNodeClass('selected');
			selectedNode.selected = false;
			deleteworkflow.selectedNodes[idx] = false;

		} else {
			selectedNode.addNodeClass('selected');
			selectedNode.selected = true;
			deleteworkflow.selectedNodes[idx] = true;
		}

		return false;
	},

	popupClosed : function() {
		removeClass($('sitetree'),'multiselect');
		$('sitetree').stopObserving(deleteworkflow.o1);
		$('Form_DeleteItemsForm').stopObserving(deleteworkflow.o2);

		for(var idx in deleteworkflow.selectedNodes) {
			if(deleteworkflow.selectedNodes[idx]) {
				node = $('sitetree').getTreeNodeByIdx(idx);
				if(node) {
					node.removeNodeClass('selected');
					node.selected = false;
				}
			}
		}
	},

	form_submit : function() {
		var csvIDs = "";
		for(var idx in deleteworkflow.selectedNodes) {
			var selectedNode = $('sitetree').getTreeNodeByIdx(idx);
			var link = selectedNode.getElementsByTagName('a')[0];

			if(deleteworkflow.selectedNodes[idx] && ( !Element.hasClassName( link, 'contents' ) || confirm( "Are you sure you want to remove '" + link.firstChild.nodeValue + "'" ) ) )
				csvIDs += (csvIDs ? "," : "") + idx;
		}

		if(csvIDs) {
			$('Form_DeleteItemsForm').elements.csvIDs.value = csvIDs;

			statusMessage('deleting workflows');

			Ajax.SubmitForm('Form_DeleteItemsForm', null, {
				onSuccess : deleteworkflow.submit_success,
				onFailure : function(response) {
					errorMessage('Error deleting workflows', response);
				}
			});

		} else {
			alert("Please select at least 1 workflow.");
		}

		return false;
	},

	submit_success: function(response) {
		Ajax.Evaluator(response);
		treeactions.closeSelection($('deleteworkflow'));
	}
}


Behaviour.register({
	'#Form_EditForm' : {
		getPageFromServer : function(id) {
			statusMessage("loading...");
			var requestURL = 'admin/workflowadmin/loadworkflow/' + id;
			this.loadURLFromServer(requestURL);
			$('sitetree').setCurrentByIdx(id);
		},
		onkeypress : function(event) {
			event = (event) ? event : window.event;
			var kc = event.keyCode ? event.keyCode : event.charCode;
			if(kc == 13) {
				return false;
			}
		}
	}
	
});


/**
 * Initialisation function to set everything up
 */
appendLoader(function () {
	Observable.applyTo($('Form_DeleteItemsForm'));
	if($('deleteworkflow')) {
		$('deleteworkflow').onclick = deleteworkflow.button_onclick;
		$('deleteworkflow').getElementsByTagName('button')[0].onclick = function() { return false; };
		// Prevent bug #4740, particularly with IE
		Behaviour.register({
			'#Form_DeleteItemsForm' : {
				onsubmit: function(event) {
					deleteworkflow.form_submit();
					Event.stop(event);
					return false;
				}
			}
		});
		Element.hide('Form_DeleteItemsForm');
	}
});