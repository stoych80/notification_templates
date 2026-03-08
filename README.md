# "Notification Templates" plugin

A functionality within the system can use them as editable emails (subject and body). In order to use Notification Templates initially you must create them as xml files (.nf ext) in your plugin inside a folder - your_plugin_name/notification_templates. For a theme or custom functionality place those inside wp-content/uploads/notification_templates. Macros support.

Once your .nf files are in place hit the "Import Notification Templates" button located in the Notification Templates plugin admin area settings page. You can then edit them under a Notification Templates admin area page and use them in your functionality by calling notification_templates::email($notif_ref, $to, $subs, $headers, $attachments).

Automatic database compatibility management based on the plugin version - if the plugin version is incremented - on update of the plugin in the WordPress admin area - it checks for a corresponding database upgrade script(s) to be run.
