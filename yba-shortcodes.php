<?php

/**
 * Inspired by http://scribu.net/wordpress/reflection-on-filters.html
 */
class ybaShortcodes {

	public static function add( $class ) {
		self::_do( 'add_shortcode', $class );
	}

	public static function remove( $class ) {
		self::_do( 'remove_shortcode', $class );
	}

	public static function debug( $class ) {
		echo "<pre>";
		self::_do( array( __CLASS__, '_print' ), $class );
		echo "</pre>";
	}

	private static function _print( $tag, $callback ) {
		if ( is_object( $callback[0] ) )
			$class = '$' . get_class( $callback[0] );
		else
			$class = "'" . $callback[0] . "'";

		$func = " array( $class, '$callback[1]' )";

		echo "add_shortcode( '$tag', $func );\n";
	}

	private static function _do( $action, $class ) {
		$reflection = new ReflectionClass( $class );

		foreach ( $reflection->getMethods() as $method ) {
			if ( $method->isPublic() && !$method->isConstructor() ) {
				$comment = $method->getDocComment();

				$tag = preg_match( '/@tag:?\s+(.+)/', $comment, $matches ) ? $matches[1] : $method->name;

				call_user_func( $action, $tag, array( $class, $method->name ) );
			}
		}
	}
}
