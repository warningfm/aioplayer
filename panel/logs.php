<?php

    // No can do without index definition!
    if ( $inc !== true ) {
        header( "Location: index.php?s=home" );
        exit;
    }

?>

<div class="log-content row">
    <div class="col-sm-4 col-sm-offset-4">
        <div class="text-center">
            <br>Loading <span class="loadPercent"></span>, please wait...
            <div class="loadProgress progress progress-sm" style="display: none;">
                <div class="progress-bar"></div>
            </div>
            <br><br><img src="./../assets/img/preloader.gif" alt="preloader">
        </div>
    </div>
</div>

<div class="panel panel-log hidden">
    <div class="heading">Player Log</div>
    <div class="content">

        <div id="player-log">
            <div class="commands-pre">
                <pre>Loading...</pre>
            </div>
        </div>

        <br><a class="btn btn-danger" href="./?s=<?php echo $_GET[ 's' ]; ?>&delete=logfile" onclick="return confirm('Are you sure?');"><i class="fa fa-times"></i> Delete Log</a>

    </div>
</div>
<script type="text/javascript">

    window.loadinit = function() {

        // Request options
        var ajax_options = {
            xhr: function() {
                var xhr = new window.XMLHttpRequest();

                // Download progress
                xhr.addEventListener( "progress", function( evt ) {

                    if ( evt.lengthComputable ) {

                        var percentComplete = Math.round( evt.loaded / evt.total * 100 );

                        // Do something with download progress
                        $( '.loadProgress' ).show().find( '.progress-bar' ).width( percentComplete + '%' );
                        $( '.loadPercent' ).text( '(' + percentComplete + '%)' );

                    }

                }, false );

                return xhr;

            },
            url: './api.php?action=getLog'
        };

        $.ajax( ajax_options ).done( function( data ) {

            $( '.panel-log' ).removeClass( 'hidden' );
            $( '.log-content' ).hide();

            // Parse log contents
            if ( data != '' ) {

                $( '.commands-pre pre' ).html( data );

            } else {

                $( '.commands-pre pre' ).html( "Your log is empty, that's a good thing!" );

            }

            return false;

        } ).fail( function( xhr, status ) {

            $( '.log-content' ).html( '<?php echo alert( 'Connection to the update server has failed! See the details bellow: <pre id="ajax-error"></pre>', 'error' ); ?>' );

            switch ( status ) {

                case 'timeout':
                    var logText = 'Request timed out, this is probably server error and you will have to check log file manually!';
                    break;

                default:
                    var logText = status;
                    break;

            }

            $( 'pre#ajax-error' ).html( logText );

        } );

    };

</script>