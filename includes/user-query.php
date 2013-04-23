<?php

/**
 * Extends the WP_User_Query class to make it easier for us to search across different fields
 */
class BPBCM_User_Query extends WP_User_Query {
	function __construct( $query = null ) {
		add_action( 'pre_user_query', array( &$this, 'filter_registered_users_only' ) );
		parent::__construct( $query );
	}

	/**
	 * BuddyPress has different user statuses.  We need to filter the user list so only registered users are shown.
	 */
	function filter_registered_users_only( $query ) {
		$query->query_where .= ' AND user_status = 0';
	}

	/**
	 * @see WP_User_Query::get_search_sql()
	 */
	function get_search_sql( $string, $cols, $wild = false ) {
		$string = esc_sql( $string );

		// Always search all columns
		$cols = array(
			'user_email',
			'user_login',
			'user_nicename',
			'user_url',
			'display_name'
		);

		// Always do 'both' for trailing_wild
		$wild = 'both';

		$searches = array();
		$leading_wild = ( 'leading' == $wild || 'both' == $wild ) ? '%' : '';
		$trailing_wild = ( 'trailing' == $wild || 'both' == $wild ) ? '%' : '';
		foreach ( $cols as $col ) {
			if ( 'ID' == $col )
				$searches[] = "$col = '$string'";
			else
				$searches[] = "$col LIKE '$leading_wild" . like_escape($string) . "$trailing_wild'";
		}

		return ' AND (' . implode(' OR ', $searches) . ') AND user_status = 0';
	}
}

