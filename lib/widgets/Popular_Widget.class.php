<?php

class Eab_Popular_Widget extends Eab_Widget {
	
	private $_defaults;
    
    function __construct () {
    	$this->_defaults = apply_filters('eab-widgets-popular-default_fields', array( 
			'title' => __('PSE-Populäre Events', $this->translation_domain),
			'excerpt' => false,
			'excerpt_words_limit' => false,
			'thumbnail' => false,
			'limit' => 5,
		));
		$widget_ops = array(
			'description' => __('Liste beliebter Ereignisse anzeigen', $this->translation_domain)
		);
        $control_ops = array(
        	'title' => __('Populäre Events', $this->translation_domain)
        );
		parent::__construct('psource_event_popular', __('Die beliebtesten Veranstaltungen', $this->translation_domain), $widget_ops, $control_ops);
    }
    
    function widget ($args, $instance) {
		global $wpdb, $current_site, $post, $wiki_tree;

		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] )  ? $args['after_widget']  : '';
		$before_title  = isset( $args['before_title'] )  ? $args['before_title']  : '';
		$after_title   = isset( $args['after_title'] )   ? $args['after_title']   : '';
		
		$instance = apply_filters('eab-widgets-popular-instance_read', $instance, $this);
		$options = wp_parse_args((array)$instance, $this->_defaults);

		if ( isset( $instance['title'] ) && !empty( $instance['title'] ) ) {
			$options['title'] = strip_tags( $instance['title'] );
		}
		
		$title = apply_filters('widget_title', empty( $options['title'] ) ? __('Am beliebtesten', $this->translation_domain) : $options['title'], $instance, $this->id_base);
		
		$_events = Eab_CollectionFactory::get_popular_events(array(
			'posts_per_page' => $options['limit'],
			'_avoid_pgp_action'    => 1, // Avoids the later pre_get_posts action for Archive pagination
		));
		if (is_array($_events) && count($_events) > 0) {
		?>
		<?php echo wp_kses_post( $before_widget ); ?>
		<?php echo wp_kses_post( $before_title ) . esc_html( $title ) . wp_kses_post( $after_title ); ?>
	            <div id="event-popular">
			<ul>
			    <?php
				foreach ($_events as $_event) {
					$thumbnail = $excerpt = false;
					if ($options['thumbnail']) {
						$raw = wp_get_attachment_image_src(get_post_thumbnail_id($_event->get_id()));
						$thumbnail = $raw ? @$raw[0] : false;
					}
					$excerpt = false;
					if ($options['excerpt']) {
						$words = (int)$options['excerpt_words_limit'] ? (int)$options['excerpt_words_limit'] : false;
						$excerpt = eab_call_template('util_words_limit', $_event->get_excerpt_or_fallback(), $words);
					}
			    ?>
				<li>
					<a href="<?php echo esc_url( get_permalink( $_event->get_id() ) ); ?>" class="<?php echo ( $post instanceof WP_Post && $_event->get_id() == $post->ID ) ? 'current' : 'not_current'; ?>" >
						<?php if ($options['thumbnail'] && $thumbnail) { ?>
							<img src="<?php echo esc_url( $thumbnail ); ?>" /><br />
						<?php } ?>
						<?php echo esc_html( $_event->get_title() ); ?>
					</a>
					<?php if ($options['excerpt'] && $excerpt) { ?>
						<p><?php echo wp_kses_post( $excerpt ); ?></p>
					<?php } ?>
					<?php do_action('eab-widgets-popular-after_event', $options, $_event, $this); ?>
				</li>
			    <?php
				}
			    ?>
			</ul>
	            </div>
	        <br />
	        <?php echo wp_kses_post( $after_widget ); ?>
		<?php
		} else {
			echo wp_kses_post( $before_widget ) .
				wp_kses_post( $before_title ) . esc_html( $title ) . wp_kses_post( $after_title ) .
				'<p class="eab-widget-no_events">' . esc_html__('Keine beliebten Veranstaltungen.', 'eab') . '</p>' .
			wp_kses_post( $after_widget );
		}
    }
    
    function update ($new_instance, $old_instance) {
		$instance = $old_instance;
        $new_instance = wp_parse_args((array)$new_instance, $this->_defaults);
        
		$instance['title'] 					= isset( $new_instance['title'] ) ? strip_tags($new_instance['title']) : '';
        $instance['excerpt'] 				= isset( $new_instance['excerpt'] ) ? (int)$new_instance['excerpt'] : 0;
        $instance['excerpt_words_limit'] 	= isset( $new_instance['excerpt_words_limit'] ) ? (int)$new_instance['excerpt_words_limit'] : 0;
        $instance['thumbnail'] 				= isset( $new_instance['thumbnail'] ) ? (int)$new_instance['thumbnail'] : 0;
        $instance['limit'] 					= isset( $new_instance['limit'] ) ? (int)$new_instance['limit'] : 0;

        $instance = apply_filters('eab-widgets-popular-instance_update', $instance, $new_instance, $this);
	
        return $instance;
    }
    
    function form ($instance) {
    	$instance 			= apply_filters('eab-widgets-popular-instance_read', $instance, $this);
		$options 			= wp_parse_args((array)$instance, $this->_defaults);
		if ( isset( $instance['title'] ) && !empty( $instance['title'] ) ) {
			$options['title'] = strip_tags( $instance['title'] );
		}
	
	?>
	<div style="text-align:left">
        <label for="<?php echo $this->get_field_id('title'); ?>" style="line-height:35px;display:block;"><?php _e('Titel', $this->translation_domain); ?>:<br />
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $options['title']; ?>" type="text" style="width:95%;" />
            </label>
            <label for="<?php echo $this->get_field_id('excerpt'); ?>" style="display:block;">
				<input type="checkbox" 
					id="<?php echo $this->get_field_id('excerpt'); ?>" 
					name="<?php echo $this->get_field_name('excerpt'); ?>" 
					value="1" <?php echo ($options['excerpt'] ? 'checked="checked"' : ''); ?> 
				/>
            	<?php _e('Auszug anzeigen', $this->translation_domain); ?>
            </label>
            <label for="<?php echo $this->get_field_id('excerpt_words_limit'); ?>" style="display:block; margin-left:1.8em">
            	<?php _e('Beschränke den Auszug auf so viele Wörter <small>(<code>0</code> für kein Limit)</small>:', $this->translation_domain); ?>
				<input type="text" 
					size="2"
					id="<?php echo $this->get_field_id('excerpt_words_limit'); ?>" 
					name="<?php echo $this->get_field_name('excerpt_words_limit'); ?>" 
					value="<?php echo (int)$options['excerpt_words_limit']; ?>"
				/>
            </label>
            <label for="<?php echo $this->get_field_id('thumbnail'); ?>" style="display:block;">
				<input type="checkbox" 
					id="<?php echo $this->get_field_id('thumbnail'); ?>" 
					name="<?php echo $this->get_field_name('thumbnail'); ?>" 
					value="1" <?php echo ($options['thumbnail'] ? 'checked="checked"' : ''); ?> 
				/>
            	<?php _e('Miniaturansicht anzeigen', $this->translation_domain); ?>
            </label>
            <label for="<?php echo $this->get_field_id('limit'); ?>" style="line-height:35px;display:block;">
            	<?php _e('Limit', $this->translation_domain); ?>:
				<select id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>">
					<?php for ($i=1; $i<=10; $i++) { ?>
						<?php $selected = ($i == $options['limit']) ? 'selected="selected"' : ''; ?>
						<option value="<?php echo $i; ?>" <?php echo $selected;?>><?php echo $i;?></option>
					<?php } ?>
				</select> 
            </label>
            <?php do_action('eab-widgets-popular-widget_form', $options, $this); ?>
	</div>
	<?php
    }
}
