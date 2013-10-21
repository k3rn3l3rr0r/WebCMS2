/**
 * 
 * @author
 * @returns 
 */
function Webcms(){
	this.version = '0.1-beta';
	this.defaultConfirm = 'Opravdu si přejete provést akci?'
	this.externals = new Array();
	
	this.init();
}

Webcms.prototype = {
	self : null,

	init : function (){
		self = this;
		
		/**
		* Nette ajax Grido extension.
		* @author Petr BugyĂ­k
		* @param {jQuery} $
		*/
		"use strict";
		$.nette.ext('grido',
		{
			load: function()
			{
				this.selector = $('.grido');
				this.selector.grido();
			},

			success: function(payload)
			{
				this.selector.trigger('success.ajax.grido', payload);

				//scroll up after ajax update
				$('html, body').animate({scrollTop: 0}, 400);
			}
		}, {
			selector: null
		});
		
		$.nette.init(function (ajaxHandler) {
			$('a.ajax:not(.no-ajax)').live('click', ajaxHandler);
			$('form.ajax :submit').live('click', ajaxHandler);
		});

		//ajax loader animation
		$(document).ajaxStart( function() {
			   $('#loader').addClass("active"); 
		} ).ajaxStop ( function(){
				self.initTextEditors();
				$('#loader').removeClass("active"); 
		});

		self.initTextEditors();
		self.__registerListeners();
		
	},
	
	registerExternal : function(ext){
		this.externals.push(ext);
	},
	
	onStart : function(){
		// init wysiwyg editor
		self.initTextEditors();
		
		// load external functions
		for(var i = 0; i < this.externals.length; i++){
			eval(this.externals[i]);
		}
	},
	
	/* Global systems listeners registering. */
	__registerListeners : function(){
		// register bootbox confirm window for all danger buttons
		$(".btn-danger").live("click", function(e){
			e.preventDefault();
			var anchor = this;
			var title = $(this).data("confirm");
			
			if(typeof title === "undefined")
				title = self.defaultConfirm;
			
			bootbox.confirm(title, function(confirmed){
					if(confirmed)
						window.location.href = anchor.href;
			});
		});
		
		$(".jq_head a.ajax").live('click', function(){
			$(".jq_head a.ajax").parent().removeClass('active');
			
			$(this).parent().addClass('active');
		});
		
		$("#languageChanger").live('change', function(){
			$(this).parent().submit();
		});
	},
			
	initTextEditors : function(){
		
		$(".editor:not(.k-content)").ckeditor({
						filebrowserBrowseUrl: basePath  + '/admin/filesystem?dialog=1',
						filebrowserImageBrowseUrl: basePath + '/admin/filesystem?dialog=1&filter=images'
					});

	}
};
		
/* Filesystem */
function Filesystem(){
	
	this.path = new String();
	
	this.init();
};

Filesystem.prototype = {
	
	selffs : null,
	
	init : function(){
		// defaults values
		selffs = this;
		
		this.__registerListeners();
	},
			
	setPath : function(path){
		this.path = path;
	},
	
	getPath : function(path){
		return this.path;
	},
	
	__registerListeners : function(){
		
		$('.filesDialog').live('click', function(e){
			e.preventDefault();
			
			$('.jq_file').die();
			
			var options = {
				container : $(this).data('container'),
				containerId : $(this).data('container-id')
			};
			
			$('.jq_filesAdd').live('click', function(e){
				e.preventDefault();
				
				// 
				$('.jq_selected:checked').each(function(){
					var data = $(this).data();
					$(options.container).append('<div class="col-md-3 jq_fileBox"><div class="img-thumbnail"><img src="' + data.thumbnail + '" /><input type="hidden" name="files[]" value="' + data.path + '" /><input class="form-control" type="text" name="fileNames[]" /><span class="btn btn-default jq_fileDelete">&times</span></div></div>');
					$(this).attr('checked', false);
				});
			});
		});
		
		$(".jq_fileDelete").live('click', function(e){
			e.preventDefault();
			
			$(this).closest('.jq_fileBox').remove();
		});
	}
};

$(function(){
	webcms = new Webcms();
	filesystem = new Filesystem();
});