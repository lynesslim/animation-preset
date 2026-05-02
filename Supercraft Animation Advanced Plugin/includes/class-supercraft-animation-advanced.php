<?php
/**
 * Core plugin class to register assets and Elementor controls.
 *
 * @package SupercraftAnimationAdvanced
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Supercraft_Animation_Advanced {

	/**
	 * Collected trigger sequences for the current page render.
	 *
	 * @var array
	 */
	protected static $sequences = array();

	/**
	 * Number of elements scanned for interactions (debug aid).
	 *
	 * @var int
	 */
	protected static $scanned = 0;

	/**
	 * Debug counters.
	 *
	 * @var array
	 */
	protected static $debug = array(
		'enabled'   => 0,
		'collected' => 0,
	);

	/**
	 * Resolve current document/post ID for Elementor content.
	 *
	 * @return int
	 */
	protected function get_document_id() {
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return absint( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$document = \Elementor\Plugin::$instance->documents->get_current();
			if ( $document ) {
				$main_id = $document->get_main_id();
				if ( $main_id ) {
					return (int) $main_id;
				}

				$doc_id = $document->get_id();
				if ( $doc_id ) {
					return (int) $doc_id;
				}
			}
		}

		$queried = get_queried_object_id();
		if ( $queried ) {
			return (int) $queried;
		}

		$post_id = get_the_ID();
		if ( $post_id ) {
			return (int) $post_id;
		}

		global $post;
		if ( isset( $post->ID ) ) {
			return (int) $post->ID;
		}

		return 0;
	}

	/**
	 * Hook everything.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor' ) );
		add_action( 'wp_head', array( $this, 'print_loading_class' ), 1 );
		add_action( 'wp_head', array( $this, 'print_initial_styles' ), 5 );

		add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/common/section_advanced/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/section/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/column/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/container/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/container/section_advanced/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/container/section_layout/after_section_end', array( $this, 'register_element_controls' ), 10, 2 );
		add_action( 'elementor/element/after_section_end', array( $this, 'register_element_controls_fallback' ), 10, 3 );

		add_action( 'elementor/frontend/element/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/section/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/column/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );
		add_action( 'elementor/frontend/container/before_render', array( $this, 'collect_element_interactions' ), 1, 1 );

		add_action( 'wp_footer', array( $this, 'print_interactions_payload' ), 5 );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
	}

	/**
	 * Register core assets.
	 */
	public function register_scripts() {
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
			'saa-splittype',
			'https://unpkg.com/split-type',
			array(),
			'0.3.3',
			true
		);

		wp_register_script(
			'saa-presets',
			SAA_PLUGIN_URL . 'assets/js/presets.js',
			array( 'saa-gsap', 'saa-scrolltrigger', 'saa-splittype' ),
			time(),
			true
		);

		wp_register_script(
			'saa-frontend',
			SAA_PLUGIN_URL . 'assets/js/front-end.js',
			array( 'saa-gsap', 'saa-scrolltrigger' ),
			time(),
			true
		);

		wp_register_style(
			'saa-presets',
			SAA_PLUGIN_URL . 'assets/css/presets.css',
			array(),
			time()
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
	 * Enqueue on public side.
	 */
	public function enqueue_frontend() {
		if ( empty( self::$sequences ) ) {
			$this->collect_from_meta();
		}

		wp_enqueue_style( 'saa-presets' );
		wp_enqueue_script( 'saa-presets' );
		wp_enqueue_script( 'saa-frontend' );
	}

	/**
	 * Enqueue inside Elementor editor.
	 */
	public function enqueue_editor() {
		wp_enqueue_script( 'saa-editor' );
	}

	/**
	 * Register interaction controls in Elementor advanced tab.
	 *
	 * @param \Elementor\Controls_Stack $element Elementor element.
	 * @param string                    $section_id Section id.
	 */
	public function register_element_controls( $element, $section_id ) {
		$controls = $element->get_controls();
		if ( isset( $controls['saa_timeline_steps'] ) || isset( $controls['saa_interactions_section'] ) ) {
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
				'label'              => __( 'Enable Interactions', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'label_on'           => __( 'Yes', 'supercraft-animation-advanced' ),
				'label_off'          => __( 'No', 'supercraft-animation-advanced' ),
				'return_value'       => 'yes',
				'default'            => '',
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_trigger',
			array(
				'label'              => __( 'Trigger', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'inview',
				'options'            => array(
					'load'   => __( 'On Load', 'supercraft-animation-advanced' ),
					'inview' => __( 'On Scroll Into View', 'supercraft-animation-advanced' ),
					'click'  => __( 'On Click/Tap', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_trigger_selector',
			array(
				'label'              => __( 'Trigger Selector (Optional)', 'supercraft-animation-advanced' ),
				'description'        => __( 'Used for click triggers. Empty = this element.', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => '',
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
					'saa_trigger'             => 'click',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_start',
			array(
				'label'              => __( 'Scroll Start', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 80%',
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
					'saa_trigger'             => 'inview',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_end',
			array(
				'label'              => __( 'Scroll End', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'bottom top',
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
					'saa_trigger'             => 'inview',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_scrub',
			array(
				'label'              => __( 'Scrub Timeline', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'label_on'           => __( 'Yes', 'supercraft-animation-advanced' ),
				'label_off'          => __( 'No', 'supercraft-animation-advanced' ),
				'return_value'       => 'yes',
				'default'            => 'yes',
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
					'saa_trigger'             => 'inview',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_one_way',
			array(
				'label'              => __( 'One-Way Scrub', 'supercraft-animation-advanced' ),
				'description'        => __( 'When enabled, scrub progress only moves forward.', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'label_on'           => __( 'Yes', 'supercraft-animation-advanced' ),
				'label_off'          => __( 'No', 'supercraft-animation-advanced' ),
				'return_value'       => 'yes',
				'default'            => '',
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
					'saa_trigger'             => 'inview',
					'saa_scroll_scrub'        => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_play_once',
			array(
				'label'              => __( 'Play Once (Non-Scrub)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'label_on'           => __( 'Yes', 'supercraft-animation-advanced' ),
				'label_off'          => __( 'No', 'supercraft-animation-advanced' ),
				'return_value'       => 'yes',
				'default'            => 'yes',
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
					'saa_trigger'             => 'inview',
					'saa_scroll_scrub!'       => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_batch_scope',
			array(
				'label'              => __( 'Batch Scope', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'container',
				'options'            => array(
					'container' => __( 'Trigger Container First', 'supercraft-animation-advanced' ),
					'global'    => __( 'Global DOM', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_default_stagger',
			array(
				'label'              => __( 'Default Class Stagger', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'min'                => 0,
				'step'               => 0.05,
				'default'            => 0.2,
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
				),
				'frontend_available' => true,
				)
			);

		$preset_ease_options = array(
			'none'               => __( 'Immediate (none)', 'supercraft-animation-advanced' ),
			'linear'             => __( 'Linear', 'supercraft-animation-advanced' ),
			'power1.in'          => __( 'Gentle In (power1.in)', 'supercraft-animation-advanced' ),
			'power1.out'         => __( 'Gentle Out (power1.out)', 'supercraft-animation-advanced' ),
			'power1.inOut'       => __( 'Gentle In-Out (power1.inOut)', 'supercraft-animation-advanced' ),
			'power2.in'          => __( 'Smooth In (power2.in)', 'supercraft-animation-advanced' ),
			'power2.out'         => __( 'Smooth Out (power2.out)', 'supercraft-animation-advanced' ),
			'power2.inOut'       => __( 'Smooth In-Out (power2.inOut)', 'supercraft-animation-advanced' ),
			'power3.in'          => __( 'Strong In (power3.in)', 'supercraft-animation-advanced' ),
			'power3.out'         => __( 'Strong Out (power3.out)', 'supercraft-animation-advanced' ),
			'power3.inOut'       => __( 'Strong In-Out (power3.inOut)', 'supercraft-animation-advanced' ),
			'power4.in'          => __( 'Very Strong In (power4.in)', 'supercraft-animation-advanced' ),
			'power4.out'         => __( 'Very Strong Out (power4.out)', 'supercraft-animation-advanced' ),
			'power4.inOut'       => __( 'Very Strong In-Out (power4.inOut)', 'supercraft-animation-advanced' ),
			'expo.in'            => __( 'Exponential In (expo.in)', 'supercraft-animation-advanced' ),
			'expo.out'           => __( 'Exponential Out (expo.out)', 'supercraft-animation-advanced' ),
			'expo.inOut'         => __( 'Exponential In-Out (expo.inOut)', 'supercraft-animation-advanced' ),
			'circ.in'            => __( 'Circular In (circ.in)', 'supercraft-animation-advanced' ),
			'circ.out'           => __( 'Circular Out (circ.out)', 'supercraft-animation-advanced' ),
			'circ.inOut'         => __( 'Circular In-Out (circ.inOut)', 'supercraft-animation-advanced' ),
			'back.in(1.7)'       => __( 'Overshoot In (back.in)', 'supercraft-animation-advanced' ),
			'back.out(1.7)'      => __( 'Overshoot Out (back.out)', 'supercraft-animation-advanced' ),
			'back.inOut(1.7)'    => __( 'Overshoot In-Out (back.inOut)', 'supercraft-animation-advanced' ),
			'elastic.in(0.5,0.3)' => __( 'Elastic In', 'supercraft-animation-advanced' ),
			'elastic.out(0.5,0.3)' => __( 'Elastic Out', 'supercraft-animation-advanced' ),
			'elastic.inOut(0.5,0.3)' => __( 'Elastic In-Out', 'supercraft-animation-advanced' ),
			'bounce.in'          => __( 'Bounce In', 'supercraft-animation-advanced' ),
			'bounce.out'         => __( 'Bounce Out', 'supercraft-animation-advanced' ),
			'bounce.inOut'       => __( 'Bounce In-Out', 'supercraft-animation-advanced' ),
		);

		$element->add_control(
			'saa_preset_heading',
			array(
				'label'              => __( 'Preset Categories', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::HEADING,
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_anim_category',
			array(
				'label'              => __( 'Animation Category', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => '',
				'options'            => array(
					''                 => __( 'None', 'supercraft-animation-advanced' ),
					'scroll-transform' => __( 'Scroll Transform', 'supercraft-animation-advanced' ),
					'split-text'       => __( 'Split Text', 'supercraft-animation-advanced' ),
					'image-reveal'     => __( 'Image Reveal', 'supercraft-animation-advanced' ),
					'container-reveal' => __( 'Container Reveal', 'supercraft-animation-advanced' ),
					'scroll-fill-text' => __( 'Scroll Fill Text', 'supercraft-animation-advanced' ),
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset',
			array(
				'label'              => __( 'Scroll Transform Preset', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'fade-up',
				'options'            => array(
					'custom'          => __( 'Custom', 'supercraft-animation-advanced' ),
					'fade-left'       => __( 'Fade Left', 'supercraft-animation-advanced' ),
					'fade-right'      => __( 'Fade Right', 'supercraft-animation-advanced' ),
					'fade-up'         => __( 'Fade Up', 'supercraft-animation-advanced' ),
					'fade-down'       => __( 'Fade Down', 'supercraft-animation-advanced' ),
					'zoom-in'         => __( 'Zoom In', 'supercraft-animation-advanced' ),
					'zoom-out'        => __( 'Zoom Out', 'supercraft-animation-advanced' ),
					'blur-fade'       => __( 'Blur Fade', 'supercraft-animation-advanced' ),
					'blur-fade-left'  => __( 'Blur Fade Left', 'supercraft-animation-advanced' ),
					'blur-fade-right' => __( 'Blur Fade Right', 'supercraft-animation-advanced' ),
					'blur-fade-up'    => __( 'Blur Fade Up', 'supercraft-animation-advanced' ),
					'blur-fade-down'  => __( 'Blur Fade Down', 'supercraft-animation-advanced' ),
					'blur-zoom-in'    => __( 'Blur Zoom In', 'supercraft-animation-advanced' ),
					'blur-zoom-out'   => __( 'Blur Zoom Out', 'supercraft-animation-advanced' ),
					'fade'            => __( 'Fade Only', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category' => 'scroll-transform',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_scrub',
			array(
				'label'              => __( 'Enable Scroll Scrub', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'condition'          => array(
					'saa_anim_category' => 'scroll-transform',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_forward',
			array(
				'label'              => __( 'Forward Only', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'condition'          => array(
					'saa_anim_category'      => 'scroll-transform',
					'saa_scroll_preset_scrub' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_trigger',
			array(
				'label'              => __( 'Trigger (e.g. top 85%)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 85%',
				'condition'          => array(
					'saa_anim_category'       => 'scroll-transform',
					'saa_scroll_preset!'      => 'custom',
					'saa_scroll_preset_scrub!' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_delay',
			array(
				'label'              => __( 'Delay (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 0,
				'condition'          => array(
					'saa_anim_category'       => 'scroll-transform',
					'saa_scroll_preset!'      => 'custom',
					'saa_scroll_preset_scrub!' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_duration',
			array(
				'label'              => __( 'Duration (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 1,
				'condition'          => array(
					'saa_anim_category'       => 'scroll-transform',
					'saa_scroll_preset!'      => 'custom',
					'saa_scroll_preset_scrub!' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_ease',
			array(
				'label'              => __( 'Ease', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'power2.out',
				'options'            => $preset_ease_options,
				'condition'          => array(
					'saa_anim_category'       => 'scroll-transform',
					'saa_scroll_preset!'      => 'custom',
					'saa_scroll_preset_scrub!' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$ct_fields = array(
			'start_x'       => array( 'label' => __( 'Start X (px)', 'supercraft-animation-advanced' ), 'default' => 0 ),
			'start_y'       => array( 'label' => __( 'Start Y (px)', 'supercraft-animation-advanced' ), 'default' => 0 ),
			'start_rotate'  => array( 'label' => __( 'Start Rotate (deg)', 'supercraft-animation-advanced' ), 'default' => 0 ),
			'start_scale'   => array( 'label' => __( 'Start Scale', 'supercraft-animation-advanced' ), 'default' => 1 ),
			'start_opacity' => array( 'label' => __( 'Start Opacity', 'supercraft-animation-advanced' ), 'default' => 1 ),
			'start_blur'    => array( 'label' => __( 'Start Blur (px)', 'supercraft-animation-advanced' ), 'default' => 0 ),
			'end_x'         => array( 'label' => __( 'End X (px)', 'supercraft-animation-advanced' ), 'default' => 0 ),
			'end_y'         => array( 'label' => __( 'End Y (px)', 'supercraft-animation-advanced' ), 'default' => 0 ),
			'end_rotate'    => array( 'label' => __( 'End Rotate (deg)', 'supercraft-animation-advanced' ), 'default' => 0 ),
			'end_scale'     => array( 'label' => __( 'End Scale', 'supercraft-animation-advanced' ), 'default' => 1 ),
			'end_opacity'   => array( 'label' => __( 'End Opacity', 'supercraft-animation-advanced' ), 'default' => 1 ),
			'end_blur'      => array( 'label' => __( 'End Blur (px)', 'supercraft-animation-advanced' ), 'default' => 0 ),
		);

		foreach ( $ct_fields as $key => $config ) {
			$element->add_control(
				'saa_ct_' . $key,
				array(
					'label'              => $config['label'],
					'type'               => \Elementor\Controls_Manager::NUMBER,
					'step'               => ( 'start_scale' === $key || 'end_scale' === $key ) ? 0.01 : 1,
					'default'            => $config['default'],
					'condition'          => array(
						'saa_anim_category' => 'scroll-transform',
						'saa_scroll_preset' => 'custom',
					),
					'frontend_available' => true,
				)
			);
		}

		$element->add_control(
			'saa_ct_duration',
			array(
				'label'              => __( 'Custom Duration (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 1,
				'condition'          => array(
					'saa_anim_category'       => 'scroll-transform',
					'saa_scroll_preset'       => 'custom',
					'saa_scroll_preset_scrub!' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_ct_delay',
			array(
				'label'              => __( 'Custom Delay (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 0,
				'condition'          => array(
					'saa_anim_category'       => 'scroll-transform',
					'saa_scroll_preset'       => 'custom',
					'saa_scroll_preset_scrub!' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_ct_ease',
			array(
				'label'              => __( 'Custom Ease', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'power2.out',
				'options'            => $preset_ease_options,
				'condition'          => array(
					'saa_anim_category' => 'scroll-transform',
					'saa_scroll_preset' => 'custom',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_ct_trigger',
			array(
				'label'              => __( 'Custom Trigger', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 85%',
				'condition'          => array(
					'saa_anim_category'       => 'scroll-transform',
					'saa_scroll_preset'       => 'custom',
					'saa_scroll_preset_scrub!' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_scrub_start',
			array(
				'label'              => __( 'Scrub Start', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 85%',
				'condition'          => array(
					'saa_anim_category'      => 'scroll-transform',
					'saa_scroll_preset_scrub' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_scrub_end',
			array(
				'label'              => __( 'Scrub End', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 15%',
				'condition'          => array(
					'saa_anim_category'      => 'scroll-transform',
					'saa_scroll_preset_scrub' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_scroll_preset_scrub_ease',
			array(
				'label'              => __( 'Scrub Ease', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'none',
				'options'            => $preset_ease_options,
				'condition'          => array(
					'saa_anim_category'      => 'scroll-transform',
					'saa_scroll_preset_scrub' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_mode',
			array(
				'label'              => __( 'Split Mode', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'chars',
				'options'            => array(
					'chars' => __( 'Characters', 'supercraft-animation-advanced' ),
					'words' => __( 'Words', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category' => 'split-text',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_variant_char',
			array(
				'label'              => __( 'Character Variant', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'fade-x',
				'options'            => array(
					'fade-x'    => __( 'Fade X', 'supercraft-animation-advanced' ),
					'fade-y'    => __( 'Fade Y', 'supercraft-animation-advanced' ),
					'fade-blur' => __( 'Fade Blur', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category' => 'split-text',
					'saa_split_mode'    => 'chars',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_variant_word',
			array(
				'label'              => __( 'Word Variant', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'fade-x',
				'options'            => array(
					'fade-x'    => __( 'Fade X', 'supercraft-animation-advanced' ),
					'fade-y'    => __( 'Fade Y', 'supercraft-animation-advanced' ),
					'fade-blur' => __( 'Fade Blur', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category' => 'split-text',
					'saa_split_mode'    => 'words',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_scrub',
			array(
				'label'              => __( 'Enable Scroll Scrub', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'condition'          => array(
					'saa_anim_category' => 'split-text',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_forward',
			array(
				'label'              => __( 'Forward Only', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'condition'          => array(
					'saa_anim_category' => 'split-text',
					'saa_split_scrub'   => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_preset',
			array(
				'label'              => __( 'Split Preset', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'medium',
				'options'            => array(
					'custom'   => __( 'Custom', 'supercraft-animation-advanced' ),
					'light'    => __( 'Light', 'supercraft-animation-advanced' ),
					'medium'   => __( 'Medium', 'supercraft-animation-advanced' ),
					'dramatic' => __( 'Dramatic', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category' => 'split-text',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_delay',
			array(
				'label'              => __( 'Split Delay (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 0,
				'condition'          => array(
					'saa_anim_category' => 'split-text',
					'saa_split_scrub!'  => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$split_custom_fields = array(
			'offset_x'      => __( 'Offset X (px)', 'supercraft-animation-advanced' ),
			'offset_y'      => __( 'Offset Y (px)', 'supercraft-animation-advanced' ),
			'stagger'       => __( 'Stagger (s)', 'supercraft-animation-advanced' ),
			'duration'      => __( 'Duration (s)', 'supercraft-animation-advanced' ),
			'opacity_start' => __( 'Opacity Start', 'supercraft-animation-advanced' ),
			'blur_start'    => __( 'Blur Start (px)', 'supercraft-animation-advanced' ),
		);

		foreach ( $split_custom_fields as $key => $label ) {
			$element->add_control(
				'saa_split_' . $key,
				array(
					'label'              => $label,
					'type'               => \Elementor\Controls_Manager::NUMBER,
					'step'               => 0.01,
					'condition'          => array(
						'saa_anim_category' => 'split-text',
						'saa_split_preset'  => 'custom',
					),
					'frontend_available' => true,
				)
			);
		}

		$element->add_control(
			'saa_split_scroll_start',
			array(
				'label'              => __( 'Split Scroll Start', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 85%',
				'condition'          => array(
					'saa_anim_category' => 'split-text',
					'saa_split_scrub'   => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_scroll_end',
			array(
				'label'              => __( 'Split Scroll End', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 40%',
				'condition'          => array(
					'saa_anim_category' => 'split-text',
					'saa_split_scrub'   => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_split_ease',
			array(
				'label'              => __( 'Split Ease', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'power2.out',
				'options'            => $preset_ease_options,
				'condition'          => array(
					'saa_anim_category' => 'split-text',
					'saa_split_preset'  => 'custom',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_image_preset',
			array(
				'label'              => __( 'Image Reveal Preset', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'left',
				'options'            => array(
					'left'   => __( 'Left', 'supercraft-animation-advanced' ),
					'right'  => __( 'Right', 'supercraft-animation-advanced' ),
					'top'    => __( 'Top', 'supercraft-animation-advanced' ),
					'bottom' => __( 'Bottom', 'supercraft-animation-advanced' ),
					'custom' => __( 'Custom', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category' => 'image-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_image_direction',
			array(
				'label'              => __( 'Image Direction', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'left',
				'options'            => array(
					'left'   => __( 'Left', 'supercraft-animation-advanced' ),
					'right'  => __( 'Right', 'supercraft-animation-advanced' ),
					'top'    => __( 'Top', 'supercraft-animation-advanced' ),
					'bottom' => __( 'Bottom', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category' => 'image-reveal',
					'saa_image_preset'  => 'custom',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_image_duration',
			array(
				'label'              => __( 'Image Duration (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 1.5,
				'condition'          => array(
					'saa_anim_category' => 'image-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_image_delay',
			array(
				'label'              => __( 'Image Delay (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 0,
				'condition'          => array(
					'saa_anim_category' => 'image-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_image_ease',
			array(
				'label'              => __( 'Image Ease', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'power2.out',
				'options'            => $preset_ease_options,
				'condition'          => array(
					'saa_anim_category' => 'image-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_image_trigger',
			array(
				'label'              => __( 'Image Trigger', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 85%',
				'condition'          => array(
					'saa_anim_category' => 'image-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_image_scale',
			array(
				'label'              => __( 'Image Scale', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.01,
				'default'            => 1.3,
				'condition'          => array(
					'saa_anim_category' => 'image-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_preset',
			array(
				'label'              => __( 'Container Reveal Preset', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'center',
				'options'            => array(
					'center' => __( 'Center Out', 'supercraft-animation-advanced' ),
					'left'   => __( 'Left', 'supercraft-animation-advanced' ),
					'right'  => __( 'Right', 'supercraft-animation-advanced' ),
					'top'    => __( 'Top', 'supercraft-animation-advanced' ),
					'bottom' => __( 'Bottom', 'supercraft-animation-advanced' ),
					'custom' => __( 'Custom', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category' => 'container-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_scrub',
			array(
				'label'              => __( 'Enable Scroll Scrub', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'condition'          => array(
					'saa_anim_category' => 'container-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_forward',
			array(
				'label'              => __( 'Forward Only', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'condition'          => array(
					'saa_anim_category'   => 'container-reveal',
					'saa_container_scrub' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_direction',
			array(
				'label'              => __( 'Container Direction', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'center',
				'options'            => array(
					'center' => __( 'Center', 'supercraft-animation-advanced' ),
					'left'   => __( 'Left', 'supercraft-animation-advanced' ),
					'right'  => __( 'Right', 'supercraft-animation-advanced' ),
					'top'    => __( 'Top', 'supercraft-animation-advanced' ),
					'bottom' => __( 'Bottom', 'supercraft-animation-advanced' ),
				),
				'condition'          => array(
					'saa_anim_category'   => 'container-reveal',
					'saa_container_preset' => 'custom',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_duration',
			array(
				'label'              => __( 'Container Duration (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 1.2,
				'condition'          => array(
					'saa_anim_category' => 'container-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_delay',
			array(
				'label'              => __( 'Container Delay (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'step'               => 0.1,
				'default'            => 0,
				'condition'          => array(
					'saa_anim_category' => 'container-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_ease',
			array(
				'label'              => __( 'Container Ease', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'power2.out',
				'options'            => $preset_ease_options,
				'condition'          => array(
					'saa_anim_category' => 'container-reveal',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_trigger',
			array(
				'label'              => __( 'Container Trigger', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 85%',
				'condition'          => array(
					'saa_anim_category'   => 'container-reveal',
					'saa_container_scrub!' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_scroll_start',
			array(
				'label'              => __( 'Container Scrub Start', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 85%',
				'condition'          => array(
					'saa_anim_category'   => 'container-reveal',
					'saa_container_scrub' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_container_scroll_end',
			array(
				'label'              => __( 'Container Scrub End', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 20%',
				'condition'          => array(
					'saa_anim_category'   => 'container-reveal',
					'saa_container_scrub' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_fill_start',
			array(
				'label'              => __( 'Scroll Fill Start', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 85%',
				'condition'          => array(
					'saa_anim_category' => 'scroll-fill-text',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_fill_end',
			array(
				'label'              => __( 'Scroll Fill End', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'top 60%',
				'condition'          => array(
					'saa_anim_category' => 'scroll-fill-text',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_fill_base',
			array(
				'label'              => __( 'Base Color (Unfilled)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::COLOR,
				'condition'          => array(
					'saa_anim_category' => 'scroll-fill-text',
				),
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_fill_line',
			array(
				'label'              => __( 'Line by Line', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'condition'          => array(
					'saa_anim_category' => 'scroll-fill-text',
				),
				'frontend_available' => true,
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'saa_target_mode',
			array(
				'label'              => __( 'Target Mode', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'self',
				'options'            => array(
					'self'     => __( 'This Element', 'supercraft-animation-advanced' ),
					'selector' => __( 'Custom Selector', 'supercraft-animation-advanced' ),
				),
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_target_selector',
			array(
				'label'              => __( 'Target Selector', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => '',
				'condition'          => array(
					'saa_target_mode' => 'selector',
				),
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_action',
			array(
				'label'              => __( 'Action Type', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::SELECT,
				'default'            => 'custom',
				'options'            => array(
					'custom'   => __( 'Custom Properties', 'supercraft-animation-advanced' ),
					'fade-in'  => __( 'Preset: Fade In', 'supercraft-animation-advanced' ),
					'fade-up'  => __( 'Preset: Fade Up', 'supercraft-animation-advanced' ),
					'slide-up' => __( 'Preset: Slide Up', 'supercraft-animation-advanced' ),
					'zoom-in'  => __( 'Preset: Zoom In', 'supercraft-animation-advanced' ),
				),
				'frontend_available' => true,
			)
		);

		$channels = array(
			'x'       => 'X',
			'y'       => 'Y',
			'scale'   => 'Scale',
			'opacity' => 'Opacity',
			'rotate'  => 'Rotate',
			'skew_x'  => 'Skew X',
			'skew_y'  => 'Skew Y',
			'blur'    => 'Blur (px)',
		);

		foreach ( $channels as $key => $label ) {
			$repeater->add_control(
				'saa_from_' . $key,
				array(
					'label'              => sprintf( __( 'From %s', 'supercraft-animation-advanced' ), $label ),
					'type'               => \Elementor\Controls_Manager::TEXT,
					'default'            => '',
					'frontend_available' => true,
				)
			);

			$repeater->add_control(
				'saa_to_' . $key,
				array(
					'label'              => sprintf( __( 'To %s', 'supercraft-animation-advanced' ), $label ),
					'type'               => \Elementor\Controls_Manager::TEXT,
					'default'            => '',
					'frontend_available' => true,
				)
			);
		}

		$repeater->add_control(
			'saa_duration',
			array(
				'label'              => __( 'Duration (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'min'                => 0,
				'step'               => 0.05,
				'default'            => 0.6,
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_delay',
			array(
				'label'              => __( 'Delay (s)', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::NUMBER,
				'min'                => 0,
				'step'               => 0.05,
				'default'            => 0,
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_position',
			array(
				'label'              => __( 'Timeline Position', 'supercraft-animation-advanced' ),
				'description'        => __( 'Examples: ">", "<", "+=0.2". Empty uses delay.', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => '',
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_ease',
			array(
				'label'              => __( 'Ease', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => 'power2.out',
				'frontend_available' => true,
			)
		);

		$repeater->add_control(
			'saa_stagger',
			array(
				'label'              => __( 'Stagger Override (s)', 'supercraft-animation-advanced' ),
				'description'        => __( 'Leave empty to auto-stagger class selectors.', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::TEXT,
				'default'            => '',
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'saa_timeline_steps',
			array(
				'label'              => __( 'Timeline Steps', 'supercraft-animation-advanced' ),
				'type'               => \Elementor\Controls_Manager::REPEATER,
				'fields'             => $repeater->get_controls(),
				'title_field'        => '{{{ saa_action }}} => {{{ saa_target_selector }}}',
				'condition'          => array(
					'saa_enable_interactions' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$element->end_controls_section();
	}

	/**
	 * Fallback hook for element types/section IDs (notably Containers in some Elementor versions).
	 *
	 * @param \Elementor\Controls_Stack $element Elementor element.
	 * @param string                    $section_id Current section id.
	 * @param array                     $args Section args.
	 */
	public function register_element_controls_fallback( $element, $section_id, $args ) {
		$allowed_sections = array(
			'section_advanced',
			'_section_style',
			'section_layout',
			'section_effects',
			'section_responsive',
		);

		if ( ! in_array( $section_id, $allowed_sections, true ) ) {
			return;
		}

		$this->register_element_controls( $element, $section_id );
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

		self::$scanned++;

		$settings = $element->get_settings_for_display();
		if ( empty( $settings['saa_timeline_steps'] ) ) {
			$settings = $element->get_settings();
		}

		if ( empty( $settings['saa_timeline_steps'] ) ) {
			$data = $element->get_data();
			if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
				$settings = $data['settings'];
			}
		}

		$this->apply_preset_attributes( $element, $settings );

		if ( empty( $settings['saa_enable_interactions'] ) || 'yes' !== $settings['saa_enable_interactions'] ) {
			return;
		}

		self::$debug['enabled']++;
		$element->add_render_attribute( '_wrapper', 'data-saa-animate', 'true' );

		if ( ! empty( $settings['saa_timeline_steps'] ) && is_array( $settings['saa_timeline_steps'] ) ) {
			$sequence = $this->build_sequence_from_settings( $element, $settings );
			if ( ! empty( $sequence['steps'] ) ) {
				self::$sequences[] = $sequence;
				self::$debug['collected']++;
			}
			return;
		}

		// Backward compatibility for older saved data format (`saa_interactions`).
		if ( ! empty( $settings['saa_interactions'] ) && is_array( $settings['saa_interactions'] ) ) {
			$legacy_sequences = $this->build_legacy_sequences_from_settings( $element, $settings );
			foreach ( $legacy_sequences as $legacy_sequence ) {
				if ( empty( $legacy_sequence['steps'] ) ) {
					continue;
				}
				self::$sequences[] = $legacy_sequence;
				self::$debug['collected']++;
			}
		}
	}

	/**
	 * Build normalized trigger sequence from live Elementor element settings.
	 *
	 * @param \Elementor\Element_Base $element Elementor element.
	 * @param array                   $settings Elementor settings.
	 *
	 * @return array
	 */
	protected function build_sequence_from_settings( $element, $settings ) {
		$element_id = $element->get_id();
		$self_selector = $element_id ? '.elementor-element-' . sanitize_html_class( $element_id ) : '';

		$trigger = isset( $settings['saa_trigger'] ) ? sanitize_key( $settings['saa_trigger'] ) : 'inview';
		$trigger_selector = ! empty( $settings['saa_trigger_selector'] ) ? $this->sanitize_selector( $settings['saa_trigger_selector'] ) : $self_selector;

		$sequence = array(
			'id'              => 'saa-' . sanitize_html_class( (string) $element_id ),
			'source'          => 'render',
			'trigger'         => in_array( $trigger, array( 'load', 'click', 'inview' ), true ) ? $trigger : 'inview',
			'triggerSelector' => $trigger_selector,
			'container'       => $self_selector,
			'scroll'          => array(
				'start' => isset( $settings['saa_scroll_start'] ) ? sanitize_text_field( $settings['saa_scroll_start'] ) : 'top 80%',
				'end'   => isset( $settings['saa_scroll_end'] ) ? sanitize_text_field( $settings['saa_scroll_end'] ) : 'bottom top',
				'scrub' => $this->is_switcher_enabled( isset( $settings['saa_scroll_scrub'] ) ? $settings['saa_scroll_scrub'] : null, true ),
				'once'  => $this->is_switcher_enabled( isset( $settings['saa_play_once'] ) ? $settings['saa_play_once'] : null, true ),
				'oneWay'=> $this->is_switcher_enabled( isset( $settings['saa_scroll_one_way'] ) ? $settings['saa_scroll_one_way'] : null, false ),
			),
			'batch'           => array(
				'scope'          => isset( $settings['saa_batch_scope'] ) && 'global' === $settings['saa_batch_scope'] ? 'global' : 'container',
				'defaultStagger' => isset( $settings['saa_default_stagger'] ) ? floatval( $settings['saa_default_stagger'] ) : 0.2,
			),
			'steps'           => array(),
		);

		foreach ( $settings['saa_timeline_steps'] as $step ) {
			$target_selector = $this->resolve_selector( $element, $step );
			if ( '' === $target_selector ) {
				continue;
			}

			$normalized_step = $this->normalize_step( $step, $target_selector, $sequence['batch']['defaultStagger'] );
			if ( empty( $normalized_step['from'] ) && empty( $normalized_step['to'] ) ) {
				continue;
			}

			$sequence['steps'][] = $normalized_step;
		}

		return $sequence;
	}

	/**
	 * Normalize one timeline step.
	 *
	 * @param array  $step Raw step settings.
	 * @param string $target_selector Target selector.
	 * @param float  $default_stagger Default class stagger value.
	 *
	 * @return array
	 */
	protected function normalize_step( $step, $target_selector, $default_stagger ) {
		$action = isset( $step['saa_action'] ) ? sanitize_key( $step['saa_action'] ) : 'custom';

		$preset = $this->get_action_preset( $action );
		$from = $preset['from'];
		$to = $preset['to'];

		$channels = array(
			'x'       => 'x',
			'y'       => 'y',
			'scale'   => 'scale',
			'opacity' => 'opacity',
			'rotate'  => 'rotate',
			'skew_x'  => 'skewX',
			'skew_y'  => 'skewY',
		);

		foreach ( $channels as $control_suffix => $prop ) {
			$from_key = 'saa_from_' . $control_suffix;
			$to_key = 'saa_to_' . $control_suffix;
			$from_val = isset( $step[ $from_key ] ) ? $this->parse_numeric_or_string( $step[ $from_key ] ) : null;
			$to_val = isset( $step[ $to_key ] ) ? $this->parse_numeric_or_string( $step[ $to_key ] ) : null;

			if ( null !== $from_val ) {
				$from[ $prop ] = $from_val;
			}
			if ( null !== $to_val ) {
				$to[ $prop ] = $to_val;
			}
		}

		$from_blur = isset( $step['saa_from_blur'] ) ? $this->parse_numeric_or_string( $step['saa_from_blur'] ) : null;
		$to_blur = isset( $step['saa_to_blur'] ) ? $this->parse_numeric_or_string( $step['saa_to_blur'] ) : null;
		if ( null !== $from_blur ) {
			$from['filter'] = $this->blur_to_filter( $from_blur );
		}
		if ( null !== $to_blur ) {
			$to['filter'] = $this->blur_to_filter( $to_blur );
		}

		$stagger_raw = isset( $step['saa_stagger'] ) ? trim( (string) $step['saa_stagger'] ) : '';
		$has_stagger_override = '' !== $stagger_raw;
		$stagger = $has_stagger_override ? floatval( $stagger_raw ) : ( $this->is_class_selector( $target_selector ) ? floatval( $default_stagger ) : 0.0 );

		return array(
			'selector' => $target_selector,
			'action'   => $action,
			'from'     => $from,
			'to'       => $to,
			'duration' => isset( $step['saa_duration'] ) ? floatval( $step['saa_duration'] ) : 0.6,
			'delay'    => isset( $step['saa_delay'] ) ? floatval( $step['saa_delay'] ) : 0.0,
			'ease'     => isset( $step['saa_ease'] ) ? sanitize_text_field( $step['saa_ease'] ) : 'power2.out',
			'position' => isset( $step['saa_position'] ) ? $this->sanitize_timeline_position( $step['saa_position'] ) : '',
			'stagger'  => $stagger,
		);
	}

	/**
	 * Resolve selector based on settings.
	 *
	 * @param \Elementor\Element_Base $element Elementor element.
	 * @param array                   $step Step settings.
	 *
	 * @return string
	 */
	protected function resolve_selector( $element, $step ) {
		if ( ! empty( $step['saa_target_selector'] ) ) {
			$selector = $this->sanitize_selector( $step['saa_target_selector'] );
			if ( '' !== $selector ) {
				return $selector;
			}
		}

		if ( isset( $step['saa_target_mode'] ) && 'selector' === $step['saa_target_mode'] ) {
			return '';
		}

		$id = $element->get_id();
		if ( ! $id ) {
			return '';
		}

		return '.elementor-element-' . sanitize_html_class( $id );
	}

	/**
	 * Apply preset category classes/styles/data attributes on element wrapper.
	 *
	 * @param \Elementor\Element_Base $element Elementor element.
	 * @param array                   $settings Element settings.
	 */
	protected function apply_preset_attributes( $element, $settings ) {
		if ( ! is_array( $settings ) ) {
			return;
		}

		$cat = isset( $settings['saa_anim_category'] ) ? sanitize_key( $settings['saa_anim_category'] ) : '';
		if ( '' === $cat ) {
			return;
		}

		$classes = array();
		$styles = array();
		$data = array(
			'data-saa-preset' => 'yes',
		);

		switch ( $cat ) {
			case 'scroll-transform':
				$is_scrub = $this->is_switcher_enabled( isset( $settings['saa_scroll_preset_scrub'] ) ? $settings['saa_scroll_preset_scrub'] : null, false );
				$classes[] = $is_scrub ? 'scroll-transform-scrub' : 'scroll-transform';
				$preset = isset( $settings['saa_scroll_preset'] ) ? sanitize_key( $settings['saa_scroll_preset'] ) : 'fade-up';

				if ( '' !== $preset && 'custom' !== $preset ) {
					$classes[] = $preset;
					$preset_map = array(
						'fade-left'       => array( '--transform-start-x' => '-100px', '--transform-end-x' => '0px', '--transform-end-opacity' => '1' ),
						'fade-right'      => array( '--transform-start-x' => '100px', '--transform-end-x' => '0px', '--transform-end-opacity' => '1' ),
						'fade-up'         => array( '--transform-start-y' => '50px', '--transform-end-y' => '0px', '--transform-end-opacity' => '1' ),
						'fade-down'       => array( '--transform-start-y' => '-50px', '--transform-end-y' => '0px', '--transform-end-opacity' => '1' ),
						'zoom-in'         => array( '--transform-start-scale' => '0.8', '--transform-end-scale' => '1', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'zoom-out'        => array( '--transform-start-scale' => '1.2', '--transform-end-scale' => '1', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'blur-fade'       => array( '--transform-start-blur' => '20px', '--transform-end-blur' => '0px', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'blur-fade-left'  => array( '--transform-start-x' => '-100px', '--transform-end-x' => '0px', '--transform-start-blur' => '20px', '--transform-end-blur' => '0px', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'blur-fade-right' => array( '--transform-start-x' => '100px', '--transform-end-x' => '0px', '--transform-start-blur' => '20px', '--transform-end-blur' => '0px', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'blur-fade-up'    => array( '--transform-start-y' => '50px', '--transform-end-y' => '0px', '--transform-start-blur' => '20px', '--transform-end-blur' => '0px', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'blur-fade-down'  => array( '--transform-start-y' => '-50px', '--transform-end-y' => '0px', '--transform-start-blur' => '20px', '--transform-end-blur' => '0px', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'blur-zoom-in'    => array( '--transform-start-scale' => '0.8', '--transform-end-scale' => '1', '--transform-start-blur' => '15px', '--transform-end-blur' => '0px', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'blur-zoom-out'   => array( '--transform-start-scale' => '1.2', '--transform-end-scale' => '1', '--transform-start-blur' => '15px', '--transform-end-blur' => '0px', '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
						'fade'            => array( '--transform-start-opacity' => '0', '--transform-end-opacity' => '1' ),
					);
					if ( isset( $preset_map[ $preset ] ) ) {
						foreach ( $preset_map[ $preset ] as $var => $val ) {
							$styles[] = $var . ':' . $val;
						}
					}

					if ( $is_scrub ) {
						if ( ! empty( $settings['saa_scroll_preset_scrub_start'] ) ) {
							$styles[] = '--transform-scroll-start:' . sanitize_text_field( $settings['saa_scroll_preset_scrub_start'] );
						}
						if ( ! empty( $settings['saa_scroll_preset_scrub_end'] ) ) {
							$styles[] = '--transform-scroll-end:' . sanitize_text_field( $settings['saa_scroll_preset_scrub_end'] );
						}
						if ( ! empty( $settings['saa_scroll_preset_scrub_ease'] ) ) {
							$styles[] = '--transform-ease:' . sanitize_text_field( $settings['saa_scroll_preset_scrub_ease'] );
						}
						if ( $this->is_switcher_enabled( isset( $settings['saa_scroll_preset_forward'] ) ? $settings['saa_scroll_preset_forward'] : null, false ) ) {
							$data['data-transform-forward-only'] = 'true';
						}
					} else {
						if ( ! empty( $settings['saa_scroll_preset_trigger'] ) ) {
							$styles[] = '--transform-trigger:' . sanitize_text_field( $settings['saa_scroll_preset_trigger'] );
						}
						if ( isset( $settings['saa_scroll_preset_duration'] ) && '' !== (string) $settings['saa_scroll_preset_duration'] ) {
							$styles[] = '--transform-duration:' . floatval( $settings['saa_scroll_preset_duration'] ) . 's';
						}
						if ( isset( $settings['saa_scroll_preset_delay'] ) && '' !== (string) $settings['saa_scroll_preset_delay'] ) {
							$styles[] = '--transform-delay:' . floatval( $settings['saa_scroll_preset_delay'] ) . 's';
						}
						if ( ! empty( $settings['saa_scroll_preset_ease'] ) ) {
							$styles[] = '--transform-ease:' . sanitize_text_field( $settings['saa_scroll_preset_ease'] );
						}
					}
				} else {
					$map = array(
						'saa_ct_start_x'       => '--transform-start-x',
						'saa_ct_start_y'       => '--transform-start-y',
						'saa_ct_start_rotate'  => '--transform-start-rotate',
						'saa_ct_start_scale'   => '--transform-start-scale',
						'saa_ct_start_opacity' => '--transform-start-opacity',
						'saa_ct_start_blur'    => '--transform-start-blur',
						'saa_ct_end_x'         => '--transform-end-x',
						'saa_ct_end_y'         => '--transform-end-y',
						'saa_ct_end_rotate'    => '--transform-end-rotate',
						'saa_ct_end_scale'     => '--transform-end-scale',
						'saa_ct_end_opacity'   => '--transform-end-opacity',
						'saa_ct_end_blur'      => '--transform-end-blur',
						'saa_ct_duration'      => '--transform-duration',
						'saa_ct_delay'         => '--transform-delay',
						'saa_ct_ease'          => '--transform-ease',
						'saa_ct_trigger'       => '--transform-trigger',
					);
					foreach ( $map as $key => $var ) {
						if ( ! isset( $settings[ $key ] ) || '' === (string) $settings[ $key ] ) {
							continue;
						}
						$val = $settings[ $key ];
						if ( false !== strpos( $key, 'delay' ) || false !== strpos( $key, 'duration' ) ) {
							$val = floatval( $val ) . 's';
						} elseif ( false !== strpos( $key, 'rotate' ) ) {
							$val = floatval( $val ) . 'deg';
						} elseif ( false !== strpos( $key, 'blur' ) ) {
							$val = floatval( $val ) . 'px';
						} elseif ( false !== strpos( $key, '_x' ) || false !== strpos( $key, '_y' ) ) {
							$val = floatval( $val ) . 'px';
						}

						if ( $is_scrub && ( false !== strpos( $key, 'duration' ) || false !== strpos( $key, 'delay' ) || false !== strpos( $key, 'trigger' ) ) ) {
							continue;
						}
						$styles[] = $var . ':' . sanitize_text_field( (string) $val );
					}

					if ( ! isset( $settings['saa_ct_start_opacity'] ) || '' === (string) $settings['saa_ct_start_opacity'] ) {
						$styles[] = '--transform-start-opacity:1';
					}

					if ( $is_scrub ) {
						if ( ! empty( $settings['saa_scroll_preset_scrub_start'] ) ) {
							$styles[] = '--transform-scroll-start:' . sanitize_text_field( $settings['saa_scroll_preset_scrub_start'] );
						}
						if ( ! empty( $settings['saa_scroll_preset_scrub_end'] ) ) {
							$styles[] = '--transform-scroll-end:' . sanitize_text_field( $settings['saa_scroll_preset_scrub_end'] );
						}
						if ( ! empty( $settings['saa_scroll_preset_scrub_ease'] ) ) {
							$styles[] = '--transform-ease:' . sanitize_text_field( $settings['saa_scroll_preset_scrub_ease'] );
						}
						if ( $this->is_switcher_enabled( isset( $settings['saa_scroll_preset_forward'] ) ? $settings['saa_scroll_preset_forward'] : null, false ) ) {
							$data['data-transform-forward-only'] = 'true';
						}
					}
				}
				break;

			case 'split-text':
				$mode = isset( $settings['saa_split_mode'] ) ? sanitize_key( $settings['saa_split_mode'] ) : 'chars';
				$variant = 'words' === $mode
					? ( isset( $settings['saa_split_variant_word'] ) ? sanitize_key( $settings['saa_split_variant_word'] ) : 'fade-x' )
					: ( isset( $settings['saa_split_variant_char'] ) ? sanitize_key( $settings['saa_split_variant_char'] ) : 'fade-x' );
				$preset = isset( $settings['saa_split_preset'] ) ? sanitize_key( $settings['saa_split_preset'] ) : 'medium';
				$is_scrub_split = $this->is_switcher_enabled( isset( $settings['saa_split_scrub'] ) ? $settings['saa_split_scrub'] : null, false );

				if ( 'custom' !== $preset ) {
					$is_word = 'words' === $mode;
					$offset_default = 'light' === $preset ? 15 : ( 'dramatic' === $preset ? 50 : 30 );
					$stagger_default = $is_word
						? ( 'light' === $preset ? 0.06 : ( 'dramatic' === $preset ? 0.12 : 0.1 ) )
						: ( 'light' === $preset ? 0.04 : ( 'dramatic' === $preset ? 0.08 : 0.05 ) );
					$duration_default = 'light' === $preset ? 1.0 : ( 'dramatic' === $preset ? 1.8 : 1.5 );
					$is_blur_variant = 'fade-blur' === $variant;
					$is_offset_y = ( 'fade-y' === $variant || 'fade-blur' === $variant );
					$blur_default = $is_blur_variant ? ( 'light' === $preset ? 10 : ( 'dramatic' === $preset ? 20 : 15 ) ) : null;
					$styles[] = ( $is_word ? '--word-offset-x' : '--char-offset-x' ) . ':' . $offset_default . 'px';
					$styles[] = ( $is_word ? '--word-offset-y' : '--char-offset-y' ) . ':' . ( $is_offset_y ? $offset_default : 0 ) . 'px';
					$styles[] = ( $is_word ? '--word-stagger' : '--char-stagger' ) . ':' . $stagger_default . 's';
					$styles[] = ( $is_word ? '--word-duration' : '--char-duration' ) . ':' . $duration_default . 's';
					$styles[] = ( $is_word ? '--word-opacity-start' : '--char-opacity-start' ) . ':0';
					if ( null !== $blur_default ) {
						$styles[] = ( $is_word ? '--word-blur-start' : '--char-blur-start' ) . ':' . $blur_default . 'px';
					}

					if ( $is_scrub_split ) {
						$styles[] = ( $is_word ? '--word-scroll-start' : '--char-scroll-start' ) . ':' . ( ! empty( $settings['saa_split_scroll_start'] ) ? sanitize_text_field( $settings['saa_split_scroll_start'] ) : 'top 85%' );
						$styles[] = ( $is_word ? '--word-scroll-end' : '--char-scroll-end' ) . ':' . ( ! empty( $settings['saa_split_scroll_end'] ) ? sanitize_text_field( $settings['saa_split_scroll_end'] ) : 'top 40%' );
						if ( $this->is_switcher_enabled( isset( $settings['saa_split_forward'] ) ? $settings['saa_split_forward'] : null, false ) ) {
							$data['data-split-forward-only'] = 'true';
						}
					} elseif ( isset( $settings['saa_split_delay'] ) && '' !== (string) $settings['saa_split_delay'] ) {
						$styles[] = '--animation-delay:' . floatval( $settings['saa_split_delay'] ) . 's';
					}
				}

				if ( 'words' === $mode ) {
					if ( 'fade-y' === $variant ) {
						$classes[] = $is_scrub_split ? 'split-text-word-fade-y-scroll' : 'split-text-word-fade-y';
					} elseif ( 'fade-blur' === $variant ) {
						$classes[] = $is_scrub_split ? 'split-text-word-fade-y-blur-scroll' : 'split-text-word-fade-y-blur';
					} else {
						$classes[] = $is_scrub_split ? 'split-text-word-fade-scroll' : 'split-text-word-fade';
					}
				} else {
					if ( 'fade-y' === $variant ) {
						$classes[] = $is_scrub_split ? 'split-text-char-fade-y-scroll' : 'split-text-char-fade-y';
					} elseif ( 'fade-blur' === $variant ) {
						$classes[] = $is_scrub_split ? 'split-text-char-fade-y-blur-scroll' : 'split-text-char-fade-y-blur';
					} else {
						$classes[] = $is_scrub_split ? 'split-text-char-fade-scroll' : 'split-text-char-fade';
					}
				}

				if ( 'custom' === $preset ) {
					$mode_prefix = 'words' === $mode ? '--word-' : '--char-';
					$map = array(
						'saa_split_offset_x'      => $mode_prefix . 'offset-x',
						'saa_split_offset_y'      => $mode_prefix . 'offset-y',
						'saa_split_stagger'       => $mode_prefix . 'stagger',
						'saa_split_duration'      => $mode_prefix . 'duration',
						'saa_split_opacity_start' => $mode_prefix . 'opacity-start',
						'saa_split_blur_start'    => $mode_prefix . 'blur-start',
					);
					if ( $is_scrub_split ) {
						$map['saa_split_scroll_start'] = $mode_prefix . 'scroll-start';
						$map['saa_split_scroll_end'] = $mode_prefix . 'scroll-end';
					}

					foreach ( $map as $key => $var ) {
						if ( ! isset( $settings[ $key ] ) || '' === (string) $settings[ $key ] ) {
							continue;
						}
						$val = $settings[ $key ];
						if ( false !== strpos( $key, 'stagger' ) || false !== strpos( $key, 'duration' ) ) {
							$val = floatval( $val ) . 's';
						} elseif ( false !== strpos( $key, 'offset' ) || false !== strpos( $key, 'blur' ) ) {
							$val = floatval( $val ) . 'px';
						} else {
							$val = sanitize_text_field( (string) $val );
						}
						$styles[] = $var . ':' . $val;
					}

					if ( ! empty( $settings['saa_split_ease'] ) ) {
						$styles[] = $mode_prefix . 'ease:' . sanitize_text_field( $settings['saa_split_ease'] );
					}
					if ( $is_scrub_split && $this->is_switcher_enabled( isset( $settings['saa_split_forward'] ) ? $settings['saa_split_forward'] : null, false ) ) {
						$data['data-split-forward-only'] = 'true';
					}
					if ( ! $is_scrub_split && isset( $settings['saa_split_delay'] ) && '' !== (string) $settings['saa_split_delay'] ) {
						$styles[] = '--animation-delay:' . floatval( $settings['saa_split_delay'] ) . 's';
					}
				}
				break;

			case 'image-reveal':
				$classes[] = 'image-reveal';
				$dir = isset( $settings['saa_image_preset'] ) ? sanitize_key( $settings['saa_image_preset'] ) : 'left';
				if ( 'custom' === $dir ) {
					$dir = isset( $settings['saa_image_direction'] ) ? sanitize_key( $settings['saa_image_direction'] ) : 'left';
				}
				$classes[] = 'image-reveal-' . $dir;
				if ( isset( $settings['saa_image_duration'] ) && '' !== (string) $settings['saa_image_duration'] ) {
					$styles[] = '--reveal-duration:' . floatval( $settings['saa_image_duration'] ) . 's';
				}
				if ( isset( $settings['saa_image_delay'] ) && '' !== (string) $settings['saa_image_delay'] ) {
					$styles[] = '--reveal-delay:' . floatval( $settings['saa_image_delay'] ) . 's';
				}
				if ( ! empty( $settings['saa_image_ease'] ) ) {
					$styles[] = '--reveal-ease:' . sanitize_text_field( $settings['saa_image_ease'] );
				}
				if ( ! empty( $settings['saa_image_trigger'] ) ) {
					$styles[] = '--reveal-trigger:' . sanitize_text_field( $settings['saa_image_trigger'] );
				}
				if ( isset( $settings['saa_image_scale'] ) && '' !== (string) $settings['saa_image_scale'] ) {
					$styles[] = '--reveal-image-scale:' . floatval( $settings['saa_image_scale'] );
				}
				break;

			case 'container-reveal':
				$is_container_scrub = $this->is_switcher_enabled( isset( $settings['saa_container_scrub'] ) ? $settings['saa_container_scrub'] : null, false );
				$classes[] = $is_container_scrub ? 'container-reveal-scroll' : 'container-reveal';
				$dir = isset( $settings['saa_container_preset'] ) ? sanitize_key( $settings['saa_container_preset'] ) : 'center';
				if ( 'custom' === $dir ) {
					$dir = isset( $settings['saa_container_direction'] ) ? sanitize_key( $settings['saa_container_direction'] ) : 'center';
				}
				$classes[] = 'container-reveal-' . $dir;
				if ( isset( $settings['saa_container_duration'] ) && '' !== (string) $settings['saa_container_duration'] ) {
					$styles[] = '--reveal-duration:' . floatval( $settings['saa_container_duration'] ) . 's';
				}
				if ( isset( $settings['saa_container_delay'] ) && '' !== (string) $settings['saa_container_delay'] ) {
					$styles[] = '--animation-delay:' . floatval( $settings['saa_container_delay'] ) . 's';
				}
				if ( ! empty( $settings['saa_container_ease'] ) ) {
					$styles[] = '--reveal-ease:' . sanitize_text_field( $settings['saa_container_ease'] );
				}

				if ( $is_container_scrub ) {
					if ( ! empty( $settings['saa_container_scroll_start'] ) ) {
						$styles[] = '--reveal-scroll-start:' . sanitize_text_field( $settings['saa_container_scroll_start'] );
					}
					if ( ! empty( $settings['saa_container_scroll_end'] ) ) {
						$styles[] = '--reveal-scroll-end:' . sanitize_text_field( $settings['saa_container_scroll_end'] );
					}
					if ( $this->is_switcher_enabled( isset( $settings['saa_container_forward'] ) ? $settings['saa_container_forward'] : null, false ) ) {
						$data['data-reveal-forward-only'] = 'true';
					}
				} elseif ( ! empty( $settings['saa_container_trigger'] ) ) {
					$styles[] = '--reveal-trigger:' . sanitize_text_field( $settings['saa_container_trigger'] );
				}
				break;

			case 'scroll-fill-text':
				$classes[] = 'scroll-fill-text';
				if ( ! empty( $settings['saa_fill_start'] ) ) {
					$start = sanitize_text_field( $settings['saa_fill_start'] );
					$styles[] = '--scroll-fill-start:' . $start;
					$data['data-scroll-fill-start'] = $start;
				}
				if ( ! empty( $settings['saa_fill_end'] ) ) {
					$end = sanitize_text_field( $settings['saa_fill_end'] );
					$styles[] = '--scroll-fill-end:' . $end;
					$data['data-scroll-fill-end'] = $end;
				}

				$base_color = '';
				if ( ! empty( $settings['saa_fill_base'] ) ) {
					$base_color = $this->normalize_color( $settings['saa_fill_base'] );
				}
				if ( '' === $base_color && ! empty( $settings['__globals__']['saa_fill_base'] ) ) {
					$base_color = $this->global_css_var( $settings['__globals__']['saa_fill_base'] );
				}
				if ( '' !== $base_color ) {
					$styles[] = '--scroll-fill-base:' . sanitize_text_field( $base_color );
					$data['data-scroll-fill-base'] = sanitize_text_field( $base_color );
				}
				if ( $this->is_switcher_enabled( isset( $settings['saa_fill_line'] ) ? $settings['saa_fill_line'] : null, false ) ) {
					$data['data-scroll-fill-line'] = 'yes';
				}
				break;
		}

		if ( ! empty( $classes ) ) {
			$element->add_render_attribute( '_wrapper', 'class', $classes );
		}
		if ( ! empty( $styles ) ) {
			$element->add_render_attribute( '_wrapper', 'style', implode( ';', $styles ) );
		}
		foreach ( $data as $k => $v ) {
			$element->add_render_attribute( '_wrapper', $k, $v );
		}
	}

	/**
	 * Normalize Elementor color controls that may be arrays.
	 *
	 * @param mixed $value Raw color value.
	 * @return string
	 */
	protected function normalize_color( $value ) {
		if ( is_array( $value ) ) {
			if ( ! empty( $value['color'] ) ) {
				return (string) $value['color'];
			}
			if ( ! empty( $value['value'] ) ) {
				return (string) $value['value'];
			}
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Convert Elementor global color references to CSS var syntax.
	 *
	 * @param string $global Global token.
	 * @return string
	 */
	protected function global_css_var( $global ) {
		$global = (string) $global;
		if ( '' === $global ) {
			return '';
		}
		$raw = preg_replace( '/^.*[=:]/', '', $global );
		$raw = preg_replace( '/[^a-zA-Z0-9_-]/', '', $raw );
		if ( '' === $raw ) {
			return '';
		}
		return 'var(--e-global-color-' . $raw . ')';
	}

	/**
	 * Print payload before footer scripts so front-end JS can read it.
	 */
	public function print_interactions_payload() {
		if ( empty( self::$sequences ) ) {
			$this->collect_from_meta();
		}

		$sequences = $this->dedupe_sequences( self::$sequences );

		if ( empty( $sequences ) ) {
			printf(
				"\n<!-- SAA: no sequences collected; scanned %d elements; enabled %d; collected %d -->\n",
				intval( self::$scanned ),
				intval( self::$debug['enabled'] ),
				intval( self::$debug['collected'] )
			);
			return;
		}

		$payload = array(
			'version'   => 2,
			'sequences' => $sequences,
		);

		$inline = 'window.SAA_DATA = ' . wp_json_encode( $payload ) . ';';
		wp_add_inline_script( 'saa-frontend', $inline, 'before' );

		$data = wp_json_encode( $payload );
		if ( ! $data ) {
			return;
		}

		printf( '<script id="saa-data" type="application/json">%s</script>', $data );
	}

	/**
	 * Add loading class immediately at start of head.
	 */
	public function print_loading_class() {
		if ( is_admin() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		?>
		<script>
		(function() {
			document.documentElement.classList.add('saa-loading');
			setTimeout(function() {
				document.documentElement.classList.remove('saa-loading');
				document.body.classList.remove('saa-loading');
			}, 1500);
		})();
		</script>
		<style>.saa-loading [data-saa-animate]{opacity:0;visibility:hidden;}</style>
		<?php
	}

	/**
	 * Print initial CSS to avoid flash before JS runs.
	 */
	public function print_initial_styles() {
		if ( is_admin() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( empty( self::$sequences ) ) {
			$this->collect_from_meta();
		}

		if ( empty( self::$sequences ) ) {
			return;
		}

		$css = '';
		foreach ( self::$sequences as $sequence ) {
			if ( empty( $sequence['steps'] ) || ! is_array( $sequence['steps'] ) ) {
				continue;
			}

			if ( isset( $sequence['trigger'] ) && 'click' === $sequence['trigger'] ) {
				continue;
			}

			foreach ( $sequence['steps'] as $step ) {
				if ( empty( $step['selector'] ) || empty( $step['from'] ) || ! is_array( $step['from'] ) ) {
					continue;
				}

				$style = $this->from_props_to_initial_css( $step['from'] );
				if ( '' === $style ) {
					continue;
				}

				$css .= 'body:not(.elementor-editor-active) ' . $step['selector'] . '{' . $style . '}';
			}
		}

		if ( '' === $css ) {
			return;
		}

		echo '<style id="saa-initial">' . $css . '</style>';
	}

	/**
	 * Convert GSAP from props to initial CSS.
	 *
	 * @param array $from_props From properties.
	 * @return string
	 */
	protected function from_props_to_initial_css( $from_props ) {
		$styles = array();
		$transforms = array();

		if ( isset( $from_props['opacity'] ) ) {
			$styles[] = 'opacity:' . floatval( $from_props['opacity'] ) . ';';
		}
		if ( isset( $from_props['x'] ) ) {
			$transforms[] = 'translateX(' . floatval( $from_props['x'] ) . 'px)';
		}
		if ( isset( $from_props['y'] ) ) {
			$transforms[] = 'translateY(' . floatval( $from_props['y'] ) . 'px)';
		}
		if ( isset( $from_props['scale'] ) ) {
			$transforms[] = 'scale(' . floatval( $from_props['scale'] ) . ')';
		}
		if ( isset( $from_props['rotate'] ) ) {
			$transforms[] = 'rotate(' . floatval( $from_props['rotate'] ) . 'deg)';
		}
		if ( isset( $from_props['skewX'] ) ) {
			$transforms[] = 'skewX(' . floatval( $from_props['skewX'] ) . 'deg)';
		}
		if ( isset( $from_props['skewY'] ) ) {
			$transforms[] = 'skewY(' . floatval( $from_props['skewY'] ) . 'deg)';
		}
		if ( ! empty( $transforms ) ) {
			$styles[] = 'transform:' . implode( ' ', $transforms ) . ';';
		}
		if ( isset( $from_props['filter'] ) ) {
			$styles[] = 'filter:' . sanitize_text_field( (string) $from_props['filter'] ) . ';';
		}

		return implode( '', $styles );
	}

	/**
	 * Fallback: collect interactions from Elementor post meta.
	 */
	protected function collect_from_meta() {
		$candidate_ids = array(
			$this->get_document_id(),
			get_queried_object_id(),
			get_the_ID(),
		);

		if ( is_front_page() ) {
			$candidate_ids[] = (int) get_option( 'page_on_front' );
		}
		if ( is_home() ) {
			$candidate_ids[] = (int) get_option( 'page_for_posts' );
		}

		$candidate_ids = array_values( array_unique( array_filter( array_map( 'intval', $candidate_ids ) ) ) );
		if ( empty( $candidate_ids ) ) {
			return;
		}

		foreach ( $candidate_ids as $post_id ) {
			$data_raw = get_post_meta( $post_id, '_elementor_data', true );
			if ( empty( $data_raw ) ) {
				continue;
			}

			$data = json_decode( $data_raw, true );
			if ( empty( $data ) || ! is_array( $data ) ) {
				continue;
			}

			$this->walk_element_tree( $data );
		}
	}

	/**
	 * Walk Elementor data array and collect sequences.
	 *
	 * @param array $elements Elementor element tree.
	 */
	protected function walk_element_tree( $elements ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

			if ( ! empty( $settings['saa_enable_interactions'] ) && 'yes' === $settings['saa_enable_interactions'] ) {
				if ( ! empty( $settings['saa_timeline_steps'] ) && is_array( $settings['saa_timeline_steps'] ) ) {
					$sequence = $this->build_sequence_from_array( $element, $settings );
					if ( ! empty( $sequence['steps'] ) ) {
						self::$sequences[] = $sequence;
					}
				} elseif ( ! empty( $settings['saa_interactions'] ) && is_array( $settings['saa_interactions'] ) ) {
					$legacy_sequences = $this->build_legacy_sequences_from_array( $element, $settings );
					foreach ( $legacy_sequences as $legacy_sequence ) {
						if ( empty( $legacy_sequence['steps'] ) ) {
							continue;
						}
						self::$sequences[] = $legacy_sequence;
					}
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->walk_element_tree( $element['elements'] );
			}
		}
	}

	/**
	 * Build normalized sequence from meta array data.
	 *
	 * @param array $element Elementor element array.
	 * @param array $settings Elementor settings.
	 *
	 * @return array
	 */
	protected function build_sequence_from_array( $element, $settings ) {
		$element_id = isset( $element['id'] ) ? $element['id'] : '';
		$self_selector = $element_id ? '.elementor-element-' . sanitize_html_class( $element_id ) : '';

		$trigger = isset( $settings['saa_trigger'] ) ? sanitize_key( $settings['saa_trigger'] ) : 'inview';
		$trigger_selector = ! empty( $settings['saa_trigger_selector'] ) ? $this->sanitize_selector( $settings['saa_trigger_selector'] ) : $self_selector;

		$sequence = array(
			'id'              => 'saa-' . sanitize_html_class( (string) $element_id ),
			'source'          => 'meta',
			'trigger'         => in_array( $trigger, array( 'load', 'click', 'inview' ), true ) ? $trigger : 'inview',
			'triggerSelector' => $trigger_selector,
			'container'       => $self_selector,
			'scroll'          => array(
				'start' => isset( $settings['saa_scroll_start'] ) ? sanitize_text_field( $settings['saa_scroll_start'] ) : 'top 80%',
				'end'   => isset( $settings['saa_scroll_end'] ) ? sanitize_text_field( $settings['saa_scroll_end'] ) : 'bottom top',
				'scrub' => $this->is_switcher_enabled( isset( $settings['saa_scroll_scrub'] ) ? $settings['saa_scroll_scrub'] : null, true ),
				'once'  => $this->is_switcher_enabled( isset( $settings['saa_play_once'] ) ? $settings['saa_play_once'] : null, true ),
				'oneWay'=> $this->is_switcher_enabled( isset( $settings['saa_scroll_one_way'] ) ? $settings['saa_scroll_one_way'] : null, false ),
			),
			'batch'           => array(
				'scope'          => isset( $settings['saa_batch_scope'] ) && 'global' === $settings['saa_batch_scope'] ? 'global' : 'container',
				'defaultStagger' => isset( $settings['saa_default_stagger'] ) ? floatval( $settings['saa_default_stagger'] ) : 0.2,
			),
			'steps'           => array(),
		);

		foreach ( $settings['saa_timeline_steps'] as $step ) {
			$target_selector = $this->resolve_selector_from_array( $element, $step );
			if ( '' === $target_selector ) {
				continue;
			}

			$normalized_step = $this->normalize_step( $step, $target_selector, $sequence['batch']['defaultStagger'] );
			if ( empty( $normalized_step['from'] ) && empty( $normalized_step['to'] ) ) {
				continue;
			}

			$sequence['steps'][] = $normalized_step;
		}

		return $sequence;
	}

	/**
	 * Build backward-compatible sequences from legacy render-time settings (`saa_interactions`).
	 *
	 * @param \Elementor\Element_Base $element Elementor element.
	 * @param array                   $settings Element settings.
	 * @return array
	 */
	protected function build_legacy_sequences_from_settings( $element, $settings ) {
		$out = array();
		if ( empty( $settings['saa_interactions'] ) || ! is_array( $settings['saa_interactions'] ) ) {
			return $out;
		}

		$element_id = $element->get_id();
		$self_selector = $element_id ? '.elementor-element-' . sanitize_html_class( $element_id ) : '';
		$default_stagger = isset( $settings['saa_default_stagger'] ) ? floatval( $settings['saa_default_stagger'] ) : 0.2;

		foreach ( $settings['saa_interactions'] as $index => $interaction ) {
			if ( ! is_array( $interaction ) ) {
				continue;
			}

			$target_selector = $this->resolve_selector( $element, $interaction );
			if ( '' === $target_selector ) {
				continue;
			}

			$step = $this->build_legacy_step( $interaction, $target_selector, $default_stagger );
			if ( empty( $step['from'] ) && empty( $step['to'] ) ) {
				continue;
			}

			$out[] = $this->build_legacy_sequence(
				$interaction,
				$self_selector,
				$element_id,
				$index,
				$default_stagger,
				$step,
				'render'
			);
		}

		return $out;
	}

	/**
	 * Build backward-compatible sequences from legacy meta settings (`saa_interactions`).
	 *
	 * @param array $element Elementor element array.
	 * @param array $settings Element settings.
	 * @return array
	 */
	protected function build_legacy_sequences_from_array( $element, $settings ) {
		$out = array();
		if ( empty( $settings['saa_interactions'] ) || ! is_array( $settings['saa_interactions'] ) ) {
			return $out;
		}

		$element_id = isset( $element['id'] ) ? $element['id'] : '';
		$self_selector = $element_id ? '.elementor-element-' . sanitize_html_class( $element_id ) : '';
		$default_stagger = isset( $settings['saa_default_stagger'] ) ? floatval( $settings['saa_default_stagger'] ) : 0.2;

		foreach ( $settings['saa_interactions'] as $index => $interaction ) {
			if ( ! is_array( $interaction ) ) {
				continue;
			}

			$target_selector = $this->resolve_selector_from_array( $element, $interaction );
			if ( '' === $target_selector ) {
				continue;
			}

			$step = $this->build_legacy_step( $interaction, $target_selector, $default_stagger );
			if ( empty( $step['from'] ) && empty( $step['to'] ) ) {
				continue;
			}

			$out[] = $this->build_legacy_sequence(
				$interaction,
				$self_selector,
				$element_id,
				$index,
				$default_stagger,
				$step,
				'meta'
			);
		}

		return $out;
	}

	/**
	 * Normalize a legacy repeater row into a single modern timeline step.
	 *
	 * @param array  $interaction Legacy row settings.
	 * @param string $target_selector Target selector.
	 * @param float  $default_stagger Default stagger fallback.
	 * @return array
	 */
	protected function build_legacy_step( $interaction, $target_selector, $default_stagger ) {
		$action = isset( $interaction['saa_preset'] ) ? sanitize_key( $interaction['saa_preset'] ) : 'fade-up';
		$preset = $this->get_action_preset( $action );
		$stagger = $this->is_class_selector( $target_selector ) ? floatval( $default_stagger ) : 0.0;

		return array(
			'selector' => $target_selector,
			'action'   => $action,
			'from'     => $preset['from'],
			'to'       => $preset['to'],
			'duration' => isset( $interaction['saa_duration'] ) ? floatval( $interaction['saa_duration'] ) : 0.6,
			'delay'    => isset( $interaction['saa_delay'] ) ? floatval( $interaction['saa_delay'] ) : 0.0,
			'ease'     => isset( $interaction['saa_ease'] ) ? sanitize_text_field( $interaction['saa_ease'] ) : 'power2.out',
			'position' => '',
			'stagger'  => $stagger,
		);
	}

	/**
	 * Build a modern sequence wrapper for one legacy interaction row.
	 *
	 * @param array  $interaction Legacy row settings.
	 * @param string $self_selector Container/self selector.
	 * @param string $element_id Element id.
	 * @param int    $index Row index.
	 * @param float  $default_stagger Default stagger.
	 * @param array  $step Normalized step.
	 * @param string $source Source label.
	 * @return array
	 */
	protected function build_legacy_sequence( $interaction, $self_selector, $element_id, $index, $default_stagger, $step, $source ) {
		$trigger = isset( $interaction['saa_trigger'] ) ? sanitize_key( $interaction['saa_trigger'] ) : 'load';
		if ( ! in_array( $trigger, array( 'load', 'click', 'inview' ), true ) ) {
			$trigger = 'load';
		}

		$trigger_selector = $self_selector;
		if ( 'click' === $trigger ) {
			$trigger_selector = ! empty( $interaction['saa_trigger_selector'] ) ? $this->sanitize_selector( $interaction['saa_trigger_selector'] ) : $step['selector'];
			if ( '' === $trigger_selector ) {
				$trigger_selector = $self_selector;
			}
		}

		return array(
			'id'              => 'saa-' . sanitize_html_class( (string) $element_id ) . '-legacy-' . absint( $index ),
			'source'          => 'legacy-' . sanitize_key( $source ),
			'trigger'         => $trigger,
			'triggerSelector' => $trigger_selector,
			'container'       => $self_selector,
			'scroll'          => array(
				'start' => 'top 80%',
				'end'   => 'bottom top',
				'scrub' => false,
				'once'  => $this->is_switcher_enabled( isset( $interaction['saa_play_once'] ) ? $interaction['saa_play_once'] : null, true ),
				'oneWay'=> false,
			),
			'batch'           => array(
				'scope'          => 'container',
				'defaultStagger' => $default_stagger,
			),
			'steps'           => array( $step ),
		);
	}

	/**
	 * Resolve selector for array-based collection.
	 *
	 * @param array $element Elementor element array.
	 * @param array $step Step data.
	 *
	 * @return string
	 */
	protected function resolve_selector_from_array( $element, $step ) {
		if ( ! empty( $step['saa_target_selector'] ) ) {
			$selector = $this->sanitize_selector( $step['saa_target_selector'] );
			if ( '' !== $selector ) {
				return $selector;
			}
		}

		if ( isset( $step['saa_target_mode'] ) && 'selector' === $step['saa_target_mode'] ) {
			return '';
		}

		$id = isset( $element['id'] ) ? $element['id'] : '';
		if ( ! $id ) {
			return '';
		}

		return '.elementor-element-' . sanitize_html_class( $id );
	}

	/**
	 * Parse numeric values while preserving unit strings (e.g. "80%", "20vw").
	 *
	 * @param mixed $raw Raw value.
	 * @return float|string|null
	 */
	protected function parse_numeric_or_string( $raw ) {
		$text = trim( (string) $raw );
		if ( '' === $text ) {
			return null;
		}
		if ( is_numeric( $text ) ) {
			return floatval( $text );
		}
		return sanitize_text_field( $text );
	}

	/**
	 * Returns true when selector is class-based.
	 *
	 * @param string $selector CSS selector.
	 * @return bool
	 */
	protected function is_class_selector( $selector ) {
		$selector = trim( (string) $selector );
		return 1 === preg_match( '/^\.[a-zA-Z0-9_-]+$/', $selector );
	}

	/**
	 * Sanitize selector input to keep valid CSS-only strings.
	 *
	 * @param string $selector Raw selector.
	 * @return string
	 */
	protected function sanitize_selector( $selector ) {
		$selector = trim( wp_strip_all_tags( (string) $selector ) );
		if ( '' === $selector ) {
			return '';
		}
		if ( preg_match( '/[{};<>]/', $selector ) ) {
			return '';
		}
		return $selector;
	}

	/**
	 * Sanitize timeline position while preserving GSAP tokens like "<", ">", "+=0.2", "-=0.2".
	 *
	 * @param string $position Raw timeline position input.
	 * @return string
	 */
	protected function sanitize_timeline_position( $position ) {
		$position = html_entity_decode( (string) $position, ENT_QUOTES, 'UTF-8' );
		$position = trim( $position );
		if ( '' === $position ) {
			return '';
		}

		$position = preg_replace( '/[^<>=+\\-0-9. ]/', '', $position );
		$position = trim( preg_replace( '/\\s+/', '', $position ) );

		return substr( $position, 0, 24 );
	}

	/**
	 * Convert a blur input into CSS filter value.
	 *
	 * @param float|string $value Blur input.
	 * @return string
	 */
	protected function blur_to_filter( $value ) {
		if ( is_numeric( $value ) ) {
			return 'blur(' . floatval( $value ) . 'px)';
		}

		$raw = trim( (string) $value );
		if ( preg_match( '/^-?[0-9]+(?:\\.[0-9]+)?$/', $raw ) ) {
			return 'blur(' . $raw . 'px)';
		}
		if ( preg_match( '/^-?[0-9]+(?:\\.[0-9]+)?px$/', $raw ) ) {
			return 'blur(' . $raw . ')';
		}

		return 'blur(0px)';
	}

	/**
	 * Map action keys to preset from/to defaults.
	 *
	 * @param string $action Action key.
	 * @return array
	 */
	protected function get_action_preset( $action ) {
		switch ( $action ) {
			case 'fade-in':
				return array(
					'from' => array( 'opacity' => 0 ),
					'to'   => array( 'opacity' => 1 ),
				);
			case 'fade-up':
				return array(
					'from' => array( 'opacity' => 0, 'y' => 24 ),
					'to'   => array( 'opacity' => 1, 'y' => 0 ),
				);
			case 'slide-up':
				return array(
					'from' => array( 'y' => 36, 'opacity' => 0 ),
					'to'   => array( 'y' => 0, 'opacity' => 1 ),
				);
			case 'zoom-in':
				return array(
					'from' => array( 'opacity' => 0, 'scale' => 0.9 ),
					'to'   => array( 'opacity' => 1, 'scale' => 1 ),
				);
			default:
				return array(
					'from' => array(),
					'to'   => array(),
				);
		}
	}

	/**
	 * Parse Elementor switcher values safely.
	 *
	 * @param mixed $value Raw switcher value.
	 * @param bool  $default Default when value is missing.
	 * @return bool
	 */
	protected function is_switcher_enabled( $value, $default = false ) {
		if ( null === $value ) {
			return (bool) $default;
		}

		$normalized = strtolower( trim( (string) $value ) );
		if ( '' === $normalized ) {
			return false;
		}

		if ( in_array( $normalized, array( 'no', '0', 'false', 'off' ), true ) ) {
			return false;
		}

		return in_array( $normalized, array( 'yes', '1', 'true', 'on' ), true );
	}

	/**
	 * Remove duplicate sequences by id, keeping first collected sequence.
	 *
	 * @param array $sequences Sequence list.
	 * @return array
	 */
	protected function dedupe_sequences( $sequences ) {
		if ( empty( $sequences ) || ! is_array( $sequences ) ) {
			return array();
		}

		$seen = array();
		$out = array();

		foreach ( $sequences as $sequence ) {
			$key = '';
			if ( isset( $sequence['id'] ) && '' !== (string) $sequence['id'] ) {
				$key = (string) $sequence['id'];
			} else {
				$key = md5( wp_json_encode( $sequence ) );
			}

			if ( isset( $seen[ $key ] ) ) {
				$existing_index = $seen[ $key ];
				$existing = isset( $out[ $existing_index ] ) ? $out[ $existing_index ] : array();
				$existing_source = isset( $existing['source'] ) ? (string) $existing['source'] : '';
				$new_source = isset( $sequence['source'] ) ? (string) $sequence['source'] : '';

				// Prefer render-collected sequence when duplicate ids exist.
				if ( 'meta' === $existing_source && 'render' === $new_source ) {
					$out[ $existing_index ] = $sequence;
				}
				continue;
			}

			$seen[ $key ] = count( $out );
			$out[] = $sequence;
		}

		return $out;
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
					'Trigger any element and drive a GSAP timeline of multiple selector-based steps.',
					'supercraft-animation-advanced'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if current query renders Elementor content.
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
