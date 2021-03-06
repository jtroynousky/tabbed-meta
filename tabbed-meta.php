<?php

/**
 * Plugin Name: Tabbed Meta
 * Description: Easy custom meta field creation that can be organized into tabs
 * Author: Micah Ernst
 * AUthor URI: http://micahernst.com
 */

require_once( dirname( __FILE__ ) . '/fields.php' );

if( !class_exists( 'NS_Tabbed_Meta' ) ) :

class NS_Tabbed_Meta {

	/**
	 * Keep track of meta boxes
	 */
	var $meta_boxes = array();

 	/**
 	 * Setup our hooks and filters
 	 */
 	function __construct() {
 		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 1 );
 		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
 		add_action( 'edit_form_advanced', array( $this, 'add_nonce' ) );
 		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
 	}

 	/**
 	 * Add some scripts and styles to the page
 	 *
 	 */
 	public function scripts() {
 		wp_enqueue_style( 'tabbed-meta', plugins_url( 'css/screen.css', __FILE__ ) );
 		wp_enqueue_style( 'jquery-ui-css', plugins_url( 'css/jquery-ui.css', __FILE__ ) );
 		wp_enqueue_script( 'tabbed-meta', plugins_url( 'js/main.js', __FILE__ ), array(
			'jquery',
			'jquery-ui-core',
			'jquery-ui-datepicker',
			'underscore'
 		));
 	}

 	/**
 	 * Add a nonce for our fields
 	 */
 	public function add_nonce() {
 		wp_nonce_field( 'tm_fields', 'tm_fields_nonce' );
 	}

 	/**
 	 * Add all the meta boxes for the current post type
 	 */
 	public function add_meta_boxes( $post_type ) {

 		// make sure there are boxes to add first
 		if( !isset( $this->meta_boxes[$post_type] ) )
 			return;

		foreach( $this->meta_boxes[$post_type] as $slug => $options ) {

			// tabbed style
			if( isset( $options['tabs'] ) ) {
				$func = function( $classes ) { 
					$classes[] = 'tm-metabox has-panels';
					return $classes;
				};

			// other style
			} else {
				$func = function( $classes ) { 
					$classes[] = 'tm-metabox';
					return $classes;
				};
			}

			$context = isset( $options['context'] ) ? $options['context'] : 'normal';

			// put a class on the metabox so we can style it better
			add_filter( "postbox_classes_{$post_type}_{$slug}", $func );
			add_meta_box( $slug, $options['label'], array( $this, 'meta_box' ), $post_type, 'normal', 'low', array( 'options' => $options ) );
		}
 		
 	}

 	/**
 	 * Build a meta box based on the passed options
 	 *
 	 * @param array $options
 	 * @return void
 	 */
 	public function meta_box( $post, $metabox ) {

 		// get the passed options
 		$options = $metabox['args']['options'];

 		// detect if this meta box has multiple groups or not
 		if( isset( $options['tabs'] ) ) {

 			// start building menu html
 			$menu_html = '<div class="tm-menu-bg"></div><ul class="tm-menu">';

 			// start field html
 			$field_html = '<div class="tm-field-group-container">';

 			// keep track of the number of groups
 			$i = 0;

 			foreach( $options['tabs'] as $tab => $tab_options ) {

 				// default to displaying first field group
 				$class = $i == 0 ? 'selected' : '';

 				// add the group menus
 				$menu_html .= '<li class="' . esc_attr( $class ) . '" data-tab="' . esc_attr( $tab ) . '">' . esc_html( $tab_options['label'] ) . '</li>';

 				// start field group
 				$field_html .= '<div class="tm-field-group '.esc_attr( $class ).'" data-tab="' . esc_attr( $tab ) . '">';

 				foreach( $tab_options['fields'] as $name => $args ) {

 					// create field itself
 					$field_html .= $this->get_field( $post->ID, $name, $args );
 				}

 				// close field group
 				$field_html .= '</div>';

 				$i++;
 			}

 			// close menu html
 			$menu_html .= '</ul>';

 			// close field html
 			$field_html .= '</div>';

 			echo $menu_html . $field_html;

 		// otherwise assume just a collection of fields
 		} elseif( isset( $options['fields'] ) ) {

 			echo '<div class="tm-field-group-container">';

 			foreach( $options['fields'] as $name => $args ) {
 				echo $this->get_field( $post->ID, $name, $args );
 			}

 			echo '</div>';
 		}
 	}

 	/**
 	 * Add a single field to a meta box
 	 *
 	 * @param array $field
 	 * @return string $html
 	 */
 	public function get_field( $post_id, $name = '', $args = array() ) {

 		// name is required
 		if( empty( $name ) ) return;

 		$html = '';

 		// stuff name in args
 		$args['name'] = $name;

 		// default value
 		$default = !empty( $args['default'] ) ? $args['default'] : '';

 		// get field value
 		$value = get_post_meta( $post_id, $name, true );

 		// if the value is empty, set it to the default
 		$args['value'] = !isset( $value ) ? $default : $value;

 		// default field type is text
 		$field_type = isset( $args['type'] ) ? $args['type'] : 'text';

 		// build part of the field
 		$html .= '<div class="tm-field tm-field-'.esc_attr( $field_type ).'">';

 		// add label
 		if( !empty( $args['label'] ) ) {
 			$html .= '<label>'.esc_html( $args['label'] ).'</label>';
 		}
 
 		$func = 'NS_Tabbed_Meta_' . $field_type . '_Field::render';

 		// call method to build field
 		if( is_callable( $func ) ) {
 			$html .= call_user_func_array( $func, array( $args ) );
 		}

 		// add some help text
 		if( !empty( $args['help'] ) ) {
 			$html .= '<div class="help howto">' . wp_kses_post( $args['help'] ) . '</div>';
 		}

 		// close field
 		$html .= '</div>';

 		return $html;
 	}

 	/**
 	 * Save all of the custom fields we've added
 	 *
 	 * @param int $post_id
 	 * @param object $post
 	 */
 	public function save_post( $post_id, $post ) {

 		if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;
	
		if( !current_user_can( 'edit_post', $post_id ) )
			return;
		
		if( wp_is_post_revision( $post_id ) )
			return;
		
		if( !isset( $_POST['tm_fields_nonce'] ) )
			return;
		
		// check our nonce
		if( !wp_verify_nonce( $_POST['tm_fields_nonce'], 'tm_fields' ) )
			return;

		// only try to save fields for current post type
		if( !isset( $this->meta_boxes[$post->post_type] ) )
			return;

		// only try to save the posts for this post_type
		$fields = $this->get_fields_for_post_type( $post->post_type );

		foreach( $fields as $name => $options ) {

			// allow some fields to be skipped/ignored
			if( isset( $options['ignore'] ) && $options['ignore'] )
				continue;

			$type = isset( $options['type'] ) ? $options['type'] : 'text';

			$func = 'NS_Tabbed_Meta_' . $type . '_Field::save';

	 		// save field
	 		if( is_callable( $func ) ) 
	 			call_user_func_array( $func, array( $post_id, $name, $post, $options ) );
		 		
		}
 	}

 	/**
 	 * Helper method to get the fields for a given post type
 	 *
 	 * @param string $type
 	 * @return array $fields
 	 */
 	public function get_fields_for_post_type( $type ) {

 		$fields = array();

 		$meta_boxes = $this->meta_boxes[$type];

 		foreach( $meta_boxes as $meta_box ) {
 			if( isset( $meta_box['tabs'] ) ) {
 				foreach( $meta_box['tabs'] as $group ) {
 					foreach( $group['fields'] as $key => $value ) {
 						$fields[$key] = $value;
 					}
 				}
 			} else {
 				foreach( $meta_box['fields'] as $key => $value ) {
 					$fields[$key] = $value;
 				}
 			}
 		}

 		return $fields;
 	}

 	/**
 	 * Add a meta box to our instance
 	 *
 	 * @param string $key
 	 * @param string|array $post_types
 	 * @param array $args
 	 */
 	public function add_meta_box( $key, $post_types, $args ) {
 		$post_types = (array) $post_types;
 		foreach( $post_types as $type ) {
 			$this->meta_boxes[$type][$key] = $args;
 		}
 	}

 	/**
 	 * Add just a field if you've already defined a meta box
 	 *
 	 * @param string $key
 	 * @param string $meta_box
 	 * @param string|array $post_types
 	 * @param array $args
 	 */
 	public function add_field( $key, $meta_box, $post_types, $args ) {
 		$post_types = (array) $post_types;
 		foreach( $post_types as $type ) {
 			if( isset( $this->meta_boxes[$type][$meta_box] ) ) {
 				$this->meta_boxes[$type][$meta_box]['fields'][$key] = $args;
 			}
 		}
 	}
}

