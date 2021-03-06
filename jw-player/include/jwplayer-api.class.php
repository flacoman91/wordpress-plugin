<?php

/*-----------------------------------------------------------------------------
 * PHP client library for JW Platform System API
 *
 * Version:     1.4
 * Updated:     Wed Feb  8 11:59:56 CET 2012
 *
 * For the System API documentation see:
 * http://apidocs.jwplayer.com/
 *-----------------------------------------------------------------------------
 */

class JWPlayer_api {
	private $_url = 'http://api.jwplatform.com/v1';
	private $_library;

	private $_key, $_secret, $_version;

	public function __construct( $key, $secret ) {

		$this->_version = 'jwp-wp-plugin-' . JWPLAYER_PLUGIN_VERSION;
		$this->_key = $key;
		$this->_secret = $secret;

		// Determine which HTTP library to use:
		if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
			$this->_library = 'wpvip';
		} else {
			$this->_library = 'wp';
		}
	}

	public function version() {
		return $this->_version;
	}

	// RFC 3986 complient rawurlencode()
	// Only required for phpversion() <= 5.2.7RC1
	// See http://www.php.net/manual/en/function.rawurlencode.php#86506
	private function _urlencode( $input ) {
		if ( is_array( $input ) ) {
			return array_map( array( '_urlencode' ), $input );
		} elseif ( is_scalar( $input ) ) {
			return str_replace( '+', ' ', str_replace( '%7E', '~', rawurlencode( $input ) ) );
		} else {
			return '';
		}
	}

	// Sign API call arguments
	private function _sign( $args ) {
		ksort( $args );
		// $sbs = '';
		// foreach ( $args as $key => $value ) {
		// 	if ( '' != $sbs ) {
		// 		$sbs .= '&';
		// 	}
		// 	// Construct Signature Base String
		// 	$sbs .= $this->_urlencode( $key ) . '=' . $this->_urlencode( $value );
		// }
		// We will use the same function as we use for generating the query
		$sbs = http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
		// Add shared secret to the Signature Base String and generate the signature
		$signature = sha1( $sbs . $this->_secret );

		return $signature;
	}

	// Add required api_* arguments
	private function _args( $args, $sign = true ) {
		$args['api_nonce'] = str_pad( mt_rand( 0, 99999999 ), 8, STR_PAD_LEFT );
		$args['api_timestamp'] = time();

		if ( $sign ) {
			$args['api_key'] = $this->_key;
		}

		if ( ! array_key_exists( 'api_format', $args ) ) {
			// Use the serialised PHP format,
			// otherwise use format specified in the call() args.
			$args['api_format'] = 'json';
		}

		// Add API kit version
		$args['api_kit'] = 'php-' . $this->_version;

		// Sign the array of arguments
		if ( $sign ) {
			$args['api_signature'] = $this->_sign( $args );
		}

		return $args;
	}

	// Construct call URL
	public function call_url( $call, $args = array() ) {
		$sign = '/accounts/credentials/show' !== $call;
		$url = $this->_url . $call . '?' . http_build_query( $this->_args( $args, $sign ), '', '&', PHP_QUERY_RFC3986 );
		return $url;
	}

	// Make an API call
	public function call( $call, $args = array() ) {
		$url = $this->call_url( $call, $args );

		$response = null;
		switch ( $this->_library ) {
			case 'wpvip':
				$response = vip_safe_wp_remote_get( $url );
				break;
			case 'wp':
			default:
				$response = wp_remote_get( $url );
			break;
		}

		if ( is_wp_error( $response ) ) {
			$response = 'Error: call to JW Player API failed';
		} else {
			$response = wp_remote_retrieve_body( $response );
		}

		$decoded_response = json_decode( $response, $assoc = true );
		return $decoded_response;
	}

	// Upload a file
	public function upload( $upload_link = array(), $file_path, $api_format = 'json' ) {
		$url = $upload_link['protocol'] . '://' . $upload_link['address'] . $upload_link['path'] .
			'?key=' . $upload_link['query']['key'] . '&token=' . $upload_link['query']['token'] .
			'&api_format=' . $api_format;

		$post_data = array( 'file' => '@' . $file_path );
		$response = wp_remote_post( $url, array(
			'method' => 'post',
			'timeout' => 30,
			'blocking' => true,
			'body' => $post_data,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		} else {
			return json_decode( $response, $assoc = true );
		}
	}
}
