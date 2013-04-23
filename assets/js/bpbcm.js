jQuery(document).ready( function($) {
	$('#bpbcm-moderator').autocomplete({
		source: ajaxurl + '?action=bpbcm_user_search',
	});
}, (jQuery));
