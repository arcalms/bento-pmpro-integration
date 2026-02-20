<?php
/**
 * Sensei LMS event handlers for Bento integration.
 *
 * @package BentoPMProIntegration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Bento_Sensei_Events
 *
 * Registers WordPress hooks for Sensei LMS events and forwards them to Bento
 * via Bento_Events_Controller::trigger_event().
 */
class Bento_Sensei_Events {

	/**
	 * Register all Sensei LMS action hooks.
	 */
	public static function init(): void {
		// Course enrolment status changes (enrol and unenrol share one hook).
		add_action( 'sensei_course_enrolment_status_changed', [ __CLASS__, 'on_enrolment_changed' ], 10, 3 );

		// Student starts a course.
		add_action( 'sensei_user_course_start', [ __CLASS__, 'on_course_started' ], 10, 2 );

		// Student completes a course.
		add_action( 'sensei_user_course_end', [ __CLASS__, 'on_course_completed' ], 10, 2 );

		// Student completes a lesson.
		add_action( 'sensei_user_lesson_end', [ __CLASS__, 'on_lesson_completed' ], 10, 2 );

		// Student submits a quiz.
		add_action( 'sensei_user_quiz_submitted', [ __CLASS__, 'on_quiz_submitted' ], 10, 5 );
	}

	// -------------------------------------------------------------------------
	// Internal helper
	// -------------------------------------------------------------------------

	/**
	 * Dispatch a Bento event if the event is enabled.
	 *
	 * Delegates enabled-check and field resolution to Bento_Integration_Settings
	 * so the logic lives in one place.
	 *
	 * @param string $event_key Settings key for the event.
	 * @param int    $user_id   WordPress user ID.
	 * @param array  $details   Event-specific detail payload.
	 */
	private static function fire_event( string $event_key, int $user_id, array $details ): void {
		if ( ! Bento_Integration_Settings::is_event_enabled( $event_key ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}

		$resolved = Bento_Integration_Settings::resolve_event_fields( $event_key, $user_id, $details );

		Bento_Integration_Settings::queue_event(
			$user_id,
			$resolved['event_name'],
			$user->user_email,
			$details,
			$resolved['custom_fields']
		);
	}

	// -------------------------------------------------------------------------
	// Hook handlers
	// -------------------------------------------------------------------------

	/**
	 * sensei_course_enrolment_status_changed – fires for both enrol and unenrol.
	 *
	 * @param int  $user_id     WordPress user ID.
	 * @param int  $course_id   Course post ID.
	 * @param bool $is_enrolled True = enrolled, false = unenrolled.
	 */
	public static function on_enrolment_changed( int $user_id, int $course_id, bool $is_enrolled ): void {
		$event_key    = $is_enrolled ? 'sensei_course_enrolled' : 'sensei_course_unenrolled';
		$course_title = get_the_title( $course_id );

		self::fire_event( $event_key, $user_id, [
			'course_id'    => $course_id,
			'course_title' => $course_title,
		] );
	}

	/**
	 * sensei_user_course_start – fires when a student starts a course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course post ID.
	 */
	public static function on_course_started( int $user_id, int $course_id ): void {
		self::fire_event( 'sensei_course_started', $user_id, [
			'course_id'    => $course_id,
			'course_title' => get_the_title( $course_id ),
		] );
	}

	/**
	 * sensei_user_course_end – fires when a student completes a course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course post ID.
	 */
	public static function on_course_completed( int $user_id, int $course_id ): void {
		self::fire_event( 'sensei_course_completed', $user_id, [
			'course_id'    => $course_id,
			'course_title' => get_the_title( $course_id ),
		] );
	}

	/**
	 * sensei_user_lesson_end – fires when a student completes a lesson.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $lesson_id Lesson post ID.
	 */
	public static function on_lesson_completed( int $user_id, int $lesson_id ): void {
		// Look up the parent course for context.
		$course_id = (int) get_post_meta( $lesson_id, '_lesson_course', true );

		self::fire_event( 'sensei_lesson_completed', $user_id, [
			'lesson_id'    => $lesson_id,
			'lesson_title' => get_the_title( $lesson_id ),
			'course_id'    => $course_id,
		] );
	}

	/**
	 * sensei_user_quiz_submitted – fires when a student submits a quiz.
	 *
	 * @param int    $user_id             WordPress user ID.
	 * @param int    $quiz_id             Quiz post ID.
	 * @param float  $grade               The grade received.
	 * @param float  $quiz_pass_percentage Minimum pass percentage.
	 * @param string $quiz_grade_type     Grade type ('auto'|'manual'|'pass_fail').
	 */
	public static function on_quiz_submitted( int $user_id, int $quiz_id, float $grade, float $quiz_pass_percentage, string $quiz_grade_type ): void {
		self::fire_event( 'sensei_quiz_submitted', $user_id, [
			'quiz_id'              => $quiz_id,
			'grade'                => $grade,
			'pass'                 => $grade >= $quiz_pass_percentage,
			'quiz_pass_percentage' => $quiz_pass_percentage,
			'quiz_grade_type'      => $quiz_grade_type,
		] );
	}
}
