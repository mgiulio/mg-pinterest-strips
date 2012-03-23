<?php

/*
Plugin Name: mg-wp-widget-pinterest
Plugin URI: http://mgiulio.altervista.org
Description: Shows your Pinterest pins
Version: 0.1
Author: mgiulio (Giulio Mainardi)
Author URI: http://mgiulio.altervista.org
License: GPL2
*/

require_once (ABSPATH . WPINC . '/class-feed.php');
class SimplePie_Cache_Extras extends SimplePie
{
	function get_cache_object()
	{
//		$cache =& new $this->cache_class($this->cache_location, call_user_func($this->cache_name_function, $this->feed_url), 'spc');
		$cache = call_user_func(array($this->cache_class, 'create'), $this->cache_location, call_user_func($this->cache_name_function, $this->feed_url), 'spc');
		return $cache;
	}
 
	function get_cache_filename()
	{
		$cache = $this->get_cache_object();
		return $cache->name;
	}
 
	function get_cache_timestamp()
	{
		$cache = $this->get_cache_object();
		return $cache->mtime();
	}
 
	function get_cache_time_remaining($format = false)
	{
		$cache = $this->get_cache_object();
		$remaining = ($cache->mtime() + $this->cache_duration) - time();
 
		if ($format)
		{
			return SimplePie_Misc::time_hms($remaining);
		}
		else
		{
			return $remaining;
		}
	}
}

function mg_fetch_feed($url, /* $cache_life, */ &$feed) {
	require_once (ABSPATH . WPINC . '/class-feed.php');

	$feed = new SimplePie_Cache_Extras();
	$feed->set_feed_url($url);
	$feed->set_cache_class('WP_Feed_Cache');
	$feed->set_file_class('WP_SimplePie_File');
	$feed->set_cache_duration(60/* $cache_life *//* apply_filters('wp_feed_cache_transient_lifetime', 43200, $url) */);
	//do_action_ref_array( 'wp_feed_options', array( &$feed, $url ) );
	
	$is_cache_expired = $feed->get_cache_time_remaining() < 0;
	$feed->init();
	$feed->handle_content_type();

	if ( $feed->error() )
		return new WP_Error('simplepie-error', $feed->error());

	return $is_cache_expired;
}

class mg_Widget_Pinterest extends WP_Widget {
	function __construct() {
		parent::__construct(
			'mg-widget-pinterest', // Root HTML id attr
			'Pinterest Widget', // Name
			array('description' => __( 'A Pinterest Widget', 'text_domain' ))
		);
		
		$this->plugin_dir = plugin_dir_path(__FILE__);
		$this->plugin_url = plugin_dir_url(__FILE__);
		
		$this->cache_dir = $this->plugin_dir . 'cache/';
		if (!file_exists($this->cache_dir))
			mkdir($this->cache_dir);
		$this->cache_url = $this->plugin_url . 'cache/';
	}
	
	function form($instance) {
		extract(wp_parse_args($instance, array(
			'username' => '', 
			'items' => 5, 
			'strip_width' => 50,
			'num_strips' => 4,
			'cache_life' => 3600
		)));
		
		?>
		<p>
			<label for="<?php echo $this->get_field_id('username'); ?>">
				<?php _e('Pinterest username:'); ?>
			</label> 
			<input 
				class="widefat" 
				id="<?php echo $this->get_field_id('username'); ?>" 
				name="<?php echo $this->get_field_name('username'); ?>" 
				type="text" 
				value="<?php echo esc_attr($username); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('strip_width'); ?>">
				<?php _e('Strip width(px):'); ?>
			</label> 
			<input 
				class="widefat" 
				id="<?php echo $this->get_field_id('strip_width'); ?>" 
				name="<?php echo $this->get_field_name('strip_width'); ?>" 
				type="text" 
				value="<?php echo esc_attr($strip_width); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('num_strips'); ?>">
				<?php _e('How many strips?'); ?>
			</label> 
			<input 
				class="widefat" 
				id="<?php echo $this->get_field_id('num_strips'); ?>" 
				name="<?php echo $this->get_field_name('num_strips'); ?>" 
				type="text" 
				value="<?php echo esc_attr($num_strips); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('items'); ?>">
				<?php _e('Items:'); ?>
			</label> 
			<input 
				class="widefat" 
				id="<?php echo $this->get_field_id('items'); ?>" 
				name="<?php echo $this->get_field_name('items'); ?>" 
				type="text" 
				value="<?php echo esc_attr($items); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('cache_life'); ?>">
				<?php _e('Cache life(seconds):'); ?>
			</label> 
			<input 
				class="widefat" 
				id="<?php echo $this->get_field_id('cache_life'); ?>" 
				name="<?php echo $this->get_field_name('cache_life'); ?>" 
				type="text" 
				value="<?php echo esc_attr($cache_life); ?>" />
		</p>
		<?php
	}
	
