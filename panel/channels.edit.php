<?php

    // No can do without index definition!
    if ( $inc !== true ) {
        header( "Location: index.php?s=home" );
        exit;
    }

    // Options (might change in future)
    $codecs       = array( 'mp3' => 'MP3', 'oga' => 'OGG', 'm4a' => 'AAC' );
    $logo_ext     = array( 'jpeg', 'png', 'webp', 'jpg', 'svg' );
    $streamUrlExt = array( 'pls', 'm3u', 'xspf' );
    $templates    = getTemplates( './..' );
    $form         = new form;


    // Attempt to delete logo from existing channel
    if ( $_GET[ 'logo' ] == 'delete' ) {
        @unlink( './../' . $channels[ $_GET[ 'e' ] ][ 'logo' ] );
        exit;
    }


    // Handle POST
    if ( isset( $_POST[ 'submit' ] ) ) {

        $_POST[ 'name' ] = trim( $_POST[ 'name' ] );

        // Verify fields
        if ( empty( $_POST[ 'name' ] ) ) {

            echo alert( 'You need to specify name of the channel you are creating or editing.', 'error' );

        } else if ( !is_array( $_POST[ 'quality' ] ) OR empty( $_POST[ 'url_0' ][ 0 ] ) ) {

            echo alert( 'You have to configure streams! Player does not work without them.', 'error' );

            // Success
        } else {


            // Handle upload
            if ( !empty( $_FILES[ 'logo' ][ 'tmp_name' ] ) ) {

                $filename = "logo." . time();

                // Before continue, delete old image
                if ( $_GET[ 'e' ] != 'add' && !empty( $channels[ $_GET[ 'e' ] ][ 'logo' ] ) ) {
                    @unlink( "./../{$channels[$_GET['e']]['logo']}" ); // Delete old image
                }

                // Attempt to save
                $up = upload( 'logo', './../tmp/images/', $filename );
                if ( !is_array( $up ) ) {

                    $error = alert( "Uploading logo failed! ERROR: {$up}", 'error' );

                } else if ( !in_array( ext_get( $up[ 'path' ] ), $logo_ext ) ) {

                    $error = alert( "Invalid image format! You can only upload JPEG, JPG, PNG, WEBP and SVG images!", 'error' );
                    @unlink( $up[ 'path' ] );

                } else { // Save success, now do tell!

                    $logoPath = str_replace( './../tmp/', 'tmp/', $up[ 'path' ] );

                    if ( ext_get( $up[ 'path' ] ) != 'svg' ) { // Only resize if not SVG

                        // Calculate crop width by having set height
                        $imageSize      = getimagesize( $up[ 'path' ] );
                        $calculateWidth = $imageSize[ 0 ] / ( $imageSize[ 1 ] / 80 );

                        // Crop
                        $img = new image ( $up[ 'path' ] );
                        $img->resize( "{$calculateWidth}x80", 'auto' );
                        $img->save( $up[ 'path' ] );

                    }

                }
            }


            // Convert quality group's POST to a nicer PHP valid array
            $c              = count( $_POST[ 'quality' ] ) - 1;
            $quality_groups = array();

            // Loop through stream groups
            for ( $i = 0; $i <= $c; $i++ ) {

                $streamName = $_POST[ 'quality' ][ $i ];

                // Count fields
                $name        = 'url_' . $i;
                $totalFields = count( $_POST[ $name ] ) - 1;
                $streams     = array();

                // LOOP
                for ( $f = 0; $f <= $totalFields; $f++ ) {

                    $codec             = $_POST[ 'codec_' . $i ][ $f ];
                    $streams[ $codec ] = $_POST[ $name ][ $f ];

                    if ( !filter_var( $_POST[ $name ][ $f ], FILTER_VALIDATE_URL ) ) { // Validate if the stream URL is actually an URL or not

                        $error = alert( 'Stream URL <b>"' . $_POST[ $name ][ $f ] . '"</b> is not valid url! Please read section <b>"How to configure streams?"</b> bellow.', 'error' );

                    } else if ( in_array( ext_get( $_POST[ $name ][ $f ] ), $streamUrlExt ) ) { // Check if stream URL is a playlist

                        $error = alert( 'Stream URL <b>"' . $_POST[ $name ][ $f ] . '"</b> is a playlist file, not an actual stream! Please read section <b>"How to configure streams?"</b> bellow.', 'error' );

                    }

                }

                // Update groups
                $quality_groups[ $streamName ] = $streams;

            }


            // Attempt to check stats config and create output conf
            if ( empty( $error ) ) {

                switch ( $_POST[ 'stats' ] ) {

                    // Use direct method
                    case 'direct':

                        if ( !filter_var( $_POST[ 'direct-url' ], FILTER_VALIDATE_URL ) OR ( !empty( $_POST[ 'direct-url-fallback' ] ) && !filter_var( $_POST[ 'direct-url-fallback' ], FILTER_VALIDATE_URL ) ) )
                            $error = alert( 'Configured stream URL for stats is not valid. Please enter real URL to the stream.', 'error' );

                        // normalize array
                        $stats = array(
                            'method'   => 'direct',
                            'url'      => $_POST[ 'direct-url' ],
                            'fallback' => $_POST[ 'direct-url-fallback' ]
                        );
                        break;

                    // Shoutcast Method
                    case 'shoutcast':

                        // Check if Shoutcast admin URL can be parsed
                        if ( parseURL( $_POST[ 'shoutcast-url' ] ) == null )
                            $error = alert( 'Shoutcast Stats URL could not be detected. Please use <b>http://url-to-server:port</b> format.', 'error' );

                        // normalize array
                        $stats = array(
                            'method'     => 'shoutcast',
                            'url'        => parseURL( $_POST[ 'shoutcast-url' ] ),
                            'auth'       => $_POST[ 'shoutcast-pass' ],
                            'sid'        => $_POST[ 'shoutcast-sid' ],
                            'sc-history' => (bool)$_POST[ 'sc-history' ]
                        );
                        break;

                    // Icecast Method
                    case 'icecast':

                        // Check if Icecast admin URL can be parsed
                        if ( parseURL( $_POST[ 'icecast-url' ] ) == null )
                            $error = alert( 'Icecast stats URL could not be detected. Please use <b>http://url-to-server:port</b> format.', 'error' );

                        // normalize array
                        $stats = array(
                            'method'    => 'icecast',
                            'url'       => parseURL( $_POST[ 'icecast-url' ] ),
                            'auth-user' => $_POST[ 'icecast-user' ],
                            'auth-pass' => $_POST[ 'icecast-pass' ],
                            'mount'     => $_POST[ 'icecast-mount' ],
                            'fallback'  => $_POST[ 'icecast-fallback-mount' ] );
                        break;

                    // SAM Broadcaster Method
                    case 'sam': // normalize array
                        $stats = array(
                            'method'    => 'sam',
                            'host'      => $_POST[ 'sam-host' ],
                            'auth-user' => $_POST[ 'sam-user' ],
                            'auth-pass' => $_POST[ 'sam-pass' ],
                            'db'        => $_POST[ 'sam-db' ] );
                        break;

                    // Centovacast Method
                    case 'centovacast':

                        // Check if Centovacast panel URL can be parsed
                        if ( parseURL( $_POST[ 'centova-url' ] ) == null )
                            $error = alert( 'Centova cast control panel URL could not be detected. Please use <b>http://url-to-server:port</b> format.', 'error' );

                        // normalize array
                        $stats = array(
                            'method'    => 'centovacast',
                            'url'       => parseURL( $_POST[ 'centova-url' ] ),
                            'user'      => $_POST[ 'centova-user' ],
                            'use-cover' => $_POST[ 'centova-use-cover' ] );
                        break;

                    // Radionomy method
                    case 'radionomy': // normalize array
                        $stats = array(
                            'method'    => 'radionomy',
                            'user-id'   => $_POST[ 'radionomy-uid' ],
                            'api-key'   => $_POST[ 'radionomy-apikey' ],
                            'use-cover' => $_POST[ 'radionomy-use-cover' ] );
                        break;

                    // Custom URL method
                    case 'custom': // normalize array
                        $stats = array(
                            'method' => 'custom',
                            'url'    => $_POST[ 'custom-url' ],
                            'user'   => $_POST[ 'custom-user' ],
                            'pass'   => $_POST[ 'custom-pass' ] );
                        break;

                    case 'disabled':
                        $stats = array( 'method' => 'disabled' );
                        break;

                    default:
                        $error = alert( 'Invalid stats configuration! Can not continue!', "error" );
                        break;

                }


                // We just used switch done here ;)

            }


            // Prepare output config array
            $conf[] = array(
                'name'      => str_to_utf8( $_POST[ 'name' ] ),
                'logo'      => ( ( empty( $logoPath ) ) ? $channels[ $_GET[ 'e' ] ][ 'logo' ] : $logoPath ),
                'skin'      => $_POST[ 'skin' ],
                'show-time' => ( ( $_POST[ 'show-time' ] != 'true' ) ? false : true ),
                'streams'   => $quality_groups,
                'stats'     => ( isset( $stats ) ) ? $stats : array()
            );


            // If we already have channels, merge existing data
            if ( $_GET[ 'e' ] != 'add' AND empty( $error ) ) { ## EDIT

                $confOut = $channels;
                $confOut[ $_GET[ 'e' ] ] = $conf[ 0 ];

            } else if ( is_array( $channels ) AND empty( $error ) ) { ## Merge new channels with existing ones

                $confOut = array_merge( $channels, $conf );

            } else {

                $confOut = $conf;

            }


            // If any of above action's issued error, show it to user, otherwise save to file
            if ( !empty( $error ) ) {

                echo $error;

            } else if ( file_put_contents( './../inc/conf/channels.php', '<?php $channels = ' . var_export( $confOut, true ) . ';' ) ) {

                // Clear File Cache
                clearstatcache( true );
                if ( function_exists( 'opcache_invalidate' ) ) opcache_invalidate( './../inc/conf/channels.php', true );


                $_SESSION[ 'msg' ] = alert( 'Successfully ' . ( ( $_GET[ 'e' ] == 'add' ) ? 'added' : 'updated' ) . ' channel.', 'success' );
                header( "Location: ?s=channels" );
                exit;

            } else {

                echo alert( 'Unable to store channel settings, you may not have sufficient permissions!', 'error', true );

            }

        }

    }


    // Not submit & not new file
    if ( $_GET[ 'e' ] != 'add' && !isset( $_POST[ 'submit' ] ) ) {


        if ( empty( $channels[ $_GET[ 'e' ] ] ) OR !is_numeric( $_GET[ 'e' ] ) ) {
            $_SESSION[ 'msg' ] = alert( 'Unable to edit specified channel because it was not found!' );
            header( "Location: ?s=channels" );
            exit;
        }


        // Only Convert PHP array of streams to html comparable one if its available
        if ( is_array( $channels[ $_GET[ 'e' ] ][ 'streams' ] ) ) {

            // Few preset variables
            $cid    = $_GET[ 'e' ];
            $_POST  = $channels[ $cid ];
            $countq = 0;

            // Convert PHP array of streams to html compatible one
            foreach ( $channels[ $cid ][ 'streams' ] as $name => $arr ) {

                $_POST[ 'quality' ][ $countq ] = $name;

                foreach ( $arr as $codec => $url ) {
                    $_POST[ 'url_' . $countq ][]   = $url;
                    $_POST[ 'codec_' . $countq ][] = $codec;
                }

                $countq++; ## Increase counter
            }

            unset( $_POST[ 'streams' ] );

        } // End convert


        // Parse config stats
        $stats = $channels[ $cid ][ 'stats' ];
        switch ( $stats[ 'method' ] ) {

            case 'direct':
                $_POST[ 'stats' ] = $stats[ 'method' ];
                $_POST[ 'direct-url' ] = $stats[ 'url' ];
                $_POST[ 'direct-url-fallback' ] = $stats[ 'fallback' ];
                break;

            case 'shoutcast':
                $_POST[ 'stats' ] = $stats[ 'method' ];
                $_POST[ 'shoutcast-url' ] = $stats[ 'url' ];
                $_POST[ 'shoutcast-pass' ] = $stats[ 'auth' ];
                $_POST[ 'shoutcast-sid' ] = $stats[ 'sid' ];
                $_POST[ 'sc-history' ] = $stats[ 'sc-history' ];
                break;

            case 'icecast':
                $_POST[ 'stats' ] = $stats[ 'method' ];
                $_POST[ 'icecast-url' ] = $stats[ 'url' ];
                $_POST[ 'icecast-user' ] = $stats[ 'auth-user' ];
                $_POST[ 'icecast-pass' ] = $stats[ 'auth-pass' ];
                $_POST[ 'icecast-mount' ] = $stats[ 'mount' ];
                $_POST[ 'icecast-fallback-mount' ] = $stats[ 'fallback' ];
                break;

            case 'sam':
                $_POST[ 'stats' ] = $stats[ 'method' ];
                $_POST[ 'sam-host' ] = $stats[ 'host' ];
                $_POST[ 'sam-user' ] = $stats[ 'auth-user' ];
                $_POST[ 'sam-pass' ] = $stats[ 'auth-pass' ];
                $_POST[ 'sam-db' ] = $stats[ 'db' ];
                break;

            case 'centovacast':
                $_POST[ 'stats' ] = $stats[ 'method' ];
                $_POST[ 'centova-url' ] = $stats[ 'url' ];
                $_POST[ 'centova-user' ] = $stats[ 'user' ];
                $_POST[ 'centova-use-cover' ] = $stats[ 'use-cover' ];
                break;

            case 'radionomy':
                $_POST[ 'stats' ] = $stats[ 'method' ];
                $_POST[ 'radionomy-uid' ] = $stats[ 'user-id' ];
                $_POST[ 'radionomy-apikey' ] = $stats[ 'api-key' ];
                $_POST[ 'radionomy-use-cover' ] = $stats[ 'use-cover' ];
                break;

            case 'custom':
                $_POST[ 'stats' ] = $stats[ 'method' ];
                $_POST[ 'custom-url' ] = $stats[ 'url' ];
                $_POST[ 'custom-user' ] = $stats[ 'user' ];
                $_POST[ 'custom-pass' ] = $stats[ 'pass' ];
                break;

            default:
                $_POST[ 'stats' ] = 'disabled';
                break;

        }

    }
