<?php

/*
Plugin Name: mg Pinterest Strips
Plugin URI: http://mgiulio.altervista.org
Description: Display Pinterest pins as vertical strips
Version: 0.1
Author: mgiulio (Giulio Mainardi)
Author URI: http://mgiulio.altervista.org
License: GPLv2
*/

class mg_Pinterest_Strips extends WP_Widget {
	function __construct() {
		$this->logging_enabled = false;
		
		parent::__construct(
			'mg-pinterest-strips', // Root HTML id attr
			'Pinterest Strips', // Name
			array('description' => __( 'Display Pinterest pins as vertical strips', 'text_domain' ))
		);
		
		$this->plugin_dir = plugin_dir_path(__FILE__);
		$this->plugin_url = plugin_dir_url(__FILE__);
		
		$this->cache_dir = $this->plugin_dir . 'cache/';
		if (!file_exists($this->cache_dir))
			mkdir($this->cache_dir);
		$this->cache_url = $this->plugin_url . 'cache/';
		
		$this->instance_default = array(
			'username' => 'mgiulio', 
			'board' => '', 
			'max_items' => 40, 
			'strip_width' => 50,
			'num_strips' => 4,
			'cache_life' => 3600
		);
	}
	
	function get_markup_path() {
		return $this->cache_dir . "markup-{$this->number}.html";
	}
	
	function get_sprite_path() {
		return $this->cache_dir . "sprite-{$this->number}.jpg";
	}
	
	function form($instance) {
		extract(wp_parse_args($instance, $this->instance_default));
		
		if (isset($instance['errors'])) {
			echo "<ul style='color: #ff0000;'>";
			foreach ($instance['errors'] as $err)
				echo "<li>$err<li>";
			echo "</ul>";
		}
		
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
			<label for="<?php echo $this->get_field_id('board'); ?>">
				<?php _e('Board(not required):'); ?>
			</label> 
			<input 
				class="widefat" 
				id="<?php echo $this->get_field_id('board'); ?>" 
				name="<?php echo $this->get_field_name('board'); ?>" 
				type="text" 
				value="<?php echo esc_attr($board); ?>" />
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
			<label for="<?php echo $this->get_field_id('max_items'); ?>">
				<?php _e('Max number of pins:'); ?>
			</label> 
			<input 
				class="widefat" 
				id="<?php echo $this->get_field_id('max_items'); ?>" 
				name="<?php echo $this->get_field_name('max_items'); ?>" 
				type="text" 
				value="<?php echo esc_attr($max_items); ?>" />
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
		unset($old_instance['errors']);
		$old_instance = wp_parse_args($old_instance, $this->instance_default);
		$instance = $old_instance;
		
		$must_regenerate_cache = false;
				
		// Username validation
		$username = $new_instance['username'];
		if ($username != $old_instance['username']) {
			if ($username == '') 
				$errors[] = "The username is required";
			else if (($res = $this->is_valid_pinterest_username($username)) != 'ok')
				$errors[] = $res;
			else {
				$instance['username'] = $username;
				$must_regenerate_cache = true;
			}
		}
			
		// Board name validation
		$board = $new_instance['board'];
		if ($board != $old_instance['board']) {
			if ($board == '') {
				$instance['board'] = '';
				$must_regenerate_cache = true;
			}
			else {
				$res = $this->board_validation($board, $username);
				if ($res['status'] != 'ok')
					$errors[] = "Something bad happened in board '$board' validation: {$res['status']}";
				else {
					$instance['board'] = $res['sanitized'];
					$must_regenerate_cache = true;
				}
			}
		}
		
		// Max Items validation
		$max_items = $new_instance['max_items'];
		if ($max_items != $old_instance['max_items']) {
			if ($max_items == '')
				$errors[] = "The max number of items is required";
			else if (($max_items = (int)$max_items) < 1)
				$errors[] = "Max number of items must be greater than one";
			else {
				$instance['max_items'] = $max_items;
				$must_regenerate_cache = true;
			}
		}
		
		// Strip width validation
		$strip_width = $new_instance['strip_width'];
		if ($strip_width != $old_instance['strip_width']) {
			if ($strip_width == '')
				$errors[] = "The strip width field is required";
			else {
				$strip_width = (int)$strip_width;
				if (!($strip_width >= 10))
					$errors[] = "The strip width must be >= 10";
				else if (!($strip_width <= 1024))
					$errors[] = "The strip width must be <= 1024";
				else {
					$instance['strip_width'] = $strip_width;
					$must_regenerate_cache = true;
				}
			}
		}
		
		// Num strips validation
		$num_strips = $new_instance['num_strips'];
		if ($num_strips != $old_instance['num_strips']) {
			if ($num_strips == '')
				$errors[] = "The number of strips field is required";
			else {
				$num_strips = (int)$num_strips;
				if (!($num_strips >= 1))
					$errors[] = "num_strips must be >= 1";
				else if (!($num_strips <= 1024))
					$errors[] = "num_strips must be <= 1024";
				else {
					$instance['num_strips'] = $num_strips;
					$must_regenerate_cache = true;
				}
			}
		}
		
		//Cachelife validation
		$cache_life = $new_instance['cache_life'];
		if ($cache_life != $old_instance['cache_life']) {
			if ($cache_life == '')
				$errors[] = "cache life is required";
			else {
				$cache_life = (int)$cache_life;
				if (!($cache_life >= 60))
					$errors[] = "cache life must be >= 60";
				else if (!($cache_life <= 360000))
					$errors[] = "cache life must be <= 3600000";
				else {
					$instance['cache_life'] = $cache_life;
					$must_regenerate_cache = true;
				}
			}
		}
		
		if ($must_regenerate_cache)
			$this->regenerate_cache($instance);
			
		if (!empty($errors))
			$instance['errors'] = $errors;
		
		return $instance;
	}
	
