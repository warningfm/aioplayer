<?php

    // Some required functions
    require './../inc/functions.php';
    require './../inc/lib/image-resize.class.php';

    // Verify authorization
    session_start();
    if ( $_SESSION[ 'a-login' ] !== true ) {

        header( 'HTTP/1.1 403 Forbidden', true, 403 );
        exit;

    }

    // Avoid session locking
    session_write_close();

    // Create Default Array that we'll use through script
    $response = array();

    // Actions
    switch ( $_GET[ 'action' ] ) {

        // Get list of Artworks
        case 'getArtwork':

            // Read list of files
            $files = browse( './../tmp/images/' );

            // Check if its array
            if ( is_array( $files ) AND count( $files ) >= 1 ) {

                // Loop
                foreach ( $files as $file ) {

                    // Skip logo files
                    if ( preg_match( '/^logo\.[0-9]+/i', $file ) ) continue;

                    // Skip non image files
                    if ( !preg_match( '/\.(jpe?g|png|webp|svg)$/i', $file ) ) continue;

                    // Create array of files to respond with
                    $response[] = array(
                        'name' => ext_del( basename( $file ) ),
                        'path' => "tmp/images/{$file}",
                        'size' => file_size( filesize( "./../tmp/images/{$file}" ) ),
                    );

                }

                ksort( $response );

            }

            break;

        // Delete artwork
        case 'deleteArtwork':
            $response = array( 'success' => delete_artist( $_GET[ 'name' ] ) );
            break;

        // Push log file to browser (whole)
        case 'getLog':

            $logFile = './../tmp/logs/errors.log';
            header( "Content-type: text/plain" );

            // If file exists, proceed.
            if ( is_file( $logFile ) ) {

                header( 'Content-Length: ' . filesize( $logFile ) );
                echo file_get_contents( $logFile );

            }

            exit;
            break;

        // Default
        default:
            $response = array( 'error', 'nothing here!' );
            break;

    }

    header( "Content-type: application/json" );
    echo json_encode( $response );

?>

