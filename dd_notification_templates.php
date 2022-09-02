<?php
/*
Plugin Name: Notification Templates
Plugin URI: http://
Description: A functionality within the system can use them as editable emails (subject and body). Click <a href="/wp-admin/admin.php?page=dd_notification_templates_about">here</a> to find out more.
Version: 1.0.1
Author: Stoycho Stoychev
Bitbucket Plugin URI:
--------------------------------------------------------------------------------
*/
defined('ABSPATH') || die('do not access this file directly');

class dd_notification_templates {

	// class instance
	private static $instance;
	public static $statuses = array(
		'active' => 'Active',
		'inactive' => 'InActive'
	);

	// class constructor
	public function __construct() {
		add_action('wp_loaded', array( $this, 'dd_notification_templates_wp_loaded'));
		add_filter('set-screen-option', function ($status, $option, $value) {
			return $value;
		}, 10, 3);
		
		if (is_admin()) {
			add_action('admin_notices', 'dd_notification_templates::admin_notices',99999999999);
			add_action('admin_menu',array($this, 'dd_notification_templates_admin_menu'),51);
			
			add_action("wp_ajax_import_notification_templates_ajax", array($this, 'import_notification_templates_ajax'));
			add_action("wp_ajax_nopriv_import_notification_templates_ajax", function () {
				die('Allowed only when logged in');
			});
		}
	}
	private static function check_compatibility_version() {
		global $wpdb;
		$plugin_data = get_plugin_data(__FILE__);
		$db_version = get_option(__CLASS__.'_db_version','0.99.99');
		$compatibility_version_to_be_updated = false;
		while (version_compare($db_version, $plugin_data['Version']) < 0) {
			$compatibility_version_to_be_updated = true;
			if ($db_version == '0.99.99') {
				$wpdb->query('CREATE TABLE '.__CLASS__.'_html_wrappers (
						`'.__CLASS__.'_html_wrapper_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
						`title` VARCHAR(255) NOT NULL,
						`header` TEXT NOT NULL,
						`footer` TEXT NOT NULL,
						UNIQUE KEY (`title`),
						PRIMARY KEY (`'.__CLASS__.'_html_wrapper_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
				$wpdb->query('CREATE TABLE '.__CLASS__.'_groups (
						`'.__CLASS__.'_group_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
						`title` VARCHAR(255) NOT NULL,
						UNIQUE KEY (`title`),
						PRIMARY KEY (`'.__CLASS__.'_group_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
				$wpdb->query('CREATE TABLE '.__CLASS__.' (
						`dd_notification_template_ref` VARCHAR(255) NOT NULL,
						`title` VARCHAR(255) NOT NULL,
						`subject` VARCHAR(255) NOT NULL,
						`body` TEXT NOT NULL,
						`description` TEXT DEFAULT NULL,
						`substitution` TEXT DEFAULT NULL,
						`last_imported` DATETIME DEFAULT NULL,
						`record_modified` DATETIME DEFAULT NULL,
						`status` VARCHAR(255) DEFAULT NULL,
						`is_html` TINYINT(1) UNSIGNED DEFAULT NULL,
						`'.__CLASS__.'_html_wrapper_id` INT UNSIGNED DEFAULT NULL,
						`'.__CLASS__.'_group_id` INT UNSIGNED DEFAULT NULL,
						UNIQUE KEY (`title`),
						PRIMARY KEY (`dd_notification_template_ref`),
						CONSTRAINT `'.__CLASS__.'_group_id2` FOREIGN KEY (`'.__CLASS__.'_group_id`) REFERENCES `'.__CLASS__.'_groups` ('.__CLASS__.'_group_id) ON DELETE SET NULL ON UPDATE CASCADE,
						CONSTRAINT `'.__CLASS__.'_html_wrapper_id2` FOREIGN KEY (`'.__CLASS__.'_html_wrapper_id`) REFERENCES `'.__CLASS__.'_html_wrappers` ('.__CLASS__.'_html_wrapper_id) ON DELETE SET NULL ON UPDATE CASCADE
					) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
			}
			$db_version=self::increment_version($db_version);
		}
		if ($compatibility_version_to_be_updated) update_option(__CLASS__.'_db_version', $plugin_data['Version']);
		return $compatibility_version_to_be_updated;
	}
	private static function increment_version($version) {
		$parts = explode('.', $version);
		if ($parts[2] + 1 < 99) {
			$parts[2]++;
		} else {
			$parts[2] = 0;
			if ($parts[1] + 1 < 99) {
				$parts[1]++;
			} else {
				$parts[1] = 0;
				$parts[0]++;
			}
		}
		return implode('.', $parts);
	}
	
	public function dd_notification_templates_wp_loaded() {
		if(is_admin()) {
			add_filter('plugin_row_meta',function ($links, $file) {
				if ($file == plugin_basename( __FILE__ )) {
					$links[] = '<a href="" target="_blank">Bitbucket</a>';
				}
				return $links;
			}, 10, 2);
			if (isset($_POST['submit_dd_notification_templates']) && $_POST['submit_dd_notification_templates'] == 'Save' && current_user_can('administrator')) {
				global $wpdb;
				$errors = array();
				$_POST = array_map('trim', stripslashes_deep($_POST));
				if (empty($_POST['title'])) {
					$errors[] = 'Title cannot be empty';
				} else if (isset($_POST['id'])) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_template_ref FROM dd_notification_templates WHERE title=%s AND dd_notification_template_ref!=%s',$_POST['title'],$_POST['id']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				}
				if (empty($_POST['subject'])) {
					$errors[] = 'Subject cannot be empty';
				}
				if (empty($_POST['body'])) {
					$errors[] = 'Body cannot be empty';
				}
				if (!$errors) {
					if (isset($_POST['id'])) {
						$dd_notification_templates_html_wrapper_id=!empty($_POST['dd_notification_templates_html_wrapper_id']) && is_numeric($_POST['dd_notification_templates_html_wrapper_id']) ? $_POST['dd_notification_templates_html_wrapper_id'] : 'NULL';
						$dd_notification_templates_group_id=!empty($_POST['dd_notification_templates_group_id']) && is_numeric($_POST['dd_notification_templates_group_id']) ? $_POST['dd_notification_templates_group_id'] : 'NULL';
						$sql = $wpdb->prepare('UPDATE dd_notification_templates SET title=%s,subject=%s,body=%s,description=%s,record_modified=NOW(),status=%s,is_html=%d,dd_notification_templates_html_wrapper_id='.$dd_notification_templates_html_wrapper_id.',dd_notification_templates_group_id='.$dd_notification_templates_group_id.' WHERE dd_notification_template_ref=%s',$_POST['title'],$_POST['subject'],$_POST['body'],$_POST['description'],$_POST['status'],isset($_POST['is_html']) ? $_POST['is_html'] : 0,$_POST['id']);
						$wpdb->query($sql);
						//in case we want to save extra stuff i.e. Custom Defines Macros
						do_action('dd_notification_templates_notif_template_saved');
					}
					wp_safe_redirect('/wp-admin/admin.php?page=dd_notification_templates&ddnt_msg='.urlencode('The notification template has been saved.'));exit;
				} else {
					//the variables are populated and passed in the function 'dd_notification_templates' where the form is
				}
			}
			if (isset($_POST['submit_dd_notification_templates_html_wrappers']) && $_POST['submit_dd_notification_templates_html_wrappers'] == 'Save' && current_user_can('administrator')) {
				global $wpdb;
				$errors = array();
				$_POST = array_map('trim', stripslashes_deep($_POST));
				if (empty($_POST['title'])) {
					$errors[] = 'Title cannot be empty';
				} else if (!(isset($_POST['id']) && $_POST['id']>0)) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_html_wrapper_id FROM dd_notification_templates_html_wrappers WHERE title=%s',$_POST['title']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				} else if (isset($_POST['id']) && $_POST['id']>0) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_html_wrapper_id FROM dd_notification_templates_html_wrappers WHERE title=%s AND dd_notification_templates_html_wrapper_id!=%d',$_POST['title'],$_POST['id']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				}
				if (empty($_POST['header'])) {
					$errors[] = 'Header cannot be empty';
				}
				if (empty($_POST['footer'])) {
					$errors[] = 'Footer cannot be empty';
				}
				if (!$errors) {
					if (isset($_POST['id']) && $_POST['id']>0) {
						$sql = $wpdb->prepare('UPDATE dd_notification_templates_html_wrappers SET title=%s,header=%s,footer=%s WHERE dd_notification_templates_html_wrapper_id=%d',$_POST['title'],$_POST['header'],$_POST['footer'],$_POST['id']);
					} else {
						$sql = $wpdb->prepare('INSERT INTO dd_notification_templates_html_wrappers (title,header,footer) VALUES (%s,%s,%s)',$_POST['title'],$_POST['header'],$_POST['footer']);
					}
					$wpdb->query($sql);
					wp_safe_redirect('/wp-admin/admin.php?page=dd_notification_templates_html_wrappers&ddnt_msg='.urlencode('The html wrapper has been saved.'));exit;
				} else {
					//the variables are populated and passed in the function 'dd_notification_templates_html_wrappers' where the form is
				}
			}
			if (isset($_POST['submit_dd_notification_templates_groups']) && $_POST['submit_dd_notification_templates_groups'] == 'Save' && current_user_can('administrator')) {
				global $wpdb;
				$errors = array();
				$_POST = array_map('trim', stripslashes_deep($_POST));
				if (empty($_POST['title'])) {
					$errors[] = 'Title cannot be empty';
				} else if (!(isset($_POST['id']) && $_POST['id']>0)) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_group_id FROM dd_notification_templates_groups WHERE title=%s',$_POST['title']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				} else if (isset($_POST['id']) && $_POST['id']>0) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_group_id FROM dd_notification_templates_groups WHERE title=%s AND dd_notification_templates_group_id!=%d',$_POST['title'],$_POST['id']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				}
				if (!$errors) {
					if (isset($_POST['id']) && $_POST['id']>0) {
						$sql = $wpdb->prepare('UPDATE dd_notification_templates_groups SET title=%s WHERE dd_notification_templates_group_id=%d',$_POST['title'],$_POST['id']);
					} else {
						$sql = $wpdb->prepare('INSERT INTO dd_notification_templates_groups (title) VALUES (%s)',$_POST['title']);
					}
					$wpdb->query($sql);
					wp_safe_redirect('/wp-admin/admin.php?page=dd_notification_templates_groups&ddnt_msg='.urlencode('The group has been saved.'));exit;
				} else {
					//the variables are populated and passed in the function 'dd_notification_templates_groups' where the form is
				}
			}
		}
	}
	