	function regenerate_cache($instance) {
		$feed_url = $instance['board'] != '' ? 
			"http://pinterest.com/{$instance['username']}/{$instance['board']}.rss" :
			"http://pinterest.com/{$instance['username']}/feed.rss"
		;		
		
		$feed = $this->fetch_feed($feed_url, $instance['max_items']);
		if (!$feed)
			return false;
			
		ob_start();
		if ($ok = $this->build_pinboard($feed, $instance['strip_width'], $instance['num_strips']))
			fwrite(fopen($this->get_markup_path(), 'w'), ob_get_contents());
		ob_end_clean();
		
		return $ok;
	}

	function widget($args, $instance) {
		extract($args);
		$username = $instance['username'];
		
		if ($username == '')
			return;
			
		$board = $instance['board'];
		
		$feed_url = $board != '' ? 
			"http://pinterest.com/$username/$board.rss" :
			"http://pinterest.com/$username/feed.rss"
		;		
		
		$title = $board ?
			"<a href='http://pinterest.com/$username'>$username</a>'s " .
			"<a href='http://pinterest.com/$username/$board'>$board</a> on " .
			"<a href='http://pinterest.com'>Pinterest</a>"
			: 
			"<a href='http://pinterest.com/$username'>$username</a> on " .
			"<a href='http://pinterest.com'>Pinterest</a>"
		;
		//$title = apply_filters('widget_title', $title, $this->id_base, $username, $board);
		

		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		if (!$this->cache_is_invalid($instance['cache_life']))
			readfile($this->get_markup_path());
		else {
			$pins = $this->fetch_feed($feed_url, $instance['max_items']);
			if (!$pins)
				readfile($this->get_markup_path());
			else {
				ob_start();
				$ok = $this->build_pinboard($pins, $instance['strip_width'], $instance['num_strips']);
				if ($ok) {
					fwrite(fopen($this->get_markup_path(), 'w'), ob_get_contents());
					ob_end_flush();
				}
				else {
					ob_end_clean();
					readfile($this->get_markup_path());
				}
			}
		}		
			
		echo $after_widget;
	}
	
	function build_pinboard($pins, $stripW, $numStrips) {
		$this->log("build_pinboard: start");
		
		$pinboard_inner_width = $stripW * $numStrips;
		
		// Compute the sprite height and allocate it
		$this->log("Sprite height computation: start");
		$spriteH = 0;
		foreach ($pins as $pin) {
			$this->log("Fetching {$pin['image_url']}");
			$im = $this->create_image_from_url($pin['image_url']);
			if (!$im)
				return false;
			
			$w = imagesx($im);
			$h = imagesy($im);
			$aspectRatio = $w / (float)$h;
			$thumb_h = $stripW / $aspectRatio;
			
			$spriteH += $thumb_h;
			
			$pinIm[] = array('im' => $im, 'w' => $w, 'h' => $h, 'thumb_h' => $thumb_h);
		}
		$spriteIm = imagecreatetruecolor($stripW, $spriteH);
		$this->log("Sprite height computation: end");

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
			$cols[$c][] = "<a href='{$pin['link']}' title='{$pin['title']}' style='display: block; width: {$stripW}px; height: {$thumb_h}px; margin: 0; padding: 0; background: url($spriteUrl) no-repeat 0 -{$y}px; text-indent: -9999px;'>{$pin['title']}</a>";
			$c = ($c+1) % $numStrips;
			
			$y += $thumb_h;
		}
		// Save the sprite
		$ok = imagejpeg($spriteIm, $this->get_sprite_path());
		imagedestroy($spriteIm);
		if (!$ok)
			return false;
		
