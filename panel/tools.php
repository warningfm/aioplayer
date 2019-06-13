<?php

    // No can do without index definition!
    if ( $inc !== true ) {
        header( "Location: index.php?s=home" );
        exit;
    }

    // Load channels file (used for all actions on this page)
    if ( is_file( "./../inc/conf/channels.php" ) ) include( "./../inc/conf/channels.php" );
    if ( !is_array( $channels ) ) $channels = array();

    // Artwork manager allowed extensions option
    $allow_ext = array( 'jpeg', 'jpg', 'png', 'svg', 'webp' );

    // New since 1.3 - get list of templates
    $templates = getTemplates( './..' );

    // Handle compiling new CSS stylesheet
    if ( $_POST[ 'submit' ] == 'compile' ) {

        include './scss.lib.inc.php';

        // Path
        $base_path = "./..{$templates[ $_POST[ 'template' ] ][ 'path' ]}";
        $scss_file = '';

        // Find scheme
        if ( is_array( $templates[ $_POST[ 'template' ] ][ 'schemes' ] ) && count( $templates[ $_POST[ 'template' ] ][ 'schemes' ] ) >= 1 ) {
            foreach ( $templates[ $_POST[ 'template' ] ][ 'schemes' ] as $key => $path ) {

                if ( $path[ 'name' ] == $_POST[ 'base-theme' ] ) {

                    $scss_file = $path[ 'compile' ];
                    break;

                }

            }
        }


        // Validate data
        if ( empty( $_POST[ 'filename' ] ) OR empty( $_POST[ 'base-theme' ] ) OR empty( $_POST[ 'base-color' ] ) ) {

            $theme_message = alert( 'Invalid data submission! There are some missing fields, please try again!', 'error' );

        } else if ( $scss_file == '' || !is_file( "{$base_path}/{$scss_file}" ) ) {

            $theme_message = alert( 'Unable to compile new theme since the <b>base theme</b> file or <b>template</b> path is missing!', 'error' );

        } else if ( !is_dir( "{$base_path}/custom/" ) && mkdir( "{$base_path}/custom/", 0755 ) == false ) {

            $theme_message = alert( 'Directory "custom" under the template directory does not exist because something went wrong while creating it!', 'error' );

        } else {

            // Compile SASS and save it as file.
            $scss = new scssc();
            $scss->setImportPaths( $base_path . '/' . dirname( $scss_file ) );
            $scss->setFormatter( 'scss_formatter_compressed' );

            // Compile!
            $contents = $scss->compile( "\$accent-color: {$_POST[ 'base-color' ]}; \$bg-color: {$_POST['bg-color']}; @import '" . basename( $scss_file ) . "';" );

            // Append color & scheme to the output file so we can use the information on update
            if ( !empty( $contents ) ) {

                // Replace pre-defined text strings
                $contents = preg_replace( array( '/accent=([^;]*);/i', '/scheme=([^;]*);/i' ), array( "accent={$_POST['base-color']};", "scheme=" . basename( $scss_file ) . ";" ), $contents );

            }

            // Attempt to save
            if ( file_put_contents( "{$base_path}/custom/{$_POST['filename']}.css", $contents ) ) {

                // Clear templates cache
                if ( is_file( "{$cwd}/tmp/cache/templates.cache" ) ) @unlink( "{$cwd}/tmp/cache/templates.cache" );
                $theme_message = alert( 'Successfully compiled new player theme!', 'success' );

            } else {

                $theme_message = alert( 'Unable to save new theme! Please make sure the template directory is writable (chmod 755)!', 'error' );

            }

        }

    }

    // Handle artist image upload
    if ( $_POST[ 'submit' ] == 'upload' ) {

        if ( !in_array( ext_get( $_FILES[ 'image' ][ 'name' ] ), $allow_ext ) ) {

            $artwork_upload = alert( 'You have uploaded invalid image file!', 'error' );

        } else if ( empty( $_POST[ 'artist' ] ) ) {

            $artwork_upload = alert( 'You need to enter artist name!', 'error' );

        } else {

            $artist = parse_track( $_POST[ 'artist' ] );
            delete_artist( $_POST[ 'artist' ] );

            // Attempt to save
            $up = upload( 'image', './../tmp/images/', $artist );
            if ( !is_array( $up ) ) {

                $artwork_upload = alert( "Uploading failed! ERROR: {$up}", 'error' );

            } else {

                // From post to variable
                $p[ 'cropY' ] = trim( $_POST[ 'cropY' ] );
                $p[ 'cropX' ] = trim( $_POST[ 'cropX' ] );


                // Check image size (since 1.31)
                if ( !is_numeric( $settings[ 'images_size' ] ) || $settings[ 'images_size' ] < 100 )
                    $settings[ 'images_size' ] = '280';

                // Calculate crop position depending on input/output image size
                if ( $p[ 'cropY' ] != 0 ) $p[ 'cropY' ] = $p[ 'cropY' ] * ( $settings[ 'images_size' ] / 140 );
                else if ( $p[ 'cropX' ] != 0 ) $p[ 'cropX' ] = $p[ 'cropX' ] * ( $settings[ 'images_size' ] / 140 );


                // Crop
                image::handle( $up[ 'path' ], "{$settings['images_size']}x{$settings['images_size']}", 'crop', null, array( 'cropY' => $p[ 'cropY' ], 'cropX' => $p[ 'cropX' ] ) );

                // Show success
                $artwork_upload = alert( 'Artist was added successfully!', 'success' );


            }

        }

    }

