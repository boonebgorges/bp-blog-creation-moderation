<?php

/**
 * A registration event
 */

class BPBCM_Registration {
	var $domain;
	var $path;
	var $title;
	var $site_id;
	var $meta;

	var $moderator_id;

	var $user_id;
	var $user_login;
	var $user_email;

	var $registration_id;

	public function __construct( $reg_id = null ) {
		if ( ! is_null( $reg_id ) ) {
			$reg = get_post( $reg_id );

			if ( ! is_a( $reg, 'WP_Post' ) || 'bp-blog-registration' !== $reg->post_type ) {
				return;
			}

			$this->registration_id = $reg_id;
			$this->title = $reg->post_title;

			$applicant = new WP_User( $reg->post_author );
			$this->user_id = $applicant->ID;
			$this->user_login = $applicant->user_login;
			$this->user_email = $applicant->user_email;

			$meta = get_post_meta( $reg_id, 'bpbcm_meta', true );
			$this->domain = $meta['domain'];
			$this->path = $meta['path'];
			$this->site_id = $meta['site_id'];
			$this->meta = $meta;

			$moderator_id = get_post_meta( $reg_id, 'bpbcm_moderator_id', true );
		}
	}

	public function create() {
		$args = array(
			'post_type' => 'bp-blog-registration',
			'post_title' => $this->title,
			'post_author' => $this->user_id,
			'post_status' => 'pending',
		);
		$this->registration_id = wp_insert_post( $args );

		if ( $this->registration_id ) {
			// Dump some misc data into the 'meta' value
			$this->meta['domain'] = $this->domain;
			$this->meta['path'] = $this->path;
			$this->meta['site_id'] = $this->site_id;
			update_post_meta( $this->registration_id, 'bpbcm_meta', $this->meta );

			update_post_meta( $this->registration_id, 'bpbcm_moderator_id', $this->moderator_id );
		}

		return $this->registration_id;
	}

	public function notify_moderator() {
		$moderator = new WP_User( $this->moderator_id );
		$applicant = new WP_User( $this->user_id );

		$subject = __( 'New blog application', 'bpbcm' );

		$link_base = bp_get_root_domain() . '/' . bp_get_blogs_root_slug() . '/';
		$approve_link = add_query_arg( array(
			'registration_id' => $this->registration_id,
			'action' => 'accept',
		), $link_base );
		$reject_link = add_query_arg( array(
			'registration_id' => $this->registration_id,
			'action' => 'reject',
		), $link_base );

		$message = sprintf(
			__( 'A user on %1$s has applied for a new blog.

Name: %2$s
Login: %3$s
Blog Title: %4$s
URL: %5$s

Approve: %6$s
Reject: %7$s', 'bpbcm' ),
			get_option( 'blogname' ),
			bp_core_get_user_displayname( $applicant->ID ),
			$applicant->user_login,
			$this->title,
			( is_ssl() ? 'https://' : 'http://' ) . $this->domain . $this->path,
			$approve_link,
			$reject_link
		);
		$message = apply_filters( 'bpbcm_notification_email_body', $message );
		var_dump( $message ); die();
	}
}
