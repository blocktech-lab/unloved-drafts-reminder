<?php
/**
 * Uninstaller
 *
 * Uninstall the plugin by removing any options from the database
 *
 * @package unloved-drafts-reminder
 */

// If the uninstall was not called by WordPress, exit.

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Delete options.

delete_option( 'unloved_drafts_reminder_age' );
delete_option( 'unloved_drafts_reminder_output' );
delete_option( 'unloved_drafts_reminder_prev_time' );
delete_option( 'unloved_drafts_reminder_since' );
delete_option( 'unloved_drafts_reminder_time' );
delete_option( 'unloved_drafts_reminder_what' );
delete_option( 'unloved_drafts_reminder_when' );

// Remove scheduled event.

wp_clear_scheduled_hook( 'unloved_drafts_reminder_mailer' );
