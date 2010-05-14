<?php
/*
Plugin Name: WP-Cron Dashboard
Plugin URI: http://wppluginsj.sourceforge.jp/i18n-ja_jp/wp-cron-dashboard/
Description: WP-Cron Dashboard Display for Wordpress
Author: wokamoto
Version: 1.1.0
Author URI: http://dogmap.jp/
Text Domain: wp-cron-dashboard
Domain Path: /languages/

 Based on http://blog.slaven.net.au/archives/2007/02/01/timing-is-everything-scheduling-in-wordpress/

License:
 Released under the GPL license
  http://www.gnu.org/copyleft/gpl.html

  Copyright 2007-2010 wokamoto (email : wokamoto1973@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

class CronDashboard {
	var $plugin_dir, $plugin_file;
	var $textdomain_name = 'wp-cron-dashboard';

	/*
	* Constructor
	*/
	function CronDashboard() {
		$this->__construct();
	}
	function __construct() {
		$this->_set_plugin_dir(__FILE__);
		$this->_load_textdomain();
	}

	// set plugin dir
	function _set_plugin_dir( $file = '' ) {
		$file_path = ( !empty($file) ? $file : __FILE__);
		$filename = explode("/", $file_path);
		if(count($filename) <= 1) $filename = explode("\\", $file_path);
		$this->plugin_dir  = $filename[count($filename) - 2];
		$this->plugin_file = $filename[count($filename) - 1];
		unset($filename);
	}

	// load textdomain
	function _load_textdomain( $sub_dir = 'languages' ) {
		global $wp_version;
		$plugins_dir = trailingslashit(defined('PLUGINDIR') ? PLUGINDIR : 'wp-content/plugins');
		$abs_plugin_dir = $this->_wp_plugin_dir($this->plugin_dir);
		$sub_dir = ( !empty($sub_dir)
			? preg_replace('/^\//', '', $sub_dir)
			: (file_exists($abs_plugin_dir.'languages') ? 'languages' : (file_exists($abs_plugin_dir.'language') ? 'language' : (file_exists($abs_plugin_dir.'lang') ? 'lang' : '')))
			);
		$textdomain_dir = trailingslashit(trailingslashit($this->plugin_dir) . $sub_dir);

		if ( version_compare($wp_version, '2.6', '>=') && defined('WP_PLUGIN_DIR') )
			load_plugin_textdomain($this->textdomain_name, false, $textdomain_dir);
		else
			load_plugin_textdomain($this->textdomain_name, $plugins_dir . $textdomain_dir);
	}

	// WP_CONTENT_DIR
	function _wp_content_dir($path = '') {
		return trailingslashit( trailingslashit( defined('WP_CONTENT_DIR')
			? WP_CONTENT_DIR
			: trailingslashit(ABSPATH) . 'wp-content'
			) . preg_replace('/^\//', '', $path) );
	}

	// WP_PLUGIN_DIR
	function _wp_plugin_dir($path = '') {
		return trailingslashit($this->_wp_content_dir('plugins/' . preg_replace('/^\//', '', $path)));
	}

	function add_admin_menu($s) {
		global $wp_version;

		// User Level Permission
		//  -- Subscriber = 0,Contributor = 1,Author = 2,Editor= 7,Administrator = 9
		$user_level = 9;

		add_submenu_page(
			version_compare($wp_version, "2.7", ">=") ? 'tools.php' : 'edit.php',
			'wp-cron' ,
			__('WP-Cron', $this->textdomain_name) ,
			$user_level ,
			__FILE__ ,
			array(&$this, 'wp_cron_menu')
		);
		return $s;
	}

	function wp_cron_menu() {
		global $wp_filter;

		$note = '';
		$out = '';
		$datetime_format = get_option("date_format")." @".get_option("time_format");

		if (isset($_POST['submit'])) {
			wp_unschedule_event($_POST['time'], $_POST['procname']);

			// Note snuff
			$note .= '<div id="message" class="updated fade"><p>';
			$note .= __('Sucessfully unscheduled',$this->textdomain_name)." ".$_POST['procname']." (".date($datetime_format,$_POST['time']).")";
			$note .= '</p></div>'."\n";
		}

		$out .= '<div class="wrap">'."\n";
		$out .= '<h2>'.__('Overview of tasks scheduled for WP-Cron',$this->textdomain_name).'</h2>'."\n";

		$out .= $this->show_cron_schedules($datetime_format);
		$out .= '<br/>'."\n";

		$out .= __('Current date/time is',$this->textdomain_name).": <strong>".date($datetime_format)."</strong>\n";
		$out .= "</div>";

		// Output
		echo $note.$out."\n";
	}

	function _get_cron_array() {
		if ( function_exists('_get_cron_array') ) {
			return _get_cron_array();
		} else {
			$cron = get_option('cron');
			return ( is_array($cron) ? $cron : false );
		}
	}

	function show_cron_schedules($datetime_format = '') {
		if ($datetime_format == '')
			$datetime_format = get_option("date_format")." @".get_option("time_format");

		$ans = '';
		$timeslots = $this->_get_cron_array();
		if ( empty($timeslots) ) {
			$ans .= '<div style="margin:.5em 0;width:100%;">';
			$ans .= __('Nothing scheduled',$this->textdomain_name);
			$ans .= '</div>'."\n";
		} else {
			$count = 1;
			foreach ( $timeslots as $time => $tasks ) {
				$ans .= '<div style="margin:.5em 0;width:100%;">';
				$ans .= sprintf(
					__('Anytime after <strong>%s</strong> execute tasks',$this->textdomain_name) ,
					date($datetime_format, $time)
					);
				$ans .= '</div>'."\n";
				foreach ($tasks as $procname => $task) {
					$ans .= '<div id="tasks-'.$count.'" style="margin:.5em;width:70%;">'."\n";

					$ans .= __('Entry #',$this->textdomain_name).$count.': '.$procname."\n";
					// Add in delete button for each entry.
					$ans .= '<form method="post">'."\n";
					$ans .= '<input type="hidden" name="procname" value="'.$procname.'"/>'."\n";
					$ans .= '<input type="hidden" name="time" value="'.$time.'"/>'."\n";
					$ans .= '<input name="submit" style="float:right; margin-top: -20px;" type="submit" value="'.__('Delete',$this->textdomain_name).'"/>'."\n";
					$ans .= '</form>'."\n";

					$ans .= "</div>\n";
					$count++;
				}
			}
			unset($timeslots);
		}
		return $ans;
	}
}

$crondashboard = new CronDashboard;
add_action('admin_menu', array($crondashboard, 'add_admin_menu'));
unset($crondashboard);
?>