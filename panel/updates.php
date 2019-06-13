<?php

    // No can do without index definition!
    if ( $inc !== true ) {
        header( "Location: index.php?s=home" );
        exit;
    }

    // Writable or not?
    if ( !is_writable( './../tmp/updates' ) ) {
        echo alert( 'Directory <b>/tmp/updates/</b> is not writable! This means that player will not be able to download update files!
		<br>You can fix this issue by setting <b>chmod</b> of folder <b>/tmp/updates/</b> to <b>755</b>.' );
    }

?>

<div class="update-content">
    <div class="text-center">
        <br>Loading, please wait...<br><br><img src="./../assets/img/preloader.gif" alt="preloader">
    </div>
</div>

<div class="panel update-panel hidden">
    <div class="heading">Update History</div>
    <div class="content">

        <div id="changelog">
            <div class="commands-pre">
                <pre class="latest-changelog">Loading...</pre>
            </div>
        </div>

        <iframe id="update" style="border: 0; width:0px; height:0px;" width="0" height="0" src="about:blank" border="0"></iframe>
    </div>
</div>
<script type="text/javascript">

    window.loadinit = function() {

        // Request options
        var ajax_options = {
            dataType: 'jsonp',
            timeout : 7000,
            cache   : false,
            url     : 'https://prahec.com/envato/update?action=check&itemid=<?php echo $item; ?>&method=jsonp&callback=?'
        };

        $.ajax( ajax_options ).done( function( data ) {

            if ( typeof( version ) == 'undefined' ) {

                $( '.update-content' ).html( '<?php echo alert( 'Failed to check for updates, unable to determine script version!', 'error' ); ?>' );

            } else if ( data.version == null ) {

                $( '.update-content' ).html( '<?php echo alert( 'Sorry but there are no updates available, please check again later!' ); ?>' );

            } else if ( parseFloat( data.version ) <= parseFloat( version ) ) {

                $( '.update-content' ).html( '<?php echo alert( 'You are running the latest version!', 'success' ); ?>' );

            } else {

                $( '.update-content' ).html( '<?php echo alert( 'Update version <b class="update-version"></b> is available! <a href="#" class="init-update">Please update now.</a>', 'info' );?>' );
                $( '.update-version' ).html( data.version );

            }


            // At end, append changelog if available
            if ( data.changelog != null ) {

                // Add background color to messages fixed, changed, etc...
                var colours = {
                    'Fixed'   : '#27ae60',
                    'Changed' : '#f1c40f',
                    'Added'   : '#f39c12',
                    'Improved': '#34495e',
                    'Updated' : '#2980b9',
                    'Disabled': '#e74c3c',
                    'Removed' : '#e74c3c'
                };

                // Loop through available colors/messages
                $.each( colours, function( key, color ) {

                    var message    = new RegExp( "- " + key + "", "g" );
                    data.changelog = data.changelog.replace( message, '<span style="display: inline-block; color: ' + color + '; text-align: right; margin: 1px 0; \
							padding: 0 2px; width: 65px; font-weight: bold;">' + key + '</span>' );

                } );

                // Tweak Update Name
                data.changelog = data.changelog.replace(
                    /Update ([0-9\.]+) \((.*)\):\n/gi,
                    "<span style=\"font-size: 15px;\">Update <b style=\"font-weight: bold;\">v$1</b> ($2)</span><div class=\"divider\"></div>"
                );

                // Append to DOM
                $( '.latest-changelog' ).html( data.changelog );

            } else {

                // Display message when server doesn't respond with change log
                $( '.latest-changelog' ).html( 'Sorry, latest change log is unavailable.' );

            }

            // Show change log
            $( '.update-panel' ).removeClass( 'hidden' );

            // Bind UPDATE button
            $( '.init-update' ).on( 'click', function() {

                // Delete useless stuff
                $( '.update-content' ).remove();
                $( '.update-panel' ).before( '<div class="panel"><div class="heading">Update Log</div><div class="content">' +
                                             '<pre class="update-text commands-pre">Update process started, please wait...</pre></div></div>' );
                $( this ).remove();

                // Change iframe target to the updater
                $( 'iframe#update' ).attr( 'src', 'iframe.update.php?start=true&itemid=<?php echo $item; ?>' );

                return false;

            } );

        } ).fail( function( xhr, status ) {

            $( '.update-content' ).html( '<?php echo alert( 'Connection to the update server has failed! See the details bellow: <pre id="ajax-error"></pre>', 'error' ); ?>' );

            switch ( status ) {

                case 'timeout':
                    var log_text = 'Unable to connect to the update server. Please check again later!';
                    break;

                default:
                    var log_text = status;
                    break;

            }

            $( 'pre#ajax-error' ).html( log_text );

        } );

    };

</script>