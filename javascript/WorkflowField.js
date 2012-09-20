jQuery.entwine("workflow", function($) {
	$(".workflow-field").entwine({
		Loading: null,
		Dialog:  null,
		onmatch: function() {
			var self = this;

			this.setLoading(this.find(".workflow-field-loading"));
			this.setDialog(this.find(".workflow-field-dialog"));

			this.getDialog().data("workflow-field", this).dialog({
				autoOpen: false,
				width:    800,
				height:   600,
				modal:    true
			});

			this.getDialog().on("click", "button", function() {
				$(this).addClass("loading ui-state-disabled");
			});

			this.getDialog().on("submit", "form", function() {
				$(this).ajaxSubmit(function(response) {
					if($(response).is(".workflow-field")) {
						self.getDialog().empty().dialog("close");
						self.replaceWith(response);
					} else {
						self.getDialog().html(response);
					}
				});

				return false;
			});
		},
		showDialog: function(url) {
			var dlg = this.getDialog();

			dlg.empty().dialog("open");
			dlg.parent().addClass("loading");

			$.get(url).done(function(body) {
				dlg.html(body).parent().removeClass("loading");
			});
		},
		loading: function(toggle) {
			this.getLoading().toggle(typeof(toggle) == "undefined" || toggle);
		}
	});

	function helper() {
		return $("<div />").addClass("ui-state-highlight").appendTo("body");
	}

	$(".workflow-field .workflow-field-actions").entwine({
		onmatch: function() {
			this.sortable({
				axis:        "y",
				containment: this,
				placeholder: "ui-state-highlight workflow-placeholder",
				handle:      ".workflow-field-action-drag",
				tolerance:   "pointer",
				update: function() {
					var actions = $(this).find(".workflow-field-action");
					var field   = $(this).closest(".workflow-field");
					var link    = field.data("sort-link");
					var ids     = actions.map(function() { return $(this).data("id"); });

					var data = {
						"id[]":  ids.get(),
						"class": "WorkflowAction"
					};

					field.loading();
					$.post(link, data).done(function() { field.loading(false); });
				}
			});
		}
	});

	$(".workflow-field .workflow-field-action-transitions").entwine({
		onmatch: function() {
			this.sortable({
				axis:        "y",
				containment: this,
				handle:      ".workflow-field-action-drag",
				tolerance:   "pointer",
				update: function() {
					var trans = $(this).find("li");
					var field = $(this).closest(".workflow-field");
					var link  = field.data("sort-link");
					var ids   = trans.map(function() { return $(this).data("id"); });

					var data = {
						"id[]":   ids.get(),
						"class":  "WorkflowTransition",
						"parent": $(this).closest(".workflow-field-action").data("id")
					};

					field.loading();
					$.post(link, data).done(function() { field.loading(false); });
				}
			});
		}
	});

	$(".workflow-field .workflow-field-create-class").entwine({
		onmatch: function() {
			this.chosen().addClass("has-chnz");
		},
		onchange: function() {
			this.siblings(".workflow-field-do-create").toggleClass("ui-state-disabled", !this.val());
		}
	});

	$(".workflow-field .workflow-field-do-create").entwine({
		onclick: function() {
			var sel   = this.siblings(".workflow-field-create-class");
			var field = this.closest(".workflow-field");
	
			if(sel.val()) {
				field.showDialog(sel.val());
			}

			return false;
		}
	});
	
	$(".workflow-field .workflow-field-open-dialog").entwine({
		onclick: function() {
			this.closest(".workflow-field").showDialog(this.prop("href"));
			return false;
		}
	});

	$(".workflow-field .workflow-field-delete").entwine({
		onclick: function() {
			if(confirm("Are you sure you want to permanently delete this?")) {
				$.post(this.prop('href')).done(function(body) {
					$(".workflow-field").replaceWith(body);
				});
			}

			return false;
		}
	});
});