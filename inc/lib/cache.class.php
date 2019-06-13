<?php

    /*************************************************************************************************************
     * Cache Class (Disk cache, Memcached, Memcache and APC)
     * @author  Jaka Prasnikar
     * @link    https://prahec.com/
     * @version 3.1 (Updated 25.12.2016)
     ************************************************************************************************************ */
    class Cache {


        /**
         * This class options are all here, useful for later use (filecache etc)
         *
         * @var array
         */
        protected $options;


        /**
         * This is temporary variable with object of a caching method (Memcache, Memcached, APC)
         *
         * @var
         */
        protected $object;


        /**
         * When the stack is initiated, this variable is filled with cache information
         *
         * @var bool
         */
        private $store = false;


        /**
         * Simple bool variable that lets us know if script started properly or not
         *
         * @var bool
         */
        protected $startup = false;


        /**
         * cache constructor.
         *
         * @param array $options
         */
        function __construct( $options = array() ) {

            // Default Cache class options
            $this->options = $options + array(
                    'path'    => 'cache/',                // Location where to cache items
                    'ext'     => '.cache',                // Disk cache file extension
                    'encrypt' => false,                   // Disk cache basic file encryption
                    'mode'    => 'disk',                  // Modes: disk, apc, apcu, redis, memcache, memcached
                    'extra'   => array(),                 // Additional options (only redis, memcached and memcache supports at the moment)
                    'prefix'  => ''                       // Key prefix (all modes)
                );


            // Check settings & caching mode
            if ( $this->checkSettings() ) {

                // Start cache keys collector
                $this->startup = true;
                $this->stack( 'init' );

            }


        }


        /**
         * Before starting up the caching class this must be done. Here we check if the options
         * are valid and the connections to Memcache/Memcached are working
         *
         * @return bool
         */
        private function checkSettings() {

            // Set $check to false
            $check = false;

            // Various tests & connect
            switch ( $this->options[ 'mode' ] ) {

                case 'disk': // disk cache
                    $check = true;
                    break;

                case 'apc': // php_apc
                    if ( extension_loaded( 'apc' ) AND ini_get( 'apc.enabled' ) ) $check = true;
                    break;

                case 'apcu': // php_apcu
                    if ( extension_loaded( 'apcu' ) AND ini_get( 'apc.enabled' ) ) $check = true;
                    break;

                case 'redis':

                    if ( extension_loaded( 'redis' ) ) {

                        $this->object = new Redis();

                        // Use socket
                        if ( !preg_match( '/(.*):([0-9]+)/', $this->options[ 'path' ], $host ) ) {

                            if ( $this->object->pconnect( $this->options[ 'path' ], 0, 2 ) )
                                $check = true;

                        } else { // Use host:port

                            if ( $this->object->pconnect( $host[ 1 ], $host[ 2 ], 2 ) )
                                $check = true;

                        }

                        // Special case, authorization...
                        if ( isset( $this->options[ 'extra' ][ 'auth' ] ) ) {

                            if ( $this->object->auth( $this->options[ 'extra' ][ 'auth' ] ) == false )
                                $check = false;

                        }

                        // You can pass additional options to the Memcached handler
                        if ( count( $this->options[ 'extra' ] ) > 1 && ( count( $this->options[ 'extra' ] ) != 1 && isset( $this->options[ 'extra' ][ 'auth' ] ) ) ) {

                            foreach ( $this->options[ 'extra' ] as $opt => $val ) {

                                $this->object->setOption( $opt, $val );

                            }

                        }

                    }

                    break;

                case 'memcache': // php_memcache

                    // Extension must be loaded obviously
                    if ( extension_loaded( 'memcache' ) ) {

                        // Initiate Memcache object
                        $this->object = new Memcache;

                        // Use socket
                        if ( !preg_match( '/(.*):([0-9]+)/', $this->options[ 'path' ], $host ) ) {

                            if ( $this->object->addServer( $this->options[ 'path' ], 0, true ) )
                                $check = true;

                        } else { // Use host:port

                            if ( $this->object->addServer( $host[ 1 ], $host[ 2 ], true ) )
                                $check = true;

                        }
                    }

                    break;

                case 'memcached': // php_memcached

                    // Extension must be loaded obviously
                    if ( extension_loaded( 'memcached' ) ) {

                        // Create new Memcached object
                        $this->object = new Memcached();
                        $servers      = $this->object->getServerList();

                        // Check list of servers added to the Memcached extension
                        if ( count( $servers ) > 1 ) {

                            $check = true;

                        } else if ( !preg_match( '/(.*):([0-9]+)/', $this->options[ 'path' ], $host ) ) { // Use socket, faster

                            if ( $this->object->addServer( $this->options[ 'path' ], 0, true ) )
                                $check = true;

                        } else { // Use host:port

                            if ( $this->object->addServer( $host[ 1 ], $host[ 2 ], true ) )
                                $check = true;

                        }

                        // You can pass additional options to the Memcached handler
                        if ( count( $this->options[ 'extra' ] ) > 1 ) {

                            foreach ( $this->options[ 'extra' ] as $opt => $val ) {

                                $this->object->setOption( $opt, $val );

                            }

                        }

                    }

                    break;

            }

            // Return true on success or false on failure
            return $check;

        }


        /**
         * Cache storage, stores all cached keys and their time out, not their values
         *
         * @param string $act clean, check, delete or any value will re-read store
         * @param string $key name of the key you wish to check/delete from store
         *
         * @return array|bool
         */
        protected function stack( $act = 'init', $key = '' ) {

            // If class failed on startup, quit now!
            if ( $this->startup == false ) return false;

            // First call should setup store variable which will contain cache keys
            if ( $this->store == false && $act == 'init' ) {

                // Get from cache
                $v = $this->get( "cache_store" );

                // Check if cache returned proper result
                if ( $v !== false && is_array( $v ) ) {

                    $this->store = $v;

                } else {

                    $this->store = array();

                }

            }


            // Switch actions
            switch ( $act ) {

                // Check key existence/expiration
                case 'check':

                    // First check if key even exists
                    if ( isset( $this->store[ $key ] ) ) {

                        // Check if key exists and if it expired (used at GET method)
                        if ( $this->store[ $key ][ 'expires' ] == '0' OR time() < $this->store[ $key ][ 'expires' ] ) {

                            return true;

                        }

                    }

                    return false;
                    break;


                // Delete a single key from cache
                case 'delete':

                    if ( $this->store === false ) {

                        return false;

                    } else { ## Add key -> ttl to cache_status

                        unset( $this->store[ $key ] );
                        $this->set( 'cache_store', $this->store, 0 );
                        return true;

                    }

                    break;


                // Flush whole cache, meaning delete all keys in store
                case 'flush':

                    // At least return empty array
                    $clean_status = array();

                    // Check if store is array
                    if ( is_array( $this->store ) ) {

                        // Loop through stored cache entries and delete them
                        foreach ( $this->store as $key => $more ) {

                            $clean_status[] = $key;
                            $this->delete( $key );

                        }

                        // Clear script cache
                        unset( $this->store );
                        $this->store = array();

                    }

                    return $clean_status;
                    break;

            }

            return false;

        }


        /**
         * Set cache by key data and expiration time
         *
         * @param string $key  Name of the key to store
         * @param string $data Value to store (string, array, int, float or object)
         * @param string $ttl  how long cache should be stored (0 = unlimited)
         *
         * @return array|bool
         */
        public function set( $key, $data, $ttl = '600' ) {

            // If class failed to startup, quit now!
            if ( $this->startup == false ) return false;


            // Prefix / Default response
            $name   = $this->parseKey( $key );
            $return = false;


            // Various Modes / Actions
            switch ( $this->options[ 'mode' ] ) {

                // APC extension uses its own calls
                case 'apc':
                    $return = apc_store( $name, $data, $ttl );
                    break;


                // APCu extension uses its own calls
                case 'apcu':
                    $return = apcu_store( $name, $data, $ttl );
                    break;


                // Redis method
                case 'redis':
                    $return = $this->object->set( $name, $data, $ttl );
                    break;


                // Memcache method
                case 'memcache':

                    // Try to replace key, else make new one
                    if ( !$return = $this->object->replace( $name, $data, false, $ttl ) )
                        $return = $this->object->set( $name, $data, false, $ttl );

                    break;


                // Memcached
                case 'memcached':

                    // Try to replace key, else make new one
                    if ( !$return = $this->object->replace( $name, $data, $ttl ) )
                        $return = $this->object->set( $name, $data, $ttl );

                    break;


                // Default is always disk cache
                default:

                    // Encryption
                    if ( $this->options[ 'encrypt' ] === true ) $data = base64_encode();

                    // Check if path exists
                    if ( !is_dir( $this->options[ 'path' ] ) ) { // if not create it recursively

                        if ( !mkdir( $this->options[ 'path' ], 0755, true ) )
                            return false;

                    }


                    // Write cache if its writable
                    if ( is_writable( $this->options[ 'path' ] ) ) {

                        // Serialize arrays & objects
                        if ( is_array( $data ) OR is_object( $data ) )
                            $data = serialize( $data );

                        file_put_contents( $this->options[ 'path' ] . $name . $this->options[ 'ext' ], $data );
                        $return = true;

                    }

                    break;


            }

            // The cache_store key is little different because it has no expiration
            if ( $key == 'cache_store' ) {

                return $return;

            } else {

                // Also set expire/hits ONLY if SET was success
                if ( $return !== false ) {

                    // Reset store hits on SET, logical...
                    $this->store[ $key ][ 'hits' ] = 0;

                    // Set expire TTL (basically just expire time)
                    $this->store[ $key ][ 'expires' ] = ( ( $ttl == '0' ) ? 0 : time() + $ttl );

                }

                // Return success/false
                return $return;

            }

        }


        /**
         * Get existing record from cache, if it does not exist false is returned
         *
         * @param $key
         *
         * @return bool|mixed|string
         */
        public function get( $key ) {

            // If class failed to startup, quit now!
            if ( $this->startup == false ) return false;

            // Use Prefix
            $name = $this->parseKey( $key );
            $data = false;


            // Various Modes / Actions
            switch ( $this->options[ 'mode' ] ) {

                // APC extension uses its own calls
                case 'apc':

                    // Fetch from store
                    $apc = apc_fetch( $name, $success );

                    // If successful, return the data
                    if ( $success ) $data = $apc;
                    break;


                // APCu extension uses its own calls
                case 'apcu':

                    // Fetch from store
                    $apc = apcu_fetch( $name, $success );

                    // If successful, return the data
                    if ( $success ) $data = $apc;
                    break;


                // Redis method (simple)
                case 'redis':
                    $data = $this->object->get( $name );
                    break;


                // Memcache method (simple)
                case 'memcache':
                    $data = $this->object->get( $name );
                    break;


                // Memcached method
                case 'memcached':
                    $data = $this->object->get( $name );
                    break;


                // Default is always disk cache
                default:

                    // Check if cache exists
                    if ( is_file( $this->options[ 'path' ] . $name . $this->options[ 'ext' ] ) ) {

                        // Validate key expiration date and data (allow cache_store without actual valid expiration)
                        if ( $key == 'cache_store' OR $this->stack( 'check', $key ) === true ) {

                            $cache_data = file_get_contents( $this->options[ 'path' ] . $name . $this->options[ 'ext' ] );    ## Read file into variable
                            $cache_data = ( ( $this->options[ 'encrypt' ] === true ) ? base64_decode( $cache_data ) : $cache_data );      ## Encryption
                            $serialized = @unserialize( $cache_data );

                            // If un-serialize function returned ANY sort of data, return it
                            if ( $serialized !== false )
                                $data = $serialized;

                            else  // Nope, just data
                                $data = $cache_data;

                        }

                    }

                    break;

            }

            // Update hit count if data isn't false
            if ( $data !== false && isset( $this->store[ $key ][ 'hits' ] ) ) $this->store[ $key ][ 'hits' ]++;

            // Return information received from a method
            return $data;

        }


        /**
         * Delete key from cache by the definition
         *
         * @param $key
         *
         * @return bool
         */
        public function delete( $key ) {

            // If class failed to startup, quit now!
            if ( $this->startup == false ) return false;

            // Use prefix
            $name    = $this->parseKey( $key );
            $deleted = false;

            // Various Modes / Actions
            switch ( $this->options[ 'mode' ] ) {

                // APC extension uses its own calls
                case 'apc':
                    $deleted = apc_delete( $name );
                    break;


                // APCu extension uses its own calls
                case 'apcu':
                    $deleted = apcu_delete( $name );
                    break;


                // Redis method
                case 'redis':
                    $deleted = $this->object->delete( $name );
                    break;


                // Memcache method
                case 'memcache':
                    $deleted = $this->object->delete( $name );
                    break;


                // Memcached method
                case 'memcached':
                    $deleted = $this->object->delete( $name );
                    break;


                // Default is always disk cache
                default:

                    if ( is_file( $this->options[ 'path' ] . $name . $this->options[ 'ext' ] ) ) {

                        // Del cache
                        $deleted = @unlink( $this->options[ 'path' ] . $name . $this->options[ 'ext' ] );

                    }

                    break;

            }

            // If cache key was successfully deleted, also clean it from cache_store
            if ( $deleted === true ) {

                // Ignore cache_store, should never be deleted
                if ( $key != 'cache_store' )
                    $this->stack( 'delete', $key );

            }

            return false;

        }


        /**
         * Useful function to delete all keys with specific REGEX match, comes very handy when using caching for different things
         * Example: deleteAll( 'page_cache_.*' );
         *
         * @param string $regex
         *
         * @return array|bool
         */
        public function deleteAll( $regex = '.*' ) {

            // If class failed to startup, quit now!
            if ( $this->startup == false OR !is_array( $this->store ) ) return false;

            // Default variable
            $deleted = array();

            // Since version 2.3 we use cache store for all cache modes
            if ( $this->store != false && is_array( $this->store ) ) {

                // We loop through whole cache store
                foreach ( $this->store as $key => $expire ) {

                    if ( $key == 'cache_store' ) continue;              ## Skip cache store
                    if ( preg_match( '/' . $regex . '/i', $key ) ) {    ## Use regex for deleteAll

                        $deleted[] = $key;
                        $this->delete( $key );

                    }

                }

            }

            // Return list of deleted keys (useful)
            return $deleted;

        }


        /**
         * Small function to convert keys to proper values supported by all caching modes
         *
         * @param $key
         *
         * @return mixed
         */
        protected function parseKey( $key ) {

            return str_replace( array( ' ' ), '_', $this->options[ 'prefix' ] . $key );

        }


        /**
         * Access the caching object directly, useful for memcached, memcache, redis, apcu and apc.
         *
         * @return mixed
         */
        public function direct() {

            // If class failed on startup, quit now!
            if ( $this->startup == false ) return false;

            // If using disk method, return this object
            if ( $this->options[ 'mode' ] == 'disk' ) return $this;

            // Return whichever object we're using
            return $this->object;

        }


        /**
         * Simple function to clean up absolute/missing caches from cache store
         *
         * @return array
         */
        public function clean() {

            // Pre-defined array
            $cleaned = array();

            // Check if store is array
            if ( is_array( $this->store ) && $this->startup == true ) {

                // Loop through stored cache entries and delete them
                foreach ( $this->store as $key => $more ) {

                    // Check if key is active, if not, clean it from store
                    if ( $this->get( $key ) == false ) {

                        // Remove from store and add key to array of cleaned so far
                        $this->stack( 'delete', $key );
                        $cleaned[] = $key;

                        // When using disk cache, we can also remove absolute file from drive
                        if ( $this->options[ 'mode' ] == 'disk' )
                            $this->delete( $key );

                    }

                }

            }

            return $cleaned;

        }


        /**
         * Flush whole cache created with this extension, use with extreme caution!
         *
         * @return array|bool
         */
        public function flush() {

            // Attempt cleaning up cache_store
            $tmp = $this->stack( 'flush' );

            // We call this here so changes are permanent
            $this->quit();

            // Return what ever we got from stack clean
            return $tmp;

        }


        /**
         * This function must always be run after you have completed working with cache
         * it ensures that cache_store is written to the caching method
         */
        public function quit() {

            // Save cache_store
            if ( is_array( $this->store ) && $this->startup !== false )
                $this->set( 'cache_store', $this->store, 0 );

        }

    }

?>