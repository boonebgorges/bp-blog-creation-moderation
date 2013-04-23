<?php

/**
 * Plugin bootstrap
 */

class BPBCM {
	public function __construct() {
		$this->includes();
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Called early to hijack BP's handler
		add_action( 'bp_actions', array( $this, 'catch_create_request' ), 1 );

		add_action( 'bp_actions', array( $this, 'catch_process_request' ) );

		add_action( 'signup_blogform', array( $this, 'registration_form_markup' ) );
		add_action( 'wp_ajax_bpbcm_user_search', array( $this, 'user_search_ajax_handler' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function includes() {
		require( __DIR__ . '/registration.php' );
		require( __DIR__ . '/user-query.php' );
	}

	public function requires_moderation( $user_id = null ) {
		return true;
		$mod = true;

		if ( is_null( $user_id ) ) {
			$user_id = bp_loggedin_user_id();
		}

		if ( is_super_admin( $user_id ) ) {
			$mod = false;
		}

		return apply_filters( 'bpbcm_requires_moderation', $mod, $user_id );
	}

	public function can_moderate( $user_id = null ) {
		$can_moderate = false;

		if ( is_null( $user_id ) ) {
			$user_id = bp_loggedin_user_id();
		}

		if ( is_super_admin( $user_id ) ) {
			$can_moderate = true;
		}

		return apply_filters( 'bpbcm_can_moderate', $can_moderate, $user_id );
	}

	/**
	 * Outputs the 'Moderator' label on the Create Blog screen
	 */
	public function moderator_title() {
		return apply_filters( 'bpbcm_moderator_title', 'Moderator' );
	}

	/**
	 * Outputs the 'Moderator' description on the Create Blog screen
	 */
	public function moderator_description() {
		return apply_filters( 'bpbcm_moderator_descritpion', 'Type the username of the moderator who will approve your blog registration.' );
	}

	/**
	 * Outputs the 'Moderator' description on the Create Blog screen
	 */
	public function success_message() {
		return apply_filters( 'bpbcm_success_message', 'You have successfully registered for a new blog. You will receive an email when your blog has been approved and is ready to use.' );
	}

	/**
	 * Schema:
	 *
	 * post_author: The blog creator
	 * post_title: The blog title
	 * post_status: Approval status:
	 *		- 'publish': Approved
	 *		- 'pending': Waiting for approval
	 *		- 'trash': Rejected
	 * 'bpbcm_moderator_id' (postmeta): User id of the moderating admin
	 * 'bpbcm_meta' (postmeta): Misc metadata for registration
	 */
	public function register_post_type() {
		register_post_type( 'bp-blog-registration', array(
			'hierarchical'        => false,
			'public'              => false,
			'show_in_nav_menus'   => true,
			'show_ui'             => true,
			'supports'            => array( 'title', 'editor' ),
			'has_archive'         => true,
			'query_var'           => false,
			'rewrite'             => false,
			'labels'              => array(
				'name'                => __( 'bp blog registrations', 'bcbcm' ),
				'singular_name'       => __( 'bp blog registration', 'bcbcm' ),
				'add_new'             => __( 'add new bp blog registration', 'bcbcm' ),
				'all_items'           => __( 'bp blog registrations', 'bcbcm' ),
				'add_new_item'        => __( 'add new bp blog registration', 'bcbcm' ),
				'edit_item'           => __( 'edit bp blog registration', 'bcbcm' ),
				'new_item'            => __( 'new bp blog registration', 'bcbcm' ),
				'view_item'           => __( 'view bp blog registration', 'bcbcm' ),
				'search_items'        => __( 'search bp blog registrations', 'bcbcm' ),
				'not_found'           => __( 'no bp blog registrations found', 'bcbcm' ),
				'not_found_in_trash'  => __( 'no bp blog registrations found in trash', 'bcbcm' ),
				'parent_item_colon'   => __( 'parent bp blog registration', 'bcbcm' ),
				'menu_name'           => __( 'bp blog registrations', 'bcbcm' ),
			),
		) );
	}

	/**
	 * @todo This is hooked to bp_actions but probably needs to be moved at some point
	 *       bp_show_blog_signup_form() needs to be called directly from the template
	 */
	public function catch_create_request() {
		global $current_user, $current_site, $wpdb;

		if ( bp_is_blogs_component() && bp_is_current_action( 'create' ) && ! empty( $_POST['submit'] ) ) {

			if ( !check_admin_referer( 'bp_blog_signup_form' ) )
				return false;

			$current_user = wp_get_current_user();

			if( !is_user_logged_in() )
				die();

			// BP's validation check on the main fields
			$result = bp_blogs_validate_blog_form();

			// Check the passed moderator name
			$moderator = isset( $_POST['bpbcm-moderator'] ) ? $_POST['bpbcm-moderator'] : '';
			$moderator_user = get_user_by( 'login', $moderator );
			$moderator_id = is_a( $moderator_user, 'WP_User' ) ? $moderator_user->ID : 0;

			if ( ! $this->can_moderate( $moderator_id ) ) {
				$result['errors']->add( 'bpbcm-moderator', 'That is not a valid moderator.' );
			}

			if ( $result['errors']->get_error_code() ) {
				unset($_POST['submit']);
				var_dump( $result['errors'] );
				// @todo Maybe I can put this in a cookie or something
				bp_show_blog_signup_form( $result['blogname'], $result['blog_title'], $result['errors'] );
				return false;
			}

			$public = (int) $_POST['blog_public'];

			$meta = apply_filters( 'signup_create_blog_meta', array( 'lang_id' => 1, 'public' => $public ) ); // depreciated
			$meta = apply_filters( 'add_signup_meta', $meta );

			// If this is a subdomain install, set up the site inside the root domain.
			if ( is_subdomain_install() )
				$domain = $result['blogname'] . '.' . preg_replace( '|^www\.|', '', $current_site->domain );

			// Record
			$registration = new BPBCM_Registration();
			$registration->moderator_id = $moderator_id;
			$registration->domain = $result['domain'];
			$registration->path = $result['path'];
			$registration->title = $result['blogname'];
			$registration->site_id = $wpdb->siteid;
			$registration->meta = $meta;
			$registration->user_id = bp_loggedin_user_id();
			$registration->user_login = $current_user->user_login;
			$registration->user_email = $current_user->user_email;
			$post_id = $registration->create();

			// Send an email notification to the moderator
			$registration->notify_moderator();

			// Faking a redirect, groan
			unset( $_POST['submit'] );
			$_POST['status'] = 'success';


		}
	}

	public function catch_process_request() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( bp_is_blogs_component() && ! bp_current_action() && ! empty( $_GET['registration_id'] ) && ! empty( $_GET['action'] ) ) {
			// Verify that this is a legit request
			$reg_id = intval( $_GET['registration_id'] );
			$registration = new BPBCM_Registration( $reg_id );

			if ( ! empty( $registration->registration_id ) ) {
				// Make sure this is the intended moderator
				if ( bp_loggedin_user_id() !== $registration->moderator_id ) {
					return;
				}

				$action = $_GET['action'];
				if ( 'approve' === $action ) {
					if ( 'approved' !== $registration->get_status() ) {
						$success = $registration->approve();
					}
				} elseif ( 'reject' === $action ) {
					if ( 'rejected' !== $registration->get_status() ) {
						$success = $registration->reject();
					}
				}
			}

			remove_action( 'bp_screens', 'bp_blogs_screen_index', 2 );

//			bp_core_load_template( 'blogs/index' );
		}
	}

	/**
	 * Outputs our additional markup on the Create A Blog screen
	 */
	function registration_form_markup( $errors = null ) {
		if ( ! $this->requires_moderation() ) {
			return;
		}

		?>

		<?php if ( isset( $_POST['status'] ) && 'success' === $_POST['status'] ) : ?>
			<p><?php echo esc_html( $this->success_message() ) ?></p>
		<?php else : ?>
			<label for="bpbcm-moderator"><?php echo esc_html( $this->moderator_title() ) ?>: </label>
			<input autocomplete="off" id="bpbcm-moderator" name="bpbcm-moderator" />
			<p class="description"><?php echo esc_html( $this->moderator_description() ) ?></a>
		<?php endif; ?>

		<?php
	}

	/**
	 * Handles AJAX user search requests
	 */
	public function user_search_ajax_handler() {
		$term = $_REQUEST['term'];

		$args = apply_filters( 'bpbcm_user_query_args', array(
			'blog_id' => null,
			'search' => $term,
			'fields' => 'all',
		) );
		$user_query = new BPBCM_User_Query( $args );

		$retval = array();
		foreach ( $user_query->results as $u ) {
			$retval[] = array(
				'label' => $u->display_name,
				'value' => $u->user_login,
			);
		}

		die( json_encode( $retval ) );
	}

	/**
	 * Enqueue styles and scripts
	 */
	public function enqueue() {
		if ( bp_is_current_component( 'blogs' ) && bp_is_current_action( 'create' ) ) {
			wp_enqueue_script( 'bpbcm', plugins_url() . '/bp-blog-creation-moderation/assets/js/bpbcm.js', array( 'jquery', 'jquery-ui-autocomplete' ) );
			wp_enqueue_style( 'bpbcm', plugins_url() . '/bp-blog-creation-moderation/assets/css/bpbcm.css' );
		}
	}
}

function bpbcm() {
	global $bp;
	if ( ! isset( $bp->bpbcm ) ) {
		$bp->bpbcm = new bpbcm();
	}
	return $bp->bpbcm;
}
add_action( 'bp_loaded', 'bpbcm' );
