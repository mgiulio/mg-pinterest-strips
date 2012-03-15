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
		if (empty($instance))
			$instance = array(
				'username' => '', 
				'items' => 5, 
				'cache life' => 3600
			);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('username'); ?>">
				<?php _e('Username:'); ?>
			</label> 
			<input 
				class="widefat" 
				id="<?php echo $this->get_field_id('username'); ?>" 
				name="<?php echo $this->get_field_name('username'); ?>" 
				type="text" 
				value="<?php echo esc_attr($instance['username']); ?>" />
		</p>
		<?php
	}
	
	function update($new_instance, $old_instance) {
		$instance = array();
		$instance['username'] = strip_tags( $new_instance['username']);
		return $instance;
	}

	function widget($args, $instance) {
		extract($args/* , EXTR_SKIP */);
		
		$username = $instance['username'];
		
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
		echo "Here will go the pins";
		echo $after_widget;

		$rss->__destruct();
		unset($rss);
	}
	
}

add_action('widgets_init', create_function('', 'register_widget("mg_Widget_Pinterest");'));
