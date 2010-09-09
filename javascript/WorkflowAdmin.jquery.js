(function ($) {
	ssauUrlTree = $.tree.reference(treeContainer);
	
	treeContainer.tree({
		data : {
			type : "json",
			async: true,
			opts : {
				async: true,
				url : 'workflowadmin/childnodes'
			}
		},
		ui: {
			theme_name: 'default'
		},
		callback: {
			onselect: function (node, tree) {
				var bits = node.id.split('-');
				if (bits[1]) {
					var internalHref = $('[name=internalhref]');
					internalHref.val('[sitetree_link id=' + bits[1] + ']');
					var linkTitle = $('[name=linkTitle]');
					linkTitle.val(node.getAttribute('title'));
				}
			},
			onsearch: function (nodes, tree) {
				// by default, jstree looks for the ID that was searched on, which in our case isn't
				// what is actually there. Lets convert it eh?
				// "a:contains('[sitetree_link id=8]')"
				var selectedId = nodes.selector.replace(/.+=(\d+).+/, 'SiteTree-$1');
				ssauUrlTree.scroll_into_view('#'+selectedId);
			}
		}
	});
})(jQuery);