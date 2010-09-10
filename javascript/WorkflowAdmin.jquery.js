(function ($, proto) {
	var WorkflowTree = null;
	var treeContainer = null;

	var DELETE_URL = 'admin/workflowadmin/deleteworkflow';
	var LOAD_URL = 'admin/workflowadmin/loadworkflow';
	var SORT_URL = 'admin/workflowadmin/sort';
	
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

		// make sure it's set for workflow definition creation
		updateCreateSelection('WorkflowDefinition');

		createForm.find('select').change(function () {
			var current = treeContainer.find('a.clicked').parent();
			createForm.find('input[name=ParentID]').val('');
				createForm.find('input[name=ParentType]').val('');
				createForm.find('input[name=CreateType]').val('');
			if (current && current.length) {
				var id = current.attr('id').split('-');
				createForm.find('input[name=ParentID]').val(id[1]);
				createForm.find('input[name=ParentType]').val(id[0]);
				createForm.find('input[name=CreateType]').val(createForm.find('#'+currentCreateType + ' select').val());
			}
		});

		createForm.ajaxForm(function (data) {
			if (data) {
				var d = $.parseJSON(data);
				if (d && d.success) {
					var current = treeContainer.find('a.clicked').parent();
					WorkflowTree.refresh(current);
					loadEditFor(d.type+'-'+d.ID);
				}
			}
		});

		$('#addworkflow button').click(function () {
			createForm.submit();
		})

		$('#deleteworkflow button').click(function () {
			var current = treeContainer.find('a.clicked').parent();
			var id = current.attr('id');
			var bits = id.split('-');
			if (!bits[1]) {
				return;
			}
			if (confirm("Are you sure?")) {
				$.post(DELETE_URL, {ID: bits[1], Type: bits[0]}, function (data) {
					var d = $.parseJSON(data);
					if (d && d.success) {
						WorkflowTree.refresh();
					}
				})
			}
		})

		var loadEditFor = function (typeId) {
			var node = $('#'+typeId);
			var bits = typeId.split('-');
			if (bits[1]) {
				var id = bits[1];
				var url = LOAD_URL + '/' + id + '?ClassType=' + bits[0] + '&ajax=1';
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
							var allowedTypes = node.attr('allowed');
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
		}

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
				check_move: function (node, refNode, type, tree) {
					if (type == 'insert') {
						return false;
					}
					var moveParent = node.parent();
					var pid = null;
					pid = moveParent.attr('id');

					var classes = $(node).attr('class').split(' ');
					var baseType = classes[0];
					// get the actual 'li' node

					var target = refNode.parent();
					var targetClasses = target.attr('class').split(' ');
					var targetType = targetClasses[0];

					var targetParent = target.parent();

					var tpid = null;
					tpid = targetParent.attr('id');

					if (tpid == pid && baseType == targetType) {
						return true;
					}

					return false;

				},
				onmove: function (node, refNode, type, tree, rb) {
					var parent = $(node).parent();
					var kids = parent.find('li');
					var newOrder = '';
					var sep = '';
					for (var i = 0; i < kids.length; i++) {
						newOrder += sep + $(kids[i]).attr('id');
						sep = ',';
					}
					$.post(SORT_URL, {ids: newOrder});
				},
				onselect: function (node, tree) {
					loadEditFor(node.id);
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
			WorkflowTree.refresh()
		});
	})

	
})(jQuery, $);