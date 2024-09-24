/*
 * Real Accessability
 * Version: 1.0
 * Author: REALMEDIA
 * Website: https://realmedia.co.il
 * Dual licensed under the MIT and GPL licenses.
 */

(function($) {
	
	"use strict";

	$.RealAccessability = function( options ) {
	 
	    var settings = $.extend( {
	        exclude: '#fb-root',
	        markup: 'h1, h2, h3, h4, h5, h6, span, div, p, a, input, textarea, li, i',	
	        hideOnScroll: true	    
		}, options );
	    
		var effects = [
			'real-accessability-grayscale',
			'real-accessability-invert'
		];	 
		
		var obj = {
			fontSize: 0,
			effect: null,
			linkHighlight: false,
			regularFont: false
		}	
		
		var mustExclude = '#real-accessability, #real-accessability ul, #real-accessability li, #real-accessability a, #real-accessability i, #real-accessability div, #wpadminbar, #wpadminbar div, #wpadminbar a, #wpadminbar ul, #wpadminbar li, #wpadminbar span';	
		
		settings.exclude = mustExclude + ', ' + settings.exclude;
		
		// Init
		var init = function() {	
			var cookie = getCookie('real-accessability');
			
			if(cookie !== '') {
				obj = JSON.parse(cookie);	
				$(settings.markup).not(settings.exclude).each(function() {
					var fontSize = parseInt( $(this).css('font-size') );
					$(this).attr('data-raofz', fontSize);			
				});
				
				if(obj.fontSize !== 0) {	
					$(settings.markup).not(settings.exclude).each(function() {
						var fontSize = parseInt( $(this).data('raofz') );
						$(this).css('font-size', fontSize+(2*obj.fontSize));				
					});	
				}
				
				// For themes that touched the default <?php body_class(); ?> class
				if(!$('body').hasClass(obj.effect) && obj.effect !== null) {
					$('body').addClass(obj.effect);
					$('#'+obj.effect).addClass('clicked');
				}
				
				if(!$('body').hasClass(obj.linkHighlight) && obj.linkHighlight == true) {
					$('body').addClass('real-accessability-linkHighlight');
					$('#real-accessability-linkHighlight').addClass('clicked');
				}
				
				if(!$('body').hasClass(obj.regularFont) && obj.regularFont == true) {
					$('body').addClass('real-accessability-regularFont');
					$('#real-accessability-regularFont').addClass('clicked');
				}								
			}			
			
			$('#real-accessability-btn').on('click', openToolbar);	
			$('#real-accessability-biggerFont').on('click', increaseFont);
			$('#real-accessability-smallerFont').on('click', decreaseFont);
			$('.real-accessability-effect').on('click', effectChange);
			$('#real-accessability-linkHighlight').on('click', linkHighlight);
			$('#real-accessability-regularFont').on('click', regularFont);
			$('#real-accessability-reset').on('click', reset);
		}   
	 
		// Get saved cookie option
		var getCookie = function(name) {
		    var name = name + '=';
		    var arr = document.cookie.split(';');
		    
		    for(var i=0; i<arr.length; i++) {
		        var c = arr[i];
		        while (c.charAt(0) == ' ') c = c.substring(1);
		        if (c.indexOf(name) == 0) {
			        return c.substring(name.length,c.length);
			    }
		    }
		    
		    return '';
		}
		
		// Open toolbar 
		function openToolbar() {
			if($(this).parent().hasClass('open')) {
				$(this).parent().removeClass('open');
			} else {
				$(this).parent().addClass('open');
				if(getCookie('real-accessability') == '') {
					$(settings.markup).not(settings.exclude).each(function() {
						var fontSize = parseInt( $(this).css('font-size') );
						$(this).attr('data-raofz', fontSize);			
					});	
				}			
			}			
		}
		
		// Make font bigger
		function increaseFont() {
			if(obj.fontSize < 3) {
				showLoader(function() {
					obj.fontSize++;
					$(settings.markup).not(settings.exclude).each(function() {
						var fontSize = parseInt( $(this).data('raofz') );
						$(this).css('font-size', fontSize+(2*obj.fontSize));			
					});		
				});		
			}				
		}
		
		// Make font smaller
		function decreaseFont() {
			if(obj.fontSize > -3) {
				showLoader(function() {
					obj.fontSize--;
					$(settings.markup).not(settings.exclude).each(function() {
						var fontSize = parseInt( $(this).data('raofz') );
						$(this).css('font-size', fontSize+(2*obj.fontSize));				
					});	
				});		
			}					
		}
		
		// Change effect
		function effectChange() {
			var chosenEffect = $(this).attr('id');
			obj.effect = null;
			
			showLoader(function() {					
				for(var i=0; i<effects.length; i++) {
					if(!$('body').hasClass(chosenEffect) && chosenEffect == effects[i]) {
						$('body').addClass(effects[i]);	
						$('#'+effects[i]).addClass('clicked');		
						obj.effect = effects[i];		
					} else {
						$('body').removeClass(effects[i]);	
						$('#'+effects[i]).removeClass('clicked');	
					}
				}
			});	
		}
		
		// Highlight all the links
		function linkHighlight() {
			var $this = $(this);
			
			showLoader(function() {
				if($('body').hasClass('real-accessability-linkHighlight')) {
					$('body').removeClass('real-accessability-linkHighlight');
					$this.removeClass('clicked');
					obj.linkHighlight = false;
				} else {
					$('body').addClass('real-accessability-linkHighlight');	
					$this.addClass('clicked');
					obj.linkHighlight = true;
				}	
			});		
		}	
		
		// Regular font / More readable font (Arial, Helvetica, sans-serif)
		function regularFont() {
			var $this = $(this);
			
			showLoader(function() {
				if($('body').hasClass('real-accessability-regularFont')) {
					$('body').removeClass('real-accessability-regularFont');
					$this.removeClass('clicked');
					obj.regularFont = false;
				} else {
					$('body').addClass('real-accessability-regularFont');	
					$this.addClass('clicked');
					obj.regularFont = true;
				}	
			});		
		}				
		
		// Reset all
		var reset = function() {
			showLoader(function() {
				$('#real-accessability ul li a').removeClass('clicked');
				
				$(settings.markup).not(settings.exclude).each(function() {
					var fontSize = parseInt( $(this).data('raofz') );
					$(this).css('font-size', fontSize);				
				});						
				
				if($('body').hasClass('real-accessability-grayscale')) {
					$('body').removeClass('real-accessability-grayscale');
				}				
				
				if($('body').hasClass('real-accessability-invert')) {
					$('body').removeClass('real-accessability-invert');
				}			
				
				if($('body').hasClass('real-accessability-linkHighlight')) {
					$('body').removeClass('real-accessability-linkHighlight');
				}		
				
				if($('body').hasClass('real-accessability-regularFont')) {
					$('body').removeClass('real-accessability-regularFont');
				}				
				
				// Reset the object
				obj.fontSize = 0;
				obj.effect = null;
				obj.linkHighlight = false;
				obj.regularFont = false;
			});			
		}
		
		// Disable clicking on toolbar links
		var disableClicking = function() {
			$('#real-accessability a').bind('click', function(e) {
				e.preventDefault();		
			});			
		}
		
		var showLoader = function(callback) {
				$('a#real-accessability-btn i.real-accessability-icon').css('display', 'none');	
				$('a#real-accessability-btn i.real-accessability-loading').show();					
				setTimeout(function() { 
					callback(); 
					$('a#real-accessability-btn i.real-accessability-loading').hide();				
					$('a#real-accessability-btn i.real-accessability-icon').show();						
				}, 300);			
		}
		
		// Hide toolbar when scrolling
		var hideOnScroll = function() {
			if(settings.hideOnScroll) {
				$(window).scroll(function() {
					if($('#real-accessability').hasClass('open')) {
						$('#real-accessability').removeClass('open');	
					}	
				});
			}				
		}
		
		// Save object in cookie named 'real-accessability' when user redirect page
		var saveOnRedirect = function() {
			$(window).on('beforeunload', function() {
				document.cookie = 'real-accessability=' + JSON.stringify(obj) + '; path=/';
			});				
		}
		
		// Add role="link" to all links
		var addLinkRole = function() {
			$('a').not(settings.exclude).attr('role', 'link');
		}		
		
		var removeLinkTarget = function() {
			$('a[target]').not(settings.exclude).removeAttr('target');
		}
		
		init();
		disableClicking();
		hideOnScroll();
		saveOnRedirect();
		addLinkRole();
		removeLinkTarget();
	    
	}

})(jQuery);