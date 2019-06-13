<?php

    // No can do without index definition!
    if ( $inc !== true ) {
        header( "Location: index.php?s=home" );
        exit();
    }

    // Get active template
    $list     = getTemplates( './..' );
    $template = $list[ $settings[ 'template' ] ];

    // Now handle format width x height
    if ( !empty( $template[ 'size' ] ) && strstr( $template[ 'size' ], 'x' ) !== false ) {

        $e = explode( 'x', $template[ 'size' ] );
        $w = $e[ 0 ];
        $h = $e[ 1 ];

    }

?>


<div style="display: block; margin: 10px 0;">
    <label class="control-label">Define desired player size:</label> &nbsp;
    <input type="number" step="1" min="300" max="99999" name="width" value="720" class="form-control mouse-num" style="width: 80px; display: inline-block"> x
    <input type="number" step="1" min="75" max="99999" name="height" value="355" class="form-control mouse-num" style="width: 80px; display: inline-block"> pixels
</div>

<div class="panel">

    <div class="heading">Popup Embed Player
        <small><span>(720 x 355)</span></small>
    </div>

    <div class="content">
        <p>
            This method is recommended. When player is used as popup window, it allows users to continue interacting with your web site without any problems.
            It also ensures that the player is responsive on mobile devices and that it fits screen well. See code bellow to embed this method into your web site.
            The values written in bracket's are recommended sizes for specific player embedding type.
            <span class="text-red">Note: You can also force specific channel via URL. Simply append <b>#channel name</b> to URL.</span>
        </p>

        <b>Preview 1:</b><br>
        <a class="btn btn-primary launchplayer" href="#"><i class="fa fa-external-link-square"></i> Open Popup</a>

        <br><br>
        <b>Preview 2:</b><br>
        <a href="#" class="launchplayer"><img src="./../assets/img/popup.eg.jpg" alt="Open Popup"></a>

        <br><br><b>Source Code:</b><br>
        <textarea class="form-control" style="width: 720px;" id="popup">
<?php echo htmlentities( '<a href="#" onclick="window.open(\'http://' . $_SERVER[ 'SERVER_NAME' ] . preg_replace( '!/panel/(.*)!', '/', $_SERVER[ 'REQUEST_URI' ] ) . '\', \'aio_radio_player\', \'width=720, height=355\'); return false;">Open Popup</a>' ); ?>
</textarea>
    </div>
</div>

<div class="panel">
    <div class="heading">iFrame Embed Player
        <small><span>(720 x 355)</span></small>
    </div>
    <div class="content">

        <p>
            To embed the player on any page simply use code bellow. Its very easy to deploy the player using iframe, it will work as some youtube video.
            If you want to disable auto play via URL you can append <b>?autoplay=false</b> to the URL. The values written in bracket's are recommended sizes for specific player embedding type.<br>
            <span class="text-red">Note: Since version 1.30 you can also append <b>?t=template</b> to URL so you can force specific template on various embeds.</span>
        </p>

        <b>Preview:</b><br>
        <iframe src="./../index.php?autoplay=false" width="720" height="355" border="0" style="border: 0; box-shadow: 1px 1px 0 #fff;"></iframe>

        <br><br><b>Source Code:</b><br><textarea class="form-control" style="width: 720px;" id="iframe">
<?php echo htmlentities( '<iframe width="720" height="355" border="0" style="border: 0; box-shadow: 1px 1px 0  #fff;" src="http://' . $_SERVER[ 'SERVER_NAME' ] . preg_replace( '!/panel/(.*)!', '/', $_SERVER[ 'REQUEST_URI' ] ) . '"></iframe>' ); ?>
</textarea>
    </div>
</div>

<script type="text/javascript">

    window.loadinit = function() {

        var timeout, width = 720, height = 355;

        // On player size change event
        $( document ).on( 'playerSize', function() {

            $( '.panel .heading small span' ).text( '(' + width + ' x ' + height + ')' );

        } );

        // Launch bind
        $( '.launchplayer' ).on( 'click', function() {

            // Open popup player
            window.open( './../index.php', 'aio_radio_player', 'width=' + width + ', height=' + height );
            return false;

        } );

        /**
         * Simple function to handle all stuff that has to change on the page
         */
        function genHome( changeInput ) {

            // Get text areas
            var temp_embed = $( 'textarea#iframe' );
            var temp_popup = $( 'textarea#popup' )

            // Width & Height
            $( 'iframe' ).width( width ).height( height );
            temp_embed.val( temp_embed.val().replace( /width="[0-9]+"/, 'width="' + width + '"' ).replace( /height="[0-9]+"/, 'height="' + height + '"' ) );
            temp_popup.val( temp_popup.val().replace( /width=([0-9]+)/, 'width=' + width + '' ).replace( /height=([0-9]+)/, 'height=' + height + '' ) );

            // Trigger event
            $( document ).trigger( 'playerSize' );

            // If changeInput is true (parameter) also set input
            if ( changeInput === true ) {

                $( 'input[name="width"]' ).val( width );
                $( 'input[name="height"]' ).val( height );

            }

        }

        /**
         * Bind input width change
         */
        $( 'input[name="width"], input[name="height"]' ).on( 'change', function() {

            clearTimeout( timeout );
            timeout = setTimeout( function() {

                // Set new width & height
                width  = $( 'input[name="width"]' ).val();
                height = $( 'input[name="height"]' ).val();

                // Now change elements on page
                genHome();

            }, 500 );

            return true;

        } );

        <?php if ( !empty( $w ) && !empty( $h ) ) : ?>
        width  = '<?php echo $w; ?>';
        height = '<?php echo $h; ?>';
        genHome( true );
        <?php endif; ?>

    };

</script>