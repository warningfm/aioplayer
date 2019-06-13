<?php

// Make sure admin is doing update!
session_start();
if ( $_SESSION[ 'a-login' ] !== true ) exit;
session_write_close();
// It is admin ;)

?><!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>AIO - Radio Player Debug</title>
        <link rel="shortcut icon" href="./assets/favicon.ico">
        <script src="./../assets/js/jquery-1.11.2.min.js"></script>
    </head>
    <body>
        <?php

            // Before anything else
            header( 'X-Accel-Buffering: no' );
            header( 'Content-Encoding: none' );

            // Stop previous buffers
            while ( @ob_end_flush() ) ;

            // Turn on implicit flushing
            ob_implicit_flush( true );

            // Required functions
            function send() {
                ob_flush();
                flush();
            }

            function js( $txt ) {
                echo "\t\t<script>{$txt}</script>\n";
                send();
            }

            // Some PHP settings & error handling
            set_time_limit( 0 );
            error_reporting( E_ALL ^ E_NOTICE );
            ini_set( 'default_charset', 'utf-8' );
            ini_set( "log_errors", "on" );
            ini_set( "error_log", getcwd() . "./../tmp/logs/php.log" );

            // Include stuff
            include './../inc/functions.php';
            if ( is_file( './../inc/conf/general.php' ) ) include './../inc/conf/general.php';

            // Load channels file (used for all actions on this page)
            if ( is_file( "./../inc/conf/channels.php" ) ) include( "./../inc/conf/channels.php" );
            if ( !is_array( $channels ) ) $channels = array();

            // Kill output buffers
            echo str_repeat( ' ', 1024 * 64 ) . "\n";
            send();

            // Various testing options
            switch ( $_GET[ 'test' ] ) {

                // Test port 8000 (Icecast/Shoutcast)
                case 'ports':
                    $test = array( 'Shoutcast & Icecast' => 'http://defikon.com:8000/status.xsl' );
                    break;

                // Centova cast test
                case 'centovacast':
                    $test = array( 'Centovacast' => 'http://sc2.streamingpulse.com:2199/' );
                    break;

                // Test port 80 (Radionomy)
                case 'radionomy':
                    $test = array( 'Radionomy API' => 'http://api.radionomy.com/currentsong.cfm' );
                    break;

                // Test the update center SSL (TLS + SNI)
                case 'ssl_check':
                    $test = array( 'Update Center' => 'https://prahec.com/envato', 'iTunes SSL Check' => 'https://is2-ssl.mzstatic.com/' );
                    break;

                // Test connection for all configured channels
                case 'user':

                    foreach ( $channels as $channel ) {

                        // Skip Disabled streams
                        if ( $channel[ 'stats' ][ 'method' ] == 'disabled' )
                            continue;

                        // Skip Radionomy
                        if ( $channel[ 'stats' ][ 'method' ] == 'radionomy' )
                            continue;

                        // Skip SAM Broadcaster
                        if ( $channel[ 'stats' ][ 'method' ] == 'sam' )
                            continue;

                        // Skip Direct Stream
                        if ( $channel[ 'stats' ][ 'method' ] == 'direct' ) {

                            js( '$(window.parent.document).find(".debug-output").append(\'<br>Connecting to ' . $channel[ 'stats' ][ 'url' ] . ' (' . $channel[ 'name' ] . ')...\');' );
                            $direct = true;

                            // Test connection
                            if ( read_stream( $channel[ 'stats' ][ 'url' ] ) !== false )
                                js( '$(window.parent.document).find(".debug-output").append(\' <b><span style="color: green;"><br>Connection successfully established!</span></b><br>\');' );

                            else
                                js( '$(window.parent.document).find(".debug-output").append(\' <b><span style="color: red;"><br>Connection failed!</span></b><br>\');' );

                            // Yea, don't continue to CURL test =)
                            continue;

                        }

                        // Add channel to array
                        $test[ $channel[ 'name' ] ] = $channel[ 'stats' ][ 'url' ];

                    }

                    break;

                default:
                    $test = array(
                        'Shoutcast & Icecast' => 'http://defikon.com:8000/status.xsl',
                        'Centovacast'         => 'http://uk1.streamingpulse.com:2199/',
                        'Radionomy'           => 'http://api.radionomy.com/currentsong.cfm'
                    );
                    break;

            }


            // Now do the testing
            if ( is_array( $test ) AND count( $test ) >= 1 ) {

                // LOOP
                foreach ( $test as $name => $url ) {

                    js( '$(window.parent.document).find(".debug-output").' . ( ( $c == 1 ) ? 'html(\'' : 'append(\'<br>' ) . 'Connecting to ' . $url . ' (' . $name . ')...\');' );


                    // Open temporary file handler
                    if ( $settings[ 'debugging' ] == 'enabled' ) $verbose = fopen( 'php://temp', 'w+' );

                    // In 1.22 we changed get() heavily to allow debugging too, its awesome now!
                    $test = get( $url, false, false, false, 5, $curl_error,
                                 array(
                                     CURLOPT_RANGE   => '0-500',
                                     CURLOPT_VERBOSE => ( ( $settings[ 'debugging' ] == 'enabled' ) ? true : false ),
                                     CURLOPT_STDERR  => $verbose
                                 )
                    );


                    // Test connection
                    if ( $test !== false )
                        js( '$(window.parent.document).find(".debug-output").append(\' <b><span style="color: green;"><br>Connection successfully established!</span></b><br>\');' );

                    else
                        js( '$(window.parent.document).find(".debug-output").append(\' <b><span style="color: red;"><br>' . ( ( !empty( $curl_error ) ) ? $curl_error : 'Connection failed!' ) . '</span></b><br>\');' );


                    // Verbose output
                    if ( $settings[ 'debugging' ] == 'enabled' ) {

                        js( '$(window.parent.document).find(".debug-output").append(\'<br><b>CURL VERBOSE LOG</b> ( ' . $url . ' )<br>' . str_repeat( '*', 125 ) . '<br>\');' );

                        rewind( $verbose ); ## Rewind
                        while ( !feof( $verbose ) ) { ## Read

                            $msg = str_replace( array( "\n", "\r" ), '', fgets( $verbose, 2048 ) );
                            js( '$(window.parent.document).find(".debug-output").append(\'' . $msg . '<br>\');' );

                        }

                        js( '$(window.parent.document).find(".debug-output").append(\'' . str_repeat( '*', 125 ) . '<br>\');' );
                        fclose( $verbose );

                    }

                } // END LOOP

            } else if ( !isset( $direct ) ) {

                // Nothing to test, show "nothing to test" message
                js( '$(window.parent.document).find(".debug-output").html(\'Unable to find a channel for testing. Note: If you have single Radionomy channel, this does not work.\');' );

            }
        ?>
    </body>
</html>