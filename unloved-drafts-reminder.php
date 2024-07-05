<?php
/**
 * Unloved Drafts Reminder
 *
 * @package           unloved-drafts-reminder
 * @author            Blocktech Lab
 *
 * Plugin Name:       Unloved Drafts Reminder
 * Plugin URI:        https://blocktech.dev
 * Description:       Email users with pending drafts
 * Version:           1.0.0
 * Requires at least: 4.6
 * Requires PHP:      7.4
 * Author:            Blocktech Lab
 * Author URI:        https://blocktech.dev
 * Text Domain:       unloved-drafts-reminder
 */

/**
 * Add meta to plugin details
 *
 * Add options to plugin meta line
 *
 * @param    string $links  Current links.
 * @param    string $file   File in use.
 * @return   string         Links, now with settings added.
 */
function unloved_drafts_reminder_plugin_meta( $links, $file ) {

	if ( false !== strpos( $file, 'unloved-drafts-reminder.php' ) ) {

		$links = array_merge(
			$links,
			array( '<a href="https://github.com/blocktech-lab/unloved-drafts-reminder">' . __( 'Github', 'unloved-drafts-reminder' ) . '</a>' ),
		);
	}

	return $links;
}

add_filter( 'plugin_row_meta', 'unloved_drafts_reminder_plugin_meta', 10, 2 );

/**
 * Modify actions links.
 *
 * Add or remove links for the actions listed against this plugin
 *
 * @param    string $actions      Current actions.
 * @param    string $plugin_file  The plugin.
 * @return   string               Actions, now with deactivation removed!
 */
function unloved_drafts_reminder_action_links( $actions, $plugin_file ) {

	// Make sure we only perform actions for this specific plugin!
	if ( strpos( $plugin_file, 'unloved-drafts-reminder.php' ) !== false ) {

		// If the appropriate constant is defined, remove the deactionation link.
		if ( defined( 'DO_NOT_DISABLE_MY_DRAFT_REMINDER' ) && true === DO_NOT_DISABLE_MY_DRAFT_REMINDER ) {
			unset( $actions['deactivate'] );
		}

		// Add link to the settings page.
		array_unshift( $actions, '<a href="' . admin_url() . 'options-general.php#unloved-drafts-reminder">' . __( 'Settings', 'unloved-drafts-reminder' ) . '</a>' );

	}

	return $actions;
}

add_filter( 'plugin_action_links', 'unloved_drafts_reminder_action_links', 10, 2 );

/**
 * Define scheduler
 *
 * If a schedule isn't already set up, set one up!
 * It will run daily at a time which can be adjusted.
 */
function unloved_drafts_reminder_set_up_schedule() {

	$day = 'tomorrow';

	// Get the time that the event needs scheduling for.
	// If one isn't specified, default to 1am!
	$time = get_option( 'unloved_drafts_reminder_time' );
	if ( ! $time ) {
		$time = '1am';
	}

	// If we have an old time saved, check to see if it's changed.
	// If it has, remove the event and update the old one.
	$saved_time = get_option( 'unloved_drafts_reminder_prev_time' );

	if ( ! $saved_time || $time !== $saved_time ) {
		if ( strtotime( 'today ' . $time ) > strtotime( 'now' ) ) {
			$day = 'today';
		}
		wp_clear_scheduled_hook( 'unloved_drafts_reminder_mailer' );
		update_option( 'unloved_drafts_reminder_prev_time', $time );
	}

	// Schedule an event if one doesn't already exist.
	if ( ! wp_next_scheduled( 'unloved_drafts_reminder_mailer' ) && ! wp_installing() ) {
		wp_schedule_event( strtotime( $day . ' ' . $time ), 'daily', 'unloved_drafts_reminder_mailer' );
	}
}

add_action( 'admin_init', 'unloved_drafts_reminder_set_up_schedule' );

/**
 * Scheduler engine
 *
 * This is the function that runs when the daily scheduler rolls around
 */
function unloved_drafts_reminder_schedule_engine() {

	// Grab the settings for how often to run this.
	// If it's not been set, assume weekly!
	$when = get_option( 'unloved_drafts_reminder_when' );
	if ( ! $when ) {
		$when = 'Monday';
	}

	// Check to see if it should be run.
	if ( 'Daily' === $when || ( 'Daily' !== $when && gmdate( 'l' ) === $when ) ) {
		unloved_drafts_reminder_process_posts();
	}
}

