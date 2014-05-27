<?php
if ( !defined( 'WP_APC_KEY_SALT' ) ) {
	define( 'WP_APC_KEY_SALT', 'wp' );
}


function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->add( $key, $data, $group, (int) $expire );
}


function wp_cache_close() {
	return true;
}


function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->decr( $key, $offset, $group );
}


function wp_cache_delete( $key, $group = '' ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->delete( $key, $group );
}


function wp_cache_flush() {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->flush();
}


function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->get( $key, $group, $force, $found );
}


function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->incr( $key, $offset, $group );
}


function wp_cache_init() {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	$wp_object_cache = new WP_Object_Cache();
}


function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->replace( $key, $data, $group, (int) $expire );
}


function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->set( $key, $data, $group, (int) $expire );
}


function wp_cache_switch_to_blog( $blog_id ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	$wp_object_cache->switch_to_blog( $blog_id );
}


function wp_cache_add_global_groups( $groups ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	$wp_object_cache->add_global_groups( $groups );
}


function wp_cache_add_non_persistent_groups( $groups ) {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	$wp_object_cache->add_non_persistent_groups( $groups );
}


function wp_cache_reset() {
	/**
	 * @var $wp_object_cache WP_Object_Cache
	 */
	global $wp_object_cache;

	return $wp_object_cache->reset();
}


class WP_Object_Cache {
	var $cache_hits = 0;
	var $cache_misses = 0;
	var $global_groups = array();
	var $non_persistent_groups = array();
	var $abspath = '';
	var $blog_prefix = '';


	function __construct() {
		global $blog_id;

		$this->abspath     = md5( ABSPATH );
		$this->multisite   = is_multisite();
		$this->blog_prefix = $this->multisite ? (int) $blog_id : 1;
	}


	function add_global_groups( $groups ) {
		$groups = (array) $groups;

		$groups = array_fill_keys( $groups, true );

		$this->global_groups = array_merge( $this->global_groups, $groups );
	}


	function add_non_persistent_groups( $groups ) {
		$groups = (array) $groups;

		$groups = array_fill_keys( $groups, true );

		$this->non_persistent_groups = array_merge( $this->non_persistent_groups, $groups );
	}


	function get( $key, $group = 'default', $force = false, &$success = null ) {
		unset( $force );

		$key = $this->_key( $key, $group );
		$var = apc_fetch( $key, $success );

		if ( $success ) {
			$this->cache_hits++;
			return $var;
		}

		$this->cache_misses++;
		return false;
	}


	function add( $key, $var, $group = 'default', $ttl = 0 ) {
		if ( wp_suspend_cache_addition() ) {
			return false;
		}

		return $this->_store_if_exists( $key, $var, $group, $ttl );
	}


	function set( $key, $var, $group = 'default', $ttl = 0 ) {
		return $this->_store( $key, $var, $group, $ttl );
	}


	function replace( $key, $var, $group = 'default', $ttl = 0 ) {
		return $this->_store_if_exists( $key, $var, $group, $ttl );
	}


	function delete( $key, $group = 'default', $deprecated = false ) {
		unset( $deprecated );

		$key = $this->_key( $key, $group );

		return apc_delete( $key );
	}


	function incr( $key, $offset = 1, $group = 'default' ) {
		return $this->_adjust( $key, $offset, $group );
	}


	function decr( $key, $offset = 1, $group = 'default' ) {
		$offset *= -1;

		return $this->_adjust( $key, $offset, $group );
	}


	function flush() {
		return apc_clear_cache( 'user' );
	}


	function reset() {
		_deprecated_function( __FUNCTION__, '3.5', 'switch_to_blog()' );
		return false;
	}


	function stats() {
		echo '<p>';
		echo '<strong>Cache Hits:</strong> ' . $this->cache_hits . '<br />';
		echo '<strong>Cache Misses:</strong> ' . $this->cache_misses . '<br />';
		echo '</p>';
	}


	function switch_to_blog( $blog_id ) {
		$blog_id           = (int) $blog_id;
		$this->blog_prefix = $this->multisite ? $blog_id : 1;
	}


	protected function _key( $key, $group ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		$prefix = 0;

		if ( !isset( $this->global_groups[$group] ) ) {
			$prefix = $this->blog_prefix;
		}

		return WP_APC_KEY_SALT . ':' . $this->abspath . ':' . $prefix . ':' . $group . ':' . $key;
	}


	protected function _store( $key, $var, $group, $ttl ) {
		if ( !isset( $this->non_persistent_groups[$group] ) ) {
			return false;
		}

		$key = $this->_key( $key, $group );
		$ttl = max( intval( $ttl ), 0 );

		if ( is_object( $var ) ) {
			$var = clone $var;
		}

		if ( is_array( $var ) ) {
			$var = new ArrayObject( $var );
		}

		return apc_store( $key, $var, $ttl );
	}


	protected function _store_if_exists( $key, $var, $group, $ttl ) {
		$exist_key = $this->_key( $key, $group );

		if ( apc_exists( $exist_key ) ) {
			return false;
		}

		return $this->_store( $key, $var, $group, $ttl );
	}


	protected function _adjust( $key, $offset, $group ) {
		$offset = intval( $offset );
		$key    = $this->_key( $key, $group );
		$var    = intval( apc_fetch( $key ) );
		$var += $offset;

		return $var;
	}
}