	function update($new_instance, $old_instance) {
		$instance = array();
		$instance['username'] = strip_tags($new_instance['username']);
		$instance['items'] = strip_tags($new_instance['items']);
		$instance['strip_width'] = strip_tags($new_instance['strip_width']);
		$instance['num_strips'] = strip_tags($new_instance['num_strips']);
		$instance['cache_life'] = strip_tags($new_instance['cache_life']);
		
		$feed_url = "http://pinterest.com/{$instance['username']}/feed.rss";
		$rss = NULL;
		mg_fetch_feed($feed_url, $rss);
		if (is_wp_error($rss))
			return;
		ob_start();
		$this->build_pinboard_no_borders_sprite($rss, $instance['strip_width'], $instance['num_strips']);
		fwrite(fopen($this->cache_dir . "markup-{$this->number}.html", 'w'), ob_get_contents());
		ob_end_clean();
		
		return $instance;
	}

	function widget($args, $instance) {
		extract($args);
		$username = $instance['username'];
		
		if ($username == '')
			return;
		
		$feed_url = "http://pinterest.com/$username/feed.rss";		
		$rss = NULL;
		$is_fresh = mg_fetch_feed($feed_url, $rss);
		if (is_wp_error($rss))
			return;
		
		$title = apply_filters('widget_title', $instance['title']);
		$title = 
			'<a href="http://pinterest.com/' . $username . '">' . 
			$username . 
			'</a> on <a href="http://pinterest.com">Pinterest</a>';

		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		if ($is_fresh || 
			!file_exists($this->cache_dir . "markup-{$this->number}.html") ||
			!file_exists($this->cache_dir . "sprite-{$this->number}.jpg")
		) {
			ob_start();
			$this->build_pinboard_no_borders_sprite($rss, $instance['strip_width'], $instance['num_strips']);
			fwrite(fopen($this->cache_dir . "markup-{$this->number}.html", 'w'), ob_get_contents());
			ob_end_flush();
		}
		else
			readfile($this->cache_dir . "markup-{$this->number}.html");
		
		echo $after_widget;

		$rss->__destruct();
		unset($rss);
	}
	
	function buildPinboard($rss) {
		$pinWidth = 50;
		$numCols = 4;
		$margin = 1;
		
		$colWidth = $pinWidth + $margin;
		$pinboard_inner_width = $colWidth*$numCols - 2*$marging-$margin;
		
		$cols = array();
		$c = 0;
		$i = 0;
		foreach ($rss->get_items(0, $instance['items']) as $item) {
			$title = esc_attr(strip_tags($item->get_title()));
			$link = $item->get_link();
			$desc = $item->get_description();
			$imgSrc = array();
			preg_match('/src="([^"]+)"/', $desc, $imgSrc);
			//preg_match('/src="(.*)"/', $desc, $imgSrc);
			$imgUrl = $imgSrc[1];
			
			$cols[$c][] = "<a href='$link'><img style='max-width: none; display: block; width: {$pinWidth}px; margin: 0; padding: 0; margin-bottom: {$margin}px;' src='$imgUrl' title='$title' alt='$title'></a>";
			$c = ($c+1) % $numCols;
			
			$pinIm = imagecreatefromjpeg($imgUrl);
			$pinW = imagesx($pinIm);
			$pinH = imagesy($pinIm);
			$pinAspectRatio = $pinW / (float)$pinH;
			$thumbW = $pinWidth;
			$thumbH = $thumbW / $pinAspectRatio;
			$thumbIm = imagecreatetruecolor($thumbW, $thumbH);
			imagecopyresized($thumbIm, $pinIm, 0, 0, 0, 0, $thumbW, $thumbH, $pinW, $pinH);
			$thumbUrl = $this->plugin_dir . "thumb-{$i}.jpg";
			imagejpeg($thumbIm, $thumbUrl);
			$i++;
		}
		echo "<div class='pinboard' style='width: {$pinboard_inner_width}px; margin: 10px auto; padding: {$margin}px; background-color: none;'>";
			foreach ($cols as $i => $c) {
				echo "<div class='col' style='width: " . ($i < $numCols-1 ? $colWidth : $colWidth-1) . "px; float: left; margin: 0; padding: 0'>";
				echo implode('', $c);
				echo "</div>";
			}
			echo "<div style='clear: both;'>&nbsp;</div>";
		echo "</div>";
	}
	
