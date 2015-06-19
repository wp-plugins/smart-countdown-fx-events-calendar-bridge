/**
 * Admin interface enhacments
 */
jQuery(document).ready(function($) {
    $('.scd-te-hide-control').on('change', function() {
    	var $this = $(this);
    	var value = $this.val();
    	var table = $this.closest('table');
    	table.find('.scd-te-hide').hide();
    	switch(value) {
	    	case '-1' : 
	    		// disabled
	    		break;
	    	default :
	    		// all other values
	    		table.find('.scd-te-general').show();
    	};
    });
    $('.scd-te-hide-control').trigger('change');
    
    // hide time_zone meta key from custom fields list in posts
	var time_zone_meta_field = $('input[type="text"][value="scd_time_zone"]');
	if(time_zone_meta_field.length > 0) {
		time_zone_meta_field.closest('tr').hide();
	}
});