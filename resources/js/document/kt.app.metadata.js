if(typeof(kt.app)=='undefined')kt.app={};
kt.app.metadata = new function()
{	
	//is a field in edit mode	
	this.editing = false;
	this.documentID = 0;
	
	this.setup = function(makeEditable, documentID, tags)
	{
		this.documentID = documentID;
		
		//add the tags to the display
		if(tags && tags.length)
		{
			for(var i = 0; i < tags.length; ++i) 
			{
				var token = jQuery("<li><p>"+tags[i].name+"</p> </li>")
					.addClass('token-input-token-facebook')
					.insertBefore('.document-tags');

				jQuery("<span>&times;</span>")
					.addClass('token-input-delete-token-facebook')
					.attr('title', 'Delete tag')
					.appendTo(token)
					.click(function ()
					{
						kt.app.metadata.deleteToken(jQuery(this).parent());
						return false;
					});

				jQuery.data(token.get(0), "tokeninput", {"id": tags[i].id, "name": tags[i].name});
			}
		}
		
		if (string2bool(makeEditable))
		{
			kt.app.metadata.setEditableRegions();
		}
		
		kt.app.metadata.setExpandableFieldsets();
		
	}
	
	this.setEditableRegions = function()
	{		
		kt.app.metadata.setDocumentTitleEditable();
		kt.app.metadata.setDocumentFilenameEditable();
		kt.app.metadata.setDocumentTagsEditable();
		kt.app.metadata.setDocumentTypeEditable();
		kt.app.metadata.setMetadataEditable();
	}
	
	this.setExpandableFieldsets = function()
	{
		jQuery('.more').click(function() {
			var slider = jQuery('.slide');
			if (slider.is(":visible"))
			{
				jQuery('.more').text('more...');
			}
			else
			{
				jQuery('.more').text('less...');
			}
			
			slider.slideToggle('slow', function() {
				// Animation complete
				
			});
		});
		
		//.hover(function() {
			//jQuery(this).css('cursor', 'pointer');
		//})
	}
	
	this.setDocumentTitleEditable = function()
	{		
		jQuery('.document-title').hover(
		function(){
			jQuery('.editable-control.title', jQuery(this)).css('visibility', 'visible');
		},
		function(){
			if(jQuery('.editable-control.title', jQuery(this)).hasClass('edit'))
			{
				jQuery('.editable-control.title', jQuery(this)).css('visibility', 'hidden');
			}
		});
		jQuery('.document-title').editableSet({
			titleElement: '.save-placeholder',
			controller: '.editable-control.title',
			action: 'metadataService.changeDocumentTitle',
			onCancel: function(){
				jQuery('.editable-control.title', jQuery(this))
					.removeClass('undo')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
					
				kt.app.metadata.setEditableRegions();
			},
			beforeLoad: function() {
			},
			afterLoad: function() {
				jQuery('.editable-control.title', jQuery(this))
					.removeClass('edit')
					.addClass('undo')
					.attr('title', 'Click to undo');
			},
			onError: function(){
				kt.app.metadata.setEditableRegions();
			},	
			onSave: function(){
				jQuery('.editable-control.title', jQuery(this))
					.removeClass('undo')
					.addClass('spin');
				
				//check whether all the required fields have been completed
				var requiredDone = true;
				var val = jQuery('input:text[name=documentTitle]').val();
				val = jQuery.trim(val);
				if(val == null || val == undefined || val == '' || val == 'no value')
				{
					jQuery('.editable-control.title', jQuery(this))
						.removeClass('spin')
						.addClass('undo')
						.attr('title', 'Click to undo');
						
					jQuery('input:text[name=documentTitle]', jQuery(this)).addClass('incomplete');
					requiredDone = false;
				}
				
				return requiredDone;
			},
			repopulate: function(){},
			afterSave: function(data, status){
				jQuery('.editable-control.title', jQuery(this))
					.removeClass('spin')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
				
				kt.app.metadata.setEditableRegions();
				
				if(data)
				{
					var parsedJSON = '';
					
					if (data.success)
					{
						parsedJSON = jQuery.parseJSON(data.success);
						jQuery('#value-title').text(parsedJSON[0].documentTitle);
						jQuery('#docBreadCrumbName').text(parsedJSON[0].documentTitle);
						
						// Change Title of Window
						document.title = parsedJSON[0].documentTitle+" | KnowledgeTree";
						
						
					}
					else if (data.error)
					{
						parsedJSON = jQuery.parseJSON(data.success);
						jQuery('.editable-control.title', jQuery(this)).trigger('click');
						jQuery('input:text[name=documentTitle]', jQuery(this)).val(parsedJSON[0].documentFilename);
						jQuery('.form_submit', jQuery(this)).after('<br><span class="metadataError">'+parsedJSON[0].message+'</span>');
					}
				}
			}
		});
	}
	
	this.setDocumentFilenameEditable = function()
	{
		jQuery('.document-filename').hover(
		function(){
			jQuery('.editable-control.filename', jQuery(this)).css('visibility', 'visible');
		},
		function(){
			if(jQuery('.editable-control.filename', jQuery(this)).hasClass('edit'))
			{
				jQuery('.editable-control.filename', jQuery(this)).css('visibility', 'hidden');
			}
		});
		jQuery('.document-filename').editableSet({
			titleElement: '.save-placeholder',
			controller: '.editable-control.filename',
			action: 'metadataService.changeDocumentFilename',
			onCancel: function(){
				jQuery('.editable-control.filename', jQuery(this))
					.removeClass('undo')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
				
				kt.app.metadata.setEditableRegions();
			},
			beforeLoad: function() {
			},
			afterLoad: function() {
				jQuery('.editable-control.filename', jQuery(this))
					.removeClass('edit')
					.addClass('undo')
					.attr('title', 'Click to undo');
			},
			onError: function() {
				kt.app.metadata.setEditableRegions();
			},	
			onSave: function(){
				jQuery('.editable-control.filename', jQuery(this))
					.removeClass('undo')
					.addClass('spin');
				
				var requiredDone = true;
				var val = jQuery('input:text[name=documentFilename]').val();
				val = jQuery.trim(val);
				if(val == null || val == undefined || val == '' || val == 'no value')
				{
					jQuery('.editable-control.filename', jQuery(this))
						.removeClass('spin')
						.addClass('undo')
						.attr('title', 'Click to undo');

					jQuery('input:text[name=documentFilename]', jQuery(this)).addClass('incomplete');
					requiredDone = false;
				}
				
				return requiredDone;
			},
			repopulate: function(){},
			afterSave: function(data, status) {
				jQuery('.editable-control.filename', jQuery(this))
					.removeClass('spin')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
				
				kt.app.metadata.setEditableRegions();
				
				if(data)
				{
					var parsedJSON = '';
					if (data.success)
					{
						parsedJSON = jQuery.parseJSON(data.success);
						jQuery('#value-filename').text(parsedJSON[0].documentFilename);
					}
					else if (data.error)
					{
						parsedJSON = jQuery.parseJSON(data.error);
						jQuery('.editable-control.filename', jQuery(this)).trigger('click');
						jQuery('.form_submit', jQuery(this)).after('<br><span class="metadataError">'+parsedJSON[0].message+'</span>');
					}
				}
			}
		});
	}
	
	this.setDocumentTagsEditable = function()
	{		
		jQuery('#document-tags').hover(
		function(){
			jQuery('.editable-control.tags', jQuery(this)).css('visibility', 'visible');
		},
		function(){
			if(jQuery('.editable-control.tags', jQuery(this)).hasClass('edit'))
			{
				jQuery('.editable-control.tags', jQuery(this)).css('visibility', 'hidden');
			}
		});
		jQuery('.document-tags-edit').editableSet({
			titleElement: '.save-placeholder',
			controller: '.editable-control.tags',
			context: '#document-tags',
			action: 'metadataService.saveTags',
			onCancel: function(){
				jQuery('.editable-control.tags')
					.removeClass('undo')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
				
				kt.app.metadata.setEditableRegions();
			},
			beforeLoad: function() {
			},
			afterLoad: function() {
				jQuery('.editable-control.tags')
					.removeClass('edit')
					.addClass('undo')
					.attr('title', 'Click to undo');
			},
			onError: function(){
				kt.app.metadata.setEditableRegions();
			},	
			onSave: function(){
				jQuery('.editable-control.tags')
					.removeClass('undo')
					.addClass('spin');
			},
			repopulate: function(){},
			afterSave: function(data, status){
				jQuery('.editable-control.tags')
					.removeClass('spin')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
								
				if(data && data.success)
				{
					parsedJSON = jQuery.parseJSON(data.success);
					var tags = parsedJSON[0].tags;
					
					//show the newly-added tags
					jQuery.each(tags, function(index, val)
					{
						var token = jQuery("<li><p>"+val+"</p> </li>")
							.addClass('token-input-token-facebook')
							.insertBefore('.document-tags');

						jQuery("<span>&times;</span>")
							.addClass('token-input-delete-token-facebook')
							.appendTo(token)
							.click(function ()
							{
								kt.app.metadata.deleteToken(jQuery(this).parent());
								return false;
							});

						jQuery.data(token.get(0), "tokeninput", {"id": val, "name": val});
					});
				}
				
				kt.app.metadata.setEditableRegions();
			}
		});
	}
	
	this.setDocumentTypeEditable = function()
	{
		jQuery('.document-type').hover(
		function(){
			jQuery('.editable-control.doctype', jQuery(this)).css('visibility', 'visible');
		},
		function(){
			if(jQuery('.editable-control.doctype', jQuery(this)).hasClass('edit'))
			{
				jQuery('.editable-control.doctype', jQuery(this)).css('visibility', 'hidden');
			}
		});
		jQuery('.document-type').editableSet({
			controller: '.editable-control.doctype',
			action: 'metadataService.changeDocumentType',
			onCancel: function(){
				jQuery('.editable-control.doctype', jQuery(this))
					.removeClass('undo')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
				
				kt.app.metadata.setEditableRegions();
			},
			beforeLoad: function() {
			},
			afterLoad: function() {
				jQuery('.editable-control.doctype', jQuery(this))
					.removeClass('edit')
					.addClass('undo')
					.attr('title', 'Click to undo');
			},
			onError: function(){
				kt.app.metadata.setEditableRegions();
			},	
			onSave: function(){
				jQuery('.editable-control.doctype', jQuery(this))
					.removeClass('undo')
					.addClass('spin');
			},
			repopulate: function(){},
			afterSave: function(data, status){
				jQuery('.editable-control.doctype', jQuery(this))
					.removeClass('spin')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
				
				//reset the document fields to reflect the new document type								
				if(data && data.success)
				{
					parsedJSON = jQuery.parseJSON(data.success);
					
					//update the Document Type span text
					jQuery('#documentTypeID').html(parsedJSON[0].documentTypeName);
					
					//reset the document fields to reflect the new document type
					jQuery('.editableMetadata').empty();
					jQuery('.editableMetadata').remove();

					//create the new editable div
					var editableDiv = jQuery('<div>').addClass('editableMetadata');
					//NB: set its rel attribute because this is used as the "action" url
					//editableDiv.attr('rel', './lib/widgets/persistMetadata.php?documentID='+jQuery('#documentidembedded').html());
					
					//create div for each fieldset
					jQuery.each(parsedJSON[0].metadata, function(index, fieldset)
					{
						var fieldsetDiv = jQuery('<div>').addClass('detail-fieldset');
						var header = jQuery('<h3>').text(fieldset.name).attr('title', fieldset.description);
						var metadataControlSpan = jQuery('<span>').addClass('editable-control metadata edit').attr('title', 'Click to edit');
						metadataControlSpan.html('&nbsp;');
						header.append(metadataControlSpan);
						fieldsetDiv.append(header);
						
						//NB: set its rel attribute because this is used as the "action" url
						fieldsetDiv.attr('rel', '{"documentID":"'+parsedJSON[0].documentID+'"}');

						//create the div to contain the fields
						var table = jQuery('<table>').addClass('metadatatable').attr('cellspacing', '0').attr('cellpadding', '5');
					
						var counter = 0;
						
						//now create each field's widget
						jQuery.each(fieldset.fields, function(index, field)
						{						
							var tableRow = jQuery('<tr>').addClass('metadatarow');
							
							//is the field required?
							if(string2bool(field.required))
							{
								tableRow.addClass('required');
							}
							
							tableRow.attr('id', 'metadatafield-'+field.fieldid);
		
							var tableHeader = jQuery('<th>').text(field.name);
							tableHeader.attr('title', field.description);
							tableRow.append(tableHeader);
							
							var tableCell = kt.app.metadata.getTableCell(field);
		
							tableRow.append(tableCell);
			
							table.append(tableRow);
						});
		
						fieldsetDiv.append(table);
						
						editableDiv.append(fieldsetDiv);
					});
					
					jQuery('.document-type').after(editableDiv);
					
					//need to insert the 'more ... less' slider widget after 2nd fieldset
					if(parsedJSON[0].metadata.length > 2)
					{
						jQuery('.detail-fieldset:eq(1)').after('<br/><div><span class="more">More...</span></div><br/>');
						jQuery('.detail-fieldset:gt(1)').wrapAll('<div class="slide" style="display:none" />');
						
						kt.app.metadata.setExpandableFieldsets();
					}
				}
				
				//metadata can be editable again				
				kt.app.metadata.setEditableRegions();
				
				kt.app.metadata.openRequiredMetadata();
		 	}
		});
	}
	
	//when doctype changes, and there are now Required fields, open all the required fieldsets for editing
	this.openRequiredMetadata = function()
	{
		var highestRowCounter = 0;
		
		//iterate through the fields and see if any are required
		jQuery('.detail-fieldset').each(function(index, value){
			if(jQuery('.metadatarow.required', jQuery(this)).length > 0)
			{
				highestRowCounter = index;
				jQuery('.editable-control.metadata', jQuery(this))
					.trigger('click')
					.attr('title', '')
					.removeClass('undo')
					.css('cursor', 'default')
					.unbind('click');				
			}
		});
		
		if (highestRowCounter > 2)
		{
			jQuery('.more').trigger('click');
		}
	}
	
	//assemble each widget required by jEditableSet, and wrap it in a <td>
	this.getTableCell = function(field)
	{
	 	var span = null;
		
	 	var classType = '';
		
	 	var valueClass = '';
	 	
		switch(field.control_type)
		{
			case 'string':
				classType = 'metadata-textbox';
				var dataType = 'text';
				
				span = jQuery('<span>')
					.addClass('descriptiveText')
					.attr('data-name', field.fieldid)
					.attr('data-type', dataType)
					.attr('data-value-id', 'value-'+field.fieldid);
			break;
			case 'lookup':				
				classType = 'metadata-singleselect';
				var dataType = 'select';
				var dataOptions = '';
				
				if (field.selection && field.selection.length > 0)
				{
					dataOptions = '[["No selection","no value"],';

					jQuery.each(field.selection, function(index, value){
						dataOptions += '[\"'+value+'\",\"'+value+'\"],';
					});

					dataOptions += ']';
				}
				
				span = jQuery('<span>')
					.addClass('descriptiveText')
					.attr('data-name', field.fieldid)
					.attr('data-type', dataType)
					.attr('data-value-id', 'value-'+field.fieldid);
				
				if (dataOptions.length > 0)
				{
					span.attr('data-options', dataOptions);
				}
			break;
			case 'large text':
				classType = 'metadata-textarea';
				var dataType = 'textarea';
				if(parseInt(field.options.ishtml))
				{
					type = 'metadata-htmleditor';
					var dataType = 'htmleditor';
				} else {
					valueClass = ' class="textareaVal" ';
				}
				
				span = jQuery('<span>')
					.addClass('descriptiveText')
					.attr('data-name', field.fieldid)
					.attr('data-type', dataType)
					.attr('data-value-id', 'value-'+field.fieldid);
			break;
			case 'tree':
				classType = 'metadata-tree';
				var dataType = 'tree';
				var dataOptions = '';
				
				var html = '<span class="descriptiveText" data-name="'+field.fieldid+'" data-type="'+dataType+'" data-options=\''+field.selection+'\' data-value-id="value-'+field.fieldid+'"></span>';
								
				span = jQuery(html);
			break;
			case 'multiselect':
				if(field.options.type == 'multiwithlist')
				{
					classType = 'metadata-multilistselect';
					var dataType = 'multiselect';
					var dataOptions = '';
					
					if (field.selection && field.selection.length > 0)
					{
						dataOptions = '[["No selection","no value"],';

						jQuery.each(field.selection, function(index, value){
							dataOptions += '[\"'+value+'\",\"'+value+'\"],';
						});

						dataOptions += ']';
					}
					
					span = jQuery('<span>')
						.addClass('descriptiveText')
						.attr('data-name', field.fieldid+'[]')
						.attr('data-type', dataType)
						.attr('data-value-id', 'value-'+field.fieldid);
					
					if (dataOptions.length > 0)
					{
						span.attr('data-options', dataOptions);
					}
				}
				else if(field.options.type == 'multiwithcheckboxes')
				{
					classType = 'metadata-multicheckselect';
					var datatype = 'checkbox';
					
					if (field.selection && field.selection.length > 0)
					{
						html = '<span>';
						
						jQuery.each(field.selection, function(index, option){
							html += '<span class="descriptiveText" data-checked_value="'+option+'" data-value-id="value-'+field.fieldid+'" data-name="'+field.fieldid+'[]" data-type="checkbox"></span>';							
						});
						
						html += '</span>';
						
						span = jQuery(html);
					}
				}
			break;
			case 'date':
				classType = 'metadata-date';
				var dataType = 'datepicker';
				span = jQuery('<span>')
					.addClass('descriptiveText')
					.attr('data-name', field.fieldid)
					.attr('data-type', dataType)
					.attr('data-value-id', 'value-'+field.fieldid);
			break;
		}
		
		var valueSpan = jQuery('<span id="value-'+field.fieldid+'" '+valueClass+'>no value</span>');
		
		span.append(valueSpan);
		
		var tableCell = jQuery('<td>');
		
		tableCell.addClass(classType);
		
		tableCell.append(span);
		
		return tableCell;
	}
	
	this.setMetadataEditable = function()
	{
		jQuery('.detail-fieldset').hover(
		function(){
			jQuery('.editable-control.metadata', jQuery(this)).css('visibility', 'visible');
		},
		function(){
			if(jQuery('.editable-control.metadata', jQuery(this)).hasClass('edit'))
			{
				jQuery('.editable-control.metadata', jQuery(this)).css('visibility', 'hidden');
			}
		});
		jQuery('.detail-fieldset').editableSet({
			controller: '.editable-control.metadata',
			action: 'metadataService.updateMetadata',
			onCancel: function(){
				kt.app.metadata.editing = false;
				jQuery('.editable-control.metadata', jQuery(this))
					.removeClass('undo').addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
				
				kt.app.metadata.setEditableRegions();
			},
			onInvalid: function(hashInvalids) {
				var me = jQuery(this);

				//go through the hashtable keys, and get the error message for each
				jQuery.each(hashInvalids.keys(), function(index, elementID)
				{
					if (typeof(elementID) == 'string')
					{
						jQuery('#'+elementID, me).val('');
						jQuery('#metadatafield-'+elementID, me).addClass('incomplete');
						jQuery('.invalid-metadata-message').empty();
						jQuery('.form_submit', me).after('&nbsp;&nbsp;<span class="invalid-metadata-message" style="color:red; font-size:10px">'+hashInvalids.get(elementID)+'</span>');
					}
				});
			},
			beforeLoad: function() {
				jQuery('.editable-control.metadata', jQuery(this)).unbind('click');
			},
			afterLoad: function() {
				jQuery('.editable-control.metadata', jQuery(this))
					.removeClass('edit')
					.addClass('undo')
					.attr('title', 'Click to undo');
					
				kt.app.metadata.editing = true;
			},
			onError: function() {
				kt.app.metadata.editing = false;
				kt.app.metadata.setEditableRegions();
			},
			onSave: function(){
				jQuery('.editable-control.metadata', jQuery(this))
					.removeClass('undo')
					.addClass('spin');
				
				var atLeastOneRequiredNotDone = kt.app.metadata.atLeastOneRequiredNotDone(jQuery(this));
				
				if (atLeastOneRequiredNotDone)
				{	
					jQuery('.editable-control.metadata', jQuery(this))
						.attr('title', '')
						.removeClass('spin')
						.css('cursor', 'default')
						.unbind('click');
				}
				
				return !atLeastOneRequiredNotDone;
			},
			afterSave: function(data, status){
				kt.app.metadata.editing = false;
				jQuery('.editable-control.metadata', jQuery(this))
					.removeClass('spin')
					.addClass('edit')
					.attr('title', 'Click to edit')
					.css('visibility', 'hidden');
				
				if(data)
				{
					if (data.success)
					{
						var parsedJSON = jQuery.parseJSON(data.success);
						//now pouplate the just-saved values
						kt.app.metadata.updateValues(parsedJSON[0]);
					}
				}
				
				//document type can be editable again
				kt.app.metadata.setEditableRegions();
			}
		});
	}
	
	//populate the saved values in the form	
	this.updateValues = function(fields) 
	{
		jQuery.each(fields['fields'], function(index, field)
		{
			switch(field.control_type)
			{
				case 'string':
					jQuery('#value-'+field.fieldid).text(field.value);
				break;
				case 'lookup':
					jQuery('#value-'+field.fieldid).text(field.value);
				break;
				case 'tree':
					jQuery('#value-'+field.fieldid).text(field.value);
				break;
				case 'large text':
					if(field.options.ishtml)
					{
						//strip all html tags
						jQuery('#value-'+field.fieldid).html(field.value);    //.replace(/<\/?[a-z][a-z0-9]*[^<>]*>/ig, ""));
					}
					else
					{
						jQuery('#value-'+field.fieldid).text(field.value);
					}
				break;
				case 'date':
					jQuery('#value-'+field.fieldid).text(field.value);
				break;
				case 'multiselect':
					if(field.options.type == 'multiwithlist')
					{
						jQuery('#value-'+field.fieldid).text(field.value);
					}
					else if(field.options.type == 'multiwithcheckboxes')
					{
						jQuery('#value-'+field.fieldid).text(field.value);
					}
				break;
			}
		});
	}
	
	//check whether all required fields have been completed
	this.atLeastOneRequiredNotDone = function(element)
	{
		var atLeastOneRequiredNotDone = false;
				
		jQuery('.required', element).each(function(index)
		{
			//get the fields id: to chop off the "metadatafield-" prefix
			var id = (jQuery(this).attr('id').substring(jQuery(this).attr('id').indexOf('-')+1));
			
			//the first <td> contains the element we are interested in
			var firstTD = jQuery('td:first', jQuery(this));
							
			//the td's class identifies its type				
			switch(firstTD.attr('class'))
			{
				case 'metadata-textbox':
					var val = jQuery('input:text[name='+id+']').val();
					
					if(val == null || val == undefined || val == '' || val == 'no value')
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
				break;
				
				case 'metadata-date':
					var val = jQuery('input:text[name='+id+']').val();
					
					if(val == null || val == undefined || val == '' || val == 'no value')
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
				break;
				
				case 'metadata-tree':
					var val = jQuery('input:radio[name='+id+']:checked').val();
					
					if(val == null || val == undefined)
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
				break;
				
				case 'metadata-multicheckselect':
					//array to contain all the selected values
					var vals = new Array();
					
					jQuery('input:checkbox[name="'+id+'[]"]:checked').each(function()
					{
					    vals.push(jQuery(this).val());
					});
					
					if (vals.length == 0)
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
				break;
				
				case 'metadata-multilistselect':
					//array to contain all the selected values
					var vals = new Array();
					
					jQuery('select[name="'+id+'[]"] option:selected').each(function()
					{
					    vals.push(jQuery.trim(jQuery(this).val()));
					});
					
					if (vals.length == 0)
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
					else if (vals.length == 1 && vals[0] == 'no value')
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
				break;
				case 'metadata-singleselect':
					//var val = jQuery('#singleselect_'+id).val();
					var val = jQuery('select[name='+id+']').val();

					if(val == null || val == undefined || val == '' || val == 'no value')
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
				break;
				
				case 'metadata-textarea':
					var val = jQuery('textarea[name='+id+']').val();
					
					if(val == null || val == undefined || val == '' || val == 'no value')
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
					
					
				break;
				case 'metadata-htmleditor':
					var val = jQuery('#'+id).val();
					
					if(val == null || val == undefined || val == 'no value')
					{
						jQuery(this).addClass('incomplete');
						atLeastOneRequiredNotDone = true;
					}
					
				break;
			}
			
			//don't do this as need to mark each field that wasn't complete
			//if(atLeastOneRequiredNotDone)
			//{
				//return false;
			//}
		});
		
		return atLeastOneRequiredNotDone;
	}
	
	// Delete a token from the token list
	this.deleteToken = function(token) 
	{
		// Remove the id from the saved list
		var token_data = jQuery.data(token.get(0), "tokeninput");
		var tag = jQuery(":first-child", token).text();

		var params = {documentID: this.documentID, tag: tag};

		// Delete the token
		token.remove();
		ktjapi.callMethod('metadataService.deleteTag', params, function(data, textStatus) 
		{
			// Parse the data if necessary
			data = jQuery.parseJSON(data) ? jQuery.parseJSON(data) : data;
		}, 
		false, function(){}, 30000, 30000);
	}
	
	this.onbeforeunload = function() 
	{
		if (!kt.app.metadata.editing)
		{
			return undefined;
		}
		
		var atLeastOneRequiredNotDone = false;

		jQuery('.required').each(function(index, value){
			//get the fields id: to chop off the "metadatafield-" prefix
			var id = (jQuery(this).attr('id').substring(jQuery(this).attr('id').indexOf('_')+1));

			var valueSpan = jQuery('#value-'+id);

			if(valueSpan.text() == null || valueSpan.text() == undefined || valueSpan.text() == '' || valueSpan.text() == 'no value')
			{
				atLeastOneRequiredNotDone = true;
				jQuery(this).addClass('incomplete');
			}
		});
		
		return atLeastOneRequiredNotDone ? 'If you leave this page now, your metadata will be in an inconsistent state.' : undefined;
	}
	
}
 