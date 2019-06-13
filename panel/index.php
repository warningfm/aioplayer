<?php

    // Variables & functions
    $inc = true;
    $item = 'i2cjx7cx';

    // Include general player settings
    if ( is_file( './../inc/conf/general.php' ) ) include './../inc/conf/general.php';

    // Log errors into file
    error_reporting( E_ALL ^ E_NOTICE );
    ini_set( "log_errors", ( $settings[ 'debugging' ] != 'disabled' ) ? true : false );
    ini_set( "error_log", getcwd() . "./../tmp/logs/php.log" );


    // Output buffer & PHP SESSION
    ob_start();
    session_start();

    // Required control panel files
    include 'template.php';
    include './../inc/functions.php';
    include './../inc/lib/forms.class.php';
    include './../inc/lib/image-resize.class.php';

    ## Debugging - Show / Hide PHP errors
    ini_set( 'display_errors', ( ( $settings[ 'debugging' ] == 'enabled' ) ? true : false ) );

    // Logout user
    if ( isset( $_GET[ 'logout' ] ) ) {
        unset( $_SESSION[ 'a-login' ] );
        header( "Location: ?s=login" );
    }

    // Create header and attempt login
    head( $settings );
    if ( $_SESSION[ 'a-login' ] !== true ) {

        include 'login.php';

    } else {

        tabs();
        if ( is_file( "{$_GET['s']}.php" ) ) {

            include "{$_GET['s']}.php";

        } else {

            include 'home.php';

        }


    }

    footer();
    createMissing( '../.' );

?>