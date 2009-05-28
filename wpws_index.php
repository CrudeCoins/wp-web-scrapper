<?php
/*
Plugin Name: WP Web Scrapper
Plugin URI: http://webdlabs.com/projects/wp-web-scraper/
Description: An easy to implement web scraper for WordPress. Display realtime data from any websites directly into your posts, pages or sidebar.
Author: Akshay Raje
Version: 0.2
Author URI: http://webdlabs.com

*/

if(get_option('wpws_sc_posts') == 1) add_shortcode('wpws', 'wpws_shortcode');
if(get_option('wpws_sc_sidebar') == 1) add_filter('widget_text', 'do_shortcode');
add_action('admin_menu', 'wpws_settings_page');
register_activation_hook( __FILE__, 'wpws_on_activation');

function wpws_getDirectorySize($path) {
	$totalsize = 0;
	$totalcount = 0;
	$dircount = 0;
	if ($handle = opendir ($path)) {
		while (false !== ($file = readdir($handle))) {
			$nextpath = $path . '/' . $file;
			if ($file != '.' && $file != '..' && !is_link ($nextpath)) {
				if (is_dir ($nextpath)) {
					$dircount++;
					$result = wpws_getDirectorySize($nextpath);
					$totalsize += $result['size'];
					$totalcount += $result['count'];
					$dircount += $result['dircount'];
				}
				elseif (is_file ($nextpath)) {
					$totalsize += filesize ($nextpath);
					$totalcount++;
				}
			}
		}
	}
	closedir ($handle);
	$total['size'] = $totalsize;
	$total['count'] = $totalcount;
	$total['dircount'] = $dircount;
	return $total;
}

function wpws_sizeFormat($size) {
	if($size<1024)	return $size.__(" bytes");
	else if($size<(1024*1024)) {
		$size=round($size/1024,1);
		return $size.__(" KB");
	}
	else if($size<(1024*1024*1024)) {
		$size=round($size/(1024*1024),1);
		return $size.__(" MB");
	}
	else {
		$size=round($size/(1024*1024*1024),1);
		return $size.__(" GB");
	}
}

function wpws_curl($url, $agent, $timeout, $return = true) {
	$ch = curl_init();
	if (!$ch) {
		if (function_exists('file_get_contents')) {
			ini_set('default_socket_timeout', $timeout * 60);
			$html = file_get_contents($url);
			if ($html === false) {
				$curl[0] = 'Error';
				$curl[1] = 'Could not initialize cURL and file_get_contents()';			
			} else {
				$curl[0] = 'Success';
				$curl[1] = $html;			
			}
		}
	} else {
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$html = curl_exec($ch);
		if (empty($html)) {
			$curl[0] = false;
			$curl[1] = curl_error($ch);
			curl_close($ch); 
		} else {
			$curl[0] = true;
			if($return) $curl[1] = $html;		
			curl_close($ch);
		}
	}
	return $curl;	
}

function wpws_get_content($url = '', $selector = '', $clear = '', $output_format = '', $cache_timeout = '', $curl_agent = '', $curl_timeout = '', $curl_error = '') {
	
	if($cache_timeout == '') $cache_timeout = get_option('wpws_cache_timeout');
	if($output_format == '') $output_format = 'text';
	if($curl_agent == '') $curl_agent = get_option('wpws_curl_agent');
	if($curl_timeout == '') $curl_timeout = get_option('wpws_curl_timeout');
	if($curl_error == '') $ecurl_rror = get_option('wpws_curl_error');
	
	if($url == '' || $selector == '') {
		if($curl_error == '1') {return 'Required params missing';}
		elseif($curl_error == '0') {return false;} 
		else {return $curl_error;}		
	} else {
		$cache_file = 'wp-content/plugins/wp-web-scrapper/cache/'.urlencode($url).urlencode($selector);
		$cache_file_status = file_exists($cache_file);
		$timestamp_id = '<!--wpws_timestamp-->';
		if($cache_file_status) {
			$wpws_timestamp = explode($timestamp_id, file_get_contents($cache_file));
			$cache_file_ctime = $wpws_timestamp[1];
			$cache_status = (time() - $cache_file_ctime) < ($cache_timeout * 60);
		} else {$cache_status = false;}
		if($cache_status) {
			return $wpws_timestamp[0];
		} else {
			$scrap = wpws_curl(html_entity_decode($url), $curl_agent, $curl_timeout);
			if($scrap[0]) {
				require_once('phpQuery.php');
				$doc = phpQuery::newDocumentHTML($scrap[1]);
				phpQuery::selectDocument($doc);	
				if($output_format == 'text') {$output = pq($selector)->text();}
				elseif($output_format == 'html') {$output = pq($selector)->html();}
				if($clear != '') {$output = preg_replace($clear, '', $output);}
				file_put_contents($cache_file, $output.$timestamp_id.time());
				return $output;
			} else {
				if($curl_error == '1') {return $scrap[1];}
				elseif($curl_error == '0') {return false;} 
				else {return $curl_error;}
			}
		}
	}
}