?>
<div class="panel">
    <div class="heading"><i class="fa fa-medkit"></i> Connection Test & Debug</div>
    <div class="content">
        <p>
            This function allows you to test internet connectivity issues and problems. The initial idea was to make this tool available to test port connectivity because
            some web hosting providers are blocking uncommon internet ports for their "security". In most cases contacting the provider to unblock the port will fix the issue.
        </p>

        <div class="row">
            <div class="col-sm-4" style="padding-right:5px;">
                <select class="form-control" name="debug-server">
                    <?php echo( ( count( $channels ) >= 1 ) ? '<option value="user">All configured channels</option>' : '' ); ?>
                    <option value="ssl_check">AIO Update Center & iTunes Server</option>
                    <option value="centovacast">Centovacast ( Port: 2199 )</option>
                    <option value="radionomy">Radionomy API ( Port: 80 )</option>
                    <option value="ports">Shoutcast & Icecast ( Port: 8000 )</option>
                    <option value="all">Test ports 8000, 2199 and 80</option>
                </select>
            </div>
            <button class="btn btn-primary start-debug"><i class="fa fa-play"></i> &nbsp;Start Test</button>
        </div>

        <pre class="debug-output commands-pre" style="display: none; margin-top: 8px; margin-bottom: 0;"></pre>
        <iframe id="debug-iframe" src="about:blank" style="border:0;" border="0" width="0" height="0"></iframe>
    </div>
</div>

<?php echo $theme_message; ?>

<div class="panel">
    <div class="heading"><i class="fa fa-folder"></i> Custom Color Scheme(s)</div>
    <div class="content" id="theme-tool">
        <form method="POST" action="?s=tools#theme-tool">

            <p>
                This option allows you to create your own color scheme for the player.<br>
                The generated color scheme will be saved as <b>theme-name.css</b> file under <b>/templates/(your chosen template)/custom/</b> directory.
            </p><br>

            <div class="form-group">
                <label class="col-sm-2 control-label" for="filename">New theme name:</label>
                <div class="col-sm-4">
                    <input class="form-control" type="text" name="filename" placeholder="base.color" value="" id="filename" required>
                </div>
                <div class="help-block"> (If you enter name of existing theme, this will overwrite it)</div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label" for="base-theme">Select template:</label>
                <div class="col-sm-4">
                    <select class="form-control" name="template" id="template">
                        <option value="" disabled selected>None</option>
                    </select>
                </div>
            </div>

            <div class="form-group hidden">
                <label class="col-sm-2 control-label" for="base-theme">Select theme base:</label>
                <div class="col-sm-3 base-container">
                    <select class="form-control" name="base-theme" id="base-theme"></select>
                </div>
                <div class="help-block"> (Selected theme will be used as a "base" for the new color scheme)</div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label" for="basecolor">Accent color:</label>
                <div class="col-sm-4">
                    <input id="basecolor" type="color" value="#3498db" default="#3498db" name="base-color" required>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label" for="bg-color">Background color:</label>
                <div class="col-md-2 col-lg-1">
                    <input id="bg-color" type="color" value="#F5F5F5" default="#F5F5F5" name="bg-color" required>
                </div>
                <div class="help-block"> (theme might not support this option)</div>
            </div>

            <div class="row">
                <div class="col-sm-offset-2 col-sm-10">
                    <button type="submit" name="submit" value="compile" class="btn btn-success"><i class="fa fa-pencil fa-fw"></i> Compile</button>
                </div>
            </div>
            <br>

        </form>
    </div>
