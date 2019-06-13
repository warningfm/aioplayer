<?php

    // No can do without index definition!
    if ( $inc !== true ) {
        header( "Location: index.php?s=home" );
        exit;
    }

    // Remove empty spaces before the value
    $_POST = array_map( 'trim', $_POST );

    // Handle post data
    if ( isset( $_POST[ 'submit' ] ) ) {

        if ( strlen( $_POST[ 'admin_pass' ] ) < 5 AND !empty( $_POST[ 'admin_pass' ] ) ) {

            echo alert( 'Panel password must have at least 5 characters!', 'error' );

        } else if ( $_POST[ 'admin_pass' ] != $_POST[ 'admin_pass2' ] ) {

            echo alert( 'New passwords do not match, please try again!', 'error' );

        } else if ( empty( $_POST[ 'admin_user' ] ) ) {

            echo alert( 'You must enter admin username or else you won\'t be able to login to the control panel!', 'error' );

        } else if ( !is_numeric( $_POST[ 'artist_maxlength' ] ) OR !is_numeric( $_POST[ 'title_maxlength' ] ) OR empty( $_POST[ 'title' ] ) OR empty( $_POST[ 'track_regex' ] ) ) {

            echo alert( 'Some fields are empty, please check form bellow and re-submit it!', 'error' );

        } else if ( @preg_match( "/{$_POST['track_regex']}/i", null ) === false ) {

            echo alert( 'Track RegEx is invalid! Please fix it or use default value.', 'error' );

        } else if ( !is_numeric( $_POST[ 'stats_refresh' ] ) OR $_POST[ 'stats_refresh' ] < 5 OR $_POST[ 'stats_refresh' ] > 120 ) {

            echo alert( 'Invalid range for Stats Refresh Speed. The value must not be lower than <b>5</b> and higher than <b>120</b>!', 'error' );

        } else {

            // Delete submit key
            unset( $_POST[ 'submit' ], $_POST[ 'admin_pass2' ] );

            // Password handle
            if ( !empty( $_POST[ 'admin_pass' ] ) ) { // Hash password, safety

                $_POST[ 'admin_pass' ] = hash( SHA512, $_POST[ 'admin_pass' ] );

            } else { // No password provided

                $_POST[ 'admin_pass' ] = $settings[ 'admin_pass' ];

            }

            // Keep development key
            if ( isset( $settings[ 'development' ] ) ) $_POST[ 'development' ] = $settings[ 'development' ];

            // Try to save
            if ( file_put_contents( './../inc/conf/general.php', '<?php $settings=' . var_export( $_POST, true ) . ';' ) ) {

                // Clear File Cache
                clearstatcache( true );
                if ( function_exists( 'opcache_invalidate' ) ) opcache_invalidate( './../inc/conf/general.php', true );

                echo alert( 'Settings successfully updated!', success );

            } else {

                echo alert( 'Unable to save configuration changes, you may not have sufficient permissions!', 'error', true );

            }

        }


    } else {

        $_POST = $settings;

    }


    // Never show password
    unset( $_POST[ 'admin_pass' ] );
    unset( $_POST[ 'admin_pass2' ] );


    // Small v1.14 -> 1.15 FIX
    if ( !empty( $_POST[ 'lastfm_key' ] ) && empty( $_POST[ 'api_key' ] ) ) $_POST[ 'api_key' ] = $_POST[ 'lastfm_key' ];

    // Create list of available languages
    include 'lang.list.php';
    if ( is_dir( './../inc/lang' ) ) {

        $ff    = browse( './../inc/lang/' );
        $files = array();

        // Only available
        foreach ( $ff as $file ) {
            $languages[ $file ] = $language[ ext_del( $file ) ] . ' (' . strtoupper( ext_del( $file ) ) . ')';
        }

    }

    // Get all available channels
    if ( is_file( './../inc/conf/channels.php' ) ) {

        include './../inc/conf/channels.php';

    }

    // Now create options array
    $def_channel = array( 0 => 'Default' );
    if ( is_array( $channels ) ) {

        foreach ( $channels as $c ): $def_channel[ $c[ 'name' ] ] = $c[ 'name' ]; endforeach;

    }


    // New since 1.3 - get list of templates
    $templates = getTemplates( './../' );
    $list_temp = array();

    // Loop
    foreach ( $templates as $key => $var ) : $list_temp[ $key ] = $var[ 'name' ]; endforeach;


    // Init Object
    $f = new form();

    // Settings array (fields)
    $fields_arr = array(

        // General Player Settings
        array( 'label' => 'Site title', 'name' => 'site_title', 'size' => 64, 'placeholder' => 'AIO Radio', 'description' => '( Optional, more at <a href="http://ogp.me/" target="_blank">http://ogp.me/</a> )' ),
        array( 'label' => 'Player title', 'name' => 'title', 'size' => 64, 'description' => '( SEO )' ),
        array( 'label' => 'Player description', 'name' => 'description', 'type' => 'textarea', 'description' => '( SEO )' ),
        array( 'label' => 'Google analytics', 'name' => 'google_analytics', 'placeholder' => 'UA-1113571-5', 'description' => '( Tracking ID )' ),
        array( 'label' => 'Facebook share (image)', 'class' => 'col-sm-5', 'name' => 'fb_shareimg', 'description' => '( Full URL to the image min. 200 x 200 px )' ),
        array( 'label' => 'Search Engine Index', 'name' => 'disable_index', 'value' => 'true', 'type' => 'checkbox', 'class' => 'col-sm-9', 'description' => 'Disable search engine indexing ( does not instantly remove search results, more at <a href="https://support.google.com/webmasters/answer/93710?hl=en" target="_blank">Google FAQ</a> )' ),
        array( 'label' => 'Cookie(s)', 'name' => 'cookie_support', 'class' => 'col-sm-9', 'value' => 'true', 'type' => 'checkbox', 'description' => 'Use cookies to save user settings and volume permanently' ),
        array( 'label' => 'Default language', 'class' => 'col-sm-3', 'name' => 'default_lang', 'type' => 'select', 'options' => $languages, 'description' => '( Used if language is not found or Multi-language support is disabled )' ),
        array( 'label' => 'Multi-language support', 'name' => 'multi_lang', 'class' => 'col-sm-9', 'value' => 'true', 'type' => 'checkbox', 'description' => ' When checked, player will support multi-languages. See Language(s) tab for management.' ),
        array( 'label' => 'Playlist(s) icon size', 'class' => 'col-sm-2', 'name' => 'playlist_icon_size', 'placeholder' => '32', 'reset' => true, 'description' => '( in pixels )', 'type' => 'number' ),
        array( 'label' => 'Cache artist images', 'name' => 'cache_artist_images', 'class' => 'col-sm-9', 'value' => 'true', 'type' => 'checkbox', 'description' => ' Cache artist images on the server ( Also crops, compresses and optimizes images for maximum quality )' ),
        array( 'label' => 'Auto play', 'name' => 'autoplay', 'class' => 'col-sm-9', 'value' => 'true', 'type' => 'checkbox', 'description' => ' Start playback automatically (Some devices and browsers do not support this feature)' ),
        array( 'label' => 'Debug mode', 'class' => 'col-sm-4', 'name' => 'debugging', 'type' => 'select', 'options' => array( 'log-only' => 'Logging only (Recommended)', 'enabled' => 'Enabled', 'disabled' => 'Disabled' ) ),
        array( 'label' => 'Show track history', 'name' => 'history', 'class' => 'col-sm-9', 'value' => 'true', 'type' => 'checkbox', 'description' => ' When enabled listeners will be able to see their playback history ( Based on track info and not actual stream )' ),
        array( 'label' => 'Initial channel', 'name' => 'default_channel', 'class' => 'col-sm-4', 'type' => 'select', 'description' => ' ( Used if no cookie or hash is present )', 'options' => $def_channel ),
        array( 'label' => 'Initial volume', 'name' => 'default_volume', 'class' => 'col-sm-2', 'type' => 'number', 'description' => 'in percent % ( Only used for new listeners )', 'max' => '100', 'min' => 0, 'placeholder' => '50', 'reset' => true ),
        array( 'label' => 'Template', 'name' => 'template', 'class' => 'col-sm-4', 'type' => 'select', 'options' => $list_temp ),

        // Track Information
        '</div></div><div class="panel"><div class="heading"><i class="fa fa-list"></i> Track Information</div><div class="content form-content">',
        array( 'label' => 'Default artist', 'name' => 'artist_default', 'placeholder' => 'Various Artists', 'class' => 'col-sm-4', 'description' => '( If there is no stream information or stat\'s is not responding, this will be shown )' ),
        array( 'label' => 'Default title', 'name' => 'title_default', 'placeholder' => 'Unknown Track', 'class' => 'col-sm-4', 'description' => '( If there is no stream information or stat\'s is not responding, this will be shown )' ),
        array( 'label' => 'Dynamic title', 'name' => 'dynamic_title', 'class' => 'col-sm-9', 'value' => 'true', 'type' => 'checkbox', 'description' => ' Dynamic popup window title ( Show currently playing Track in window title bar )' ),
        array( 'label' => 'Artist max length', 'name' => 'artist_maxlength', 'placeholder' => 48, 'class' => 'col-sm-2', 'description' => '<b>0 = disabled</b> ( Maximum number of characters before shortening artist name )', 'type' => 'number' ),
        array( 'label' => 'Title max length', 'name' => 'title_maxlength', 'placeholder' => 58, 'class' => 'col-sm-2', 'description' => '<b>0 = disabled</b> ( Maximum number of characters before shortening track name )', 'type' => 'number' ),
        '<div class="form-group"><label for="stats_refresh" class="col-sm-2 control-label">Stats refresh speed</label><div class="col-sm-2"><div class="input-append"><div class="append">sec</div><input type="number" name="stats_refresh" class="form-control" id="stats_refresh" placeholder="15" required="" value="' . $_POST[ 'stats_refresh' ] . '"></div></div><div class="help-block">( Default: 15 - <span class="text-red">Caution: this may have big performance impact on your web server!</span> )</div></div>',
        array( 'label' => 'Player API', 'name' => 'api', 'class' => 'col-sm-9', 'value' => 'true', 'type' => 'checkbox', 'description' => ' Enable support for external JSONP API requests ( <a target="_blank" href="https://prahec.com/project/aio-radio/docs#api"><i class="fa fa-question-circle"></i> Documentation</a> )' ),
        array( 'label' => 'Artist/title regex', 'name' => 'track_regex', 'class' => 'col-sm-5', 'placeholder' => "(?P<artist>[^-]*)[ ]?-[ ]?(?P<title>.*)", 'reset' => true, 'description' => '<span class="text-red">( Only change if you know what you are doing! )</span>' ),
        array( 'label' => 'Artist images API', 'class' => 'col-sm-4', 'name' => 'api_artist', 'type' => 'select', 'options' => array( 'lastfm' => 'LastFM', 'echonest' => 'EchoNest', 'itunes' => 'iTunes ( no API key )' ) ),
        '<div class="form-group"><label for="images_size" class="col-sm-2 control-label">Artist images size</label><div class="col-sm-2"><div class="input-append"><div class="append">pix</div><input type="number" name="images_size" class="form-control" id="images_size" placeholder="280" value="' . $_POST[ 'images_size' ] . '"></div></div><div class="help-block">( Default: 280 - <span class="text-red">Caution: this may have big performance impact on your web server!</span> )</div></div>',
        array( 'label' => 'API Key', 'name' => 'api_key', 'class' => 'col-sm-4', 'description' => '( Can be obtained from the API provider web site )', 'placeholder' => 'API Key' ),
        array( 'label' => 'Player Share', 'name' => 'disable_sharing', 'value' => 'sharing_disabled', 'type' => 'checkbox', 'class' => 'col-sm-9', 'description' => 'Disable artwork hover and sharing function ( Default: <i>Enabled</i> )' ),

        // Panel Settings
        '</div></div><div class="panel"><div class="heading"><i class="fa fa-sign-in"></i> Control Panel</div><div class="content form-content">',
        array( 'label' => 'Purchase code', 'name' => 'envato_pkey', 'placeholder' => 'Codecanyon Item Purchase code', 'description' => '<a target="_blank" href="https://prahec.com/envato/pkey">( <i class="fa fa-question-circle"></i> Required for updates )</a>', 'class' => 'col-sm-4' ),
        array( 'label' => 'Panel username', 'name' => 'admin_user', 'class' => 'col-sm-4', 'placeholder' => 'admin' ),
        array( 'label' => 'Panel password', 'name' => 'admin_pass', 'class' => 'col-sm-4', 'type' => 'password', 'placeholder' => 'min. 5 characters' ),
        array( 'label' => 'Confirm panel password', 'name' => 'admin_pass2', 'class' => 'col-sm-4', 'type' => 'password', 'placeholder' => 'min. 5 characters' )

    );

