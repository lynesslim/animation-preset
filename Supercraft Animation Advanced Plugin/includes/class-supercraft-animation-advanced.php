<?php
/**
 * Core plugin class to register assets and future Elementor controls.
 *
 * @package SupercraftAnimationAdvanced
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Supercraft_Animation_Advanced {

	/**
	 * Collected interactions for the current page render.
	 *
	 * @var array
	 */
	protected static $interactions = array();

	/**
	 * Number of elements scanned for interactions (debug aid).
	 *
	 * @var int
	 */
	protected static $scanned = 0;

	/**
	 * Track elements that already received controls to avoid duplicates.
	 *
	 * @var array
	 */
	protected static $controls_added_for = array();

	/**
	 * Hook everything.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor' ) );
		add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/section/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/column/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/container/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/frontend/element/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/section/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/column/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/container/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/element/after_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/widget/after_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/section/after_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/column/after_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/container/after_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'wp_footer', array( $this, 'print_interactions_payload' ), 5 );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
	}

	/**
	 * Register core assets.
	 */
	public function register_scripts() {
		// Core GSAP CDN; replace with self-hosted if preferred.
		wp_register_script(
			'saa-gsap',
			'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
			array(),
			'3.12.5',
			true
		);

		wp_register_script(
			'saa-scrolltrigger',
			'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js',
			array( 'saa-gsap' ),
			'3.12.5',
			true
		);

		wp_register_script(
			'saa-frontend',
			SAA_PLUGIN_URL . 'assets/js/front-end.js',
			array( 'saa-gsap', 'saa-scrolltrigger' ),
			SAA_VERSION,
			true
		);

		wp_register_script(
			'saa-editor',
			SAA_PLUGIN_URL . 'assets/js/editor.js',
			array( 'jquery', 'elementor-editor' ),
			SAA_VERSION,
			true
		);
	}

	/**
	 * Enqueue on the public side when Elementor content is present.
	 */
	public function enqueue_frontend() {
		wp_enqueue_script( 'saa-frontend' );
	}

	/**
	 * Enqueue inside Elementor editor for custom controls.
	 */
	public function enqueue_editor() {
		wp_enqueue_script( 'saa-editor' );
	}

	/**
	 * Add interaction controls to all Elementor elements (advanced tab).
	 *
	 * @param \Elementor\Controls_Stack $element    The element instance.
	 * @param string                    $section_id Section identifier.
	 */
	public function register_element_controls( $element, $section_id ) {
		$element_key = '';

		if ( method_exists( $element, 'get_unique_name' ) ) {
			$element_key = $element->get_unique_name();
		} elseif ( method_exists( $element, 'get_id' ) ) {
			$element_key = $element->get_id();
		} elseif ( method_exists( $element, 'get_name' ) ) {
			$element_key = $element->get_name();
		}

		if ( $element_key && in_array( $element_key, self::$controls_added_for, true ) ) {
			return;
		}

		$element->start_controls_section(
			'saa_interactions_section',
			array(
				'label' => __( 'Supercraft Animation Advance', 'supercraft-animation-advanced' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->add_control(
			'saa_enable_interactions',
			array(
				'label'        => __( 'Enable Interactions', 'supercraft-animation-advanced' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'supercraft-animation-advanced' ),
				'label_off'    => __( 'No', 'supercraft-animation-advanced' ),
				'return_value' => 'yes',
				'default'      => '',
				'frontend_available' => true,
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'saa_trigger',
			array(
				'label'   => __( 'Trigger', 'supercraft-animation-advanced' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'load',
				'options' => array(
					'load'   => __( 'On Load', 'supercraft-animation-advanced' ),
					'inview' => __( 'On Scroll Into View', 'supercraft-animation-advanced' ),
					'click'  => __( 'On Click/Tap', 'supercraft-animation-advanced' ),
				),
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_target_mode',
			array(
				'label'   => __( 'Target', 'supercraft-animation-advanced' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'self',
				'options' => array(
					'self'     => __( 'This Element', 'supercraft-animation-advanced' ),
					'selector' => __( 'Custom Selector', 'supercraft-animation-advanced' ),
				),
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_target_selector',
			array(
				'label'     => __( 'CSS Selector', 'supercraft-animation-advanced' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => '',
				'condition' => array(
					'saa_target_mode' => 'selector',
				),
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_trigger_selector',
			array(
				'label'       => __( 'Trigger Element Selector', 'supercraft-animation-advanced' ),
				'description' => __( 'If empty, target selector is used as the trigger.', 'supercraft-animation-advanced' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'condition'   => array(
					'saa_trigger' => 'click',
				),
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_play_once',
			array(
				'label'        => __( 'Play Once', 'supercraft-animation-advanced' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'supercraft-animation-advanced' ),
				'label_off'    => __( 'No', 'supercraft-animation-advanced' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array(
					'saa_trigger' => 'inview',
				),
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_preset',
			array(
				'label'   => __( 'Preset', 'supercraft-animation-advanced' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'fade-up',
				'options' => array(
					'fade-in'  => __( 'Fade In', 'supercraft-animation-advanced' ),
					'fade-up'  => __( 'Fade Up', 'supercraft-animation-advanced' ),
					'slide-up' => __( 'Slide Up', 'supercraft-animation-advanced' ),
					'zoom-in'  => __( 'Zoom In', 'supercraft-animation-advanced' ),
				),
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_duration',
			array(
				'label'   => __( 'Duration (s)', 'supercraft-animation-advanced' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0,
				'step'    => 0.05,
				'default' => 0.6,
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_delay',
			array(
				'label'   => __( 'Delay (s)', 'supercraft-animation-advanced' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0,
				'step'    => 0.05,
				'default' => 0,
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_ease',
			array(
				'label'   => __( 'Ease', 'supercraft-animation-advanced' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'power2.out',
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_interactions',
			array(
				'label'       => __( 'Supercraft Animation Advance', 'supercraft-animation-advanced' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ saa_trigger }}} → {{{ saa_preset }}}',
				'condition'   => array(
					'saa_enable_interactions' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->end_controls_section();

		if ( $element_key ) {
			self::$controls_added_for[] = $element_key;
		}
	}

	/**
	 * Collect interactions from element settings during render.
	 *
	 * @param \Elementor\Element_Base $element The element instance.
	 */
	public function collect_element_interactions( $element ) {
		if ( ! $element instanceof \Elementor\Element_Base ) {
			return;
		}

		$settings = $element->get_settings_for_display();
		if ( empty( $settings['saa_interactions'] ) && method_exists( $element, 'get_settings' ) ) {
			$settings = $element->get_settings();
		}
		if ( empty( $settings['saa_interactions'] ) && method_exists( $element, 'get_data' ) ) {
			$data = $element->get_data();
			if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
				$settings = $data['settings'];
			}
		}

		if ( empty( $settings['saa_enable_interactions'] ) || empty( $settings['saa_interactions'] ) || ! is_array( $settings['saa_interactions'] ) ) {
			self::$scanned++;
			return;
		}

		self::$scanned++;

		foreach ( $settings['saa_interactions'] as $interaction ) {
			if ( empty( $interaction['saa_trigger'] ) ) {
				continue;
			}

			$target_selector = $this->resolve_selector( $element, $interaction );

			if ( ! $target_selector ) {
				continue;
			}

			$trigger_selector = '';

			if ( 'click' === $interaction['saa_trigger'] ) {
				$trigger_selector = ! empty( $interaction['saa_trigger_selector'] ) ? sanitize_text_field( $interaction['saa_trigger_selector'] ) : $target_selector;
			}

			self::$interactions[] = array(
				'trigger'          => sanitize_key( $interaction['saa_trigger'] ),
				'selector'         => $target_selector,
				'triggerSelector'  => $trigger_selector,
				'once'             => isset( $interaction['saa_play_once'] ) && 'yes' === $interaction['saa_play_once'],
				'preset'           => isset( $interaction['saa_preset'] ) ? sanitize_key( $interaction['saa_preset'] ) : 'fade-up',
				'duration'         => isset( $interaction['saa_duration'] ) ? floatval( $interaction['saa_duration'] ) : 0.6,
				'delay'            => isset( $interaction['saa_delay'] ) ? floatval( $interaction['saa_delay'] ) : 0,
				'ease'             => isset( $interaction['saa_ease'] ) ? sanitize_text_field( $interaction['saa_ease'] ) : 'power2.out',
			);
		}
	}

	/**
	 * Resolve selector based on interaction settings.
	 *
	 * @param \Elementor\Element_Base $element     Elementor element.
	 * @param array                   $interaction Interaction data.
	 *
	 * @return string
	 */
	protected function resolve_selector( $element, $interaction ) {
		if ( isset( $interaction['saa_target_mode'] ) && 'selector' === $interaction['saa_target_mode'] && ! empty( $interaction['saa_target_selector'] ) ) {
			return sanitize_text_field( $interaction['saa_target_selector'] );
		}

		$id = $element->get_id();

		if ( ! $id ) {
			return '';
		}

		return '.elementor-element-' . sanitize_html_class( $id );
	}

	/**
	 * Print payload before footer scripts so front-end JS can read it.
	 */
	public function print_interactions_payload() {
		if ( empty( self::$interactions ) ) {
			printf( "\n<!-- SAA: no interactions collected; scanned %d elements -->\n", intval( self::$scanned ) );
			return;
		}

		$data = wp_json_encode(
			array(
				'interactions' => self::$interactions,
			)
		);

		if ( ! $data ) {
			return;
		}

		printf(
			'<script id="saa-data" type="application/json">%s</script>',
			$data
		);
	}

	/**
	 * Add a minimal settings/info page.
	 */
	public function register_admin_page() {
		add_menu_page(
			__( 'Supercraft Animations', 'supercraft-animation-advanced' ),
			__( 'Supercraft Animations', 'supercraft-animation-advanced' ),
			'manage_options',
			'supercraft-animation-advanced',
			array( $this, 'render_admin_page' ),
			'dashicons-controls-play',
			80
		);
	}

	/**
	 * Render admin info page.
	 */
	public function render_admin_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Supercraft Animation Advanced', 'supercraft-animation-advanced' ); ?></h1>
			<p>
				<?php
				esc_html_e(
					'This is a stub admin page. Elementor controls for triggers, targets, and GSAP presets will appear inside the editor.',
					'supercraft-animation-advanced'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if the current query is rendering Elementor-built content.
	 *
	 * @return bool
	 */
	protected function has_elementor_content() {
		if ( function_exists( 'elementor_location_exists' ) && elementor_location_exists( 'single' ) ) {
			return true;
		}

		if ( function_exists( 'is_elementor_page' ) && is_elementor_page() ) {
			return true;
		}

		return false;
	}
}
