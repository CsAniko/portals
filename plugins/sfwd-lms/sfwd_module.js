if ( typeof sfwd_data != 'undefined' ) {
	sfwd_data = sfwd_data.json.replace(/&quot;/g, '"');
	sfwd_data = jQuery.parseJSON( sfwd_data );
}


function toggleVisibility(id) {
	var e = document.getElementById(id);
	if (e.style.display == 'block')
		e.style.display = 'none';
	else
		e.style.display = 'block';
}

function countChars(field,cntfield) {
	cntfield.value = field.value.length;
}

jQuery('.sfwd_datepicker').each(function () {
	jQuery('#' + jQuery(this).attr('id')).datepicker();
});

function sfwd_do_condshow_match( index, value ) {
	if ( typeof value != 'undefined' ) {
		matches = true;
		jQuery.each(value, function(subopt, setting) {
			cur = jQuery('[name=' + subopt + ']');
			type = cur.attr('type');
			if ( type == "checkbox" || type == "radio" )
				cur = jQuery('input[name=' + subopt + ']:checked');
			cur = cur.val();
			if ( cur != setting ) {
				matches = false;
				return false;
			}
		});
		if ( matches ) {
			jQuery('#' + index ).show();
		} else {
			jQuery('#' + index ).hide();					
		}
		return matches;
	}
	return false;
}

function sfwd_add_condshow_handlers( index, value ) {
	if ( typeof value != 'undefined' ) {
		jQuery.each(value, function(subopt, setting) {
			jQuery('[name=' + subopt + ']').change(function() {
				sfwd_do_condshow_match( index, value );
			});
		});
	}
}

function sfwd_do_condshow( condshow ) {
	if ( typeof sfwd_data.condshow != 'undefined' ) {
		jQuery.each(sfwd_data.condshow, function(index, value) {
			sfwd_do_condshow_match( index, value );
			sfwd_add_condshow_handlers( index, value );
		});
	}
}

function sfwd_show_pointer( handle, value ) {
	if ( typeof( jQuery( value.pointer_target ).pointer) != 'undefined' ) {
		jQuery(value.pointer_target).pointer({
					content    : value.pointer_text,
					close  : function() {
						jQuery.post( ajaxurl, {
							pointer: handle,
							action: 'dismiss-wp-pointer'
						});
					}
				}).pointer('open');
	}
}

jQuery(document).ready(function(){
if (typeof sfwd_data != 'undefined') {
	if ( typeof sfwd_data.condshow != 'undefined' ) {
		sfwd_do_condshow( sfwd_data.condshow );
	}
}
});

jQuery(document).ready(function() {
	var image_field;
	jQuery('.sfwd_upload_image_button').click(function() {
		window.send_to_editor = newSendToEditor;
		image_field = jQuery(this).next();
		formfield = image_field.attr('name');
		tb_show('', 'media-upload.php?type=image&amp;TB_iframe=true');
		return false;
	});

	storeSendToEditor 	= window.send_to_editor;
	newSendToEditor		= function(html) {
							imgurl = jQuery('img',html).attr('src');
							image_field.val(imgurl);
							tb_remove();
							window.send_to_editor = storeSendToEditor;
						};
});

// props to commentluv for this fix
// workaround for bug that causes radio inputs to lose settings when meta box is dragged.
// http://core.trac.wordpress.org/ticket/16972
jQuery(document).ready(function(){
    // listen for drag drop of metaboxes , bind mousedown to .hndle so it only fires when starting to drag
    jQuery('.hndle').mousedown(function(){                                                               
        // set live event listener for mouse up on the content .wrap and wait a tick to give the dragged div time to settle before firing the reclick function
        jQuery('.wrap').mouseup(function(){store_radio(); setTimeout('reclick_radio();',50);});
    })
});
/**
* stores object of all radio buttons that are checked for entire form
*/
if(typeof store_radio != 'function') {
	function store_radio(){
	    var radioshack = {};
	    jQuery('input[type="radio"]').each(function(){
	        if(jQuery(this).is(':checked')){
	            radioshack[jQuery(this).attr('name')] = jQuery(this).val();
	        }
	        jQuery(document).data('radioshack',radioshack);
	    });
	}
}
/**
* detect mouseup and restore all radio buttons that were checked
*/
if(typeof reclick_radio != 'function') {
	function reclick_radio(){
	    // get object of checked radio button names and values
	    var radios = jQuery(document).data('radioshack');
	    //step thru each object element and trigger a click on it's corresponding radio button
	    for(key in radios){
	        jQuery('input[name="'+key+'"]').filter('[value="'+radios[key]+'"]').trigger('click');
	    }            
	    // unbind the event listener on .wrap  (prevents clicks on inputs from triggering function)
	    jQuery('.wrap').unbind('mouseup');
	}
}

jQuery(document).ready(function() {
		if ( typeof sfwd_data.pointers != 'undefined' ) {
			jQuery.each(sfwd_data.pointers, function(index, value) {
				if ( value != 'undefined' && value.pointer_text != '' ) {
					sfwd_show_pointer( index, value );				
				}
			});
		}
	
        jQuery(".sfwd_tab:not(:first)").hide();
        jQuery(".sfwd_tab:first").show();
        jQuery(".sfwd_header_tabs a").click(function(){
                stringref = jQuery(this).attr("href").split('#')[1];
                jQuery('.sfwd_tab:not(#'+stringref+')').hide();
                jQuery('.sfwd_tab#' + stringref).show();
                jQuery('.sfwd_header_tab[href!=#'+stringref+']').removeClass('active');
                jQuery('.sfwd_header_tab#[href=#' + stringref+']').addClass('active');
                return false;
        });
});
