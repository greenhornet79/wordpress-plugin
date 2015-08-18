<?php
	/**
	 * 3-layer lazy options manager.
	 *      layer 3: Memory
	 *      layer 2: Cache (if there's any caching plugin)
	 *      layer 1: Database (options table). All options are stored as one option record in the DB to reduce the number of DB queries.
	 *
	 * If load() is not explicitly called, starts as empty manager. Same thing about saving the data - you have to explicitly call store().
	 *
	 * Class Workflows_Option_Manager
	 */
	class Workflows_Option_Manager {
		private $_id;
		private $_options;

		private static $_MANAGERS = array();

		private function __construct( $id, $load = false ) {
			$this->_id = $id;

			if ( $load ) {
				$this->load();
			}
		}

		/**
		 * @param $id
		 * @param $load
		 *
		 * @return Workflows_Option_Manager
		 */
		static function get_manager( $id, $load = false ) {
			$id = strtolower( $id );

			if ( ! isset( self::$_MANAGERS[ $id ] ) ) {
				self::$_MANAGERS[ $id ] = new Workflows_Option_Manager( $id, $load );
			} // If load is required but not yet loaded, load.
			else if ( $load && ! self::$_MANAGERS[ $id ]->is_loaded() ) {
				self::$_MANAGERS[ $id ]->load();
			}

			return self::$_MANAGERS[ $id ];
		}

		private function _get_option_manager_name() {
			return $this->_id;
		}

		function load( $flush = false ) {
			$option_name = $this->_get_option_manager_name();

			if ( $flush || ! isset( $this->_options ) ) {
				$this->_options = wp_cache_get( $option_name, WP_WF__SLUG );

				$cached = true;

				if ( false === $this->_options ) {
					$this->_options = get_option( $option_name );

					if ( is_string( $this->_options ) ) {
						$this->_options = json_decode( $this->_options );
					}

					if ( false === $this->_options ) {
						$this->clear();
					}

					$cached = false;
				}

				if ( ! $cached ) { // Set non encoded cache.
					wp_cache_set( $option_name, $this->_options, WP_WF__SLUG );
				}
			}
		}

		function is_loaded() {
			return isset( $this->_options );
		}

		function is_empty() {
			return ( $this->is_loaded() && false === $this->_options );
		}

		function clear( $flush = false ) {
			$this->_options = array();

			if ( $flush ) {
				$this->store();
			}
		}

		function get_option( $option, $default = null ) {
			if ( is_array( $this->_options ) ) {
				return isset( $this->_options[ $option ] ) ? $this->_options[ $option ] : $default;
			} else if ( is_object( $this->_options ) ) {
				return isset( $this->_options->{$option} ) ? $this->_options->{$option} : $default;
			}
		}

		function set_option( $option, $value, $flush = false ) {
			if ( ! $this->is_loaded() ) {
				$this->clear();
			}

			if ( is_array( $this->_options ) ) {
				$this->_options[ $option ] = $value;
			} else if ( is_object( $this->_options ) ) {
				$this->_options->{$option} = $value;
			}

			if ( $flush ) {
				$this->store();
			}
		}

		function unset_option( $option, $flush = false ) {
			if ( is_array( $this->_options ) ) {
				if ( ! isset( $this->_options[ $option ] ) ) {
					return;
				}

				unset( $this->_options[ $option ] );

			} else if ( is_object( $this->_options ) ) {
				if ( ! isset( $this->_options->{$option} ) ) {
					return;
				}

				unset( $this->_options->{$option} );
			}

			if ( $flush ) {
				$this->store();
			}
		}

		function store() {
			$option_name = $this->_get_option_manager_name();

			// Update DB.
			update_option( $option_name, $this->_options );

			wp_cache_set( $option_name, $this->_options, WP_WF__SLUG );
		}
	}