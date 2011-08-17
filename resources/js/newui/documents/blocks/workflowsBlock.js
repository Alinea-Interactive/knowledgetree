// ============================================================
// Shared
// ============================================================

var win;
var baseUrl;
var namespace;

// ============================================================
// Workflow Block Actions
// ============================================================

function workflowsBlock() {
	this.namespace = 'ktajax.actions.document.workflow';
	this.baseUrl = 'action.php?kt_path_info='+ this.namespace;
}

/*
* Submit the form
*/
workflowsBlock.prototype.submitForm = function(action) {
	if(this.validateForm(action) == false) { return false; }
	var documentId = jQuery('#documentId').attr('value');
	var address;
	// Check workflow action to perform.
	switch (action) {
		case 'change':
			params = jQuery('form[name="start_workflow_form"]').serialize();
		break;
		case 'transition':
			params = jQuery('form[name="transition_wf_form"]').serialize();
		break;
		case 'quick_transition':
			params = jQuery('form[name="quick_transition"]').serialize();
		break;
	}
	var address = this.baseUrl + '&' + params;
	jQuery.ajax({ url: address,	dataType: "html", type: "POST", cache: false, 
					beforeSend: function(data) { 
						// Display loading message
						jQuery('#add_workflow').addClass('ktInfo').html('Saving...');
					},
					success: function(data) {
						response = jQuery.parseJSON(data);
						if (response.permission != 1) {
							var url = response.url;
    						window.location.replace(url);
    						return true;
						}
						
						// Display saved message
						jQuery('#add_workflow').removeClass('ktInfo').html(response.message);
						// Remove modal window
						jQuery('#workflows-panel').remove().delay(2000);
						// Refresh alert sidebar
						vActions.refeshAction(documentId);
						workflows.refeshSidebar(documentId);
						return true;
					}
	});
};

/*
* Validate the time
*/
workflowsBlock.prototype.validateForm = function (action) {
	var comment = jQuery('textarea[name="fComments"]').val();
	if(comment == ''  && action != 'change') { alert("Please specify a comment"); return false; }
	return true;
}

workflowBlock = new workflowsBlock();

// ============================================================