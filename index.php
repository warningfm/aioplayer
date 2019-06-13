<?php

    ## Include files & settings
    require 'inc/functions.php';
    if ( is_file( 'inc/conf/general.php' ) ) include 'inc/conf/general.php';

    ## PHP debugging ini re-writes (where possible)
    error_reporting( E_ALL ^ E_NOTICE );
    ini_set( "log_errors", ( $settings[ 'debugging' ] != 'disabled' ) ? true : false );
    ini_set( "error_log", getcwd() . "/tmp/logs/errors.log" );

    ## Debugging - Show / Hide PHP errors
    ini_set( 'display_errors', ( ( $settings[ 'debugging' ] == 'enabled' ) ? true : false ) );

    ## Language handler
    if ( $settings[ 'multi_lang' ] != 'true' ) {

        require "inc/lang/{$settings[ 'default_lang' ]}";

    } else { ## Enabled

        // Multi-language, check browser preference for selected language
        $lang = strtolower( substr( $_SERVER[ 'HTTP_ACCEPT_LANGUAGE' ], 0, 2 ) );
        if ( file_exists( "inc/lang/{$lang}.php" ) ) { // Load if language is found

            require "inc/lang/{$lang}.php";

        } else { // Fall back to default

            require "inc/lang/{$settings[ 'default_lang' ]}";

        }

    }

    ## Handle themes here
    $list = getTemplates();

    ## Allow using ?t=parameter for template switching
    if ( isset( $_GET[ 't' ] ) && !empty( $_GET[ 't' ] ) && in_array( $_GET[ 't' ], array_keys( $list ) ) ) {

        $settings[ 'template' ] = $_GET[ 't' ];

    } else if ( empty( $settings[ 'template' ] ) || !in_array( $settings[ 'template' ], array_keys( $list ) ) ) {

        ## No switch as above, use settings template
        $settings[ 'template' ] = key( $list );

    }


    ## Handle playlists etc...
    if ( isset( $_GET[ 'c' ] ) && isset( $_GET[ 'pl' ] ) ) {

        require 'inc/playlist-handler.php';
        exit;

    }

    ## Handle requests & other backend stuff
    if ( isset( $_GET[ 'c' ] ) ) {

        require 'inc/handler.php';
        exit;

    }


    ## Handle URL to the player generation
    $CUR_URL           = explode( "?", $_SERVER[ 'REQUEST_URI' ] );
    $settings[ 'url' ] = ( !empty( $_SERVER[ 'HTTPS' ] ) ? 'https' : 'http' ) . "://{$_SERVER[ 'HTTP_HOST' ]}{$CUR_URL[ 0 ]}";

    ## Append some missing variables to settings
    $settings = $settings + $lang + array(
            'indexing'        => ( ( isset( $settings[ 'disable_index' ] ) && $settings[ 'disable_index' ] == 'true' ) ? 'NOINDEX, NOFOLLOW' : 'INDEX, FOLLOW' ),
            'default_artwork' => getArtwork( null ),
            'og_image'        => ( ( empty( $settings[ 'fb_shareimg' ] ) ) ? $settings[ 'url' ] . getArtwork( null ) : $settings[ 'fb_shareimg' ] ),
            'og_site_title'   => ( ( !empty( $settings[ 'site_title' ] ) ) ? '<meta property="og:site_name" content="' . $settings[ 'site_title' ] . '">' : ' ' ),
            'icon_size'       => ( !is_numeric( $settings[ 'playlist_icon_size' ] ) ) ? 32 : $settings[ 'playlist_icon_size' ],
            'json_settings'   => json_encode( ## Handle array which is passed to javascript for language and settings
                array(
                    'lang'            => $lang,
                    'analytics'       => ( !empty( $settings[ 'google_analytics' ] ) ? $settings[ 'google_analytics' ] : false ),
                    'channel'         => array(),
                    'title'           => str_to_utf8( $settings[ 'title' ] ),
                    'artist_length'   => $settings[ 'artist_maxlength' ],
                    'title_length'    => $settings[ 'title_maxlength' ],
                    'default_artist'  => str_to_utf8( $settings[ 'artist_default' ] ),
                    'default_title'   => str_to_utf8( $settings[ 'title_default' ] ),
                    'default_channel' => str_to_utf8( $settings[ 'default_channel' ] ),
                    'default_volume'  => ( ( isset( $settings[ 'default_volume' ] ) && $settings[ 'default_volume' ] >= 1 && $settings[ 'default_volume' ] <= 100 ) ? $settings[ 'default_volume' ] : 50 ),
                    'dynamic_title'   => ( isset( $settings[ 'dynamic_title' ] ) ) ? $settings[ 'dynamic_title' ] : false,
                    'usecookies'      => ( isset( $settings[ 'cookie_support' ] ) ) ? $settings[ 'cookie_support' ] : false,
                    'stats_refresh'   => ( is_numeric( $settings[ 'stats_refresh' ] ) && $settings[ 'stats_refresh' ] > 5 ) ? $settings[ 'stats_refresh' ] : 15,
                    'autoplay'        => ( isset( $_GET[ 'autoplay' ] ) && $_GET[ 'autoplay' ] == 'false' ) ? false : $settings[ 'autoplay' ],
                    'history'         => ( isset( $settings[ 'history' ] ) && $settings[ 'history' ] == 'true' ) ? 'true' : false,
                    'template'        => $settings[ 'template' ]
                )
            ),
        );


    // Output buffer should also minify stuff (8192 byte buffer)
    ob_start( function( $buffer ) use ( $settings ) {

        // Prior using our buffer, apply template to it
        $buffer = template( $buffer, $settings );

        // Array with replacement matching (regex)
        $regex = array(
            ## REGEX					  ## REPLACE WITH
            "/<!--.*?-->|\t/s"             => "",
            "/\>([\s\t]+)?([ ]{2,}+)?\</s" => "><"
        );

        // Replace tabs, empty spaces etc etc...
        $html_out = preg_replace( array_keys( $regex ), $regex, $buffer );

        // Optimize <style> tags
        $html_out = preg_replace_callback( '#<style(.*?)>(.*?)<\/style>#is', function( $m ) {

            // Minify the css
            $css = $m[ 2 ];
            $css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );

            $css = str_replace( array( "\r\n", "\r", "\n", "\t", '  ', '    ', '     ' ), '', $css );

            $css = preg_replace( array( '(( )+{)', '({( )+)' ), '{', $css );
            $css = preg_replace( array( '(( )+})', '(}( )+)', '(;( )*})' ), '}', $css );
            $css = preg_replace( array( '(;( )+)', '(( )+;)' ), ';', $css );

            return '<style>' . $css . '</style>';

        }, $html_out );

        // Optimize <script> tags
        $html_out = preg_replace_callback( '#<script(.*?)>(.*?)<\/script>#is', function( $m ) {

            // Minify the js
            $js = $m[ 2 ];
            $js = preg_replace( '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/', '', $js );
            $js = str_replace( array( "\r\n", "\r", "\n", "\t", '  ', '    ', '     ' ), '', $js );
            return "<script{$m[1]}>" . $js . "</script>";

        }, $html_out );

        // Finally out put our content =)
        return $html_out;

    }, 8192 );

    // Template loader
    if ( !is_file( ".{$list[ $settings[ 'template' ] ][ 'path' ]}/{$list[ $settings[ 'template' ] ][ 'template' ]}" ) ) {

        die( 'Unable to find the template file!' );

    } else {

        echo file_get_contents( ".{$list[ $settings[ 'template' ] ][ 'path' ]}/{$list[ $settings[ 'template' ] ][ 'template' ]}" );

    }

?>