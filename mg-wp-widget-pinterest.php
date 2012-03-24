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
		
		$this->plugin_dir = plugin_dir_path(__FILE__);
		$this->plugin_url = plugin_dir_url(__FILE__);
		
		$this->cache_dir = $this->plugin_dir . 'cache/';
		if (!file_exists($this->cache_dir))
			mkdir($this->cache_dir);
		$this->cache_url = $this->plugin_url . 'cache/';
	}
	
	function get_markup_path() {
		return $this->cache_dir . "markup-{$this->number}.html";
	}
	
	function get_sprite_path() {
		return $this->cache_dir . "sprite-{$this->number}.jpg";
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
		$instance['num_items'] = strip_tags($new_instance['num_items']);
		$instance['strip_width'] = strip_tags($new_instance['strip_width']);
		$instance['num_strips'] = strip_tags($new_instance['num_strips']);
		$instance['cache_life'] = strip_tags($new_instance['cache_life']);
		
		$feed_url = "http://pinterest.com/{$instance['username']}/feed.rss";
		ob_start();
		$this->build_pinboard($this->fetch_feed($feed_url), $instance['strip_width'], $instance['num_strips']);
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
		
		$title = apply_filters('widget_title', $instance['title']);
		$title = 
			'<a href="http://pinterest.com/' . $username . '">' . 
			$username . 
			'</a> on <a href="http://pinterest.com">Pinterest</a>';

		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		if ($this->cache_is_invalid($instance['cache_life']) && ($pins = $this->fetch_feed($feed_url))) {
			ob_start();
			$this->build_pinboard($pins, $instance['strip_width'], $instance['num_strips']);
			fwrite(fopen($this->get_markup_path(), 'w'), ob_get_contents());
			ob_end_flush();
		}
		else
			readfile($this->get_markup_path());
		
		echo $after_widget;
	}
	
	function build_pinboard($pins, $stripW, $numStrips) {
		$pinboard_inner_width = $stripW * $numStrips;
		
		// Compute the sprite height and allocate it
		$spriteH = 0;
		foreach ($pins as $pin) {
			$im = imagecreatefromjpeg($pin['image_url']);
			
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
		foreach ($pins as $pin) {
			// Make thumbnail and append it to the sprite
			$currIm = array_shift($pinIm);
			$thumb_h = $currIm['thumb_h'];
			imagecopyresampled($spriteIm, $currIm['im'], 0, $y, 0, 0, $stripW, $thumb_h, $currIm['w'], $currIm['h']);
			imagedestroy($currIm['im']);
			
			// Generate the markup for this item
			$cols[$c][] = "<a href='{$pin['link']}' title='$title' style='display: block; width: {$stripW}px; height: {$thumb_h}px; margin: 0; padding: 0; background: url($spriteUrl) no-repeat 0 -{$y}px; text-indent: -9999px;'>{$pin['title']}</a>";
			$c = ($c+1) % $numStrips;
			
			$y += $thumb_h;
		}
		// Save the sprite
		imagejpeg($spriteIm, $this->get_sprite_path());
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
	
	function cache_is_invalid($cache_life) {
		$last_build_timestamp = filemtime($this->get_markup_path()); //http://it2.php.net/manual/en/function.clearstatcache.php
		
		return
			$last_build_timestamp + $cache_life <= time() ||
			!file_exists($this->get_markup_path()) ||
			!file_exists($this->get_sprite_path())
		;
	}
	
	function fetch_feed($url) {
		$rss = simplexml_load_file($url);
		// Eorhandling

		$pins = array();
		foreach ($rss->channel->item as $item)
			$pins[] = array(
				'title' => (string)$item->title,//$title = esc_attr(strip_tags($item->get_title()));
				'link' =>(string)$item->link,
				'image_url' => $this->get_image_url((string)$item->description)
			);
		
		return $pins;
	}
}

add_action('widgets_init', create_function('', 'register_widget("mg_Widget_Pinterest");'));
//add_action('wp_head', 'mg_widget_pinterest_on_wp_head');

/* function mg_widget_pinterest_on_wp_head() {
	echo "<style type='text/css'></style>";
} */