	public function dd_notification_templates_admin_menu() {
		$hook = add_menu_page('Notification Templates', 'Notification Templates', 'manage_options', 'dd_notification_templates',array($this, 'dd_notification_templates'),'dashicons-megaphone' , '6.55');
		$hook = add_submenu_page(
			'dd_notification_templates',
			'Notification Templates',
			'Notification Templates',
			'manage_options',
			'dd_notification_templates',
			array($this, 'dd_notification_templates')
		);
		add_action("load-$hook", array($this, 'screen_option'));
		$hook = add_submenu_page(
			'dd_notification_templates',
			'Notification Templates HTML Wrappers',
			'Notification Templates HTML Wrappers',
			'manage_options',
			'dd_notification_templates_html_wrappers',
			array($this, 'dd_notification_templates_html_wrappers')
		);
		add_action("load-$hook", array($this, 'screen_option'));
		$hook = add_submenu_page(
			'dd_notification_templates',
			'Notification Templates Groups',
			'Notification Templates Groups',
			'manage_options',
			'dd_notification_templates_groups',
			array($this, 'dd_notification_templates_groups')
		);
		add_action("load-$hook", array($this, 'screen_option'));
		$hook = add_submenu_page(
			'dd_notification_templates',
			'About Notification Templates',
			'About Notification Templates',
			'manage_options',
			'dd_notification_templates_about',
			array($this, 'dd_notification_templates_about')
		);
		add_action("load-$hook", array($this, 'screen_option'));
	}
	