function wpws_shortcode($atts) {
	extract(shortcode_atts(array('url' => '', 'selector' => '', 'clear' => '', 'output' => 'text', 'cache' => get_option('wpws_cache_timeout'), 'agent' => get_option('wpws_curl_agent'), 'timeout' => get_option('wpws_curl_timeout'), 'error' => get_option('wpws_curl_error')), $atts));
	return wpws_get_content($url, $selector, $clear, $output, $cache, $agent, $timeout, $error);
}

function wpws_settings_page(){
	add_options_page('My Plugin Options', 'WP Web Scrapper', 8, __FILE__, 'wpws_settings_html');
}

function wpws_on_activation(){
	add_option('wpws_sc_posts', 1);
	add_option('wpws_sc_sidebar', 1);
	add_option('wpws_curl_error', 0);
	add_option('wpws_curl_agent', "WPWS bot (".get_bloginfo('url').")");
	add_option('wpws_curl_timeout', 1);
	add_option('wpws_cache_timeout', 60);
}

function wpws_settings_html(){
$cache_root = dirname(__FILE__).'/cache';
$size_array = wpws_getDirectorySize($cache_root);
?>
<script language="JavaScript">
var popUpWin=0;
function clear_cache(){
	jQuery('#wpws_cache_status').load('../wp-content/plugins/wp-web-scrapper/wpws_cache_clear.php', {count: <?php echo $size_array['count'];?>}, function(){
   		jQuery('#wpws_cache_status').addClass('fade');
	});
}
</script>
<div class="wrap">

	<?php screen_icon(); ?>
	<h2><?php _e('WP Web Scrapper Settings'); ?></h2>
	
	<form method="post" action="options.php" id="wpws_options">
	<?php wp_nonce_field('update-options'); ?>
		<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="blogname"><?php _e('WP Web Scrapper Shortcodes') ?></label></th>
			<td><fieldset>
			<label for="wpws_sc_posts">
			<input name="wpws_sc_posts" type="checkbox" id="wpws_sc_posts" value="1" <?php checked('1', get_option('wpws_sc_posts')); ?> />
			<?php _e('Enable shortcodes for posts and pages') ?></label>
			<br />
			<label for="wpws_sc_sidebar">
			<input name="wpws_sc_sidebar" type="checkbox" id="wpws_sc_sidebar" value="1" <?php checked('1', get_option('wpws_sc_sidebar')); ?> />
			<?php _e('Enable shortcodes in sidebar text widget') ?></label>
			</fieldset></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="blogname"><?php _e('Display error if cURL fails') ?></label></th>
			<td>
			<label for="wpws_curl_error">
			<input name="wpws_curl_error" type="checkbox" id="wpws_curl_error" value="1" <?php checked('1', get_option('wpws_curl_error')); ?> />
			<?php _e('Default way to handle cURL failure. Can be used while debugging.') ?></label>
			</td>
		</tr>		
		<tr valign="top">
			<th scope="row"><label for="blogname"><?php _e('cURL useragent string') ?></label></th>
			<td>
			<input name="wpws_curl_agent" type="text" id="wpws_curl_agent" value="<?php form_option('wpws_curl_agent'); ?>" class="regular-text code" />
			<span class="setting-description"><?php _e('Default useragent header to identify yourself when crawling sites. Read more.') ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="blogname"><?php _e('cURL timeout (in seconds)') ?></label></th>
			<td>
			<input name="wpws_curl_timeout" type="text" id="wpws_curl_timeout" value="<?php form_option('wpws_curl_timeout'); ?>" class="small-text code" />
			<span class="setting-description"><?php _e('Default timeout interval in seconds for cURL. Larger interval might slow down your page.') ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="blogname"><?php _e('Cache timeout (in minutes)') ?></label></th>
			<td>
			<input name="wpws_cache_timeout" type="text" id="wpws_cache_timeout" value="<?php form_option('wpws_cache_timeout'); ?>" class="small-text code"/>
			<span class="setting-description"><?php _e('Default timeout in minutes for cached webpages.') ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="blogname"><?php _e('Cache management') ?></label></th>
			<td>
			<input type="button" name="wpws_cache_clear" id="wpws_cache_clear" value="<?php _e('Clear Cache') ?>" class="button-secondary" onclick="clear_cache(); return false;"/><br />
			<span class="setting-description" id="wpws_cache_status"><?php _e('Your cache currently has '.($size_array['count'] - 3).' files occuping '.wpws_sizeFormat($size_array['size'] - 296).' of space.') ?></span>
			</td>
		</tr>
		</table>
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="wpws_sc_posts,wpws_sc_sidebar,wpws_curl_error,wpws_curl_agent,wpws_curl_timeout,wpws_cache_timeout" />
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
	</p>
	</form>

</div>
<?php
}
?>