<?php

    // Include other needed files
    require 'inc/lib/cache.class.php';
    require 'inc/lib/image-resize.class.php';
    if ( is_file( 'inc/conf/channels.php' ) ) include 'inc/conf/channels.php';
    if ( !is_array( $channels ) ) $channels = array();

    // Start few functions and init objects
    header( "Content-Type: application/json" );
    $cache = new cache( array( 'path' => 'tmp/cache/' ) );


    /* Initial player run, get all channels in nice json object
    ============================================================================================== */
    if ( isset( $_GET[ 'c' ] ) && $_GET[ 'c' ] == 'all' ) {

        // Yea, this is horrible here...
        $templates = getTemplates();

        // If count of channels is more than 1
        if ( count( $channels ) >= 1 ) {

            $out = array();

            // LOOP defined channels and remove stats settings
            foreach ( $channels as $chn_key => $chn ) {

                // Check if skin exists
                if ( empty( $chn[ 'skin' ] ) || !is_file( ".{$templates[$settings['template']]['path']}/{$chn[ 'skin' ]}" ) ) {

                    // Better set default too
                    $chn[ 'skin' ] = $templates[ $settings[ 'template' ] ][ 'schemes' ][ 0 ][ 'style' ];

                }

                // Remove sensitive stuff
                unset( $chn[ 'stats' ] );
                $out[ $chn_key ] = $chn;

            }

            // Output data into JSON array (or JSONP)
            $json_data = json_encode( $out );
            exit( ( !empty( $_GET[ 'callback' ] ) && $settings[ 'api' ] == 'true' ) ? "{$_GET['callback']}({$json_data});" : $json_data );

        } else { // No channels defined

            exitJSON();

        }

    }


    /* URL Parameter C is checked here, if channel doesn't exist, return empty json
    ============================================================================================== */
    foreach ( $channels as $key => $search ) {

        // Match requested channel in settings array
        if ( $search[ 'name' ] == $_GET[ 'c' ] ) {

            $chn_key = $key;
            break;

        }

    }

    ## Make sure this channel really exists
    if ( !is_array( $channels[ $chn_key ] ) ) exit( json_encode( array() ) ); ## Specified channel doesn't exist

    // Set few vars before attempting fate :)
    $info = array();
    $channel = $channels[ $chn_key ];
    $cache_time = ( ( $settings[ 'stats_refresh' ] - 1 ) <= 1 ) ? 10 : ( $settings[ 'stats_refresh' ] - 1 );


    /* Now do the heavy work, use configured method to get stats information
    ============================================================================================== */
    switch ( $channel[ 'stats' ][ 'method' ] ) {


        /* Connects to specified stream and opens it as a player, then it reads sent track ID. (NO CURL)
        ============================================================================================== */
        case 'direct':

            // Check if allow_url_fopen is enabled
            if ( !ini_get( 'allow_url_fopen' ) ) {

                writeLog( 'errors', "{$channel['name']}: Unable to connect to stream because required PHP option \"allow_url_fopen\" is disabled!" );
                exitJSON();

            }


            // Check cache if it doesn't exit, create new entry
            if ( !$info = $cache->get( 'stream.' . $chn_key . '.info' ) ) {

                // Attempt to read few bytes of stream to get current playing track information
                $get_info = read_stream( $channel[ 'stats' ][ 'url' ] );

                // Use backup if first failed
                if ( ( empty( $get_info ) OR $get_info === false ) AND !empty( $channel[ 'fallback' ] ) )
                    $get_info = read_stream( $channel[ 'fallback' ] );


                // Connection failed, log it
                if ( $get_info === false )
                    writeLog( 'errors', "{$channel['name']}: Connection to remote stream {$channel['stats']['url']} failed!" );


                // Now, result must not be empty or err occurred
                preg_match( '/' . $settings[ 'track_regex' ] . '/', str_to_utf8( $get_info ), $track );

                $info[ 'artist' ] = ( ( empty( $track[ 'artist' ] ) ) ? $settings[ 'artist_default' ] : trim( $track[ 'artist' ] ) );
                $info[ 'title' ] = ( ( empty( $track[ 'title' ] ) ) ? $settings[ 'title_default' ] : trim( $track[ 'title' ] ) );
                $info[ 'image' ] = getArtwork( $info[ 'artist' ], $info[ 'title' ], $settings );
                $info[ 'status' ] = 'no-cache';

                // Cache result
                $cache->set( 'stream.' . $chn_key . '.info', $info, $cache_time );

            } else {

                // Info only
                $info[ 'status' ] = 'cached';

            }

            break;


        /* Connect's to shoutcast admin panel and read's XML of a station
        ============================================================================================== */
        case 'shoutcast':

            // Check conf first
            if ( empty( $channel[ 'stats' ][ 'url' ] ) OR empty( $channel[ 'stats' ][ 'auth' ] ) ) {

                writeLog( 'errors', "{$channel['name']}: Invalid configuration! Missing Shoutcast URL or authorization password" );
                exitJSON();

            } else if ( !function_exists( 'curl_version' ) ) {

                writeLog( 'errors', "{$channel['name']}: CURL extension is not loaded!" );
                exitJSON();

            } else if ( !function_exists( 'simplexml_load_string' ) ) {

                writeLog( 'errors', "{$channel['name']}: SimpleXML extension is not loaded!" );
                exitJSON();

            }


            // Check cache
            if ( !$info = $cache->get( 'stream.' . $chn_key . '.info' ) ) {

                if ( !$xmlfile = get( "{$channel['stats']['url']}/admin.cgi?pass={$channel['stats']['auth']}&mode=viewxml&sid={$channel['stats']['sid']}" ) ) {

                    writeLog( 'errors', "{$channel['name']}: Connection to Shoutcast server failed!" );

                } else {

                    $sc_data = xml2array( $xmlfile, true );

                    // Log error if song title is empty
                    if ( empty( $sc_data[ 'songtitle' ] ) )
                        writeLog( 'errors', "{$channel['name']}: Unable to get song title, it would seem server response was \"OK\" but result is unknown." );


                    // Now, result must not be empty or err occurred
                    preg_match( '/' . $settings[ 'track_regex' ] . '/', str_to_utf8( $sc_data[ 'songtitle' ] ), $track );

                    // All normal details
                    $info[ 'artist' ] = ( ( empty( $track[ 'artist' ] ) ) ? $settings[ 'artist_default' ] : trim( $track[ 'artist' ] ) );
                    $info[ 'title' ] = ( ( empty( $track[ 'title' ] ) ) ? $settings[ 'title_default' ] : trim( $track[ 'title' ] ) );
                    $info[ 'image' ] = getArtwork( $info[ 'artist' ], $info[ 'title' ], $settings );

                    // Special, if history is available, use this
                    if ( $channel[ 'stats' ][ 'sc-history' ] && isset( $sc_data[ 'songhistory' ] ) && count( $sc_data[ 'songhistory' ][ 'SONG' ] ) >= 1 ) {

                        $info[ 'history' ] = array();
                        foreach ( $sc_data[ 'songhistory' ][ 'SONG' ] as $song ) {

                            // Match as you would any song
                            preg_match( '/' . $settings[ 'track_regex' ] . '/', str_to_utf8( $song[ 'TITLE' ] ), $sc );
                            $song[ 'artist' ] = ( ( empty( $sc[ 'artist' ] ) ) ? $settings[ 'artist_default' ] : trim( $sc[ 'artist' ] ) );
                            $song[ 'title' ] = ( ( empty( $sc[ 'title' ] ) ) ? $settings[ 'title_default' ] : trim( $sc[ 'title' ] ) );

                            // Now finally add to history
                            $info[ 'history' ][] = array(
                                'artist' => $song[ 'artist' ],
                                'title'  => $song[ 'title' ],
                                'image'  => getArtwork( $song[ 'artist' ], $song[ 'title' ], $settings ),
                                'time'   => ( (int)$song[ 'PLAYEDAT' ] * 1000 )
                            );

                        }

                    }

                    // NO cache
                    $info[ 'status' ] = 'no-cache';

                    // Cache result
                    $cache->set( 'stream.' . $chn_key . '.info', $info, $cache_time );

                }

            } else {

                // Info only
                $info[ 'status' ] = 'cached';

            }

            break;


        /* Connects to Shoutcast admin panel and reads XML of a station
        ============================================================================================== */
        case 'icecast':

            // Check conf first
            if ( empty( $channel[ 'stats' ][ 'url' ] ) OR empty( $channel[ 'stats' ][ 'auth-user' ] ) OR empty( $channel[ 'stats' ][ 'auth-pass' ] ) ) {

                writeLog( 'errors', "{$channel['name']}: Invalid configuration! Missing Icecast URL, authorization or mount details!" );
                exitJSON();

            } else if ( !function_exists( 'curl_version' ) ) {

                writeLog( 'errors', "{$channel['name']}: CURL extension is not loaded!" );
                exitJSON();

            } else if ( !function_exists( 'simplexml_load_string' ) ) {

                writeLog( 'errors', "{$channel['name']}: SimpleXML extension is not loaded!" );
                exitJSON();

            }


            // Check cache
            if ( !$info = $cache->get( 'stream.' . $chn_key . '.info' ) ) {


                // Icecast requires proper HTTP auth, so we provide it!
                if ( !$xmlfile = get( "{$channel['stats']['url']}/admin/stats", null, "{$channel['stats']['auth-user']}:{$channel['stats']['auth-pass']}" ) ) {

                    writeLog( 'errors', "{$channel['name']}: Connection to Icecast stats server failed!" );

                } else if ( preg_match( "/You need to authenticate/s", $xmlfile ) ) {

                    writeLog( 'errors', "{$channel['name']}: Unable to authorize, login failed!" );

                } else { // Now we should have details, attempt to use them.

                    $ice = array();
                    $icedata = xml2array( $xmlfile, true );

                    // Multiple mount points
                    if ( is_array( $icedata[ 'source' ][ 0 ] ) ) {

                        foreach ( $icedata[ 'source' ] as $mount ) {

                            // Parse mount name
                            $mountName = $mount[ '@attributes' ][ 'mount' ];
                            unset( $mount[ '@attributes' ] );

                            // Make nice array with mount name as key (for fall-back <3)
                            $ice[ $mountName ] = $mount;

                        }

                        // Single mount point
                    } else {

                        // Get mount name
                        $mountName = $icedata[ 'source' ][ '@attributes' ][ 'mount' ];
                        unset( $icedata[ 'source' ][ '@attributes' ] );

                        // Set mount info
                        $ice[ $mountName ] = $icedata[ 'source' ];

                    }


                    // Check if specified mount or fall-back mount exist
                    if ( !is_array( $ice[ $channel[ 'stats' ][ 'mount' ] ] ) AND !is_array( $ice[ $channel[ 'stats' ][ 'fallback' ] ] ) ) {

                        writeLog( 'errors', "{$channel['name']}: Specified mount and fall-back mount were not found!" );

                    } else {

                        // Attempt to use main mount, else use backup one
                        if ( !empty( $ice[ $channel[ 'stats' ][ 'mount' ] ][ 'title' ] ) OR !empty( $ice[ $channel[ 'stats' ][ 'mount' ] ][ 'artist' ] ) )
                            $ice = ( ( empty( $ice[ $channel[ 'stats' ][ 'mount' ] ][ 'artist' ] ) ) ? $ice[ $channel[ 'stats' ][ 'mount' ] ][ 'title' ] : $ice[ $channel[ 'stats' ][ 'mount' ] ][ 'artist' ] . ' - ' . $ice[ $channel[ 'stats' ][ 'mount' ] ][ 'title' ] );

                        else  // Backup mount
                            $ice = ( ( empty( $ice[ $channel[ 'stats' ][ 'fallback' ] ][ 'artist' ] ) ) ? $ice[ $channel[ 'stats' ][ 'fallback' ] ][ 'title' ] : $ice[ $channel[ 'stats' ][ 'fallback' ] ][ 'artist' ] . ' - ' . $ice[ $channel[ 'stats' ][ 'fallback' ] ][ 'title' ] );


                        // Now, after so much checks and stuff, do track match
                        preg_match( '/' . $settings[ 'track_regex' ] . '/', str_to_utf8( $ice ), $track );

                        $info[ 'artist' ] = ( ( empty( $track[ 'artist' ] ) ) ? $settings[ 'artist_default' ] : trim( $track[ 'artist' ] ) );
                        $info[ 'title' ] = ( ( empty( $track[ 'title' ] ) ) ? $settings[ 'title_default' ] : trim( $track[ 'title' ] ) );
                        $info[ 'image' ] = getArtwork( $info[ 'artist' ], $info[ 'title' ], $settings );
                        $info[ 'status' ] = 'no-cache';

                        // Cache
                        $cache->set( 'stream.' . $chn_key . '.info', $info, $cache_time );

                    }

                }


            } else {

                // Info only
                $info[ 'status' ] = 'cached';

            }

            break;


        /* Uses MySQLi extension to connect to the specified stream. This may be the most reliable option
        ============================================================================================== */
        case 'sam':

            // Check conf first
            if ( empty( $channel[ 'stats' ][ 'host' ] ) OR empty( $channel[ 'stats' ][ 'auth-user' ] ) OR empty( $channel[ 'stats' ][ 'auth-pass' ] ) OR empty( $channel[ 'stats' ][ 'db' ] ) ) {

                writeLog( 'errors', "{$channel['name']}: Invalid configuration! Missing all required information to access SAM Broadcaster's database." );
                exitJSON();

            } else if ( !class_exists( 'mysqli' ) ) {

                writeLog( 'errors', "{$channel['name']}: MySQLi extension is not loaded, unable to connect to database!" );
                exitJSON();

            }


            // Check for cache
            if ( !$info = $cache->get( 'stream.' . $chn_key . '.info' ) ) {

                // Since 1.21 we also allow sockets and ports
                $p_url = parse_url( $channel[ 'stats' ][ 'host' ] );

                // maybe sock?
                if ( is_file( $channel[ 'stats' ][ 'host' ] ) && empty( $p_url[ 'host' ] ) ) {

                    $channel[ 'stats' ][ 'socket' ] = $channel[ 'stats' ][ 'host' ];
                    $channel[ 'stats' ][ 'host' ] = '127.0.0.1';

                } else if ( !empty( $p_url[ 'port' ] ) ) { // Port added?

                    $channel[ 'stats' ][ 'host' ] = $p_url[ 'host' ];
                    $channel[ 'stats' ][ 'port' ] = $p_url[ 'port' ];

                } else {

                    // Not necessary, but we still define the variables just in case
                    $channel[ 'stats' ][ 'socket' ] = null;
                    $channel[ 'stats' ][ 'port' ] = null;

                }

                // Attempt connecting via mysqli
                $db = new mysqli( $channel[ 'stats' ][ 'host' ], $channel[ 'stats' ][ 'auth-user' ], $channel[ 'stats' ][ 'auth-pass' ], $channel[ 'stats' ][ 'db' ], $channel[ 'stats' ][ 'port' ], $channel[ 'stats' ][ 'socket' ] );

                // MySQL connection failed here
                if ( $db->connect_errno > 0 ) {

                    writeLog( 'errors', "{$channel['name']}: Database connection failed, MySQL returned: {$db->connect_error}" );
                    exitJSON();

                } else { // Connected!

                    // Fetch SAM history
                    $sam = mysqli_fetch_assoc(
                        $db->query( "SELECT songID, artist, title, date_played, duration FROM {$channel['stats']['db']}.historylist " .
                                    "ORDER BY `historylist`.`date_played` DESC LIMIT 0 , 1"
                        )
                    );

                    // Check if query failed?
                    if ( $db->error ) { // Failed to connect

                        writeLog( 'errors', "{$channel['name']}: SAM Database query failed with error: {$db->error}" );
                        exitJSON();

                    } else {

                        // Sometimes SAM ID3 tags are incorrect
                        if ( !empty( $sam[ 'artist' ] ) AND empty( $sam[ 'title' ] ) )
                            preg_match( '/' . $settings[ 'track_regex' ] . '/', $sam[ 'artist' ], $track );

                        else if ( empty( $sam[ 'artist' ] ) AND !empty( $sam[ 'title' ] ) )
                            preg_match( '/' . $settings[ 'track_regex' ] . '/', $sam[ 'title' ], $track );

                        else
                            $track = $sam;


                        // Now, after so much checks and stuff, do track match
                        $info[ 'artist' ] = ( ( empty( $track[ 'artist' ] ) ) ? $settings[ 'artist_default' ] : trim( str_to_utf8( $track[ 'artist' ] ) ) );
                        $info[ 'title' ] = ( ( empty( $track[ 'title' ] ) ) ? $settings[ 'title_default' ] : trim( str_to_utf8( $track[ 'title' ] ) ) );
                        $info[ 'image' ] = getArtwork( $info[ 'artist' ], $info[ 'track' ], $settings );
                        $info[ 'status' ] = 'no-cache';

                        // Cache
                        $cache->set( 'stream.' . $chn_key . '.info', $info, $cache_time );

                    }

                }

            } else {

                // Info only
                $info[ 'status' ] = 'cached';

            }

            break;


        /* This will connect to Centova-cast API which is usually located on streaming provider. Requires enabled track info widget
        ============================================================================================== */
        case 'centovacast':

            // Check config first
            if ( empty( $channel[ 'stats' ][ 'url' ] ) OR empty( $channel[ 'stats' ][ 'user' ] ) ) {

                writeLog( 'errors', "{$channel['name']}: Invalid configuration! Missing Centova Cast URL or username!" );
                exitJSON();

            } else if ( !function_exists( 'curl_version' ) ) {

                writeLog( 'errors', "{$channel['name']}: CURL extension is not loaded!" );
                exitJSON();

            }


            // Check for cache
            if ( !$info = $cache->get( 'stream.' . $chn_key . '.info' ) ) {

                if ( !$centova = get( "{$channel['stats']['url']}/external/rpc.php?m=streaminfo.get&username={$channel['stats']['user']}&rid={$channel['stats']['user']}&charset=utf8" ) ) {

                    writeLog( 'errors', "{$channel['name']}: Connection to Centova Cast RPC API failed!" );

                } else {

                    $centova = json_decode( $centova, true );
                    if ( !empty( $centova[ 'error' ] ) ) {

                        writeLog( 'errors', "{$channel['name']}: Centova Cast returned error: {$centova['error']}!" );

                    } else {

                        // We don't use str_to_utf8 here because JSON doesn't support non-utf8 characters
                        $track = $centova[ 'data' ][ 0 ][ 'track' ];

                        // Now, after so much checks and stuff, do track match
                        $info[ 'artist' ] = ( ( empty( $track[ 'artist' ] ) ) ? $settings[ 'artist_default' ] : trim( $track[ 'artist' ] ) );
                        $info[ 'title' ] = ( ( empty( $track[ 'title' ] ) ) ? $settings[ 'title_default' ] : trim( $track[ 'title' ] ) );
                        $info[ 'image' ] = getArtwork( $info[ 'artist' ], $info[ 'title' ], $settings + array( 'centovacast' => $channel[ 'stats' ][ 'use-cover' ], 'artwork' => $track[ 'imageurl' ] ) );
                        $info[ 'status' ] = 'no-cache';

                        // Cache
                        $cache->set( 'stream.' . $chn_key . '.info', $info, $cache_time );

                    }

                }

            } else {

                // Info only
                $info[ 'status' ] = 'cached';

            }

            break;


        /* Radionomy is Shoutcast provider who has their own API. Since company purchased Shoutcast this is cool.
        ============================================================================================== */
        case 'radionomy':

            // Check config first
            if ( empty( $channel[ 'stats' ][ 'user-id' ] ) OR empty( $channel[ 'stats' ][ 'api-key' ] ) ) {

                writeLog( 'errors', "{$channel['name']}: Invalid configuration, missing Radionomy Radio ID or API key!" );
                exitJSON();

            } else if ( !function_exists( 'curl_version' ) ) {

                writeLog( 'errors', "{$channel['name']}: CURL extension is not loaded!" );
                exitJSON();

            } else if ( !function_exists( 'simplexml_load_string' ) ) {

                writeLog( 'errors', "{$channel['name']}: SimpleXML extension is not loaded!" );
                exitJSON();

            }


            // Check for cache
            if ( !$info = $cache->get( 'stream.' . $chn_key . '.info' ) ) {

                // Connect to API
                if ( !$onmy = get( "http://api.radionomy.com/currentsong.cfm?radiouid={$channel['stats']['user-id']}&apikey={$channel['stats']['api-key']}&callmeback=yes&type=xml&cover=yes&previous=no" ) ) {

                    writeLog( 'errors', "{$channel['name']}: Connection to Radionomy API failed!" );

                } else {

                    $radioxml = xml2array( $onmy, true );

                    if ( $radioxml === false || !is_array( $radioxml ) ) {

                        writeLog( 'errors', "{$channel['name']}: Unable to decode Radionomy response!" );

                    } else {

                        $track = $radioxml[ 'track' ];

                        // Now, after so much checks and stuff, do track match
                        $info[ 'artist' ] = str_to_utf8( ( empty( $track[ 'artists' ] ) ) ? $settings[ 'artist_default' ] : trim( $track[ 'artists' ] ) );
                        $info[ 'title' ] = str_to_utf8( ( empty( $track[ 'title' ] ) ) ? $settings[ 'title_default' ] : trim( $track[ 'title' ] ) );
                        $info[ 'image' ] = getArtwork( $info[ 'artist' ], $info[ 'title' ], $settings + array( 'radionomy' => $channel[ 'stats' ][ 'use-cover' ], 'artwork' => $track[ 'cover' ] ) ); ## Special handler for artwork
                        $info[ 'status' ] = 'no-cache';

                        // Radionomy has this awesome function that it tells u when to call back next time
                        $call_me_back = floor( $radioxml[ 'track' ][ 'callmeback' ] / 1000 );
                        $call_me_back = ( ( $call_me_back <= 1 ) ? 300 : $call_me_back );

                        // Cache
                        $cache->set( 'stream.' . $chn_key . '.info', $info, $call_me_back );

                    }

                }


            } else {

                // Info only
                $info[ 'status' ] = 'cached';

            }

            break;


        /* Custom (URL) is option that parses ONLY artist - title from any given URL (since 1.34 supports JSON too)
        ============================================================================================== */
        case 'custom':

            // Check config first
            if ( empty( $channel[ 'stats' ][ 'url' ] ) ) {

                writeLog( 'errors', "{$channel['name']}: Invalid configuration! Missing Custom URL!" );
                exitJSON();

            } else if ( !function_exists( 'curl_version' ) ) {

                writeLog( 'errors', "{$channel['name']}: CURL extension is not loaded!" );
                exitJSON();

            }


            // Check for cache
            if ( !$info = $cache->get( 'stream.' . $chn_key . '.info' ) ) {

                // Connect to API
                if ( !$txt = get( $channel[ 'stats' ][ 'url' ], false, ( ( !empty( $channel[ 'stats' ][ 'user' ] ) && !empty( $channel[ 'stats' ][ 'pass' ] ) ) ? "{$channel['stats']['user']}:{$channel['stats']['pass']}" : '' ) ) ) {

                    writeLog( 'errors', "{$channel['name']}: Connection to Custom URL \"{$channel['stats']['url']}\" failed!" );

                } else {

                    if ( $txt === false || empty( $txt ) ) {

                        writeLog( 'errors', "{$channel['name']}: Connection to the \"Custom\" method was successful but we received no data!" );

                    } else {

                        // First try if this is a json string
                        if ( ( $track = json_decode( str_to_utf8( $txt ), true ) ) === null ) {

                            // Now, after so much checks and stuff, do track match
                            preg_match( '/' . $settings[ 'track_regex' ] . '/', str_to_utf8( $txt ), $track );

                        }

                        // Now, after so much checks and stuff, handle track info
                        $info[ 'artist' ] = ( ( empty( $track[ 'artist' ] ) ) ? $settings[ 'artist_default' ] : trim( $track[ 'artist' ] ) );
                        $info[ 'title' ] = ( ( empty( $track[ 'title' ] ) ) ? $settings[ 'title_default' ] : trim( $track[ 'title' ] ) );
                        $info[ 'image' ] = ( !isset( $track[ 'image' ] ) || empty( $track[ 'image' ] ) ) ? getArtwork( $info[ 'artist' ], $info[ 'title' ], $settings ) : $track[ 'image' ];
                        $info[ 'status' ] = 'no-cache';

                        // Cache
                        $cache->set( 'stream.' . $chn_key . '.info', $info, $cache_time );

                    }

                }


            } else {

                // Info only
                $info[ 'status' ] = 'cached';

            }

            break;


        /* Disabled, simply return defaults
        ============================================================================================== */
        case 'disabled':

            // Disabled or ERROR occurred
            $jsonData = json_encode(
                array(
                    'artist' => $settings[ 'artist_default' ],
                    'title'  => $settings[ 'title_default' ],
                    'image'  => getArtwork( null ),
                    'status' => 'disabled'
                )
            );

            exit( ( !empty( $_GET[ 'callback' ] ) && $settings[ 'api' ] == 'true' ) ? "{$_GET['callback']}({$jsonData});" : $jsonData );
            break;


        /* This should not happen, at all.
        ============================================================================================== */
        default:
            writeLog( 'errors', "{$channel['name']}: Invalid method! This is truly fancy error which should never happen!" );
            die( 'AIO - Radio Station Player API' );
            break;

    }


    /* Heavy work done, handle data returned from API's and show it in JSON encoded format
    ============================================================================================== */
    if ( $info !== false ) {

        // Encode gathered information
        $jsonData = json_encode( $info );

    } else {

        // Create simple & empty JSON array
        $jsonData = json_encode( array() );

    }


    // Show output (if this is JSONP request, adapt response to its requirements
    echo( ( !empty( $_GET[ 'callback' ] ) && $settings[ 'api' ] == 'true' ) ? "{$_GET['callback']}({$jsonData});" : $jsonData );

    // If cache is initiated, close & save its status.
    if ( is_object( $cache ) ) $cache->quit();

?>