endif;

/**
 * Sample Usage
 */

/*
add_action( 'init', function(){

	register_post_type( 'cat', array(
		'public' => true,
		'labels' => array(
			'name' => 'Cats',
			'singular_name' => 'Cat'
		)
	));

	$tm = new NS_Tabbed_Meta();

	// key, post types, options
	$tm->add_meta_box( 'cat_options', 'cat', array(
		'label' => 'Cat Options',
		'fields' => array(
			'food' => array(
				'label' => 'Food',
				'help' => 'What does your kitty like to eat?'
			),
			'color' => array(
				'label' => 'Color'
			),
			'breed' => array(
				'label' => 'Breed'
			),
			'picker' => array(
				'label' => 'Picker',
				'type' => 'post_picker'
			)
		)
	));

	$tm->add_field( 'text_field', 'cat_options', 'cat', array(
		'label' => 'Different Added Field'
	));

	// grouped meta box
	$tm->add_meta_box( 'more_options', 'cat', array(
		'label' => 'More Options',
		'tabs' => array(
			'toys' => array(
				'label' => 'Toys',
				'fields' => array(
					'favorite_toy' => array(
						'label' => 'Favorite Toy'
					)
				)
			),
			'spots' => array(
				'label' => 'Spots',
				'fields' => array(
					'favorite_spot' => array(
						'label' => 'Favorite Spot'
					),
					'another_field' => array(
						'label' => 'Derp'
					)
				)
			)
		)
	));
});
*/