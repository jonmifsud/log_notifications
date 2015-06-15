# Log Notifications

Sends an email to the desegnated addresses with error messages found within the Symphony logs since the last run. Log notifications supports both usual cron as well as poor man's cron and frequency settings can be set through the config.

# Cron Setup

Set up a cron tab at your desires frequency on `log_notification_cron.php` found within the extension. The cron does not require running through HTTP but can be run directly through cli.
Kindly note that the configuration has to be properly set for emails to go out, and if a cron is set to run every minute, the frequency setting within the config can be used to adjust the sending frequency without editing the cron settings.

# Config Options

	'log_notifications' => array(
		'frequency' => '1 minute', // supports strtotime phrases
		'poor_mans_cron' => 'yes', // if set to no emails are only sent if a cron is set up
		'include_notice' => 'no', // if you would like to include notices within the email
		'recipients' => '{your_admin_email}', // the person(s) who will recieve the email (comma separated)
		'subject' => '{email_subject}', // email subject so that you can filter/identify error messages
		'reply_to_name' => '{reply_to_name}', // could be used to reply to the dev team / client
		'reply_to_email' => '{reply_to_email}', // could be used to reply to the dev team / client
	),