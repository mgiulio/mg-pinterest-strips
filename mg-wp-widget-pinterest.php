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
		extract($args);
		$username = $instance['username'];
		
		if ($username == '')
			return;
		
		//$title = apply_filters('widget_title', $instance['title']);
		$title = 
			'<a href="http://pinterest.com/' . $username . '">' . 
			$username . 
			'</a> on <a href="http://pinterest.com">Pinterest</a>';
		
		$feedUrl = "http://pinterest.com/$username/feed.rss";

		$rss = fetch_feed($feedUrl);
		if (is_wp_error($rss))
			return;

		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		$pinboardWidth = 200;
		$colWidth = 50;
		$numCols = floor($pinboardWidth / $colWidth);
		$cols = array();
		$c = 0;
		foreach ($rss->get_items(0, $instance['items']) as $item) {
			$title = esc_attr(strip_tags($item->get_title()));
			$link = $item->get_link();
			$desc = $item->get_description();
			$imgSrc = array();
			preg_match('/src="(.*)"/', $desc, $imgSrc);
			
			$cols[$c][] = "<a href='$link'><img src='{$imgSrc[1]}' title='$title' alt='$title'></a>";
			$c = ($c+1) % $numCols;
		}
		echo "<div class='pinboard' style='width: {$pinboardWidth}px; margin: 0 auto; height: 80px; background-color: #f0f0f0;'>";
		foreach ($cols as $c) {
			echo "<div class='col' style='width: {$colWidth}px; float: left;'>";
			echo implode('', $c);
			echo "</div>";
		}
		echo "</div>";
		
		echo $after_widget;

		$rss->__destruct();
		unset($rss);
	}
	
}

add_action('widgets_init', create_function('', 'register_widget("mg_Widget_Pinterest");'));
