(function ($, proto) {
	var WorkflowTree = null;
	var treeContainer = null;
	
	$().ready(function () {
		var createForm = $('#Form_CreateWorkflowForm');
		var currentCreateType = null;
		var updateCreateSelection = function (type) {
			type = type + 'Types';
			currentCreateType = type;
			createForm.find('select').val('0').hide();
			createForm.find('#WorkflowDefinitionTypes select').show();
			createForm.find('#'+type + ' select').show();
		};

		updateCreateSelection('WorkflowDefinition');

		createForm.find('select').change(function () {
			var current = treeContainer.find('a.clicked').parent();
			if (current && current.length) {
				var id = current.attr('id').split('-');
				createForm.find('input[name=ParentID]').val(id[1]);
				createForm.find('input[name=ParentType]').val(id[0]);
				createForm.find('input[name=CreateType]').val(createForm.find('#'+currentCreateType + ' select').val());
			}
		});

		/**
		 * TREE functions
		 */
		treeContainer = $('#WorkflowTree');
		treeContainer.tree({
			data : {
				type : "json",
				async: true,
				opts : {
					async: true,
					url : '__ajax-tree/childnodes/WorkflowDefinition'
				}
			},
			ui: {
				theme_name: 'default'
			},
			callback: {
				check_move: function () {
					return false;
				},
				onmove: function (node, refNode, type, tree, rb) {
					alert(node);
				},
				onselect: function (node, tree) {
					var bits = node.id.split('-');

					if (bits[1]) {
						var id = bits[1];
						var url = 'admin/workflowadmin/loadworkflow/'+id + '?ClassType='+bits[0]+'&ajax=1';
						var editForm = proto('Form_EditForm');

						var okay = false;
						if (editForm.isChanged()) {
							okay = confirm("There are unsaved changes, are you sure?");
						} else {
							okay = true;
						}

						if (okay) {
							new Ajax.Request(url , {
								asynchronous : true,
								onSuccess : function( response ) {
									var allowedTypes = $(node).attr('allowed');
									updateCreateSelection(allowedTypes);
									
									editForm.loadNewPage(response.responseText);

									var subform;

									if(subform = proto('Form_MemberForm')) subform.close();
									if(subform = proto('Form_SubForm')) subform.close();

									if(editForm.elements.ID) {
										this.notify('PageLoaded', this.elements.ID.value);
									}

									return true;
								},
								onFailure : function(response) {
									alert(response.responseText);
									errorMessage('error loading page',response);
								}
							});
						}

					}
				},
				onsearch: function (nodes, tree) {
					// by default, jstree looks for the ID that was searched on, which in our case isn't
					// what is actually there. Lets convert it eh?
					// "a:contains('[sitetree_link id=8]')"
					var selectedId = nodes.selector.replace(/.+=(\d+).+/, 'SiteTree-$1');
					WorkflowTree.scroll_into_view('#'+selectedId);
				}
			}
		});

		var WorkflowTree = $.tree.reference(treeContainer);

		$('#Form_EditForm').bind('PageSaved', function (e, b, d) {
//			var current = treeContainer.find('a.clicked');
			WorkflowTree.refresh()
		});
	})

	
})(jQuery, $);