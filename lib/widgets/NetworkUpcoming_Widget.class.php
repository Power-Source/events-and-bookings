<?php

class Eab_NetworkUpcoming_Widget extends Eab_Widget {
	
	function __construct () {
		$widget_ops = array(
			'classname' => __CLASS__, 
			'description' => __('Zeigt eine Liste der bevorstehenden Ereignisse aus Deinem gesamten Netzwerk an', $this->translation_domain),
		);
		parent::__construct(__CLASS__, __('PSE-Netzwerk bevorstehend', $this->translation_domain), $widget_ops);
	}
	
	function form ($instance) {
		$html = '';
		$title = isset( $instance['title'] ) ? esc_attr($instance['title'])  : '';
		$limit = isset( $instance['limit'] ) ? esc_attr($instance['limit']) : '';
		
		$html .= '<p>';
		$html .= '<label for="' . $this->get_field_id('title') . '">' . __('Titel:', 'eab') . '</label>';
		$html .= '<input type="text" name="' . $this->get_field_name('title') . '" id="' . $this->get_field_id('title') . '" class="widefat" value="' . $title . '"/>';
		$html .= '</p>';

		$html .= '<p>';
		$html .= '<label for="' . $this->get_field_id('limit') . '">' . __('Nur so viele Ereignisse anzeigen:', 'eab') . '</label>';
		$html .= '<select name="' . $this->get_field_name('limit') . '" id="' . $this->get_field_id('limit') . '">';
		for ($i=1; $i<11; $i++) {
			$html .= '<option value="' . $i . '" ' . (($limit == $i) ? 'selected="selected"' : '') . '>' . $i . '</option>';
		}
		$html .= '</select>';
		$html .= '</p>';
		
		echo $html;
	}
	
	function update ($new_instance, $old_instance) {
		$instance = $old_instance;

		$instance['title'] 	= isset( $new_instance['title'] ) ? strip_tags($new_instance['title']) : '';
        $instance['limit'] 	= isset( $new_instance['limit'] ) ? (int)$new_instance['limit'] : 0;

		return $instance;
	}
	
	function widget ($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		$limit = isset( $instance['limit'] ) ? (int)$instance['limit'] : 0;
		
		$events = Eab_Network::get_upcoming_events($limit);
	
		echo $before_widget;
		if ($title) echo $before_title . $title . $after_title;
		if ($events) {
			echo '<ul>';
			foreach ($events as $event) {
				echo '<li><a href="' . 
					get_blog_permalink($event->blog_id, $event->ID) . '">' . $event->post_title . '</a>' .
				'</li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="eab-widget-no_events">' . __('Keine bevorstehenden Ereignisse im Netzwerk', $this->translation_domain) . '</p>';
		}
		echo $after_widget;	
	}
}