?>
<form method="POST" action="?s=channels&e=<?php echo $_GET[ 'e' ]; ?>" enctype="multipart/form-data">

    <div class="panel">
        <div class="content">

            <p>
                AIO - Radio Station Player supports multi-channel configuration(s) but If a single channel is configured or a single stream, player will hide the unused buttons.
                Other settings that affect all channels are covered in <b>Settings tab</b>.
                <span class="text-red">Please read instructions carefully! Invalid configuration could cause player to stop working properly.</span><br><br>
            </p>

            <div class="form-group">
                <label class="control-label col-sm-2" for="name">Channel Name</label>
                <div class="col-sm-5">
                    <input class="form-control" type="text" name="name" id="name" value="<?php echo htmlentities( $_POST[ 'name' ], null, 'utf-8' ); ?>" placeholder="Rock channel">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label" for="logo">Channel Logo</label>
                <div class="col-sm-8">
                    <div class="file-input">

                        <input type="file" id="logo" name="logo">

                        <div class="input-group col-sm-6">
                            <input type="text" class="form-control file-name" placeholder="Select an image">
                            <div class="input-group-btn">
                                <a href="#" class="btn btn-info"><i class="fa fa-folder-open fa-fw"></i> Browse</a>
                            </div>
                        </div>
                    </div>
                    <i>JPEG, JPG, PNG, WEBP and SVG accepted. Image will be cropped to fit logo area.</i>
                    <?php if ( !empty( $channels[ $_GET[ 'e' ] ][ 'logo' ] ) && is_file( './../' . $channels[ $_GET[ 'e' ] ][ 'logo' ] ) ) {
                        echo '<div class="logo-container"><br><div class="channel-logo">
				<img src="./../' . $channels[ $_GET[ 'e' ] ][ 'logo' ] . '" width="auto" height="40"></div><br><a href="#" class="delete-logo"><i class="fa fa-times"></i> Delete</a></div>';
                    } ?>
                </div>
            </div>

            <div class="clearfix"></div>

            <div class="form-group">

                <label class="col-sm-2 control-label" for="skin">Color Scheme</label>

                <div class="col-sm-3">
                    <select class="form-control" name="skin" id="skin">
                        <option value="">Default</option>
                        <?php

                            $list = array();
                            $custom = browse( "./..{$templates[ $settings[ 'template' ] ]['path']}/custom/", true, false );

                            // Get from manifest
                            if ( is_array( $templates[ $settings[ 'template' ] ][ 'schemes' ] ) && count( $templates[ $settings[ 'template' ] ][ 'schemes' ] ) >= 1 )
                                foreach ( $templates[ $settings[ 'template' ] ][ 'schemes' ] as $key => $val ): $list[ $val[ 'name' ] ] = $val[ 'style' ]; endforeach;

                            // Get from custom directory
                            if ( is_array( $custom ) && count( $custom ) >= 1 )
                                foreach ( $custom as $file ) : $list[ ucfirst( ext_del( $file ) ) ] = "custom/{$file}"; endforeach;

                            // Now show and pick used
                            foreach ( $list as $key => $file ) {
                                $vv = ( ( isset( $_GET[ 'e' ] ) && $channels[ $_GET[ 'e' ] ][ 'skin' ] == $file ) ? ' selected' : '' );
                                echo "<option value=\"{$file}\"{$vv}>{$key}</option>";
                            }

                        ?>
                    </select>
                </div>
                <span class="help-block">Hint: Generate custom color schemes under <b>Advanced</b> tab</span>
            </div>

            <div class="form-group">
                <label class="control-label col-sm-2" for="showtime">Channel Time</label>
                <div class="col-sm-8">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" value="true" name="show-time" id="showtime"<?php if ( $_POST[ 'show-time' ] == 'true' OR !isset( $_POST[ 'show-time' ] ) ) echo ' checked=""'; ?>>
                            <span class="fa fa-check"></span> Show playback timer (Based on when the last track was changed)
                        </label>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="row">
                <div class="col-sm-9 col-sm-offset-2">
                    <h5>How to configure streams?</h5>
                    <p>
                        Player supports various streaming formats but because <b>HTML5 Audio API</b> relies on web browser each web browser has different codecs support.
                        MP3 codec is supported in all major web browsers, for that reason its highly recommended. Codecs like <b>AAC+</b> and <b>OGG</b> are only supported in small amount of browsers.
                        Below you will find examples for how to link streams:
                    </p>
                    <ul>
                        <li><b>Shoutcast v1.x</b> - http://shoutcast-server-url.com:port/;stream.mp3</li>
                        <li><b>Shoutcast v2.x</b> - http://shoutcast-server-url.com:port/mountpoint</li>
                        <li><b>Iceacast v2.x</b> - http://icecast-server-url.com:port/mountpoint</li>
                    </ul>

                    <div class="text-red">
                        Note: You can use combination of codecs e.g. OGG and MP3. In combination mode first stream is used as "primary" and second as "fall-back".
                        Adding AAC+ codec may break player in some browsers because some browsers don't fall-back when playback fails.
                    </div>
                </div>
            </div>

            <div class="clearfix"></div>
            <br>

            <div class="row">
                <label class="col-sm-2 control-label">Streams (Audio)</label>
                <div class="col-sm-9">

                    <div class="qualitylist">

                        <?php

                            // If this is post, or edit create quality/streams inputs
                            if ( is_array( $_POST[ 'quality' ] ) ) {

                                // LOOP
                                $c = count( $_POST[ 'quality' ] ) - 1;
                                for ( $i = 0; $i <= $c; $i++ ) {

                                    echo '<div class="quality-group">
							<input title="Click to edit" class="input-quality" type="text" name="quality[]" value="' . $_POST[ 'quality' ][ $i ] . '">
							<div class="pull-right"><a href="#" class="delgrp"><i class="fa fa-times"></i> Delete Group</a></div>
							<table class="table streams"><tbody>';

                                    // Count fields
                                    $name = 'url_' . $i;
                                    $totalFields = count( $_POST[ $name ] ) - 1;

                                    // Loop through fields
                                    for ( $f = 0; $f <= $totalFields; $f++ ) {

                                        echo '<tr>
								<td class="col-sm-9"><input class="form-control" type="url" placeholder="Stream URL (read above!)" name="url_' . $i . '[]" value="' . $_POST[ 'url_' . $i ][ $f ] . '"></td>
								<td class="col-sm-2">
								<select name="codec_' . $i . '[]" class="form-control">';

                                        foreach ( $codecs as $codec => $name ) {
                                            if ( $_POST[ 'codec_' . $i ][ $f ] == $codec ) {
                                                $codec .= '" selected="selected';
                                            } // Select codec
                                            echo '<option value="' . $codec . '">' . $name . '</option>' . "\n";
                                        }

                                        echo '</select>
								</td><td style="width: 5%; text-align: center;"><div class="form-control-static"><a class="remove-row" href="#" style="color: red;"><i class="fa fa-times"></i></a></div></td></tr>';

                                    }

                                    echo '</tbody></table><a href="#" class="addrow"><i class="fa fa-plus"></i> Add More Streams</a></div>';

                                }
                            }
                        ?>

                    </div>

                    <a class="btn btn-success addgrp"><i class="fa fa-plus"></i> Add Another Group</a>

                </div>

            </div>

            <div class="divider"></div>

            <div class="form-group">
                <label class="col-sm-2 control-label" for="stats">Track Info Method</label>
                <div class="col-sm-4">
                    <select class="form-control" name="stats" id="stats">
                        <?php

                            $values = array(
                                'disabled'    => 'disabled',
                                'direct'      => 'Use live stream (no login)',
                                'shoutcast'   => 'Shoutcast (login required)',
                                'icecast'     => 'Icecast (login required)',
                                'sam'         => 'SAM Broadcaster (MySQL)',
                                'radionomy'   => 'Radionomy API (UID & API Key)',
                                'centovacast' => 'CentovaCast API (no login)',
                                'custom'      => 'Custom (External API)'
                            );

                            foreach ( $values as $key => $row ) {

                                if ( $_POST[ 'stats' ] == $key ) $key .= '" selected="selected';
                                echo '<option value="' . $key . '">' . $row . '</option>';

                            }
                        ?>
                    </select>
                </div>
            </div>

            <div class="stats-conf"></div>

        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <button type="submit" name="submit" value="save" class="btn btn-success"><i class="fa fa-pencil fa-fw"></i> Save</button>
            <a href="?s=channels" class="btn btn-danger"><i class="fa fa-times fa-fw"></i> Cancel</a>
        </div>
    </div>