add_action( 'unloved_drafts_reminder_mailer', 'unloved_drafts_reminder_schedule_engine' );

/**
 * Add to settings
 *
 * Add fields to the general settings to capture the various settings required for the plugin.
 */
function unloved_drafts_reminder_settings_init() {

	// Add the new section to General settings.
	add_settings_section( 'unloved_drafts_reminder_section', __( 'Unloved Drafts Reminder', 'unloved-drafts-reminder' ), 'unloved_drafts_reminder_section_callback', 'general' );

	// Add the settings field for what day to generate the emails.
	add_settings_field( 'unloved_drafts_reminder_when', __( 'Day to generate', 'unloved-drafts-reminder' ), 'unloved_drafts_reminder_when_callback', 'general', 'unloved_drafts_reminder_section', array( 'label_for' => 'unloved_drafts_reminder_when' ) );
	register_setting( 'general', 'unloved_drafts_reminder_when' );

	// Add the settings field for what time to generate the emails.
	add_settings_field( 'unloved_drafts_reminder_time', __( 'Time to generate', 'unloved-drafts-reminder' ), 'unloved_drafts_reminder_time_callback', 'general', 'unloved_drafts_reminder_section', array( 'label_for' => 'unloved_drafts_reminder_time' ) );
	register_setting( 'general', 'unloved_drafts_reminder_time' );

	// Add the settings field for which post types to look through for drafts.
	add_settings_field( 'unloved_drafts_reminder_what', __( 'Post types to check', 'unloved-drafts-reminder' ), 'unloved_drafts_reminder_what_callback', 'general', 'unloved_drafts_reminder_section', array( 'label_for' => 'unloved_drafts_reminder_what' ) );
	register_setting( 'general', 'unloved_drafts_reminder_what' );

	// Add the settings field for how old drafts must be to qualify.
	add_settings_field( 'unloved_drafts_reminder_age', __( 'Draft age to qualify', 'unloved-drafts-reminder' ), 'unloved_drafts_reminder_age_callback', 'general', 'unloved_drafts_reminder_section', array( 'label_for' => 'unloved_drafts_reminder_age' ) );
	register_setting( 'general', 'unloved_drafts_reminder_age' );

	// Add the settings field for whether draft ages should be based on creation or update.
	add_settings_field( 'unloved_drafts_reminder_since', '', 'unloved_drafts_reminder_since_callback', 'general', 'unloved_drafts_reminder_section', array( 'label_for' => 'unloved_drafts_reminder_since' ) );
	register_setting( 'general', 'unloved_drafts_reminder_since' );
}

add_action( 'admin_init', 'unloved_drafts_reminder_settings_init' );

/**
 * Section callback
 *
 * Create the new section that we've added to the Discussion settings screen
 */
function unloved_drafts_reminder_section_callback() {

	$output = get_option( 'unloved_drafts_reminder_output' );

	echo esc_html( __( 'These settings allow you to control when the emails are generated and what they should report on.', 'unloved-drafts-reminder' ) );

	// Show the current status of the event run.

	$date_format = 'l, F j, Y \a\t g:i a';
	echo '<br/><br/>';
	echo wp_kses( '<strong>' . __( 'Status: ', 'unloved-drafts-reminder' ) . '</strong>', array( 'strong' => array() ) );
	if ( ! $output ) {
		echo esc_html( __( 'Unloved Drafts Reminder has not yet run.', 'unloved-drafts-reminder' ) );
	} else {
		$timestamp = gmdate( $date_format, $output['timestamp'] );
		if ( 0 === $output['errors'] ) {
			/* translators: %1$s: timestamp */
			$text = sprintf( __( 'Unloved Drafts Reminder last ran at %1$s, successfully.', 'unloved-drafts-reminder' ), $timestamp );
		} else {
			/* translators: %1$s: timestamp */
			$text = sprintf( __( 'Unloved Drafts Reminder last ran at %1$s, with errors.', 'unloved-drafts-reminder' ), $timestamp );
		}
	}

	// If an event has been scheduled, output the next run time.
	if ( false !== wp_next_scheduled( 'unloved_drafts_reminder_mailer' ) ) {
		$next_run = gmdate( $date_format, wp_next_scheduled( 'unloved_drafts_reminder_mailer' ) );
		/* translators: %1$s: timestamp */
		if (isset($text)){
			$text .= '&nbsp;' . sprintf( __( 'It is next due to run on %1$s.', 'unloved-drafts-reminder' ), $next_run );
		}
	
	}
	if (isset($text)){
		echo esc_html( $text ) . '<br/>';
	}
}