</div>

<?php echo $artwork_upload; ?>

<div class="loadArtwork text-center">
    Loading, please wait...<br><br>
    <img src="./../assets/img/preloader.gif" alt="Preloader" class="align-middle"><br><br>
</div>

<div class="panel artworkManager hidden">
    <div class="heading"><i class="fa fa-users"></i> Artwork Manager</div>
    <div class="content">

        <p>
            This option allows you to set your own images for various artists and their tracks. These images also have higher priority over LastFM or any other API's.
        </p>
        <div class="artwork-manager">
            <table class="table hover">
                <thead>
                <tr>
                    <th></th>
                    <th>File name (formatted)</th>
                    <th>Image Path</th>
                    <th>File size</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>

        </div>

        <a href="#" class="btn btn-success" data-toggle="modal" data-target=".upload-artwork-modal"><i class="fa fa-cloud-upload"></i> Upload</a>
        <a href="#" class="btn btn-info" data-toggle="modal" data-target=".import-artwork-modal"><i class="fa fa-download"></i> Import</a>

    </div>
</div>

<!-- Upload Artwork Modal -->
<div class="modal fade upload-artwork-modal" tabindex="-1" role="dialog" aria-labelledby="Upload Artwork">
    <div class="modal-dialog modal-lg" role="document">

        <form method="POST" action="?s=tools" id="uploadArtwork" enctype="multipart/form-data">

            <div class="modal-content">

                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4><i class="fa fa-cloud-upload"></i> Upload Artwork</h4>
                </div>

                <div class="modal-body">


                    <p>
                        Artwork name will not be preserved because some filesystems do not support special characters.
                        Player will match artwork literally but you can use "ARTIST" or "ARTIST - TITLE" format as well <br>
                    </p>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="artist" style="width: auto; text-align: left;">Artist Name:</label>
                        <div class="col-sm-8">
                            <input class="form-control" type="text" name="artist" placeholder="David Guetta" value="" id="artist">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="artist-image" style="width: auto; text-align: left;">Artist Image</label>
                        <div class="col-sm-8">
                            <div class="file-input">

                                <input type="file" id="artist-image" name="image">

                                <div class="input-group col-sm-8">
                                    <input type="text" class="form-control file-name" placeholder="Select an image">
                                    <div class="input-group-btn">
                                        <a href="#" class="btn btn-info"><i class="fa fa-folder-open fa-fw"></i> Browse</a>
                                    </div>
                                </div>
                            </div>

                            <div class="croparea"><label for="artist-image" style="display: block; text-align: center;"><i class="fa fa-image" style="font-size: 30px; padding:55px 0; color: #E0E0E0;"></i></label></div>
                            <input type="hidden" name="cropX" value="0">
                            <input type="hidden" name="cropY" value="0">

                            <i>JPEG, JPG, PNG, WEBP and SVG accepted. <br>If image aspect ratio doesn't fit, you can move the crop area.</i>

                        </div>

                    </div>


                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i> Close</button>
                    <button type="submit" name="submit" value="upload" class="btn btn-primary"><i class="fa fa-cloud-upload"></i> Upload</button>
                </div>

            </div>

        </form>

    </div>
</div>

