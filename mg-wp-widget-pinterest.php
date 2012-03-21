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

/*
 * Setting paths and urls.
 */
global
	$mg_pin_plugin_dir,
	$mg_pin_plugin_url
;
$mg_pin_plugin_dir = plugin_dir_path( __FILE__ );
$mg_pin_plugin_url = plugin_dir_url( __FILE__ );

class mg_Widget_Pinterest extends WP_Widget {

	function __construct() {
		parent::__construct(
			'mg-widget-pinterest', // Root HTML id attr
			'Pinterest Widget', // Name
			array('description' => __( 'A Pinterest Widget', 'text_domain' ))
		);
	}
	
	function form($instance) {
		extract(wp_parse_args($instance, array(
			'username' => '', 
			'items' => 5, 
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
				<?php _e('Cache life:'); ?>
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
		$instance['cache_life'] = strip_tags($new_instance['cache_life']);
		
		return $instance;
	}

	function widget($args, $instance) {
		global $mg_pin_plugin_dir;
		
		extract($args);
		$username = $instance['username'];
		
		if ($username == '')
			return;
		
		$feedUrl = "http://pinterest.com/$username/feed.rss";		
		$rss = fetch_feed($feedUrl);
		if (is_wp_error($rss))
			return;
		
		$title = apply_filters('widget_title', $instance['title']);
		$title = 
			'<a href="http://pinterest.com/' . $username . '">' . 
			$username . 
			'</a> on <a href="http://pinterest.com">Pinterest</a>';

		echo $before_widget;
		echo $before_title . $title . $after_title;
		//$this->buildPinboard($rss);
		$this->buildPinboard_noBorders($rss);
		echo $after_widget;

		$rss->__destruct();
		unset($rss);
	}
	
	function buildPinboard_noBorders($rss) {
		$pinWidth = 50;
		$numCols = 4;
		
		$colWidth = $pinWidth;
		$pinboardInnerWidth = $colWidth*$numCols;
		
		$cols = array();
		$c = 0;
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
		}
		echo "<div class='pinboard' style='width: {$pinboardInnerWidth}px; margin: 10px auto; padding: 0px; background-color: none;'>";
			foreach ($cols as $i => $c) {
				echo "<div class='col' style='width: {$pinWidth}px; float: left; margin: 0; padding: 0'>";
				echo implode('', $c);
				echo "</div>";
			}
			echo "<div style='clear: both;'>&nbsp;</div>";
		echo "</div>";
	}
	
	function buildPinboard($rss) {
		$pinWidth = 50;
		$numCols = 4;
		$margin = 1;
		
		$colWidth = $pinWidth + $margin;
		$pinboardInnerWidth = $colWidth*$numCols - 2*$marging-$margin;
		
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
			$thumbUrl = $mg_pin_plugin_dir . "thumb-{$i}.jpg";
			imagejpeg($thumbIm, $thumbUrl);
			$i++;
		}
		echo "<div class='pinboard' style='width: {$pinboardInnerWidth}px; margin: 10px auto; padding: {$margin}px; background-color: none;'>";
			foreach ($cols as $i => $c) {
				echo "<div class='col' style='width: " . ($i < $numCols-1 ? $colWidth : $colWidth-1) . "px; float: left; margin: 0; padding: 0'>";
				echo implode('', $c);
				echo "</div>";
			}
			echo "<div style='clear: both;'>&nbsp;</div>";
		echo "</div>";
	}
}

add_action('widgets_init', create_function('', 'register_widget("mg_Widget_Pinterest");'));
//add_action('wp_head', 'mg_widget_pinterest_on_wp_head');

/* function mg_widget_pinterest_on_wp_head() {
	echo "<style type='text/css'></style>";
} */