	function buildPinboard_noBorders($rss) {
		$pinWidth = 50;
		$numCols = 4;
		
		$colWidth = $pinWidth;
		$pinboard_inner_width = $colWidth*$numCols;
		
		$cols = array();
		$c = 0;
		$i = 0;
		foreach ($rss->get_items(0, $instance['items']) as $item) {
			$title = esc_attr(strip_tags($item->get_title()));
			$link = $item->get_link();
			$desc = $item->get_description();
			$imgSrc = array();
			preg_match('/src="([^"]+)"/', $desc, $imgSrc);
			//preg_match('/src="(.*)"/', $desc, $imgSrc);
			$imgUrl = $imgSrc[1];
			
			$cols[$c][] = "<a href='$link'><img style='max-width: none; display: block; width: {$pinWidth}px; margin: 0; padding: 0; margin-bottom: {$margin}px;' src='$imgUrl' title='$title' alt='$title'></a>";
			$c = ($c+1) % $numCols;
			
			$pinIm = imagecreatefromjpeg($imgUrl);
			$pinW = imagesx($pinIm);
			$pinH = imagesy($pinIm);
			$pinAspectRatio = $pinW / (float)$pinH;
			$thumbW = $pinWidth;
			$thumbH = $thumbW / $pinAspectRatio;
			$thumbIm = imagecreatetruecolor($thumbW, $thumbH);
			imagecopyresized($thumbIm, $pinIm, 0, 0, 0, 0, $thumbW, $thumbH, $pinW, $pinH);
			$i++;
		}
		echo "<div class='pinboard' style='width: {$pinboard_inner_width}px; margin: 10px auto; padding: 0px; background-color: none;'>";
			foreach ($cols as $i => $c) {
				echo "<div class='col' style='width: {$pinWidth}px; float: left; margin: 0; padding: 0'>";
				echo implode('', $c);
				echo "</div>";
			}
			echo "<div style='clear: both;'>&nbsp;</div>";
		echo "</div>";
	}
	
	function build_pinboard_no_borders_sprite($rss, $stripW, $numStrips) {
		$pinboard_inner_width = $stripW * $numStrips;
		
		$items = $rss->get_items(0, $instance['items']);
		
		// Compute the sprite height and allocate it
		$spriteH = 0;
		foreach ($items as $item) {
			$im = imagecreatefromjpeg($this->get_image_url($item->get_description()));
			
			$w = imagesx($im);
			$h = imagesy($im);
			$aspectRatio = $w / (float)$h;
			$thumb_h = $stripW / $aspectRatio;
			
			$spriteH += $thumb_h;
			
			$pinIm[] = array('im' => $im, 'w' => $w, 'h' => $h, 'thumb_h' => $thumb_h);
		}
		$spriteIm = imagecreatetruecolor($stripW, $spriteH);

		$spriteUrl = $this->cache_url . "sprite-{$this->number}.jpg";
		$cols = array();
		$c = 0;
		$y = 0;
		foreach ($items as $item) {
			// Get item info
			$title = esc_attr(strip_tags($item->get_title()));
			$link = $item->get_link();
			
			// Make thumbnail and append it to the sprite
			$currIm = array_shift($pinIm);
			$thumb_h = $currIm['thumb_h'];
			imagecopyresampled($spriteIm, $currIm['im'], 0, $y, 0, 0, $stripW, $thumb_h, $currIm['w'], $currIm['h']);
			imagedestroy($currIm['im']);
			
			// Generate the markup for this item
			$cols[$c][] = "<a href='$link' title='$title' style='display: block; width: {$stripW}px; height: {$thumb_h}px; margin: 0; padding: 0; background: url($spriteUrl) no-repeat 0 -{$y}px; text-indent: -9999px;'>$title</a>";
			$c = ($c+1) % $numStrips;
			
			$y += $thumb_h;
		}
		// Save the sprite
		imagejpeg($spriteIm, $this->cache_dir . "sprite-{$this->number}.jpg");
		imagedestroy($spriteIm);
		
		echo "<div class='pinboard' style='width: {$pinboard_inner_width}px; margin: 10px auto; padding: 0px; background-color: none;'>";
			foreach ($cols as $i => $c) {
				echo "<div class='col' style='width: {$stripW}px; float: left; margin: 0; padding: 0'>";
				echo implode('', $c);
				echo "</div>";
			}
			echo "<div style='clear: both;'>&nbsp;</div>";
		echo "</div>";
	}
	
	function get_image_url($itemDesc) {
		$imgSrc = array();
		preg_match('/src="([^"]+)"/', $itemDesc, $imgSrc);
		return $imgSrc[1];
	}
}

add_action('widgets_init', create_function('', 'register_widget("mg_Widget_Pinterest");'));
//add_action('wp_head', 'mg_widget_pinterest_on_wp_head');

/* function mg_widget_pinterest_on_wp_head() {
	echo "<style type='text/css'></style>";
} */