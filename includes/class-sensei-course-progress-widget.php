<?php
/**
 * Sensei LMS Course Progress Widget
 *
 * @author 		Automattic
 * @category 	Widgets
 * @package 	Sensei/Widgets
 * @version 	1.0.0
 * @extends 	WC_Widget
 */

use ConversionXL\Institute\Theme\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Sensei_Course_Progress_Widget extends WP_Widget {
	protected $woo_widget_cssclass;
	protected $woo_widget_description;
	protected $woo_widget_idbase;
	protected $woo_widget_title;

	/**
	 * Constructor function.
	 * @since  1.1.0
	 * @return  void
	 */
	public function __construct() {
		/* Widget variable settings. */
		$this->woo_widget_cssclass = 'widget_sensei_course_progress';
		$this->woo_widget_description = esc_html__( 'Displays the current learners progress within the current course/module (only displays on single lesson page).', 'sensei-course-progress' );
		$this->woo_widget_idbase = 'sensei_course_progress';
		$this->woo_widget_title = esc_html__( 'Sensei LMS - Course Progress', 'sensei-course-progress' );
		/* Widget settings. */
		$widget_ops = array( 'classname' => $this->woo_widget_cssclass, 'description' => $this->woo_widget_description );

		/* Widget control settings. */
		$control_ops = array( 'width' => 250, 'height' => 350, 'id_base' => $this->woo_widget_idbase );

		/* Create the widget. */
		parent::__construct( $this->woo_widget_idbase, $this->woo_widget_title, $widget_ops, $control_ops );
	}

	function widget( $args, $instance ) {

		global $woothemes_sensei, $post, $current_user, $view_lesson, $user_taking_course;

        $allmodules = 'off';
		if ( isset( $instance['allmodules'] ) ) {
			$allmodules = $instance['allmodules'];
		}

		// If not viewing a lesson/quiz, don't display the widget
		if( ! ( is_singular( array( 'course', 'lesson', 'quiz' ) ) || is_tax( 'module' ) ) ) return;

		# badgeos bug workaround
		wp_reset_postdata();

		extract( $args );

		// course has no current lesson
		$current_lesson_id = null;

		if ( is_singular('quiz') ) {
			$current_lesson_id = absint( get_post_meta( $post->ID, '_quiz_lesson', true ) );
		}

		if ( is_singular('lesson') ) {
			$current_lesson_id = $post->ID;
		}

		// get the course for the current lesson/quiz
		$lesson_course_id = is_singular( 'course' ) ? $post->ID : absint( get_post_meta( $current_lesson_id, '_lesson_course', true ) );

		//Check for preview lesson
		$is_preview = false;
		if ( method_exists( 'WooThemes_Sensei_Utils', 'is_preview_lesson' ) ) {
			$is_preview = WooThemes_Sensei_Utils::is_preview_lesson( $post->ID );
		}

		$course_title = get_the_title( $lesson_course_id );
		$course_url = get_the_permalink( $lesson_course_id );

		$in_module = false;
		$lesson_module = '';
		$lesson_array = array();

		/**
		 * Simplify for CXL Institute.
		 *
		 * @see https://github.com/woocommerce/sensei-course-progress/issues/61
		 */
		if ( $lesson_course_id ) {

			$course_modules = Sensei()->modules->get_course_modules( $lesson_course_id );

			if ( ! empty( $course_modules ) ) {

				foreach ( $course_modules as $module ) {
					$lesson_array = array_merge( $lesson_array, Sensei()->modules->get_lessons( $lesson_course_id, $module->term_id ) );
				}

			} else {
				$lesson_array = Sensei()->course->course_lessons( $lesson_course_id );
			}

		}

		/**
		 * Output.
		 */
		echo $before_widget;
		?>

		<?php if ( is_singular( [ 'lesson', 'quiz' ] ) ) : ?>

			<label>Course</label>
			<h3 class="widget-title">
				<a href="<?php echo esc_url( $course_url ); ?>"><?php echo $course_title; ?></a>
			</h3>

			<?php if ( is_singular( 'lesson' ) ) : ?>
				<section class="course-meta">
					<?php cxli( 'theme' )->get_integration_instance( Integrations\Sensei::class )->display_sensei_course_progress_bar( $lesson_course_id ); ?>
				</section>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( is_singular( 'course' ) ) : ?>
			<label>Lessons</label>
		<?php endif; ?>

		<?php
		foreach( $lesson_array as $post ) {

			setup_postdata( $post );

			hybrid_get_content_template();

			wp_reset_postdata();

		}

        $nav_array = sensei_get_prev_next_lessons( $current_lesson_id );
        if ( isset( $nav_array['previous'] ) || isset( $nav_array['next'] ) ) { ?>

            <p class="course-progress-navigation">
                <?php if ( isset( $nav_array['previous'] ) ) : ?>
	                <vaadin-button theme="contrast" onclick="window.location.href='<?php echo esc_url( $nav_array['previous']['url'] ); ?>'" tabindex="0" role="button">Prev<vaadin-icon slot="prefix" icon="lumo:arrow-left"></vaadin-icon></vaadin-button>
		        <?php endif; ?>
                <?php if ( isset( $nav_array['next'] ) ) : ?>
	                <vaadin-button theme="secondary" onclick="window.location.href='<?php echo esc_url( $nav_array['next']['url'] ); ?>'" tabindex="0" role="button">Next<vaadin-icon slot="suffix" icon="lumo:arrow-right"></vaadin-icon></vaadin-button>
		        <?php endif; ?>
            </p>
        <?php } ?>

		<?php echo wp_kses_post( $after_widget );
	}

	/**
	 * Method to update the settings from the form() method.
	 * @since  1.0.0
	 * @param  array $new_instance New settings.
	 * @param  array $old_instance Previous settings.
	 * @return array               Updated settings.
	 */
	public function update ( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* The check box is returning a boolean value. */
		$instance['allmodules'] = isset( $new_instance['allmodules'] ) ? esc_html( $new_instance['allmodules'] ) : '';

		return $instance;
	} // End update()

	/**
	 * The form on the widget control in the widget administration area.
	 * Make use of the get_field_id() and get_field_name() function when creating your form elements. This handles the confusing stuff.
	 * @since  1.1.0
	 * @param  array $instance The settings for this instance.
	 * @return void
	 */
    public function form( $instance ) {

		/* Set up some default widget settings. */
		/* Make sure all keys are added here, even with empty string values. */
		$defaults = array(
						'allmodules' => false
					);

		$instance = wp_parse_args( (array) $instance, $defaults );

		if ( isset( Sensei()->modules ) ) {
		?>
				<p>
					<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id('allmodules') ); ?>" name="<?php echo esc_attr( $this->get_field_name('allmodules') ); ?>"<?php checked( $instance['allmodules'], 'on' ); ?> />
					<label for="<?php echo esc_attr( $this->get_field_id('allmodules') ); ?>"><?php esc_html_e( 'Display all Modules', 'sensei-course-progress' ); ?></label><br />
				</p>
		<?php } else { ?>
				<p><?php esc_html_e( 'There are no options for this widget.', 'sensei-course-progress' ); ?></p>
				<?php }
	} // End form()

	/**
	 * Formats the title for each module in the course outline.
	 *
	 * @param WP_Term $module
	 * @return string
	 */
	private function get_module_title_content( WP_Term $module ) {
		$link_to_module = false;

		if ( method_exists( Sensei()->modules, 'do_link_to_module' ) ) {
			$link_to_module = Sensei()->modules->do_link_to_module( $module );
		}

		if ( $link_to_module ) {
			return '<a href="' . esc_url( $module->url ) . '">' . esc_html( $module->name ) . '</a>';
		}

		return esc_html( $module->name );
	} // End get_module_title_content()

}
