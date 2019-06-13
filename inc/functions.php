<?php

    /* Helper function to write log files
    ============================================================================================================================ */
    function writeLog( $file, $text, $path = 'tmp/logs/' ) {

        global $settings; ## Not a perfect solution

        if ( $settings[ 'debugging' ] == 'disabled' ) return false; ## Logging is disabled!
        if ( is_writable( $path ) ) return file_put_contents( $path . $file . ".log", "[" . date( "j.n.Y-G:i" ) . "] {$text}\n", FILE_APPEND );
        return false;

    }

    /* Open stream and read its content to parse current playing track
    ============================================================================================================================= */
    function read_stream( $stream_uri ) {

        $result = false;
        $icy_metaint = false;

        // Stream context default request headers
        $stream_context = stream_context_create(
            array(
                'http' => array(
                    'method'        => 'GET',
                    'header'        => 'Icy-MetaData: 1',
                    'user_agent'    => 'Mozilla/5.0 (AIO Radio Station Player) AppleWebKit/537.36 (KHTML, like Gecko)',
                    'timeout'       => 6,
                    'ignore_errors' => true
                )
            )
        );

        // Attempt to open stream, read it and close connection (all here)
        if ( $stream = @fopen( $stream_uri, 'r', false, $stream_context ) ) {

            if ( $stream && ( $meta_data = stream_get_meta_data( $stream ) ) && isset( $meta_data[ 'wrapper_data' ] ) ) {

                foreach ( $meta_data[ 'wrapper_data' ] as $header ) { // Loop headers searching something to indicate codec

                    if ( strpos( strtolower( $header ), 'icy-metaint' ) !== false ) { // Expected something like: string(17) "icy-metaint:16000" for MP3

                        $tmp = explode( ":", $header );
                        $icy_metaint = trim( $tmp[ 1 ] ); // Should be interval value
                        break;

                    } else if ( $header == 'Content-Type: application/ogg' ) { // OGG Codec (start is 0)

                        $icy_metaint = 0;

                    }

                }

            }

            // Stream returned metadata refresh time, use it to get streamTitle info.
            if ( $icy_metaint !== false && is_numeric( $icy_metaint ) ) {

                $buffer = stream_get_contents( $stream, 600, $icy_metaint );

                // Attempt to find string "StreamTitle" in stream with length of 600 bytes and $icy_metaint is offset where to start
                if ( strpos( $buffer, 'StreamTitle=' ) !== false ) {

                    $title = explode( 'StreamTitle=', $buffer );
                    $title = trim( $title[ 1 ] );

                    // Use regex to match 'Song name - Title'; from StreamTitle='format';
                    if ( preg_match( "/\'?([^\'|^\;]*)\'?;/", $title, $m ) )
                        $result = $m[ 1 ];

                    // Icecast method ( only works if stream title / artist are on beginning )
                } else if ( strpos( $buffer, 'TITLE=' ) !== false && strpos( $buffer, 'ARTIST=' ) !== false ) {

                    // This is not the best solution, it doesn't parse binary it just removes control characters after regex
                    preg_match( '/TITLE=(?P<title>.*)ARTIST=(?P<artist>.*)ENCODEDBY/s', $buffer, $m );                              // Match TITLE/ARTIST on the beginning of stream (OGG metadata)
                    $result = preg_replace( '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $m[ 'artist' ] . ' - ' . $m[ 'title' ] );     // Remove control characters like '\u10'...

                }

            }

            fclose( $stream );

        }

        // Handle information gathered so far
        return ( ( $stream == false ) ? false : $result );

    }


    /**
     * CURL wrap function to make life easier using one function, this is rather simple implementation
     *
     * @param string  $url
     * @param array   $post     To post this must be array of POST elements (NAME=>VALUE) instead of boolean
     * @param string  $auth     To use HTTP Authorization, string should be passed in format (username:password)
     * @param bool    $progress Anonymous function ( $resource, $download_total, $downloaded_so_far, $upload_total, $uploaded_so_far )
     * @param int     $timeout  Self Explanatory
     * @param boolean $error    If a curl error
     * @param array   $options  You can pass custom CURL options via this param, by using existing param you will rewrite existing options
     *
     * @return mixed
     */
    function get( $url, $post = null, $auth = null, $progress = false, $timeout = 5, &$error = false, $options = array() ) {

        // Create CURL Object
        $CURL = curl_init();

        // By using array union we can pre-set/change options from function call
        $curl_opts = $options + array(
                CURLOPT_URL            => $url,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (AIO Radio Station Player) AppleWebKit/537.36 (KHTML, like Gecko)',
                CURLOPT_FOLLOWLOCATION => ( ( ini_get( 'open_basedir' ) == false ) ? true : false ),
                CURLOPT_CONNECTTIMEOUT => ( ( $timeout < 6 && $timeout != 0 ) ? 5 : $timeout ),
                CURLOPT_REFERER        => 'http' . ( ( $_SERVER[ 'SERVER_PORT' ] == 443 ) ? 's://' : '://' ) . $_SERVER[ 'HTTP_HOST' ] . strtok( $_SERVER[ 'REQUEST_URI' ], '?' ),
                CURLOPT_CAINFO         => dirname( __FILE__ ) . '/bundle.crt'
            );


        // Post data to the URL (expects array)
        if ( isset( $post ) && is_array( $post ) ) {

            // Make every just simpler using custom array for options
            $curl_opts = $curl_opts + array(
                    CURLOPT_POSTFIELDS    => http_build_query( $post, '', '&' ),
                    CURLOPT_POST          => true,
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_FORBID_REUSE  => true
                );

        }

        // Use HTTP Authorization
        if ( isset( $auth ) && !empty( $auth ) ) {

            $curl_opts = $curl_opts + array( CURLOPT_USERPWD => $auth );

        }

        // Call anonymous $progress_function function
        if ( $progress !== false && is_callable( $progress ) ) {

            $curl_opts = $curl_opts + array(
                    CURLOPT_NOPROGRESS       => false,
                    CURLOPT_PROGRESSFUNCTION => $progress
                );

        }

        // Before executing CURL pass options array to the session
        curl_setopt_array( $CURL, $curl_opts );

        // Finally execute CURL
        $data = curl_exec( $CURL );

        // Parse ERROR
        if ( curl_error( $CURL ) ) {

            // This must be referenced in-memory variable
            $error = curl_error( $CURL );

            // Only works when writeLog function is available
            if ( function_exists( 'writeLog' ) )
                writeLog( 'errors', "CURL Request \"{$url}\" failed! LOG: " . curl_error( $CURL ), dirname( __FILE__ ) . '/./../tmp/logs/' );

        }

        // Close connection and return data
        curl_close( $CURL );
        return $data;

    }


    /* Data upload's handler (returns (array) or (string) error)
    ============================================================================= */
    function upload( $form_name, $path = 'data/uploads/', $filename = '' ) {

        // Extension variable
        $extension = ext_get( $_FILES[ $form_name ][ 'name' ] );


        // Filename
        if ( empty( $filename ) ) { // If filename is empty, use uploaded file filename

            $filename = $_FILES[ $form_name ][ 'name' ];

        } else if ( $filename == '.' ) { // If we used dot, generate random filename

            $filename = uniqid() . '.' . $extension;

        } else { // If filename is set, add extension to it

            $filename .= '.' . $extension;

        }


        // Check if path for upload exists, if not create it
        if ( !is_dir( $path ) ) mkdir( $path, 0755, true );


        // ERR Handler
        $errors = array(
            UPLOAD_ERR_OK          => "",
            UPLOAD_ERR_INI_SIZE    => "Larger than upload_max_filesize.",
            UPLOAD_ERR_FORM_SIZE   => "Your upload is too big !",
            UPLOAD_ERR_PARTIAL     => "Upload partially completed !",
            UPLOAD_ERR_NO_FILE     => "No file specified !",
            UPLOAD_ERR_NO_TMP_DIR  => "Woops, server error. Please contact us! <span style=\"display:none\">UPLOAD_ERR_NO_TMP_DIR</span>",
            UPLOAD_ERR_CANT_WRITE  => "Woops, server error. Please contact us! <span style=\"display:none\">UPLOAD_ERR_CANT_WRITE</span>",
            UPLOAD_ERR_EXTENSION   => "Woops, server error. Please contact us! <span style=\"display:none\">UPLOAD_ERR_EXTENSION</span>",
            "UPLOAD_ERR_EMPTY"     => "File is empty.",
            "UPLOAD_ERR_NOT_MOVED" => "Error while saving file !"
        );


        // Handle results & do last touches
        if ( !empty ( $_FILES[ $form_name ][ 'error' ] ) ) {

            return $errors[ $_FILES[ $form_name ][ 'error' ] ];

        } else {

            // Try to move uploaded file from TEMP directory to our new set directory
            if ( !move_uploaded_file( $_FILES[ $form_name ][ 'tmp_name' ], $path . $filename ) ) return $errors[ "UPLOAD_ERR_NOT_MOVED" ];

            // Handle return array
            return array(
                'filename'  => $filename,
                'path'      => $path . $filename,
                'extension' => $extension,
                'mimetype'  => $_FILES[ $form_name ][ 'type' ],
                'size'      => $_FILES[ $form_name ][ 'size' ]
            );

        }


    }


    /* Get artist image & cache it (Uses Last.fm)
    ============================================================================================================================ */
    function getArtwork( $artist, $title = '', $settings = array() ) {

        // Few variables =)
        $extensions = array( 'jpeg', 'jpg', 'png', 'webp', 'svg' );  ## Allowed image extensions
        $artist_name = parse_track( $artist );                       ## AIO Encodes artist name into nice readable string
        $track_name = parse_track( "{$artist} - {$title}" );   ## AIO Encodes artist & title into nice readable string
        $default_artwork = false;

        // Check image size (since 1.31)
        if ( !is_numeric( $settings[ 'images_size' ] ) || $settings[ 'images_size' ] < 100 )
            $settings[ 'images_size' ] = '280';

        // If artist/title is default, don't continue...
        if ( $settings[ 'artist_default' ] == $artist && $settings[ 'title_default' ] == $title )
            $artist = null;


        // Determine default artwork image, if no artist is provided return it
        foreach ( $extensions as $ext ) {

            // Check file of current extension (we're looping)
            if ( is_file( "tmp/images/default.{$ext}" ) ) {

                // If empty or less than 3 characters
                if ( empty( $artist ) OR strlen( $artist ) < 3 ) {

                    return "tmp/images/default.{$ext}";

                } else {

                    $default_artwork = "tmp/images/default.{$ext}";
                    break;

                }

            }

        }

        // Pre-defined artist-title images
        foreach ( $extensions as $ext ) { ## Images saved in /tmp/images/ have higher priority than cached images
            if ( is_file( "tmp/images/{$track_name}.{$ext}" ) )
                return "tmp/images/{$track_name}.{$ext}";
        }

        // Pre-defined artist images
        foreach ( $extensions as $ext ) { ## Images saved in /tmp/images/ have higher priority than cached images
            if ( is_file( "tmp/images/{$artist_name}.{$ext}" ) )
                return "tmp/images/{$artist_name}.{$ext}";
        }

        // Cached version available
        foreach ( $extensions as $ext ) { ## Low priority cached images from various sources
            if ( is_file( "tmp/cache/{$artist_name}.{$ext}" ) )
                return "tmp/cache/{$artist_name}.{$ext}";
        }


        /* No cached version available, prioritize Radionomy cover
        ========================================================================================================= */
        if ( isset( $settings[ 'radionomy' ] ) && $settings[ 'radionomy' ] == 'true' && filter_var( $settings[ 'artwork' ], FILTER_VALIDATE_URL ) ) {

            $imageURL = $settings[ 'artwork' ];

            // Centova cast, we also match "nocover" which is by default a placeholder
        } else if ( isset( $settings[ 'centovacast' ] ) && $settings[ 'centovacast' ] == 'true' && filter_var( $settings[ 'artwork' ], FILTER_VALIDATE_URL ) && !preg_match( '/nocover\.png$/i', $settings[ 'artwork' ] ) ) {

            $imageURL = $settings[ 'artwork' ];

            // Nothing above, so we go with custom API function
        } else {

            // Quick Check for artist variable (iTunes is default)
            $api_func = ( ( !isset( $settings[ 'api_artist' ] ) OR !function_exists( "api_{$settings['api_artist']}" ) ) ? 'api_itunes' : "api_{$settings['api_artist']}" );
            $imageURL = $api_func( trim( $artist ), $settings );

        }


        /* Check if the image URL is valid && Caching is enabled
        ========================================================================================================= */
        if ( !filter_var( $imageURL, FILTER_VALIDATE_URL ) OR !in_array( ext_get( $imageURL ), $extensions ) ) {

            // No valid image, return default or simply false
            return $default_artwork;

        } else if ( $settings[ 'cache_artist_images' ] != 'true' ) { ## Only attempt to get image if url is valid!

            // Caching disabled & Image is valid URL, return url to the image!
            return $imageURL;

        } else if ( $settings[ 'cache_artist_images' ] == 'true' ) { ## Valid image URL and caching enabled

            // Make sure this image downloads fully before stopping the thread/process
            set_time_limit( 120 );
            ignore_user_abort( true );

            // Caching is enabled, URL is valid, download image to RAM
            $img = get( $imageURL, false, false, false, 0 );

            // If image has less than 1kb, something is wrong!
            if ( strlen( $img ) < 1024 ) return $default_artwork;

            // Download image into tmp/cache/ directory (include its extension)
            $path = "tmp/cache/{$artist_name}." . ext_get( $imageURL );
            file_put_contents( $path, $img );

            // Now resize image to 280x280px via crop class
            image::handle( $path, "{$settings[ 'images_size' ]}x{$settings[ 'images_size' ]}", 'crop' );

            // Return path to compressed and cached image
            return $path;

        }

        // If we get this far, big error!
        return $default_artwork;

    }

    /* Simple function to parse XML files into arrays
    ============================================================================================================================ */
    function xml2array( $data, $lower = false ) {

        $vals = json_decode( json_encode( (array)simplexml_load_string( $data ) ), true );

        // Lower / Uppercase array keys
        if ( $lower === true AND is_array( $vals ) )
            return array_change_key_case( $vals, CASE_LOWER );

        else
            return $vals;

    }


    /* Function to convert {$VARIABLE} brackets with PHP variable
    ============================================================================== */
    function template( $content, $array ) {

        // Change array to upper characters
        $array = array_change_key_case( $array, CASE_UPPER );
        $replace = preg_match_all( "/{\\$.*?}/", $content, $all );

        // Parse text
        for ( $i = 0; $i < $replace; $i++ ) {

            // Match brackets and get their "value"
            $value = str_replace( array( '{$', '}' ), null, $all[ '0' ][ $i ] );
            $variable = false;

            // Check for dots in value
            if ( strpos( $value, '.' ) !== false ) {

                $variable = $array;
                $keys = explode( '.', $value );
                foreach ( $keys as $key ): $variable = &$variable[ $key ]; endforeach;

            }

            // Now finally replace brackets
            if ( $variable === false && isset( $array[ $value ] ) )
                $variable = $array[ $value ];

            // Finally replace output  content
            if ( isset( $variable ) )
                $content = str_replace( '{$' . $value . '}', $variable, $content );

        }

        return $content;

    }


    /* Shorten strings via specified length
    ============================================================================== */
    function shorten( $text, $length ) {
        $text = strip_tags( $text );

        $length = abs( (int)$length );
        if ( strlen( $text ) > $length ) $text = preg_replace( "/^(.{1,$length})(\s.*|$)/s", '\\1...', $text );
        return ( $text );
    }


    /* Short function to parse any url format e.g.: http://name.com:port/folder/playlist.pls to http://host:port
    ============================================================================= */
    function parseURL( $url ) {

        // Empty
        if ( empty( $url ) ) return null;

        // Regex
        $match = parse_url( $url );

        // Make sure URL is ok before returning...
        if ( empty( $match[ 'host' ] ) ) {

            return null;

        } else if ( !is_int( $match[ 'port' ] ) ) { // No port or not numeric, default to 80

            $match[ 'port' ] = 80;

        }

        // Host isn't empty, return :)
        return "{$match['scheme']}://{$match['host']}:{$match['port']}";

    }


    /* Short function to speed up deployment of alerts
    ============================================================================== */
    function alert( $text, $mode = 'warning', $php_message = false ) {

        // *** Optional feature which allows replacing $text with actual PHP error message
        if ( $php_message === true ) {

            $err = error_get_last();
            if ( isset( $err[ 'message' ] ) && !empty( $err[ 'message' ] ) ) {
                // $text .= '<pre>' . $err[ 'message' ] . '</pre>';
            }

        }

        // Different modes with icons (looks nice <3)
        if ( $mode == 'warning' ) {

            $mode = 'alert-icon alert-warning';
            $text = '<i class="fa fa-warning"></i><div class="content">' . $text . '</div>';

        } else if ( $mode == 'error' ) {

            $mode = 'alert-icon alert-error';
            $text = '<i class="fa fa-times-circle"></i><div class="content">' . $text . '</div>';

        } else if ( $mode == 'success' ) {

            $mode = 'alert-icon alert-success';
            $text = '<i class="fa fa-check"></i><div class="content">' . $text . '</div>';

        } else if ( $mode == 'info' ) {

            $mode = 'alert-icon alert-info';
            $text = '<i class="fa fa-info-circle"></i><div class="content">' . $text . '</div>';

        }

        return '<div class="alert ' . $mode . '">' . $text . '</div>';
    }


    /* File functions (ext_get, ext_del, etc...)
    ============================================================================== */
    function ext_get( $filename ) {

        return strtolower( str_replace( '.', '', strrchr( $filename, '.' ) ) );

    }

    function ext_del( $filename ) {

        $ext = strrchr( $filename, '.' );
        return ( ( !empty( $ext ) ) ? substr( $filename, 0, -strlen( $ext ) ) : $filename );

    }

    function file_size( $b, $p = null ) {

        $units = array( "B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB" );
        $c = 0;
        if ( !$p && $p !== 0 ) {
            foreach ( $units as $k => $u ) {
                if ( ( $b / pow( 1024, $k ) ) >= 1 ) {
                    $r[ "bytes" ] = $b / pow( 1024, $k );
                    $r[ "units" ] = $u;
                    $c++;
                }
            }

            return number_format( $r[ "bytes" ], 2 ) . ' ' . $r[ "units" ];

        } else {

            return number_format( $b / pow( 1024, $p ) ) . ' ' . $units[ $p ];

        }

    }


    /**
     * Simple function to simplify looking for files and directories
     *
     * @param      $path
     * @param bool $show_files
     * @param bool $show_directories
     * @param bool $directory_append
     *
     * @return array
     */
    function browse( $path, $show_files = true, $show_directories = false, $directory_append = true ) {

        $files = array();

        // Only if dir exists
        if ( is_dir( $path ) ) {

            if ( $handle = opendir( $path ) ) {

                while ( false !== ( $entry = readdir( $handle ) ) ) {

                    // Skip back folder signs
                    if ( $entry == "." || $entry == ".." ) continue;

                    if ( is_dir( $path . $entry ) && $directory_append === true ) $entry .= '/'; // Append / to directories

                    // If specified dirs will be skipped
                    if ( is_dir( $path . $entry ) AND $show_directories === false ) continue;

                    // If specified files will be skipped
                    if ( is_file( $path . $entry ) AND $show_files === false ) continue;

                    // Finally add to the array (list)
                    $files[] = $entry;

                }

                closedir( $handle );

            }

        }

        return $files;

    }


    /**
     * Simple function to handle UTF8 encoding, also make sure we don't encode already encoded string
     *
     * @param $string
     *
     * @return string
     */
    function str_to_utf8( $string ) {

        // Check if mbstring is installed, if not, run old way
        if ( !function_exists( 'mb_convert_encoding' ) )
            return ( ( preg_match( '!!u', $string ) ) ? $string : utf8_encode( $string ) );

        // Convert encoding from XXX to UTF-8
        $string = mb_convert_encoding( $string, "UTF-8" );

        // Escape special characters
        htmlspecialchars( $string, ENT_QUOTES, 'UTF-8' );
        $string = html_entity_decode( $string );

        // Return modified - UTF-8 string
        return $string;

    }


    /* Small function to handle artist image names
    ============================================================================== */
    function parse_track( $string ) {

        // Replace some known characters/strings with text
        $string = str_replace(
            array( '&', 'ft.' ),
            array( 'and', 'feat' ),
            $string
        );

        // Rep
        $rep_arr = array(
            '/[^a-z0-9\p{L}\.]+/iu' => '.',    // Replace all non-standard strings with dot
            '/[\.]{1,}/'            => '.'     // Replace multiple dots in same string
        );

        // Replace bad characters
        $string = preg_replace( array_keys( $rep_arr ), $rep_arr, trim( $string ) );
        return rtrim( strtolower( $string ), '.' );

    }


    /* Short function to delete all extensions for artist
    ============================================================================== */
    function delete_artist( $name ) {

        // Define extensions
        $allow_ext = array( 'jpeg', 'jpg', 'png', 'svg', 'webp' );

        // Set variables
        $files = browse( './../tmp/images/' );
        $name = parse_track( $name );

        // If name is default, skip
        if ( $name == 'default' AND empty( $_FILES[ 'image' ][ 'name' ] ) ) return false;

        // If is array, loop through files and match what we're deleting
        if ( is_array( $files ) ) {

            foreach ( $files as $file ) { // Loop files

                if ( ext_del( $file ) == $name ) { // File matches, delete all extensions of this artist

                    foreach ( $allow_ext as $ext ) {

                        // Delete file
                        if ( is_file( "./../tmp/images/" . ext_del( $file ) . ".{$ext}" ) )
                            @unlink( "./../tmp/images/" . ext_del( $file ) . ".{$ext}" );

                    }
                    return true; // stop loop

                } // End match

                // If not matched, just continue. no other work to be done.

            }

        }

        return false;

    }


    /* Simple and good function to handle templates (we read jsons)
    ============================================================================== */
    function getTemplates( $cwd = '.' ) {

        // New list
        $templates = array();

        // Use cache
        if ( !is_file( "{$cwd}/tmp/cache/templates.cache" ) ) {

            // Handle themes here
            $list = browse( "{$cwd}/templates/", false, true, false );

            // Loop
            foreach ( $list as $dir ) {

                // Definitions?
                if ( is_file( "{$cwd}/templates/{$dir}/manifest.json" ) ) {

                    // Get json
                    $loadedFile = json_decode( file_get_contents( "{$cwd}/templates/{$dir}/manifest.json" ), true );

                    // Verify List - Do not append unless manifest is correct
                    if ( !empty( $loadedFile[ 'name' ] ) && is_file( "{$cwd}/templates/{$dir}/{$loadedFile['template']}" ) ) {

                        // This is JSON from the template
                        $templates[ $dir ] = $loadedFile;

                        // Add full path to the variable
                        $templates[ $dir ][ 'path' ] = "/templates/{$dir}";

                    }

                }

            }

            // Sort them ascending
            asort( $templates );

            // Store cache
            file_put_contents( "{$cwd}/tmp/cache/templates.cache", json_encode( $templates ) );

        } else {

            return json_decode( file_get_contents( "{$cwd}/tmp/cache/templates.cache" ), true );

        }

        return $templates;

    }


    /**
     * Missing folders
     *
     * @param string $path
     */
    function createMissing( $path = '.' ) {

        $folders = array( 'tmp/cache/', 'tmp/images/', 'tmp/logs/', 'tmp/updates/' );
        foreach ( $folders as $folder ) {

            if ( !is_dir( "{$path}/{$folder}" ) ) {

                if ( $folder === 'tmp/images/' ) $copyArtwork = true;
                mkdir( "{$path}/{$folder}", 0755, true );

            }

        }

        // Copy default artwork
        if ( isset( $copyArtwork ) )
            copy( "{$path}/assets/img/default.jpg", "{$path}/tmp/images/default.jpg" );

    }


    /* Very small function to exit JSON with grace
    ============================================================================== */
    function exitJSON() {

        // Clean buffer and every thing above
        if ( ob_get_level() ) ob_end_clean();

        // Empty array
        echo json_encode( array() );
        exit;

    }


    /* Bellow you will find different API functions for various services
    ============================================================================== */
    function api_lastfm( $artist, $s = array() ) {

        $data = xml2array( get( "https://ws.audioscrobbler.com/2.0/?method=artist.getInfo&artist=" . urlencode( $artist ) . "&api_key={$s['api_key']}", false, false, false, 30 ) );
        return ( isset( $data[ 'artist' ][ 'image' ][ 4 ] ) && !empty( $data[ 'artist' ][ 'image' ][ 4 ] ) ) ? $data[ 'artist' ][ 'image' ][ 4 ] : $data[ 'artist' ][ 'image' ][ 3 ];

    }

    ## https://api.spotify.com/v1
    function api_spotify( $artist, $s = array() ) {

        $data = json_decode( get( "https://api.spotify.com/v1/artists?api_key={$s['api_key']}&name=" . urlencode( $artist ) . "&format=json&results=1&start=0", false, false, false, 30 ), true );
        return $data[ 'response' ][ 'images' ][ 0 ][ 'url' ];

    }

    ## itunes.apple.com
    function api_itunes( $artist, $s = array() ) {

        // Attempt searching for image
        $data = get( 'https://itunes.apple.com/search?term=' . urlencode( $artist ) . '&media=music&limit=1' );

        // If there is an response
        if ( $data !== false ) {

            // Read JSON String
            $data = json_decode( $data, true );

            // Reading JSON
            if ( $data[ 'resultCount' ] >= 1 ) { // Check if result is not empty

                // Find position of LAST slash (/)
                $last_slash = strripos( $data[ 'results' ][ 0 ][ 'artworkUrl100' ], '/' );

                // Return the modified string
                return substr( $data[ 'results' ][ 0 ][ 'artworkUrl100' ], 0, $last_slash ) . "/{$s['images_size']}x{$s['images_size']}." . ext_get( $data[ 'results' ][ 0 ][ 'artworkUrl100' ] );

            }

        }

    }

?>