	private static function import_notification_template_file($dir, &$inserted, &$updated, &$errors, &$warnings) {
		if ($dir != '.' && $dir != '..' && is_dir($dir)) {
			$templates_files = scandir($dir);
			if ($templates_files) {
				global $wpdb;
				foreach ($templates_files as $template_file) {
					if ($template_file != '.' && $template_file != '..' && substr($template_file,-3) == '.nf') {
						$xml = simplexml_load_file($dir.'/'.$template_file, null, LIBXML_NOERROR);
						libxml_use_internal_errors(true);
						if ($xml!==false) {
							$error='';
							$id = @trim((string)$xml['id']);
							$is_html = isset($xml['is_html']) ? $xml['is_html'] : 1;
							$dd_notification_templates_html_wrapper_id=null;
							if (!empty($xml['wrapper'])) $dd_notification_templates_html_wrapper_id=$wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_html_wrapper_id FROM dd_notification_templates_html_wrappers WHERE title=%s',trim($xml['wrapper'])));
							if (empty($dd_notification_templates_html_wrapper_id)) $dd_notification_templates_html_wrapper_id='NULL';
							
							if (empty($id)) $error.='No id param specifed.';
							if((string)$xml['id'] != (string)substr($template_file,0,-3)){
								if ($error!='') $error.=' ';
								$error.='File name '.$dir.'/'.$template_file.' did not match id param '.$xml['id'].'.';
							}
							if(!isset($xml->substitution_definition[0]->fields[0])){
								if ($error!='') $error.=' ';
								$error.='Substitution definition fields not found in file: '.$dir.'/'.$template_file.'.';
							}
							if ($error) {
								$errors[] = $error;
							} else {
								$title       = trim((string)$xml->title[0]);
								$subject     = trim((string)$xml->subject[0]);
								$body        = trim((string)$xml->body[0]);
								$description = trim((string)$xml->description[0]);
								$substitution = trim($xml->substitution_definition[0]->fields[0]->asXML());
								if (($row=$wpdb->get_row($wpdb->prepare('SELECT last_imported,record_modified FROM dd_notification_templates WHERE dd_notification_template_ref=%s',$id)))) {
									if ((!$row->last_imported && $row->record_modified) || ($row->last_imported && $row->record_modified && strtotime($row->record_modified) > strtotime($row->last_imported))) {
										$wpdb->update('dd_notification_templates', ['substitution'=>$substitution], ['dd_notification_template_ref'=>$id]);
										$warnings[] = 'File '.$dir.'/'.$template_file.' was not updated because it was modified after its last import. Please manually edit this notification template.';
									} else {
										$wpdb->query($wpdb->prepare('UPDATE dd_notification_templates SET title=%s,subject=%s,body=%s,description=%s,substitution=%s,last_imported=NOW(),is_html=%d,dd_notification_templates_html_wrapper_id='.$dd_notification_templates_html_wrapper_id.' WHERE dd_notification_template_ref=%s',$title,$subject,$body,$description,$substitution,$is_html,$id));
										$updated++;
									}
								} else {
									$wpdb->query($wpdb->prepare('INSERT INTO dd_notification_templates (dd_notification_template_ref,title,subject,body,description,substitution,last_imported,status,is_html,dd_notification_templates_html_wrapper_id) VALUES (%s,%s,%s,%s,%s,%s,NOW(),\'active\',%d,'.$dd_notification_templates_html_wrapper_id.')',$id,$title,$subject,$body,$description,$substitution,$is_html));
									$inserted++;
								}
							}
						} else {
							$errors[] = 'File '.$dir.'/'.$template_file.' could not be xml parsed.';
						}
					}
				}
			}
		}
	}
	public static function import_notification_templates() {
		//check all plugins for dd_notification_templates folder
		$plugins_dirs = scandir(ABSPATH.'wp-content/plugins');
		$inserted=0;
		$updated=0;
		$errors=array();
		$warnings=array();
		if ($plugins_dirs) {
			foreach ($plugins_dirs as $plugin_dir) {
				if ($plugin_dir == '.' || $plugin_dir == '..') continue;
				$plugin_dir_nt = ABSPATH.'wp-content/plugins/'.$plugin_dir.'/dd_notification_templates';
				self::import_notification_template_file($plugin_dir_nt, $inserted, $updated, $errors, $warnings);
			}
		}
		//check the custom notif templates dir - wp-content/uploads/dd_notification_templates
		self::import_notification_template_file(ABSPATH.'wp-content/uploads/dd_notification_templates', $inserted, $updated, $errors, $warnings);
		return [$inserted, $updated, $errors, $warnings];
	}
	public function import_notification_templates_ajax() {
		if (!isset($_POST['nonce']) || !wp_verify_nonce( $_POST['nonce'], '***')) {
			die("nonce not valid");
		}
		list($inserted, $updated, $errors, $warnings) = dd_notification_templates::import_notification_templates();
		die('DONE.<br>'.$inserted.' notification templates have been inserted.<br>'.$updated.' notification templates have been updated.'.($errors ? '<ul><li>Errors:</li><li>'.implode('</li><li>', $errors).'</li></ul>' : '').($warnings ? '<ul><li>Warnings:</li><li>'.implode('</li><li>', $warnings).'</li></ul>' : ''));
	}
	public function dd_notification_templates_about() {
		$nonce = wp_create_nonce("***");
		?>
<script type="text/javascript">
jQuery(function ($) {
	$('#import_notification_templates').click(function (e) {
		$('<div></div>').appendTo('body').html('<div>Confirm?</div>').dialog({
			modal: true,
			title: 'Confirm',
			zIndex: 10000,
			autoOpen: true,
			width: '70%',
			resizable: false,
			buttons: {
				Ok: function () {
					$('#import_notification_templates_spinner').show();
					$.ajax({
						type : 'post',
						dataType : 'text',
						url : '<?=admin_url('admin-ajax.php')?>',
						data : {action: "import_notification_templates_ajax", nonce:'<?=$nonce?>'},
						success: function(response) {
							$('#import_notification_templates_spinner').hide();
							$('<div></div>').appendTo('body').html('<div>'+response+'</div>').dialog({
								modal: true,
								title: 'Import Notification Templates',
								zIndex: 10000,
								autoOpen: true,
								width: '70%',
								resizable: false,
								buttons: {
									Ok: function () {
										$(this).dialog("close");
										$(this).dialog('destroy').remove();
									}
								},
								closeOnEscape: true,
							});
						}
					});
					$(this).dialog("close");
					$(this).dialog('destroy').remove();
				},
				Cancel: function () {
					$(this).dialog("close");
					$(this).dialog('destroy').remove();
				}
			},
			closeOnEscape: true,
		});
	});
});
</script>
<h2>About Notification Templates</h2>
<p>In order to use the Notification Templates you should create them as xml files .nf ext in your plugin inside folder your_plugin_name/dd_notification_templates See <a target="_blank" href="/wp-content/plugins/dd_notification_templates/plugin_name.notification_for_example_functionality.nf">the example</a> .nf file below. For theme or custom functionality place those inside wp-content/uploads/dd_notification_templates<br>
Once your .nf files are in place hit the "Import Notification Templates" button. You can then edit them under <a href="/wp-admin/admin.php?page=dd_notification_templates">Notification Templates</a> and use them in your functionality by calling dd_notification_templates::email($notif_ref, $to, $subs, $headers, $attachments).</p>
<p style="margin-top:20px;margin-bottom:20px;text-align:center;"><input type="button" name="import_notification_templates" id="import_notification_templates" value="Import Notification Templates" style="cursor:pointer;" /> <img src="/wp-admin/images/spinner.gif" width="20" height="20" align="absmiddle" style="display:none;" id="import_notification_templates_spinner" /></p>
		<?php
		$html = file_get_contents(__DIR__.'/plugin_name.notification_for_example_functionality.nf');
		echo nl2br(htmlentities($html));
	}
	public function dd_notification_templates_html_wrappers() {
		if (isset($_GET['action']) && $_GET['action'] == 'edit') {
			global $wpdb;
			$title='';$header='';$footer='';
			if (isset($_GET['id']) && $_GET['id']>0) {
				if (($row = $wpdb->get_row('SELECT title,header,footer FROM dd_notification_templates_html_wrappers WHERE dd_notification_templates_html_wrapper_id='.$_GET['id']))) {
					$title=$row->title;$header=$row->header;$footer=$row->footer;
				}
			}
			if (isset($_POST['submit_dd_notification_templates_html_wrappers']) && $_POST['submit_dd_notification_templates_html_wrappers'] == 'Save' && current_user_can('administrator')) {
				$errors = array();
				$_POST = array_map('trim', stripslashes_deep($_POST));
				if (empty($_POST['title'])) {
					$errors[] = 'Title cannot be empty';
				} else if (!(isset($_POST['id']) && $_POST['id']>0)) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_html_wrapper_id FROM dd_notification_templates_html_wrappers WHERE title=%s',$_POST['title']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				} else if (isset($_POST['id']) && $_POST['id']>0) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_html_wrapper_id FROM dd_notification_templates_html_wrappers WHERE title=%s AND dd_notification_templates_html_wrapper_id!=%d',$_POST['title'],$_POST['id']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				}
				if (empty($_POST['header'])) {
					$errors[] = 'Header cannot be empty';
				}
				if (empty($_POST['footer'])) {
					$errors[] = 'Footer cannot be empty';
				}
				if (!$errors) {
					//The Save is done in the function 'dd_notification_templates_wp_loaded' where the redirect happens
				} else {
					$title=$_POST['title'];$header=$_POST['header'];$footer=$_POST['footer'];
				}
			}
			if (!empty($errors)) {
			?>
			<div class="updated error">
				<p>Could not save.</p><ul><li><?=implode('</li><li>', $errors);?></li></ul><p></p>
			</div>
			<?php } ?>
			<script type="text/javascript">
				jQuery(function ($) {
					$('.preview_email_wrapper').click(function () {
						$('<div></div>').appendTo('body').html($('#header').val()+$('#footer').val()).dialog({
							title: 'Preview HTML Wrapper',
							resizable: false,
							width: '80%',
							height: '500',
							position: {my:'left',at:'left+160'},
							modal: true,
							buttons: {
							  Ok: function() {
								$(this).dialog("close");
								$(this).dialog('destroy').remove();
							  }
							}
						});
					});
				});
			</script>
			<h3><?=isset($_REQUEST['id']) && $_REQUEST['id']>0 ? 'Edit' : 'Add New'?> HTML Wrapper</h3>
			<form method="post" action="">
				<input type="hidden" name="id" value="<?=isset($_REQUEST['id']) ? $_REQUEST['id'] : ''?>" />
				<table style="width: 100%;margin-top:20px;">
					<tr>
						<td valign="top" style="width:100px;">Title <span style="color:red;">*</span></td>
						<td><input style="width:90%;" type="text" name="title" value="<?=esc_attr($title)?>" /></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Header <span style="color:red;">*</span></td>
						<td><textarea name="header" id="header" style="width:95%;" rows="9"><?=$header?></textarea></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Footer <span style="color:red;">*</span></td>
						<td><textarea name="footer" id="footer" style="width:95%;" rows="9"><?=$footer?></textarea></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">&nbsp;</td>
						<td><input type="button" name="preview" class="preview_email_wrapper" value="Preview Wrapper" style="margin-left: 0px;margin-top:10px;font-size:120%;cursor:pointer;" /> <input type="submit" name="submit_dd_notification_templates_html_wrappers" value="Save" style="margin-left: 0px;margin-top:10px;font-size:120%;cursor:pointer;" /></td>
					</tr>
				</table>
			</form>
		<?php
		} else {
			require_once dirname(__FILE__).'/classes_list/dd_notification_templates_html_wrappers_list.php';
			$html_wrappers_list = new dd_notification_templates_html_wrappers_list();
			?>
			<script type="text/javascript">
				jQuery(function ($) {
					$('.delete_html_wrapper').click(function (e) {
						e.preventDefault();
						var location = $(this).attr('href');
						$('<div></div>').appendTo('body').html('<div>Are you sure?</div>').dialog({
							modal: true,
							title: 'Confirm',
							zIndex: 10000,
							autoOpen: true,
							width: '70%',
							resizable: false,
							buttons: {
								Yes: function () {
									document.location=location;
									$(this).dialog("close");
									$(this).dialog('destroy').remove();
								},
								No: function () {
									$(this).dialog("close");
									$(this).dialog('destroy').remove();
								}
							},
							closeOnEscape: true,
						});
					});
				});
			</script>
			<div id="html_wrappers" style="margin-top:15px;">
				<h3>HTML Wrappers</h3>
				<a href="?page=<?=esc_attr($_REQUEST['page'])?>&action=edit" class="button">Add New</a>
				<form method="post" action="">
					<?php
					$html_wrappers_list->prepare_items();
					$html_wrappers_list->display(); ?>
				</form>
			</div>
		<?php
		}
	}
	public function dd_notification_templates_groups() {
		if (isset($_GET['action']) && $_GET['action'] == 'edit') {
			global $wpdb;
			$title='';
			if (isset($_GET['id']) && $_GET['id']>0) {
				if (($row = $wpdb->get_row('SELECT title FROM dd_notification_templates_groups WHERE dd_notification_templates_group_id='.$_GET['id']))) {
					$title=$row->title;
				}
			}
			if (isset($_POST['submit_dd_notification_templates_groups']) && $_POST['submit_dd_notification_templates_groups'] == 'Save' && current_user_can('administrator')) {
				$errors = array();
				$_POST = array_map('trim', stripslashes_deep($_POST));
				if (empty($_POST['title'])) {
					$errors[] = 'Title cannot be empty';
				} else if (!(isset($_POST['id']) && $_POST['id']>0)) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_group_id FROM dd_notification_templates_groups WHERE title=%s',$_POST['title']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				} else if (isset($_POST['id']) && $_POST['id']>0) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_templates_group_id FROM dd_notification_templates_groups WHERE title=%s AND dd_notification_templates_group_id!=%d',$_POST['title'],$_POST['id']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				}
				if (!$errors) {
					//The Save is done in the function 'dd_notification_templates_wp_loaded' where the redirect happens
				} else {
					$title=$_POST['title'];
				}
			}
			if (!empty($errors)) {
			?>
			<div class="updated error">
				<p>Could not save.</p><ul><li><?=implode('</li><li>', $errors);?></li></ul><p></p>
			</div>
			<?php } ?>
			<h3><?=isset($_REQUEST['id']) && $_REQUEST['id']>0 ? 'Edit' : 'Add New'?> Group</h3>
			<form method="post" action="">
				<input type="hidden" name="id" value="<?=isset($_REQUEST['id']) ? $_REQUEST['id'] : ''?>" />
				<table style="width: 100%;margin-top:20px;">
					<tr>
						<td valign="top" style="width:100px;">Title <span style="color:red;">*</span></td>
						<td><input style="width:90%;" type="text" name="title" value="<?=esc_attr($title)?>" /></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">&nbsp;</td>
						<td><input type="submit" name="submit_dd_notification_templates_groups" value="Save" style="margin-left: 0px;margin-top:10px;font-size:120%;cursor:pointer;" /></td>
					</tr>
				</table>
			</form>
		<?php
		} else {
			require_once dirname(__FILE__).'/classes_list/dd_notification_templates_groups_list.php';
			$groups_list = new dd_notification_templates_groups_list();
			?>
			<script type="text/javascript">
				jQuery(function ($) {
					$('.delete_gr').click(function (e) {
						e.preventDefault();
						var location = $(this).attr('href');
						$('<div></div>').appendTo('body').html('<div>Are you sure?</div>').dialog({
							modal: true,
							title: 'Confirm',
							zIndex: 10000,
							autoOpen: true,
							width: '70%',
							resizable: false,
							buttons: {
								Yes: function () {
									document.location=location;
									$(this).dialog("close");
									$(this).dialog('destroy').remove();
								},
								No: function () {
									$(this).dialog("close");
									$(this).dialog('destroy').remove();
								}
							},
							closeOnEscape: true,
						});
					});
				});
			</script>
			<div id="html_wrappers" style="margin-top:15px;">
				<h3>Notification templates groups</h3>
				<a href="?page=<?=esc_attr($_REQUEST['page'])?>&action=edit" class="button">Add New</a>
				<form method="post" action="">
					<?php
					$groups_list->prepare_items();
					$groups_list->display(); ?>
				</form>
			</div>
		<?php
		}
	}
	public function dd_notification_templates() {
		global $wpdb;
		if (isset($_GET['action']) && $_GET['action'] == 'edit') {
			$title='';$subject='';$body='';$description='';$substitution='';$last_imported='';$record_modified='';$status='';$is_html=1;$dd_notification_templates_html_wrapper_id='';$header='';$footer='';$dd_notification_templates_group_id='';
			$found=false;
			if (isset($_GET['id'])) {
				if (($row = $wpdb->get_row($wpdb->prepare('SELECT nt.title,nt.subject,nt.body,nt.description,nt.substitution,nt.last_imported,nt.record_modified,nt.status,nt.is_html,nt.dd_notification_templates_html_wrapper_id,nt.dd_notification_templates_group_id,wr.header,wr.footer FROM dd_notification_templates nt
					LEFT JOIN dd_notification_templates_html_wrappers wr USING(dd_notification_templates_html_wrapper_id)
					LEFT JOIN dd_notification_templates_groups gr USING(dd_notification_templates_group_id)
					WHERE nt.dd_notification_template_ref=%s',$_GET['id'])))) {
					$found=true;$title=$row->title;$subject=$row->subject;$body=$row->body;$description=$row->description;$substitution=$row->substitution;$last_imported=$row->last_imported;$record_modified=$row->record_modified;$status=$row->status;$is_html=$row->is_html;$dd_notification_templates_html_wrapper_id=$row->dd_notification_templates_html_wrapper_id;$dd_notification_templates_group_id=$row->dd_notification_templates_group_id;$header=$row->header;$footer=$row->footer;
				}
			}
			if (!$found) {die('<span style="color:red;">Notification Template not found.</span> Click <a href="/wp-admin/admin.php?page=dd_notification_templates">here</a>');}
			if (isset($_POST['submit_dd_notification_templates']) && $_POST['submit_dd_notification_templates'] == 'Save' && current_user_can('administrator')) {
				$errors = array();
				$_POST = array_map('trim', stripslashes_deep($_POST));
				if (empty($_POST['title'])) {
					$errors[] = 'Title cannot be empty';
				} else if (isset($_POST['id'])) {
					if ($wpdb->get_var($wpdb->prepare('SELECT dd_notification_template_ref FROM dd_notification_templates WHERE title=%s AND dd_notification_template_ref!=%s',$_POST['title'],$_POST['id']))) {
						$errors[] = 'Title "'.$_POST['title'].'" already exist.';
					}
				}
				if (empty($_POST['subject'])) {
					$errors[] = 'Subject cannot be empty';
				}
				if (empty($_POST['body'])) {
					$errors[] = 'Body cannot be empty';
				}
				if (!$errors) {
					//The Save is done in the function 'dd_notification_templates_wp_loaded' where the redirect happens
				} else {
					$title=$_POST['title'];$subject=$_POST['subject'];$body=$_POST['body'];$description=$_POST['description'];$status=$_POST['status'];$is_html=isset($_POST['is_html']) ? 1 : 0;$dd_notification_templates_html_wrapper_id=$_POST['dd_notification_templates_html_wrapper_id'];$dd_notification_templates_group_id=$_POST['dd_notification_templates_group_id'];
				}
			}
			if (!empty($errors)) {
			?>
			<div class="updated error">
				<p>Could not save.</p><ul><li><?=implode('</li><li>', $errors);?></li></ul>
			</div>
			<?php }
			$subs = self::getSubstitutionFields($substitution, isset($_REQUEST['id']) ? $_REQUEST['id'] : '');
			?>
			<style>div.macro {padding:7px;} .macro.correct {background-color: #76f598;} .macro.incorrect {background-color: #ff8080;}</style>
			<script type="text/javascript">
				jQuery(function ($) {
					$('.preview_email_body').click(function () {
						var thedelay=100;
						if (typeof tinymce.editors['body'] != 'object') {
							$('#body-tmce').click();
							thedelay=1500;
						}
						setTimeout(function() {
						var the_content = '<b>Legend:</b><div class="macro correct">A correctly entered macro.</div><div class="macro incorrect">Text that looks like a macro but does not match the predefined ones.</div><hr><?=$header ? str_replace("\r", '', str_replace("\n", '', $header)) : ''?>'+tinymce.editors['body'].getContent()+'<?=$footer ? str_replace("\r", '', str_replace("\n", '', $footer)) : ''?>';
						var macros = <?=json_encode($subs);?>;
						var regex,add_macro_correct_wrapping_span,macro_example_value,custom_macros_example_values=[];
						<?php
						//this will pass the var custom_macros_example_values - if custom macro example value has to be picked up from its dynamic content i.e. dropdown
						do_action('dd_notification_templates_preview_email_body_custom_macros_example_values_js', isset($_REQUEST['id']) ? $_REQUEST['id'] : ''); ?>
						for (var obj in macros) {
							regex = new RegExp('{'+obj+'}', 'gi');
							add_macro_correct_wrapping_span = obj.substr(obj.length-4)!=='_url'; //if the macro ends with _url do not add the wrapping span as it may be used in a link tag
							macro_example_value = obj in custom_macros_example_values ? custom_macros_example_values[obj] : macros[obj]['example'].replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
							the_content = the_content.replace(regex, (add_macro_correct_wrapping_span ? '<span class="macro correct">' : '')+macro_example_value+(add_macro_correct_wrapping_span ? '</span>' : ''));
						}
						//the remaining macros are incorrect
						var re = /(?:^|\W){(\w+)(?!\w)/g, match, matches = [];
						while (match = re.exec(the_content)) {
							matches.push(match[1]);
						}
						for (var i in matches) {
							regex = new RegExp('{'+matches[i]+'}', 'gi');
							the_content = the_content.replace(regex, '<span class="macro incorrect">{'+matches[i]+'}</span>');
						}
						$('<div></div>').appendTo('body').html(the_content).dialog({
							title: 'Preview',
							resizable: false,
//							autoOpen: false,
							width: '80%',
							height: '500',
							position: {my:'left',at:'left+160'},
							modal: true,
							buttons: {
							  Ok: function() {
								$(this).dialog("close");
								$(this).dialog('destroy').remove();
							  }
							}
						});
						},thedelay);
					});
				});
			</script>
			<h3><?=isset($_REQUEST['id']) ? 'Edit' : 'Add New'?> Notification Template</h3>
			<form method="post" action="">
				<input type="hidden" name="id" value="<?=isset($_REQUEST['id']) ? esc_attr($_REQUEST['id']) : ''?>" />
				<table style="width: 100%;margin-top:20px;" cellpadding="10">
					<tr>
						<td valign="top" style="width:100px;">System reference</td>
						<td><?=esc_attr($_REQUEST['id'])?></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Title <span style="color:red;">*</span></td>
						<td><input style="width:90%;" type="text" name="title" value="<?=esc_attr($title)?>" /></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Subject <span style="color:red;">*</span></td>
						<td><input style="width:90%;" type="text" name="subject" value="<?=esc_attr($subject)?>" /></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Body <span style="color:red;">*</span></td>
						<td><?php
						wp_editor($body, 'body', array(
							'_content_editor_dfw' => false,
							'drag_drop_upload' => false,
							'media_buttons'    => false,
							'tabfocus_elements' => 'content-html,save-post',
							'editor_height' => 150,
							'textarea_name'    => 'body',
							'teeny'            => true,
							'tinymce' => array(
								'resize' => false,
								'wp_autoresize_on' => false,
								'add_unload_trigger' => false,
								'plugins'            => 'textcolor,wplink,hr,charmap,wordpress,wpautoresize',
								'toolbar1'			 => 'bold,italic,underline,blockquote,strikethrough,bullist,numlist,alignleft,aligncenter,alignright,alignjustify,outdent,indent,removeformat,link,unlink,undo,redo,formatselect,forecolor,backcolor,hr,charmap,wp_help',
							),
						));
						//dynamic custom macros shows up here
						do_action('dd_notification_templates_define_custom_macros_html_js', isset($_REQUEST['id']) ? $_REQUEST['id'] : '');
						?>
						<br>
						<strong>Available Macros:</strong>
						<?=self::get_avail_macros_html(null, null, $subs);?>
						<input type="button" name="preview" class="preview_email_body" value="Preview" style="margin-left: 0px;margin-top:10px;font-size:120%;cursor:pointer;" />
						</td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Description</td>
						<td><textarea style="width:90%;" rows="5" type="text" name="description"><?=esc_attr($description)?></textarea></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Last Imported</td>
						<td><?=!empty($last_imported) ? date('d/m/Y H:i',strtotime($last_imported)) : '';?></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Record Modified</td>
						<td><?=!empty($record_modified) ? date('d/m/Y H:i',strtotime($record_modified)) : '';?></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Status</td>
						<td><select name="status">
							<?php
							foreach (self::$statuses as $option=>$label) : ?>
							<option value="<?=$option?>"<?=$option==$status?' selected':''?>><?=$label?></option>
							<?php endforeach; ?>
							</select> <i>Changing the status to InActive will cause this Notification template to stop sending emails</i></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Is HTML</td>
						<td><input type="checkbox" name="is_html" value="1"<?=!empty($is_html) ? ' checked' : '';?> /></td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">HTML Wrapper</td>
						<td><select name="dd_notification_templates_html_wrapper_id" style="width:100%;">
							<option value="">-- Please Select --</option>
							<?php
							$results = $wpdb->get_results('SELECT dd_notification_templates_html_wrapper_id,title FROM dd_notification_templates_html_wrappers ORDER BY title');
							foreach ($results as $result) { ?>
							<option value="<?=$result->dd_notification_templates_html_wrapper_id?>"<?=$result->dd_notification_templates_html_wrapper_id==$dd_notification_templates_html_wrapper_id ? ' selected' : ''?>><?=$result->title?></option>
							<?php } ?>
							</select>
						</td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">Group</td>
						<td><select name="dd_notification_templates_group_id" style="width:100%;">
							<option value="">-- Please Select --</option>
							<?php
							$results = $wpdb->get_results('SELECT dd_notification_templates_group_id,title FROM dd_notification_templates_groups ORDER BY title');
							foreach ($results as $result) { ?>
							<option value="<?=$result->dd_notification_templates_group_id?>"<?=$result->dd_notification_templates_group_id==$dd_notification_templates_group_id ? ' selected' : ''?>><?=$result->title?></option>
							<?php } ?>
							</select>
						</td>
					</tr>
					<tr>
						<td valign="top" style="width:100px;">&nbsp;</td>
						<td> <input type="submit" name="submit_dd_notification_templates" value="Save" style="margin-left: 0px;margin-top:10px;font-size:120%;cursor:pointer;" /></td>
					</tr>
				</table>
			</form>
		<?php
		} else {
			require_once dirname(__FILE__).'/classes_list/dd_notification_templates_list.php';
			$dd_notification_templates_list = new dd_notification_templates_list();
			?>
			<script type="text/javascript" src="/wp-content/plugins/dd_notification_templates/assets/jquery.asmselect.js"></script>
			<link rel="stylesheet" type="text/css" href="/wp-content/plugins/dd_notification_templates/assets/css/jquery.asmselect.css" />
			<script type="text/javascript">
				jQuery(function ($) {
					$("select[multiple]").each(function( index ) {
						if (!$(this).attr('title')) {
							$(this).attr('title', '-- Please Select --')
						}
					});
					$("select[multiple]").asmSelect({
						animate: true,
						sortable: false
					});
					function toggleFilters() {
						$('.dd_notification_templates_filters_wrapper').slideToggle(400,function () {
							$('.dd_notification_templates_filters_title h4').css('background-position-y',$(this).is(':visible') ? '49%' : '5%');
						});
					}
					<?php if (isset($_REQUEST['ddnt_filter'])) : ?>
					toggleFilters();
					<?php endif; ?>
					$('.dd_notification_templates_filters_title').click(function (e) {
						toggleFilters();
					});
					$('.delete_notif_template').click(function (e) {
						e.preventDefault();
						var location = $(this).attr('href');
						$('<div></div>').appendTo('body').html('<div>Are you sure?</div>').dialog({
							modal: true,
							title: 'Confirm',
							zIndex: 10000,
							autoOpen: true,
							width: '70%',
							resizable: false,
							buttons: {
								Yes: function () {
									document.location=location;
									$(this).dialog("close");
									$(this).dialog('destroy').remove();
								},
								No: function () {
									$(this).dialog("close");
									$(this).dialog('destroy').remove();
								}
							},
							closeOnEscape: true,
						});
					});
				});
			</script>
			<style>
				.wp-list-table .manage-column.column-substitution {width: 17%;}
				.wp-list-table .manage-column.column-is_html, .wp-list-table .manage-column.column-status {width:56px;}
				.dd_notification_templates_filters_title {background-color: #EAEAEA;padding: 10px;border: solid 1px #CCC;border-radius:4px;cursor:pointer;}
				.dd_notification_templates_filters_wrapper {background-color: #EAEAEA;border:solid 1px #CCC;padding:0px 15px 15px 15px;display: none;overflow:auto;}
				.dd_notification_templates_filters_wrapper input, .dd_notification_templates_filters_wrapper select {border:solid 1px #BBB;margin: 0px;}
				.dd_notification_templates_filters_wrapper input[type="text"], .dd_notification_templates_filters_wrapper select {width:100%;}
				.dd_notification_templates_filters_wrapper select {max-width: 190px;}
				.dd_notification_templates_filters_title h4 {font-size: 1.2em;margin: 0px;padding-left:19px;background-image: url(/wp-includes/images/toggle-arrow.png);background-repeat: no-repeat;background-position-y:5%;}
				.dd_notification_templates_filters_input {float: left;margin-right:15px;margin-top:15px;width:190px;}
				.dd_notification_templates_filters_input.submit {float:none;clear: both;margin: 0px;padding: 0px;}
				.asmListItemLabel {width:131px;overflow: hidden;}
			</style>
			<div id="html_wrappers" style="margin-top:15px;">
				<h3>Notification Templates</h3>
				<div class="dd_notification_templates_filters_title"><h4>Filters</h4></div>
				<div class="dd_notification_templates_filters_wrapper">
					<form action="" method="get">
					<input type="hidden" name="page" value="dd_notification_templates" />
					<div class="dd_notification_templates_filters_input">System Reference:<br><input type="text" name="ddnt_filter[dd_notification_template_ref]" value="<?=isset($_REQUEST['ddnt_filter']['dd_notification_template_ref']) ? esc_attr(stripslashes($_REQUEST['ddnt_filter']['dd_notification_template_ref'])) : ''?>" /></div>
					<div class="dd_notification_templates_filters_input">Title:<br><input type="text" name="ddnt_filter[title]" value="<?=isset($_REQUEST['ddnt_filter']['title']) ? esc_attr(stripslashes($_REQUEST['ddnt_filter']['title'])) : ''?>" /></div>
					<div class="dd_notification_templates_filters_input">Subject:<br><input type="text" name="ddnt_filter[subject]" value="<?=isset($_REQUEST['ddnt_filter']['subject']) ? esc_attr(stripslashes($_REQUEST['ddnt_filter']['subject'])) : ''?>" /></div>
					<div class="dd_notification_templates_filters_input">Body:<br><input type="text" name="ddnt_filter[body]" value="<?=isset($_REQUEST['ddnt_filter']['body']) ? esc_attr(stripslashes($_REQUEST['ddnt_filter']['body'])) : ''?>" /></div>
					<div class="dd_notification_templates_filters_input">Description:<br><input type="text" name="ddnt_filter[description]" value="<?=isset($_REQUEST['ddnt_filter']['description']) ? esc_attr(stripslashes($_REQUEST['ddnt_filter']['description'])) : ''?>" /></div>
					<div class="dd_notification_templates_filters_input">Status:<br><select name="ddnt_filter[status]">
							<?php
							foreach (array(''=>'-- Please Select --')+self::$statuses as $option=>$label) : ?>
							<option value="<?=$option?>"<?=isset($_REQUEST['ddnt_filter']['status']) && $_REQUEST['ddnt_filter']['status']==$option ? ' selected' : ''?>><?=$label?></option>
							<?php endforeach; ?>
							</select></div>
					<div class="dd_notification_templates_filters_input">HTML Wrapper(s):<br><select multiple="multiple" name="ddnt_filter[dd_notification_templates_html_wrapper_id][]">
							<?php
							$results = $wpdb->get_results('SELECT dd_notification_templates_html_wrapper_id,title FROM dd_notification_templates_html_wrappers ORDER BY title');
							foreach ($results as $result) { ?>
							<option value="<?=$result->dd_notification_templates_html_wrapper_id?>"<?=isset($_REQUEST['ddnt_filter']['dd_notification_templates_html_wrapper_id']) && is_array($_REQUEST['ddnt_filter']['dd_notification_templates_html_wrapper_id']) && in_array($result->dd_notification_templates_html_wrapper_id, $_REQUEST['ddnt_filter']['dd_notification_templates_html_wrapper_id']) ? ' selected' : ''?>><?=$result->title?></option>
							<?php } ?>
							</select></div>
					<div class="dd_notification_templates_filters_input">Group(s):<br><select multiple="multiple" name="ddnt_filter[dd_notification_templates_group_id][]">
							<?php
							$results = $wpdb->get_results('SELECT dd_notification_templates_group_id,title FROM dd_notification_templates_groups ORDER BY title');
							foreach ($results as $result) { ?>
							<option value="<?=$result->dd_notification_templates_group_id?>"<?=isset($_REQUEST['ddnt_filter']['dd_notification_templates_group_id']) && is_array($_REQUEST['ddnt_filter']['dd_notification_templates_group_id']) && in_array($result->dd_notification_templates_group_id, $_REQUEST['ddnt_filter']['dd_notification_templates_group_id']) ? ' selected' : ''?>><?=$result->title?></option>
							<?php } ?>
							</select></div>
					<div class="dd_notification_templates_filters_input submit">
						<input type="submit" name="submit" value="Submit" style="margin-left: 0px;margin-top:10px;font-size:120%;cursor:pointer;" /> &nbsp;&nbsp;<a href="/wp-admin/admin.php?page=dd_notification_templates">Clear</a>
					</div>
					</form>
				</div><br>
				<form method="post" action="">
					<?php
					$dd_notification_templates_list->prepare_items();
					$dd_notification_templates_list->display(); ?>
				</form>
			</div>
		<?php
		}
	}
	public static function getSubstitutionFields($substitution=null, $notif_template_ref=null) {
		if (!$substitution && !$notif_template_ref) {
			throw new Exception('At least one param must be provided in getSubstitutionFields() to get macros from');
		}
		$html='';
		if (!$substitution && $notif_template_ref) {
			global $wpdb;
			if (!($substitution=$wpdb->get_var($wpdb->prepare('SELECT nt.substitution FROM dd_notification_templates nt WHERE nt.dd_notification_template_ref=%s AND nt.status=%s',$notif_template_ref,'active')))) {
				throw new Exception('Notification template "'.$notif_template_ref.'" in getSubstitutionFields() Not Found or is not active');
			}
		}
		$subs = array();
		if(!empty($substitution)) {
			$xml = simplexml_load_string($substitution);
			foreach($xml->field as $id=>$field){
				if (!isset($field['name'])) {
					throw new Exception('Attribute "name" not specified for the ' . $id . ' field');
				}
				$name = (string) $field['name'];
				if(isset($subs[$name])){
					throw new Exception('Field with name ' . $name . ' defined more than once');
				}else{
					$subs[$name]=array();
				}
				foreach ($field->children() as $detail) {
					$subs[$name][(string)$detail->getName()] = trim((string)$detail);
				}
				$subs[$name]['required'] = isset($field['required']) && strcasecmp((string)$field['required'], 'required')===0;
			}
		}
		$subs = apply_filters('dd_notification_templates_extra_subs', $subs, $notif_template_ref);
		return $subs;
	}
	
	/**
	 * Sends an email using notification template
	 * @param string $dd_notification_template_ref
	 * @param array $vars - assoc array macro_name=>value_to_use
	 * @param string $to - the email address(es) to send it to
	 * @param string $headers
	 * @param array $attachments
	 * @return bool
	 */
	public static function email($dd_notification_template_ref, $vars, $to, $headers = '', $attachments = array()) {
		global $wpdb;
		if (!($row=$wpdb->get_row($wpdb->prepare('SELECT nt.subject, nt.body, nt.substitution, nt.status, nt.is_html, wr.header, wr.footer FROM dd_notification_templates nt LEFT JOIN dd_notification_templates_html_wrappers wr USING(dd_notification_templates_html_wrapper_id) WHERE nt.dd_notification_template_ref=%s',$dd_notification_template_ref)))) {
			throw new Exception('Notification template "' . $dd_notification_template_ref . '" not found');
		}
		if ($row->status != 'active') {
			return;
		}
		$subject=$row->subject ? trim($row->subject) : '';$body=$row->body ? trim($row->body) : '';
		//if not both subject and body are provided do not send
		if (empty($subject) || empty($body)) return;
		self::replace_macros_in_template_content($subject, $row->substitution, $vars, $row->is_html, $dd_notification_template_ref);
		self::replace_macros_in_template_content($body, $row->substitution, $vars, $row->is_html, $dd_notification_template_ref);
		$body = apply_filters('dd_notification_templates_pre_send_email', $body, $to, $subject, $dd_notification_template_ref, $row);
		if ($row->header && $row->footer) {
			$body = $row->header.$body.$row->footer;
		}
		add_filter('wp_mail_content_type',function() use($row) {return 'text/'.($row->is_html?'html':'plain');});
		return wp_mail($to, $subject, $body, $headers, $attachments);
	}
	public static function replace_macros_in_template_content(&$content, $substitutions, $vars, $is_html=1, $notif_template_ref=null) {
		$subs = self::getSubstitutionFields($substitutions, $notif_template_ref);
		$replace = array();
		foreach($subs as $name => $details) {
			//var to replace with
			$find = '{'.$name.'}';
			//Look for a the value
			if (isset($vars[$name])) {
				$value = $vars[$name];
				if (is_bool($value)) {
					$replace[$find] = $value?'true':'false';
				} else if (is_string($value) && $is_html) {
					$replace[$find] = nl2br($value);
				} else {
					$replace[$find] = $value;
				}
			//Posible to set a default if not set
			} elseif (isset($details['default'])) {
				$replace[$find] = $details['default'];
			//Not default and no vaule set the default valuer
			} else {
				$replace[$find] = '';
			}
		}
		if ($is_html) $content = nl2br($content);
		$content = str_replace(array_keys($replace), array_values($replace), $content);
	}
	
	public static function get_avail_macros_html($notif_template_ref=null, $substitutions=null, $subs=null) {
		if (!$notif_template_ref && !$substitutions && !$subs) {
			throw new Exception('At least one param must be provided in get_avail_macros_html() to get macros html from');
		}
		global $wpdb;
		$html='';
		if ($notif_template_ref) {
			if (!($substitutions=$wpdb->get_var($wpdb->prepare('SELECT nt.substitution FROM dd_notification_templates nt WHERE nt.dd_notification_template_ref=%s AND nt.status=%s',$notif_template_ref,'active')))) {
				$html='Notification template "'.$notif_template_ref.'" Not Found or is not active';
			}
		}
		if ($substitutions) {
			$subs = self::getSubstitutionFields($substitutions, $notif_template_ref);
		}
		if ($subs) {
			$html='<table cellpadding="6" cellspacing="0" style=""><tr><th style="text-align:left;vertical-align:top;border:solid 1px #DDD;">Title</th><th style="text-align:left;vertical-align:top;border:solid 1px #DDD;">Macro</th></tr>';
			foreach($subs as $name => $details) {
				$html.='<tr><td style="text-align:left;vertical-align:top;border:solid 1px #DDD;">'.$details['title'].($details['required'] ? ' <span style="color:red;">*</span>' : '').'</td><td style="text-align:left;vertical-align:top;border:solid 1px #DDD;">{'.$name.'}<br><i>'.nl2br($details['example']).'</i></td></tr>';
			}
			$html.='</table>';
		}
		return $html;
	}
	
	public static function admin_notices() {
		if (isset($_GET['ddnt_msg'])) { ?>
			<div class="updated <?=isset($_GET['is_error']) ? 'error' : 'notice'?>">
				<p><?=$_GET['ddnt_msg']?></p>
			</div>
		<?php
		}
		if (self::check_compatibility_version()) {
			print('<div class="notice updated"><p style="font-size:22px;">DD Notif Templates db version has been updated.</p></div>');
		}
	}
	public function screen_option() {
		$option = 'dd_notification_templates';
		$args = array(
			'label'   => 'Notification Templates',
			'default' => 10,
			'option'  => $option
		);
		add_screen_option($option, $args);
	}
	
	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}


add_action('plugins_loaded', function () {
	dd_notification_templates::get_instance();
});