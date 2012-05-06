(function($) {
	$(".workflow-field").entwine({
		DialogEl:   null,
		ClassSel:   null,
		CreateBtn:  null,
		LoadingInd: null,
		onmatch: function() {
			var self = this;

			this.setDialogEl($("<div></div>").addClass("ss-ui-dialog").appendTo("body"));
			this.setClassSel(this.find(".workflow-field-create-class"));
			this.setCreateBtn(this.find(".workflow-field-do-create"));
			this.setLoadingInd(this.find(".workflow-field-loading"));

			this.getDialogEl().delegate("button", "click", function() {
				$(this).addClass("loading ui-state-disabled").attr("disabled", "disabled");
			});

			this.getDialogEl().delegate("form", "submit", function() {
				$(this).ajaxSubmit(function(response) {
					if($(response).is(".workflow-field")) {
						self.getDialogEl().empty().dialog("close");
						self.replaceWith(response);
					} else {
						self.getDialogEl().html(response);
					}
				});

				return false;
			});

			this.getClassSel().chosen().addClass("has-chzn").change(function() {
				self.getCreateBtn().toggleClass("ui-state-disabled", !this.value);
			});
	
			this.getCreateBtn().click(function() {
				if(self.getClassSel().val()) {
					self.dialog(self.getClassSel().val());
				}
				return false;
			});

			this.find("a.workflow-field-dialog").click(function() {
				self.dialog(this.href);
				return false;
			});

			this.find(".workflow-field-delete").click(function() {
				if(confirm("Are you sure you want to permanently delete this?")) {
					self.getLoadingInd().show();
					$.post(this.href).done(function(field) {
						self.replaceWith(field);
					});
				}
				return false;
			});
		},
		onunmatch: function() {
			this.getDialogEl().dialog("destroy").remove();
		},
		dialog: function(url) {
			var el = this.getDialogEl();

			el.empty().dialog({
				width:  800,
				height: 600,
				modal:  true
			});
			el.parent().addClass("loading");

			$.get(url).done(function(body) {
				el.html(body).parent().removeClass("loading");
			});
		}
	});

	$(".workflow-field-action").entwine({
		onmatch: function() {
		}
	});
})(jQuery);