/**
 * When callback
 *
 * Add the settings field for what date to generate the emails.
 */
function unloved_drafts_reminder_when_callback() {

	$option = get_option( 'unloved_drafts_reminder_when' );
	if ( ! $option ) {
		$option = 'Monday';
	}

	echo '<select name="unloved_drafts_reminder_when">';
	echo '<option ' . selected( 'Daily', $option, false ) . ' value="Daily">' . esc_html__( 'Daily', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'Monday', $option, false ) . ' value="Monday">' . esc_html__( 'Monday', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'Tuesday', $option, false ) . ' value="Tuesday">' . esc_html__( 'Tuesday', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'Wednesday', $option, false ) . ' value="Wednesday">' . esc_html__( 'Wednesday', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'Thursday', $option, false ) . ' value="Thursday">' . esc_html__( 'Thursday', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'Friday', $option, false ) . ' value="Friday">' . esc_html__( 'Friday', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'Saturday', $option, false ) . ' value="Saturday">' . esc_html__( 'Saturday', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'Sunday', $option, false ) . ' value="Sunday">' . esc_html__( 'Sunday', 'unloved-drafts-reminder' ) . '</option>';
	echo '</select>';
}

/**
 * Time callback
 *
 * Add the settings field for what time to generate the emails.
 */
