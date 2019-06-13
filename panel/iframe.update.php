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
        <title>AIO - Radio Player Update</title>
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

            // *** SOME VARIABLES
            $downloadStart = false;

            // *** Path to player (could be different)
            $path = realpath( './../' );

            // *** WE WRITE HELPER FUNCTIONS
            function send() {
                ob_flush();
                flush();
            }

            function js( $txt ) {
                echo "\t\t<script>{$txt}</script>\n";
                send();
            }

            // *** SOME PHP RELATED OPTIONS TO ENSURE SMOOTH UPGRADE (OR GIVE US DEBUG INFO OTHERWISE)
            set_time_limit( 0 );
            error_reporting( E_ALL ^ E_NOTICE );
            ini_set( 'default_charset', 'utf-8' );
            ini_set( "log_errors", "on" );
            ini_set( "error_log", getcwd() . "./../tmp/logs/updates.log" );
            ignore_user_abort( true );

            // Include stuff
            include $path . '/inc/functions.php';
            if ( is_file( $path . '/inc/conf/general.php' ) ) include $path . '/inc/conf/general.php';

            // Kill output buffers
            echo str_repeat( ' ', 1024 * 64 ) . "\n";
            send();


            // *** NOW THE ACTUAL UPDATER :)
            if ( empty( $settings[ 'envato_pkey' ] ) ) { // No envato key

                js( '$(window.parent.document).find(".update-text").after(\'' . alert( 'Update failed! Unable to find purchase code. To continue update process, please enter your purchase code on the settings page.', 'error' ) . '\');' );

                // Only execute when NOT development mode, when file post-update exists and $_GET is also there.
            } else if ( $settings[ 'development' ] !== true && is_file( $path . '/post-update.php' ) && isset( $_GET[ 'post-update' ] ) ) {

                if ( file_exists( $path . '/post-update.php' ) ) include $path . '/post-update.php';
                js( '$(window.parent.document).find(".update-text").append(\'<div>Successfully completed!</div>\');' );

            } else if ( !class_exists( 'ZipArchive' ) && !is_file( $path . '/inc/lib/ziparchive.class.php' ) ) { // Show error if ZipArchive is not supported

                js( '$(window.parent.document).find(".update-text").after(\'' . alert( 'Update failed, unable to initiate ZipArchive class (ZIP Extension), please contact web hosting provider...', 'error' ) . '\');' );

            } else if ( is_file( $path . '/tmp/updates/lock' ) ) {

                js( '$(window.parent.document).find(".update-text").html(\'<div class="text-red">Update already in progress, please try again later!</div>\');' );

            } else { // Key present


                // Check for lock file, if doesn't exist create it, make sure update is not run twice
                file_put_contents( $path . '/tmp/updates/lock', '' );

                // First message
                js( '$(window.parent.document).find(".update-text").html(\'Establishing connection to the update server...\');' );

                // Attempt download
                $data = get(
                    'https://prahec.com/envato/update?action=get', array( 'purchase-key' => $settings[ 'envato_pkey' ] ),
                    null,
                    function( $res, $dtotal, $dnow, $utotal, $unow = '' ) {

                        global $downloadStart;

                        // PHP 5.3 fix
                        if ( is_resource( $res ) ) {
                            $total = $dtotal;
                            $now   = $dnow;
                        } else {
                            $total = $res;
                            $now   = $dtotal;
                        }

                        // First message saying download started...
                        if ( $downloadStart === false AND $now > 0 ) {

                            js( '$(window.parent.document).find(".update-text").append(\'<div>Downloading the latest update... (<span class="progress-status download">0%</span>)</div>\');' );
                            $downloadStart = true;

                        }

                        // Calculate progress (only if download already started!)
                        if ( $downloadStart === true ) {

                            if ( $now >= 1 AND $total >= 1 ) { // Fix "Division by Zero"

                                $progress = floor( ( $now / $total ) * 100 );
                                echo '<script>$(window.parent.document).find(".progress-status.download").html(\'' . $progress . '%\');</script>';
                                send();

                            }

                        }

                    }, 0, $curl_error ); ## 0s timeout


                // Handle update server errors
                if ( $data === false ) {

                    js( '$(window.parent.document).find(".update-text").append(\'<div class="text-red">Downloading latest update failed! ' . $curl_error . '.</div>\');' );
                    @unlink( $path . '/tmp/updates/lock' );

                } else if ( strpos( $data, '"error"' ) !== false ) {

                    $json = json_decode( $data, true );
                    js( '$(window.parent.document).find(".update-text").append(\'<div class="text-red">' . $json[ 'error' ] . '</div>\');' );
                    @unlink( $path . '/tmp/updates/lock' );

                } else if ( file_put_contents( $path . '/tmp/updates/update.zip', $data ) === false ) {

                    js( '$(window.parent.document).find(".update-text").append(\'<div class="text-red">Saving update file failed, its possible that directory <b>/tmp/update/</b> is not writable!</div>\');' );
                    @unlink( $path . '/tmp/updates/lock' );

                } else {

                    // Extract message...
                    js( '$(window.parent.document).find(".update-text").append(\'<div>Installing update... <span class="progress-status unzip"></span></div>\');' );

                    // EXTRACT PART: Using ZipArchive (PHP EXT) OR Class Zip
                    if ( !class_exists( 'ZipArchive' ) ) {

                        include $path . '/inc/lib/ziparchive.class.php';

                        // Attempt extract
                        try {

                            $zip = new Zip();
                            $zip->open( $path . '/tmp/updates/update.zip' );
                            $zip->extract( './../' );

                        } catch ( ArchiveIOException $e ) {

                            // ERROR
                            js( '$(window.parent.document).find(".update-text").append(\'<div class="text-red">ERROR: ' . $e->getMessage() . '</div>\');' );
                            js( '$(window.parent.document).find(".update-text").append(\'<div class="text-red">UPDATE FAILED!</div>\');' );
                            exit();

                        }

                        // Completed!
                        $update_complete = true;

                    } else {

                        // Initiate extract
                        $zip   = new ZipArchive;
                        $files = $zip->open( $path . '/tmp/updates/update.zip' );        // Open update zip

                        if ( $files !== true ) {

                            js( '$(window.parent.document).find(".update-text").append(\'<div class="text-red">Unable to read the update!</div>\');' );

                        } else {

                            $total = $zip->numFiles;
                            for ( $i = 0; $i < $total; $i++ ) {

                                $tmp = $zip->getNameIndex( $i );
                                $zip->extractTo( $path, array( $tmp ) );

                                $file = $i + 1;
                                js( '$(window.parent.document).find(".progress-status.unzip").html(\'(' . $file . '/' . $total . ')\');' );

                            }

                            $zip->close();
                            $update_complete = true;

                        }

                    }


                    // Every thing is done here, call post-install and cleanup
                    if ( $update_complete == true ) {

                        // If update has some big changes it will include post install script to fix problems
                        if ( file_exists( $path . '/post-update.php' ) ) include $path . '/post-update.php';

                        // Delete zip file & temp file
                        @unlink( $path . '/tmp/updates/update.zip' );
                        @unlink( $path . '/tmp/updates/lock' );

                    }


                    // Finished
                    js( '$(window.parent.document).find(".update-text").append(\'<div>Completed successfully!</div>\');' );
                    js( '$(window.parent.document).find(".update-text").after(\'' . alert( 'Update successful! To complete the upgrade, please reload this page.', 'success' ) . '\');' );

                }

            }
        ?>
    </body>
</html>