<!-- Import Artwork Modal -->
<div class="modal fade import-artwork-modal" tabindex="-1" role="dialog" aria-labelledby="Import Artwork">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <form method="POST" action="?s=tools" id="import-tool">

                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4><i class="fa fa-download"></i> Import Artwork</h4>
                </div>

                <div class="modal-body import-tool">

                    <p>
                        Allows you to import images from various sources. All imported tracks will be renamed and resized to the appropriate format so the player can read it.
                        Your images (the ones you will import) should use format something like "Artist - Title" or simply "Artist" otherwise the player won't read them.
                        <i>Note: The existing artwork with same naming will be simply replaced with newly imported so be careful what you import. The path should be relative to the player folder.</i>
                    </p>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="col-sm-1 control-label" for="path" style="text-align: left;">Path:</label>
                        <div class="col-sm-8">
                            <input class="form-control" type="text" name="import_path" placeholder="tmp/artwork-images/" id="path">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <div class="col-sm-offset-1 col-sm-8">
                            <div class="help-block">You can also use FTP e.g.: ftp://username:password@ftp.server.com/path/to</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-offset-1 col-sm-11">
                            <pre class="import-output commands-pre" style="display: none; margin: 8px 0 0; max-height: 350px;"></pre>
                            <iframe id="import-iframe" src="about:blank" style="border:0;" border="0" width="0" height="0"></iframe>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal" aria-label="Close"><i class="fa fa-times"></i> Close</button>
                    <button type="submit" name="submit" value="import" class="btn btn-primary"><i class="fa fa-download"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">

    // Templates list
    var templates = <?php echo json_encode( $templates ); ?>;

    // Initial window function (executed on body load)
    window.loadinit = function() {

        // Append templates to form (simple
        $.each( templates, function( key, val ) {

            var html = $( '<option \>', {
                value: key,
                html : val.name
            } );

            $( '#template' ).append( html );

        } );

        // Bind templates to show schemes
        $( '#template' ).on( 'change', function() {

            // Might need
            var elm = $( this );

            // Check list
            if ( elm.val() != '' && typeof(templates[ elm.val() ][ 'schemes' ]) !== 'undefined' ) {

                var base = $( '<select \>', { "name": "base-theme", "id": "base-theme" } );
                $( '.base-container' ).empty().append( base );

                // Append first "not selected" option
                base.append( '<option value="" disabled">None</option>' );

                // Loop through schemes
                $.each( templates[ elm.val() ][ 'schemes' ], function( key, val ) {

                    // If no compile provided, don't show...
                    if ( typeof(val.compile) !== 'undefined' ) {

                        var html_option = $( '<option \>', { text: val.name } );
                        base.append( html_option );

                    }

                } );

                base.selectbox();
                base.closest( '.form-group' ).removeClass( 'hidden' );

            } else {

                $( '.base-container' ).closest( '.form-group' ).addClass( 'hidden' );

            }


        } );

        // Bind debug button
        $( '.start-debug' ).on( 'click', function() {

            var elm = $( this );
            $( '.debug-output' ).show().html( '<b>Connecting, please wait...</b>' );
            $( '#debug-iframe' ).attr( 'src', 'iframe.debug.php?test=' + $( '[name="debug-server"]' ).val() );

            return false;

        } );

        // Bind import button
        $( 'form#import-tool' ).on( 'submit', function() {

            var elm = $( this );
            $( '.import-output' ).show().html( '<b>Starting the import process, please wait...</b><br>' );
            $( '#import-iframe' ).attr( 'src', './iframe.artwork.php?path=' + encodeURIComponent( $( '[name="import_path"]' ).val() ) );

            return false;

        } );

        // Artist change
        $( "input[type='file']" ).on(
            "change", function() {

                // Change form input
                var cVal = $( this ).val().replace( /.*\\fakepath\\/, '' );
                $( this ).parent( '.file-input' ).find( 'input.file-name' ).val( cVal );

                // Preview image and crop area
                var url = $( this ).val();
                var ext = url.substring( url.lastIndexOf( '.' ) + 1 ).toLowerCase();

                if ( this.files && this.files[ 0 ] && (ext == "svg" || ext == "png" || ext == "jpeg" || ext == "jpg" || ext == "webp") ) {

                    var reader = new FileReader();
                    var image  = new Image();

                    reader.onload = function( e ) {

                        image.src    = e.target.result;
                        image.onload = function() {
                            $( '.croparea' ).imagearea( this.src, { width: 140, height: 140 } );
                        };
                    };

                    reader.readAsDataURL( this.files[ 0 ] );

                }
            }
        );

        // Handle artwork loading and handling
        $.ajax( { url: "./api.php", dataType: "json", data: { action: "getArtwork" } } ).done( function( data ) {

            if ( data.length >= 1 ) {

                $( data ).each( function( key, val ) {

                    var tableRow = $( '<tr><td><img src="./../' + val.path + '" width="24" height="24" data-preview="true" class="pull-left"></td>' +
                                      '<td class="artist-name">' + val.name + '</td><td>' + val.path + '</td><td>' + val.size + '</td>' +
                                      '<td><a href="#" class="edit-img btn btn-primary btn-small"><i class="fa fa-edit"></i> Replace</a> ' +
                                      ((val[ 'name' ].match( /default\./ )) ? '' : '<a href="#" class="delete-img btn btn-danger btn-small"><i class="fa fa-times"></i> Delete</a>') +
                                      '</td></tr>' );

                    // Bind edit
                    tableRow.find( '.edit-img' ).on( 'click', function() {

                        $( '.upload-artwork-modal' ).modal( 'show' );
                        var artist_name = $( this ).closest( 'tr' ).find( '.artist-name' ).text();
                        $( 'input#artist' ).val( artist_name ).focus();
                        $( '.croparea' ).html( '<img src="' + $( this ).closest( 'tr' ).find( 'img' ).attr( 'src' ) + '" width="140" height="140">' );

                        return false;

                    } );


                    // Bind delete
                    tableRow.find( '.delete-img' ).on( 'click', function() {

                        var artist_name = $( this ).closest( 'tr' ).find( '.artist-name' ).text(), elm = $( this );
                        $.get( './api.php?action=deleteArtwork&name=' + artist_name, function() {
                            $( elm ).closest( 'tr' ).remove();
                        } );

                        return false;
                    } );


                    // Hover Artist Image
                    tableRow.find( 'img[data-preview]' ).on( 'mouseover', function() { // Hover

                        var elm = $( this );

                        // Not yet hovered before
                        if ( !elm.next().hasClass( 'image-preview' ) ) {

                            var imageFile = (elm.attr( 'data-preview' ) != 'true') ? elm.attr( 'data-preview' ) : elm.attr( 'src' );
                            elm.after( '<div class="image-preview"><img width="140" height="140" src="' + imageFile + '"></div>' );

                        }

                        elm.next( '.image-preview' ).addClass( 'in' );
                        elm.on( 'mousemove', function( e ) {
                            elm.next( '.image-preview' ).css(
                                {
                                    'left': (e.clientX + 10) + 'px',
                                    'top' : (e.clientY + 20) + 'px'
                                }
                            );
                        } );

                    } );


                    // Mouse Out on Artist image
                    tableRow.find( 'img[data-preview]' ).on( 'mouseout', function() { // Mouse Out

                        $( this ).next( '.image-preview' ).removeClass( 'in' );

                    } );

                    $( '.artworkManager' ).find( 'tbody' ).append( tableRow );

                } );

            }

            $( '.artworkManager' ).removeClass( 'hidden' );
            $( '.loadArtwork' ).remove();

        } );

    };
