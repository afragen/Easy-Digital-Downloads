<?php
/**
 * Contains autoloading functionality.
 *
 * @package   Fragen\Autoloader
 * @author    Andy Fragen <andy@thefragens.com>
 * @license   GPL-2.0+
 * @link      http://github.com/afragen/autoloader
 * @copyright 2015 Andy Fragen
 */

namespace EDD;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'EDD\\Autoloader' ) ) {
	/**
	 * Class Autoloader
	 *
	 * To use with different plugins be sure to create a new namespace.
	 *
	 * @package   EDD\Autoloader
	 * @author    Andy Fragen <andy@thefragens.com>
	 */
	class Autoloader {
		/**
		 * Roots to scan when autoloading.
		 *
		 * @var array
		 */
		protected $roots = array();

		/**
		 * List of class names and locations in filesystem, for situations
		 * where they deviate from convention etc.
		 *
		 * @var array
		 */
		protected $map = array();

		/**
		 * Array of class file names that don't correspond to class names.
		 *
		 * @var array
		 */
		protected $misnamed = array();

		/**
		 * Constructor.
		 *
		 * @access public
		 *
		 * @param array      $roots            Roots to scan when autoloading.
		 * @param array|null $static_map       Array of classes that deviate from convention.
		 *                                     Defaults to null.
		 * @param array|null $misnamed_classes Array of classes whose file names deviate from convention.
		 *                                     Defaults to null.
		 */
		public function __construct( array $roots, array $static_map = null, array $misnamed_classes = null ) {
			$this->roots = $roots;
			if ( null !== $static_map ) {
				$this->map = $static_map;
			}
			if ( null !== $misnamed_classes ) {
				$this->misnamed = $misnamed_classes;
			}
			spl_autoload_register( array( $this, 'autoload' ) );
		}

		/**
		 * Load classes.
		 *
		 * @access protected
		 *
		 * @param string $class The class name to autoload.
		 *
		 * @return void
		 */
		protected function autoload( $class ) {
			// Check for a static mapping first of all.
			if ( isset( $this->map[ $class ] ) && file_exists( $this->map[ $class ] ) ) {
				include_once $this->map[ $class ];

				return;
			}

			// Else scan the namespace roots.
			foreach ( $this->roots as $namespace => $root_dir ) {
				// kludge until proper namespacing of files.
				if ( false === strpos( '$class', $namespace ) ) {
					$class = 'EDD\\' . $class;
				}
				// If the class doesn't belong to this namespace, move on to the next root.
				if ( 0 !== strpos( $class, $namespace ) ) {
					continue;
				}

				$psr4_path = substr( $class, strlen( $namespace ) + 1 );
				$psr4_path = str_replace( '\\', DIRECTORY_SEPARATOR, $psr4_path );

				// Determine the possible path to the class, include all subdirectories.
				$objects = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root_dir ), \RecursiveIteratorIterator::SELF_FIRST );
				foreach ( $objects as $name => $object ) {
					if ( is_dir( $name ) ) {
						$directories[] = rtrim( $name, './' );
					}
				}
				$directories = array_unique( $directories );

				$fnames = array();
				$fnames = $this->get_possible_edd_filenames( $class );
				$fnames = array_merge( array( $psr4_path ), $fnames, $this->misnamed );

				$paths = $this->get_paths( $directories, $fnames );

				// Test for its existence and load if present.
				foreach ( $paths as $path ) {
					if ( file_exists( $path ) ) {
						include_once $path;
						break;
					}
				}
			}
		}

		/**
		 * Get and return an array of possible file paths.
		 *
		 * @param array $dirs       Array of plugin directories and subdirectories.
		 * @param array $file_names Array of possible file names
		 *
		 * @return mixed
		 */
		private function get_paths( $dirs, $file_names ) {
			foreach ( $file_names as $file_name ) {
				$paths[] = array_map( function( $dir ) use ( $file_name ) {
					return $dir . DIRECTORY_SEPARATOR . $file_name . '.php';
				}, $dirs );
			}

			return call_user_func_array( 'array_merge', $paths );
		}

		/**
		 * Create an array of potential class file names for Easy Digital Downloads.
		 *
		 * @param string $class Class name.
		 *
		 * @return array Array of potential file names for the class.
		 */
		private function get_possible_edd_filenames( $class ) {
			$fname    = str_replace( 'EDD\\', '', $class );
			$fname    = str_replace( '_', '-', $fname );
			$fname    = strtolower( $fname );
			$fname    = str_replace( 'edd-', '', $fname );
			$fnames[] = 'class-' . $fname;
			$fnames[] = 'class-edd-' . $fname;

			return array_merge( $fnames );
		}
	}
}
