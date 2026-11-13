<?php
/**
 * Static syntax checks. PHP is never executed here.
 *
 * @package Snippet_Studio
 */

namespace Snippet_Studio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uses Woody's tokenizer check when it is available.
 */
class Validator {

	/**
	 * Check snippet code without running it.
	 *
	 * @param string $code Snippet code.
	 * @param string $type Woody snippet type.
	 * @return array{valid:bool,checked:bool,message:string}
	 */
	public static function check( $code, $type ) {
		$type = (string) $type;
		if ( ! in_array( $type, array( 'php', 'universal' ), true ) ) {
			return array(
				'valid'   => true,
				'checked' => false,
				'message' => '',
			);
		}

		if ( class_exists( '\WINP_Code_Validator' ) && method_exists( '\WINP_Code_Validator', 'check_syntax' ) ) {
			$result = \WINP_Code_Validator::check_syntax( (string) $code, $type );
			return array(
				'valid'   => ! empty( $result['valid'] ),
				'checked' => ! empty( $result['checked'] ),
				'message' => isset( $result['message'] ) ? (string) $result['message'] : '',
			);
		}

		if ( ! function_exists( 'token_get_all' ) ) {
			return array(
				'valid'   => true,
				'checked' => false,
				'message' => '',
			);
		}

		$source = ( 'universal' === $type ) ? (string) $code : '<?php ' . (string) $code;

		try {
			token_get_all( $source, TOKEN_PARSE );
		} catch ( \CompileError $error ) {
			return array(
				'valid'   => false,
				'checked' => true,
				'message' => sprintf(
					/* translators: 1: line number, 2: error message */
					__( 'Syntax error on line %1$d: %2$s', 'snippet-studio' ),
					(int) $error->getLine(),
					$error->getMessage()
				),
			);
		}

		return array(
			'valid'   => true,
			'checked' => true,
			'message' => '',
		);
	}
}