</form>

<style>
    ul {
        padding-left: 20px;
    }

    h5 {
        font-size: 14px;
        padding:   0 0 5px;
        margin:    0;
    }

    h5 a {
        font-size:   12px;
        font-weight: normal;
    }

    .quality-group {
        padding:       5px 0 5px;
        border-radius: 3px;
        margin:        0 0 15px;
    }

    .quality-group:last-child {
        margin-bottom: 20px;
    }

    table.table {
        margin: 0 0 5px;
    }

    table .col-sm-9, table .col-sm-2, tbody td, table.table tr td:first-child {
        padding: 10px 0 !important;
    }

    .input-quality {
        position:      relative;
        padding:       0;
        margin-bottom: -5px;
        background:    transparent;
        border:        0;
        outline-style: none;
        outline:       0;
        font-size:     14px;
        font-weight:   500;
        min-width:     350px;
    }

    .channel-logo {
        display:    inline-block;
        border:     1px solid #808080;
        background: #585858;
        color:      #fff;
        padding:    5px 10px;
    }
</style>

<script type="text/javascript">

    window.loadinit = function() {

        // Stats inputs
        $( 'select#stats' ).on( 'change', function() {

            <?php

            // Direct stats
            $form->clear();
            $form->add( array( 'label' => 'Stream URL', 'name' => 'direct-url', 'placeholder' => 'http://192.168.1.1:8000/mount', 'class' => 'col-sm-5' ) );
            $form->add( array( 'label' => 'Stream URL (Fallback)', 'name' => 'direct-url-fallback', 'placeholder' => 'http://192.168.1.1:8000/fallback-mount', 'class' => 'col-sm-5', 'description' => '(not required)' ) );
            $direct = $form->html;


            // Shoutcast stats
            $form->clear();
            $form->add( array( 'label' => 'Shoutcast Status Page', 'name' => 'shoutcast-url', 'placeholder' => 'http://192.168.1.1:8000/', 'class' => 'col-sm-5' ) );
            $form->add( array( 'label' => 'Admin Password', 'name' => 'shoutcast-pass', 'placeholder' => 'password', 'class' => 'col-sm-5', 'type' => 'password' ) );
            $form->add( array( 'label' => 'SID', 'name' => 'shoutcast-sid', 'placeholder' => '1', 'class' => 'col-sm-2', 'description' => '(Leave empty if running version 1.x)' ) );
            $form->add( array( 'label' => 'History', 'class' => 'col-sm-9', 'name' => 'sc-history', 'value' => 'true', 'type' => 'checkbox', 'description' => 'If song history is available on Shoutcast server, use actual history instead of AIO\\\'s' ) );
            $shoutcast = $form->html;


            // Icecast stats
            $form->clear();
            $form->add( array( 'label' => 'Icecast Status Page', 'name' => 'icecast-url', 'placeholder' => 'http://192.168.1.1:8000/', 'class' => 'col-sm-5' ) );
            $form->add( array( 'label' => 'Admin Username', 'name' => 'icecast-user', 'placeholder' => 'admin', 'class' => 'col-sm-5' ) );
            $form->add( array( 'label' => 'Admin Password', 'name' => 'icecast-pass', 'placeholder' => 'password', 'class' => 'col-sm-5', 'type' => 'password' ) );
            $form->add( array( 'label' => 'Mount Point', 'name' => 'icecast-mount', 'placeholder' => '/autodj', 'class' => 'col-sm-3' ) );
            $form->add( array( 'label' => 'Fallback Mount', 'name' => 'icecast-fallback-mount', 'placeholder' => '/stream', 'class' => 'col-sm-3', 'description' => '(Fallback to this mount if main has no info, not required)' ) );
            $icecast = $form->html;


            // SAM Broadcaster stats
            $form->clear();
            $form->add( array( 'label' => 'MySQL Host', 'name' => 'sam-host', 'placeholder' => '127.0.0.1', 'class' => 'col-sm-5' ) );
            $form->add( array( 'label' => 'MySQL Username', 'name' => 'sam-user', 'placeholder' => 'root', 'class' => 'col-sm-5' ) );
            $form->add( array( 'label' => 'MySQL Password', 'name' => 'sam-pass', 'placeholder' => 'password', 'class' => 'col-sm-5', 'type' => 'password' ) );
            $form->add( array( 'label' => 'SAM Database', 'name' => 'sam-db', 'placeholder' => 'sam', 'class' => 'col-sm-3' ) );
            $sam = $form->html;


            // Centovacast stats
            $form->clear();
            $form->add( array( 'label' => 'Centovacast URL', 'name' => 'centova-url', 'placeholder' => 'http://192.168.1.1:2199/', 'class' => 'col-sm-5' ) );
            $form->add( array( 'label' => 'Centovacast Username', 'name' => 'centova-user', 'placeholder' => 'JohnDoe', 'class' => 'col-sm-4', 'description' => '(Recent Tracks widget must be enabled!)' ) );
            $form->add( array( 'label' => 'Track Artwork', 'class' => 'col-sm-9', 'name' => 'centova-use-cover', 'value' => 'true', 'type' => 'checkbox', 'description' => 'Use Artworks from Centova Cast API (Prioritized when images are missing on the player)' ) );
            $centova = $form->html;


            // Radionomy stats fields
            $form->clear();
            $form->add( array( 'label' => 'Radio UID', 'name' => 'radionomy-uid', 'class' => 'col-sm-5' ) );
            $form->add( array( 'label' => 'Personal API Key', 'name' => 'radionomy-apikey', 'class' => 'col-sm-5', 'description' => '(<a href="http://board.radionomy.com/viewtopic.php?f=28&t=915&p=3105#p3105" target="_blank">Where to find these?</a>)' ) );
            $form->add( array( 'label' => 'Track Artwork', 'class' => 'col-sm-9', 'name' => 'radionomy-use-cover', 'value' => 'true', 'type' => 'checkbox', 'description' => 'Use Artworks from Radionomy API (Prioritized when images are missing on the player)' ) );
            $radionomy = $form->html;


            // Custom stats
            $form->clear();
            $form->add( array( 'label' => 'Custom URL', 'name' => 'custom-url', 'placeholder' => 'http://domain.com/file.php', 'class' => 'col-sm-5', 'description' => '(Response must be plain text in format <b>Artist - Title</b>)' ) );
            $form->add( array( 'label' => 'HTTP-Auth Username', 'name' => 'custom-user', 'placeholder' => 'JohnDoe', 'class' => 'col-sm-4', 'description' => '(Optional)' ) );
            $form->add( array( 'label' => 'HTTP-Auth Password', 'name' => 'custom-pass', 'placeholder' => 'Password', 'class' => 'col-sm-4', 'description' => '(Optional)', 'type' => 'password' ) );
            $custom = $form->html;

            ?>

            var elm = $( this );
            switch ( $( elm ).val() ) {

                case 'direct':
                    $( '.stats-conf' ).html( '<?php echo $direct; ?>' );
                    break;

                case 'shoutcast':
                    $( '.stats-conf' ).html( '<?php echo $shoutcast; ?>' );
                    break;

                case 'icecast':
                    $( '.stats-conf' ).html( '<?php echo $icecast; ?>' );
                    break;

                case 'sam':
                    $( '.stats-conf' ).html( '<?php echo $sam; ?>' );
                    break;

                case 'centovacast':
                    $( '.stats-conf' ).html( '<?php echo $centova; ?>' );
                    break;

                case 'radionomy':
                    $( '.stats-conf' ).html( '<?php echo $radionomy; ?>' );
                    break;

                case 'custom':
                    $( '.stats-conf' ).html( '<?php echo $custom; ?>' );
                    break;

                default:
                    $( '.stats-conf' ).empty();
                    break;

            }

            return false;

        } );


        // Add stream group
        $( '.addgrp' ).on( 'click', function() {

            var xid          = parseInt( $( '.quality-group' ).index( $( document ).find( '.quality-group' ).last() ) ) + 1 || 0;
            var qualitygroup = 'Default Quality' + ((xid >= 1) ? ' (' + (xid + 1) + ')' : '') + '';

            var html = $( '<div class="quality-group"><input title="Click to edit" class="input-quality" type="text" name="quality[]" value="' + qualitygroup + '">\
				<div class="pull-right"><a href="#" class="delgrp"><i class="fa fa-times"></i> Delete Group</a></div><table class="table streams"><tbody></tbody>\
				</table><a href="#" class="addrow"><i class="fa fa-plus"></i> Add More Streams</a></div>' );

            $( '.qualitylist' ).append( html );
            html.find( '.addrow' ).trigger( 'click' );
            return false;

        } );

        // Bind delete groups
        $( '.qualitylist' ).on( 'click', '.delgrp', function() {

            if ( confirm( 'Are you sure you wish to delete whole group?' ) ) {
                $( this ).closest( '.quality-group' ).remove();
            }

            // Fix indexes
            var xid = 0;
            $( '.quality-group' ).each( function() {

                $( this ).find( 'select, input' ).each( function() {

                    // Change name attr via regex with its group index
                    var currentName = $( this ).attr( 'name' );

                    if ( currentName != null ) { // Use Regex to replace index number
                        $( this ).attr( 'name', currentName.replace( /_([0-9]+)\[\]/, '_' + xid + '[]' ) );
                    }

                } );

                xid++; // Increse counter

            } );

            return false;

        } );

        // Bind delete streams
        $( '.qualitylist' ).on( 'click', '.remove-row', function() {

            if ( confirm( 'Are you sure you wish to delete this stream?' ) ) {
                $( this ).closest( 'tr' ).remove();
            }

            return false;

        } );

        // Bind add row (add streams)
        $( '.qualitylist' ).on( 'click', '.addrow', function() {

            var xid = parseInt( $( '.quality-group' ).index( $( this ).closest( '.quality-group' ) ) ) || 0;
            $( this ).closest( '.quality-group' ).find( 'tbody' ).append( '<tr class="stream-row"><td class="col-sm-9">\
				<input class="form-control" type="url" placeholder="Stream URL (read above!)" name="url_' + xid + '[]"></td>\
				<td class="col-sm-2"><select name="codec_' + xid + '[]" class="form-control"><option value="mp3">MP3</option><option value="oga">OGG</option><option value="m4a">AAC</option>\
				</select></td><td style="width: 5%; text-align: center;"><div class="form-control-static"><a class="remove-row" href="#" style="color: red;"><i class="fa fa-times"></i></a>\
				</div></td></tr>' );

            // Re-bind custom selectboxes
            $( 'select' ).selectbox();
            return false; // Disable other actions

        } );


        // Change input value for browse
        $( 'input[type="file"]' ).on( 'change', function() {

            var cVal = $( this ).val().replace( /.*\\fakepath\\/, '' );
            $( this ).parent( '.file-input' ).find( 'input.file-name' ).val( cVal );

        } );


        // Delete existing logo
        $( '.delete-logo' ).on( 'click', function() {

            var elm = $( this );

            // On success, delete container
            $.get( '?s=channels&e=<?php echo $_GET[ 'e' ]; ?>&logo=delete', function() {
                $( elm ).closest( '.logo-container' ).remove();
            } );

            return false;

        } );


        // Triggers
        <?php if ( isset( $_POST[ 'submit' ] ) OR $_GET[ 'e' ] != 'add' ) echo '$(\'select#stats\').trigger(\'change\');'; ?>

        if ( $( '.qualitylist .quality-group' ).length == false )
            $( '.addgrp' ).trigger( 'click' );

    };
</script>