?>
<form method="POST" action="?s=settings">

    <div class="panel">

        <div class="heading"><i class="fa fa-cogs"></i> General Settings</div>
        <div class="content form-content">

            <?php foreach ( $fields_arr as $arr ): echo( ( !is_array( $arr ) ) ? $arr : $f->add( $arr ) ); endforeach; ?>

            <div class="row">
                <div class="col-sm-9 col-sm-offset-2">
                    <b>Note</b>: You will not be able to recover password once it is set.
                    Your password will be encrypted one way (hashed).<br> To regain access please overwrite file <b>/inc/conf/general.php</b> with the original file.
                </div>
            </div>

        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <button type="submit" name="submit" value="submit" class="btn btn-success"><i class="fa fa-pencil fa-fw"></i> Save</button>
            <a href="?s=settings" class="btn btn-danger"><i class="fa fa-times fa-fw"></i> Cancel</a>
        </div>
    </div>

</form>
<script type="text/javascript">

    // Function to hide/show API key input
    function check_itunes() {

        if ( $( '#api_artist' ).val() == 'itunes' ) {

            $( '#api_key' ).closest( '.form-group' ).hide();

        } else {

            $( '#api_key' ).closest( '.form-group' ).show();

        }

        return true;

    }

    // Bind document ready
    $( document ).ready( function() {

        $( '#api_artist' ).on( 'change', check_itunes );
        check_itunes();

    } );

</script>