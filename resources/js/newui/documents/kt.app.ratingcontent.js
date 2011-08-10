/* Initializing kt.app if it wasn't initialized before */
if (typeof(kt.app) == 'undefined') { kt.app = {}; }

/* Initializing kt.api if it wasn't initialized before */
if (typeof(kt.api) == 'undefined') { kt.api = {}; }

/**
 * Dialogs to display notifications around new features
 */
kt.app.ratingcontent = new function() {
    var self = this;

    this.init = function() {
    	
    }

    this.likeDocument = function(documentId, fromBrowseView) {
		self._doLikeDocument('likeDocument', documentId, fromBrowseView);
	}
	
	this.unlikeDocument = function(documentId, fromBrowseView) {
		self._doLikeDocument('unlikeDocument', documentId, fromBrowseView);
	}
	
	this._doLikeDocument = function(action, documentId, fromBrowseView) {
		
		var params = {documentId:documentId};
        
		if (fromBrowseView == undefined) {
			fromBrowseView = true;
		}
		
		if (fromBrowseView) {
			var callback = this.updateBrowseView;
		} else {
			var callback = this.updateDocumentView;
		}
		
		self.documentId = documentId;
		self.action = action;
		
		var synchronous = false;
        var func = 'RatingContent.'+action;
        
        var synchronous = false;
        var errorCallback = function() {};
        ktjapi.callMethod(func, params, callback);
		return null;
	}
	
	this.updateBrowseView = function(response)
	{
		if (response.data.success == 'true') {
			
			if (self.action == 'likeDocument') {
				str = '<a href="javascript:;" onclick="kt.app.ratingcontent.unlikeDocument('+self.documentId+');"><img src="resources/graphics/newui/document_liked.png" /></a>';
			} else {
				str = '<a href="javascript:;" onclick="kt.app.ratingcontent.likeDocument('+self.documentId+');"><img src="resources/graphics/newui/document_notliked.png" /></a>';
			}
			
			// Update with some animation
			jQuery('#docItem_'+self.documentId+' span.like_status').fadeOut('fast',function() {
					jQuery(this).html(str);
				}).fadeIn();
		}
	}
	
	this.updateDocumentView = function(response)
	{
		if (response.data.success == 'true') {
			if (self.action == 'likeDocument') {
				str = '<a href="javascript:;" onclick="kt.app.ratingcontent.unlikeDocument('+self.documentId+', false);"><img src="resources/graphics/newui/document_liked.png" /></a>';
			} else {
				str = '<a href="javascript:;" onclick="kt.app.ratingcontent.likeDocument('+self.documentId+', false);"><img src="resources/graphics/newui/document_notliked.png" /></a>';
			}
			
			if (response.data.newNumLikes == 1) {
				countStr = 'One User likes this document';
			} else {
				countStr = response.data.newNumLikes+' Users like this document';
			}
			
			// Update with some animation
			jQuery('#documentLikeStatus span.like_status').fadeOut('fast',function() {
					jQuery(this).html(str);
				}).fadeIn();
			
			jQuery('#documentLikeStatus span.like_count').html(countStr);
			
		}
		
	}


    // Call the initialization function at object instantiation.
    this.init();
}