function unloved_drafts_reminder_time_callback() {

	$option = get_option( 'unloved_drafts_reminder_time' );
	if ( ! $option ) {
		$option = '1am';
	}

	echo '<select name="unloved_drafts_reminder_time">';
	echo '<option ' . selected( '12am', $option, false ) . ' value="12am">' . esc_html__( 'Midnight', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '1am', $option, false ) . ' value="1am">' . esc_html__( '1am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '2am', $option, false ) . ' value="2am">' . esc_html__( '2am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '3am', $option, false ) . ' value="3am">' . esc_html__( '3am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '4am', $option, false ) . ' value="4am">' . esc_html__( '4am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '5am', $option, false ) . ' value="5am">' . esc_html__( '5am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '6am', $option, false ) . ' value="6am">' . esc_html__( '6am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '7am', $option, false ) . ' value="7am">' . esc_html__( '7am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '8am', $option, false ) . ' value="8am">' . esc_html__( '8am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '9am', $option, false ) . ' value="9am">' . esc_html__( '9am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '10am', $option, false ) . ' value="10am">' . esc_html__( '10am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '11am', $option, false ) . ' value="11am">' . esc_html__( '11am', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '12pm', $option, false ) . ' value="12pm">' . esc_html__( 'Midday', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '1pm', $option, false ) . ' value="1pm">' . esc_html__( '1pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '2pm', $option, false ) . ' value="2pm">' . esc_html__( '2pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '3pm', $option, false ) . ' value="3pm">' . esc_html__( '3pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '4pm', $option, false ) . ' value="4pm">' . esc_html__( '4pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '5pm', $option, false ) . ' value="5pm">' . esc_html__( '5pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '6pm', $option, false ) . ' value="6pm">' . esc_html__( '6pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '7pm', $option, false ) . ' value="7pm">' . esc_html__( '7pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '8pm', $option, false ) . ' value="8pm">' . esc_html__( '8pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '9pm', $option, false ) . ' value="9pm">' . esc_html__( '9pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '10pm', $option, false ) . ' value="10pm">' . esc_html__( '10pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( '11pm', $option, false ) . ' value="11pm">' . esc_html__( '11pm', 'unloved-drafts-reminder' ) . '</option>';
	echo '</select>';
}

/**
 * What? callback
 *
 * Add the settings field for which post types to look through for drafts
 */
function unloved_drafts_reminder_what_callback() {

	$option = get_option( 'unloved_drafts_reminder_what' );
	if ( ! $option ) {
		$option = 'postpage';
	}

	echo '<select name="unloved_drafts_reminder_what">';
	echo '<option ' . selected( 'post', $option, false ) . ' value="post">' . esc_html__( 'Posts', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'page', $option, false ) . ' value="page">' . esc_html__( 'Pages', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'postpage', $option, false ) . ' value="post">' . esc_html__( 'Posts & Pages', 'unloved-drafts-reminder' ) . '</option>';
	echo '</select>';
}

/**
 * Age? callback
 *
 * Add the settings field for how old drafts must be to qualify.
 */
function unloved_drafts_reminder_age_callback() {

	$option = get_option( 'unloved_drafts_reminder_age' );
	if ( ! $option ) {
		$option = 0;
	}

	echo '<input name="unloved_drafts_reminder_age" size="3" maxlength="3" type="text" value="' . esc_attr( $option ) . '" />&nbsp;' . esc_html__( 'days', 'unloved-drafts-reminder' );
}

/**
 * Since? callback
 *
 * Add the settings field for whether draft ages should be based on creation or update.
 */
function unloved_drafts_reminder_since_callback() {

	$option = get_option( 'unloved_drafts_reminder_since' );
	if ( ! $option ) {
		$option = 'created';
	}

	echo '<select name="unloved_drafts_reminder_since">';
	echo '<option ' . selected( 'created', $option, false ) . ' value="created">' . esc_html__( 'Since they were created', 'unloved-drafts-reminder' ) . '</option>';
	echo '<option ' . selected( 'modified', $option, false ) . ' value="modified">' . esc_html__( 'Since they were last updated', 'unloved-drafts-reminder' ) . '</option>';
	echo '</select>';
}

/**
 * Process posts
 *
 * This processes the draft posts for each user in turn
 * It's defined as a seperate function, seperate from the scheduler action, so that it can
 * be called seperately, if required.
 *
 * @param string $debug true or false, determining if this is to be emailed or output.
 */
function unloved_drafts_reminder_process_posts( $debug = false ) {

	$output = array();
	$errors = 0;

	$since = get_option( 'unloved_drafts_reminder_since' );

	// Get age of acceptable posts. If age not set, assume 0 which means an unlimited.
	$age = get_option( 'unloved_drafts_reminder_age' );
	if ( ! $age ) {
		$age = 0;
	}

	// Get how regularly it's due to run. If not daily, then assume weekly.
	$when = strtolower( get_option( 'unloved_drafts_reminder_when' ) );
	if ( 'daily' !== $when ) {
		$when = 'weekly';
	}

	// Set up the post types that will be searched for.

	$postpage = get_option( 'unloved_drafts_reminder_what' );
	if ( ! $postpage || 'postpage' === $postpage ) {
		$postpage = array( 'page', 'post' );
	}

	// Get an array of users and loop through each.

	$users = get_users();
	foreach ( $users as $user ) {

		$draft_count = 0;

		// Now grab all the posts of each user.

		$args = array(
			'post_status' => 'draft',
			'post_type'   => $postpage,
			'numberposts' => 99,
			'author'      => $user->ID,
			'orderby'     => 'post_date',
			'sort_order'  => 'asc',
		);

		$email_addy = $user->user_email;

		$posts   = get_posts( $args );
		$message = '';

		foreach ( $posts as $post ) {

			// Check to see if draft is old enough!

			$include_draft = true;

			if ( 0 !== $age ) {

				if ( 'modified' === $since ) {
					$date = $post->post_modified;
				} else {
					$date = $post->post_date;
				}

				// Convert the post edit date into Unix time format.
				$post_unix = strtotime( $date );

				// Get current time in Unix format and subtract the number of days specified.
				$check_unix = time() - ( $age * DAY_IN_SECONDS );

				if ( $post_unix > $check_unix ) {
					$include_draft = false;
				}
			}

			// If the modified date is different to the creation date, then we'll output it.
			// In which case, we'll build an appropriate end-of-sentence.
			$modified = '';
			if ( $post->post_date !== $post->post_modified ) {
				/* translators: %1$s: the date the post was last modified */
				$modified = sprintf( __( ' and last edited on %1$s', 'unloved-drafts-reminder' ), esc_html( substr( $post->post_modified, 0, strlen( $post->post_modified ) - 3 ) ) );
			}

			if ( $include_draft ) {

				// Build a list of drafts that require the user's attention.
				++$draft_count;

				/* translators: Do not translate COUNT,TITLE, LINK, CREATED or MODIFIED : those are placeholders. */
				$message .= __(
					'###COUNT###. ###TITLE### - ###LINK### (###WORDS### words)
    This was created on ###CREATED######MODIFIED###.

',
					'unloved-drafts-reminder'
				);

				$message = str_replace(
					array(
						'###COUNT###',
						'###TITLE###',
						'###LINK###',
						'###CREATED###',
						'###MODIFIED###',
						'###WORDS###',
					),
					array(
						esc_html( $draft_count ),
						esc_html( $post->post_title ),
						esc_html( get_admin_url() . 'post.php?post=' . $post->ID . '&action=edit' ),
						esc_html( substr( $post->post_date, 0, strlen( $post->post_date ) - 3 ) ),
						$modified,
						esc_html( str_word_count( $post->post_content ) ),
					),
					$message
				);
			}
		}

		// Add a header to the email content. A different message is used dependant on whether there is 1 or more drafts.
		if ( 0 < $draft_count ) {

			if ( 1 === $draft_count ) {

				/* translators: Do not translate WHEN: this is a placeholder. */
				$header = __(
					'Howdy!

This is your ###WHEN### reminder that you have an outstanding draft that requires your attention:

',
					'unloved-drafts-reminder'
				);

			} else {

				/* translators: Do not translate WHEN or NUMBER: those are placeholders. */
				$header = __(
					'Howdy!

This is your ###WHEN### reminder that you have ###NUMBER### outstanding drafts that require your attention:

',
					'unloved-drafts-reminder'
				);
			}

			$header = str_replace(
				array(
					'###WHEN###',
					'###NUMBER###',
				),
				array(
					esc_html( $when ),
					esc_html( $draft_count ),
				),
				$header
			);

			if ( 1 === $draft_count ) {
				/* translators: %1$s: name of blog */
				$subject = sprintf( __( '[%1$s] You have an outstanding draft', 'unloved-drafts-reminder' ), get_bloginfo( 'name' ) );
			} else {
				/* translators: %1$s: name of blog, %2$s: number of drafts */
				$subject = sprintf( __( '[%1$s] You have %2$s outstanding drafts', 'unloved-drafts-reminder' ), get_bloginfo( 'name' ), $draft_count );
			}
			$body = $header . $message;

			$display_out       = '<p>' . esc_html__( 'To: ', 'unloved-drafts-reminder' ) . esc_html( $email_addy ) . '<br/>' . esc_html__( 'Subject: ', 'unloved-drafts-reminder' ) . esc_html( $subject ) . '<br/><br/>' . nl2br( esc_html( $body ) ) . '</p>';
			$output['emails'] .= $display_out;

			// If debugging, output to screen - otherwise, email the results.

			if ( $debug ) {
				return wp_kses(
					$display_out,
					array(
						'br' => array(),
						'p'  => array(),
					)
				);
			} else {
				// phpcs:ignore -- ignoring from PHPCS as this is only being used for a small number of mails
				$mail_rc = wp_mail( $email_addy, $subject, $body );
				if ( ! $mail_rc ) {
					++$errors;
				}
			}
		}
	}

	// Update the saved output for the last run.

	if ( ! $debug ) {
		$output['errors']    = $errors;
		$output['timestamp'] = time();
		update_option( 'unloved_drafts_reminder_output', $output );
	}
}

/**
 * Now shortcode
 *
 * Will generate and output the email content.
 */
function unloved_drafts_reminder_now_shortcode() {

	return unloved_drafts_reminder_process_posts( true );
}

add_shortcode( 'dc_now', 'unloved_drafts_reminder_now_shortcode' );


/**
 * Last run shortcode
 *
 * Outputs the results of the last run.
 */
function unloved_drafts_reminder_last_run_shortcode() {

	$output = get_option( 'unloved_drafts_reminder_output' );
	$debug  = '';

	if ( ! $output ) {
		$debug .= esc_html( __( 'Unloved Drafts Reminder has not yet run.', 'unloved-drafts-reminder' ) );
	} else {
		$timestamp = gmdate( 'l, F j, Y g:i:s a', $output['timestamp'] );
		if ( 0 === $output['errors'] ) {
			/* translators: %1$s: timestamp */
			$text = sprintf( __( 'Unloved Drafts Reminder last ran at %1$s, successfully.', 'unloved-drafts-reminder' ), esc_html( $timestamp ) );
		} else {
			/* translators: %1$s: timestamp %2$s: number of errors */
			$text = sprintf( __( 'Unloved Drafts Reminder last ran at %1$s, with %2$s errors.', 'unloved-drafts-reminder' ), esc_html( $timestamp ), esc_html( $output['errors'] ) );
		}
		$debug .= esc_html( $text ) . '<br/>';
		$debug .= wp_kses(
			$output['emails'],
			array(
				'br' => array(),
				'p'  => array(),
			)
		);
	}

	return $debug;
}

add_shortcode( 'dc_last_run', 'unloved_drafts_reminder_last_run_shortcode' );