		echo "<div class='pinboard' style='width: {$pinboard_inner_width}px; margin: 10px auto; padding: 0px; background-color: none;'>";
			foreach ($cols as $i => $c) {
				echo "<div class='col' style='width: {$stripW}px; float: left; margin: 0; padding: 0'>";
				echo implode('', $c);
				echo "</div>";
			}
			//echo "<div style='clear: both;'>&nbsp;</div>";
		echo "</div>";
		
		$this->log("build_pinboard: end");
		
		return true;
	}
	
	function get_image_url($itemDesc) {
		$imgSrc = array();
		preg_match('/src="([^"]+)"/', $itemDesc, $imgSrc);
		return $imgSrc[1];
	}
	
	function cache_is_invalid($cache_life) {
		return
			!file_exists($this->get_markup_path()) ||
			!file_exists($this->get_sprite_path()) ||
			filemtime($this->get_markup_path()) + $cache_life <= time() // Consider last build timestamp
		;
	}
	
	function fetch_feed($url, $max_items) {
		$this->log("Fetching $url");
		$rsp = wp_remote_get($url);
		if (is_wp_error($rsp))
			return NULL;
		
		if (wp_remote_retrieve_response_code($rsp) != 200)
			return NULL;
		
		$feed_str = wp_remote_retrieve_body($rsp);
		
		$this->log("Parsing feed: start");
		$rss = simplexml_load_string($feed_str);
		if (!$rss)
			return NULL;

		$pins = array();
		$i = 0;
		foreach ($rss->channel->item as $item) {
			$pins[] = array(
				'title' => (string)$item->title,
				'link' =>(string)$item->link,
				'image_url' => $this->get_image_url((string)$item->description)
			);
			if (++$i == $max_items)
				break;
		}
		
		$this->log("Parsing feed: end");
		
		return $pins;
	}
	
	function is_valid_pinterest_username($username) {
		$rsp = wp_remote_get("http://pinterest.com/$username/", 
			array(
				'timeout' => 60, 
				'redirection' => 30
		));
		
		if (is_wp_error($rsp))
			return "Something went bad in trying to validate user $username: " . $rsp->get_error_message();
		
		if (wp_remote_retrieve_response_code($rsp) == 404)
			return "User $username does not exist on Pinterest";
		
		return 'ok';
	}
	
	function board_validation($board_name, $username) {
		$board_name = strtolower($board_name);
		$board_name = str_replace(' ', '-', $board_name);
				
		$rsp = wp_remote_get("http://pinterest.com/$username/$board_name.rss");

		if (is_wp_error($rsp))
			return array('status' => $rsp->get_error_message);
		
		if (wp_remote_retrieve_response_code($rsp) == 404)
			return array('status' => "Board not found");
		
		return array('status' => 'ok', 'sanitized' => $board_name);
	}
	
	function create_image_from_url($url) {
		$rsp = wp_remote_get($url);
		if (is_wp_error($rsp))
			return NULL;
		
		if (wp_remote_retrieve_response_code($rsp) != 200)
			return NULL;
		
		$img_data = wp_remote_retrieve_body($rsp);
		return imagecreatefromstring($img_data);
	}
	
	function log($msg) {
		if ($this->logging_enabled)
			trigger_error("mg-pinterest-strips: $msg", E_USER_NOTICE);
	}
}

register_activation_hook(__FILE__, 'mg_pinterest_strips_on_activation');
add_action('widgets_init', create_function('', 'register_widget("mg_Pinterest_Strips");'));

function mg_pinterest_strips_on_activation() {
	if (
		version_compare(get_bloginfo('version'), '2.8', '<') ||
		version_compare(phpversion(), '5.2.4' , '<') ||
		!extension_loaded('gd') ||
		!extension_loaded('simplexml')
	)
		deactivate_plugins(basename(__FILE__));
}