</script>
<script type="text/javascript" src="./../assets/js/jquery.imagecrop.min.js"></script>
<script type="text/javascript" src="//cdn.prahec.com/js/spectrum.min.js"></script>
<link href="https://cdn.prahec.com/css/spectrum.min.css" rel="stylesheet" type="text/css">
<script type="text/javascript">
    $( "#basecolor" ).spectrum(
        {
            preferredFormat       : "hex",
            showPalette           : true,
            hideAfterPaletteSelect: true,
            showInput             : true,
            palette               : [
                [ '#1abc9c', '#16a085', '#2ecc71', '#27ae60' ],
                [ '#3498db', '#2980b9', '#9b59b6', '#9b50ba' ],
                [ '#34495e', '#2c3e50', '#f1c40f', '#f39c12' ],
                [ '#e74c3c', '#c0392b', '#ecf0f1', '#bdc3c7' ],
                [ '#95a5a6', '#7f8c8d' ]
            ]
        }
    );

    $( "#bg-color" ).spectrum(
        {
            preferredFormat       : "hex",
            showPalette           : true,
            hideAfterPaletteSelect: true,
            showInput             : true,
            palette               : [
                [ '#1abc9c', '#16a085', '#2ecc71', '#27ae60' ],
                [ '#3498db', '#2980b9', '#9b59b6', '#9b50ba' ],
                [ '#34495e', '#2c3e50', '#f1c40f', '#f39c12' ],
                [ '#e74c3c', '#c0392b', '#ecf0f1', '#bdc3c7' ],
                [ '#95a5a6', '#7f8c8d' ]
            ]
        }
    );
</script>