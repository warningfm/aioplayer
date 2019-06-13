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
        <title>AIO - Radio Player Artwork Import</title>
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
                echo "<script>{$txt}</script>\n";
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
            include './../inc/lib/image-resize.class.php';
            if ( is_file( './../inc/conf/general.php' ) ) include './../inc/conf/general.php';

            // Kill output buffers
            echo str_repeat( ' ', 65537 ) . "\n";
            send();


            // Check if path is specified
            if ( empty( $_GET[ 'path' ] ) ) {

                js( '$(window.parent.document).find(".import-output").html(\'<span class="text-red">You did not specify import path, unable to continue...</span><br>\');' );
                exit;

            }

            // List of extensions we allow to import
            $allow_ext = array( 'jpeg', 'jpg', 'png', 'svg', 'webp' );

            // Do LOCAL import
            if ( preg_match( '/ftps?:\/\//i', $_GET[ 'path' ] ) == false ) {

                $lock_local = realpath( './../' );
                $directory  = realpath( $lock_local . '/' . $_GET[ 'path' ] );

                // Verifications
                if ( !is_dir( "./../{$_GET[ 'path' ]}" ) ) {

                    js( '$(window.parent.document).find(".import-output").html(\'<span class="text-red">Specified directory does not exist or its not readable!</span><br>\');' );

                } else if ( strpos( $directory, $lock_local ) !== 0 ) {

                    js( '$(window.parent.document).find(".import-output").html(\'<span class="text-red">You are not allowed to import images from directory other than where the script is located!</span><br>\');' );

                } else {

                    // Read specified directory for files
                    $import = browse( "{$directory}/" );

                    // Check read files
                    if ( !is_array( $import ) OR count( $import ) < 1 ) {

                        js( '$(window.parent.document).find(".import-output").append(\'<span class="text-red">Unable to find any files located in the specified folder!</span><br>\');' );

                    } else {

                        // Show message
                        js( '$(window.parent.document).find(".import-output").append(\'Found <b>' . count( $import ) . '</b> files, attempting to import them, this may take a while...<br>\');' );

                        // Loop
                        foreach ( $import as $file ) {

                            if ( !in_array( ext_get( $file ), $allow_ext ) ) {

                                js( '$(window.parent.document).find(".import-output").append(\'<span class="text-red">Unable to import file <b>"' . str_replace( "'", '', basename( $file ) ) . '"</b> because it is not an image file!</span><br>\');' );

                            } else {

                                // New file name & path
                                $new_file = "{$lock_local}/tmp/images/" . parse_track( basename( $file ) );

                                // Attempt copy
                                if ( copy( "{$directory}/{$file}", $new_file ) ) {

                                    image::handle( $new_file, '280x280', 'crop' );
                                    js( '$(window.parent.document).find(".import-output").append(\'<span class="text-green">' . str_replace( "'", '', basename( $file ) ) . ' successfully imported.</span><br>\');' );

                                } else {

                                    js( '$(window.parent.document).find(".import-output").append(\'<span class="text-red">Unable to import file <b>"' . str_replace( "'", '', basename( $file ) ) . '"</b> - UNKNOWN ERROR!</span><br>\');' );

                                }

                            }

                        }

                        js( '$(window.parent.document).find(".import-output").append(\'All found image files were imported!<br>\');' );

                    }

                }

            } else { // FTP IMPORT

                if ( !filter_var( $_GET[ 'path' ], FILTER_VALIDATE_URL ) ) {

                    js( '$(window.parent.document).find(".import-output").html(\'<span class="text-red">The provided FTP Address does not appear to be valid!</span><br>\');' );
                    exit;

                }

                // Before we actually start looping and doing all kinds of stuff, we need to verify connection
                if ( $handle = @opendir( "{$_GET['path']}" ) ) {

                    // Handle was ok, show import message
                    js( '$(window.parent.document).find(".import-output").append(\'Connection succeeded, attempting to import images, this may take a while...<br>\');' );

                    // Loop now
                    while ( false !== ( $file = readdir( $handle ) ) ) {

                        if ( !in_array( ext_get( $file ), $allow_ext ) ) {

                            js( '$(window.parent.document).find(".import-output").append(\'<span class="text-red">Unable to import file <b>"' . str_replace( "'", '', basename( $file ) ) . '"</b> because it is not an image file!</span><br>\');' );

                        } else {

                            // New file name & path
                            $new_file     = "./../tmp/images/" . parse_track( basename( $file ) );
                            $new_img_data = file_get_contents( "{$_GET['path']}{$file}" );

                            // Attempt copy
                            if ( strlen( $new_img_data ) > 10 && file_put_contents( $new_file, $new_img_data ) ) {

                                image::handle( $new_file, '280x280', 'crop' );
                                js( '$(window.parent.document).find(".import-output").append(\'<span class="text-green">' . str_replace( "'", '', basename( $file ) ) . ' successfully imported.</span><br>\');' );

                            } else {

                                js( '$(window.parent.document).find(".import-output").append(\'<span class="text-red">Unable to import file <b>"' . str_replace( "'", '', basename( $file ) ) . '"</b> - UNKNOWN ERROR!</span><br>\');' );

                            }

                        }

                    }

                    js( '$(window.parent.document).find(".import-output").append(\'Artwork import process has been completed!<br>\');' );
                    closedir( $handle );

                } else {

                    $last_error = error_get_last();
                    js( '$(window.parent.document).find(".import-output").html(\'<span class="text-red">FTP Connection failed!<br>Details: ' . $last_error[ 'message' ] . '</span><br>\');' );

                }

                // END FTP
            }
        ?>
    </body>
</html>