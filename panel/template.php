<?php

    /* Function to display header of admin pages
    ================================================================================== */
    function head( $settings = array() ) {

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=1024">

            <title><?php echo( ( empty( $settings[ 'title' ] ) ) ? 'AIO - Radio Player' : $settings[ 'title' ] ); ?> :: Control Panel</title>
            <link rel="shortcut icon" href="./assets/favicon.ico">
            <link rel="icon" type="image/png" href="./assets/favicon.png" sizes="32x32" />

            <!-- AIO Radio Control Panel Style Sheet -->
            <link rel="stylesheet" href="./assets/style.css" type="text/css">
            <link rel="stylesheet" href="./assets/panel.style.css" type="text/css">

            <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" type="text/css">
            <link href="//fonts.googleapis.com/css?family=Quicksand:400,500" rel="stylesheet">

            <script src="./../assets/js/jquery-1.11.2.min.js"></script>
            <script src="./../assets/js/jquery.selectbox.min.js"></script>
            <script src="./assets/bootstrap.min.js"></script>
        </head>
        <body>
        <?php if ( $_SESSION[ 'a-login' ] === true ) { ?>
            <section class="intro small">
                <div class="container content">
                    <a href="?logout" class="pull-right btn btn-danger"><i class="fa fa-sign-out"></i> Logout</a>
                    <h2>AIO - Radio Station Player</h2>
                    <h3>Control Panel</h3>
                </div>
            </section>
            <?php
        }

    }

    /* Function to display footer of admin pages
    ================================================================================== */
    function footer( $script = '' ) {

        global $item;
        echo '</section><br><br>';

        ?>

        <!-- Scripts -->
        <script type="text/javascript">
            function setcookie( e, o, n, t, i, u ) {
                var c = new Date;
                c.setTime( c.getTime() ), n && (n = 1e3 * n * 60 * 60 * 24);
                var r           = new Date( c.getTime() + n );
                document.cookie = e + "=" + encodeURI( o ) + (n ? ";expires=" + r.toGMTString() : "") + (t ? ";path=" + t : "") + (i ? ";domain=" + i : "") + (u ? ";secure" : "")
            }

            function getcookie( e ) {
                var o = document.cookie.indexOf( e + "=" ), n = o + e.length + 1;
                if ( !o && e != document.cookie.substring( 0, e.length ) ) return null;
                if ( -1 == o ) return null;
                var t = document.cookie.indexOf( ";", n );
                return -1 == t && (t = document.cookie.length), decodeURI( document.cookie.substring( n, t ) )
            }

            var version = '<?php echo trim( rtrim( file_get_contents( 'version.txt' ) ) ); ?>';
            $( document ).ready( function() {
                $( 'input[allowreset="true"], textarea[allowreset="true"]' ).each( function() {
                    var e = $( this );
                    $( e ).wrap( '<div class="input-append"></div>' );
                    var a = $( '<div class="append resetico" title="Click to reset field to default value"><a href="#"><i class="fa fa-refresh"></i></a></div>' );
                    a.on( "click", function() {return $( e ).val( $( e ).attr( "placeholder" ) ), !1} ), $( e ).after( a )
                } ), $( ".dropdown-toggle" ).on( "click", function() {
                    var e = $( this ).next( ".dropdown-menu" );
                    return $( e ).hasClass( "active" ) ? $( e ).removeClass( "active" ).stop( !0, !0 ).fadeOut( 150 ) : ($( e ).addClass( "active" ).stop( !0, !0 ).fadeIn( 250 ), $( document ).click( function() {$( e ).removeClass( "active" ).stop( !0, !0 ).fadeOut( 150 ), $( document ).unbind( "click" )} )), !1
                } );
                var e = getcookie( "aio_radio.update" );
                null == e || "undefined" == e ? $.getJSON( "https://prahec.com/envato/update?action=check&itemid=<?php echo $item; ?>&method=jsonp&callback=?", function( e ) {(null != version || null != e.version || parseFloat( e.version ) <= parseFloat( version )) && (setcookie( "aio_radio.update", e.version, 21600 ), parseFloat( e.version ) > parseFloat( version ) && $( "#tab-updates" ).append( ' <span class="label label-warning">' + e.version + "</span>" ))} ) : parseFloat( e ) > parseFloat( version ) && $( "#tab-updates" ).append( ' <span class="label label-warning">' + e + "</span>" )
            } );
        </script>
        <?php echo '<script type="text/javascript">if (typeof (window.loadinit) == "function") { window.loadinit(); } $("select").selectbox();</script>
		</body>
	</html>';

    }

    /* Function to display tabs of admin pages
    ================================================================================== */
    function tabs() {

        ## Array of available Tab links
        $tabs = array(
            '<i class="fa fa-home"></i> Home'              => 'home',
            '<i class="fa fa-database"></i> Channels'      => 'channels',
            '<i class="fa fa-language"></i> Language'      => 'lang',
            '<i class="fa fa-wrench"></i> Tools'           => 'tools',
            '<i class="fa fa-cogs"></i> Settings'          => 'settings',
            '<i class="fa fa-cloud-download"></i> Updates' => 'updates'
        );


        ## Show version in nav
        $out = '<div class="menu"><div class="container"><span class="pull-right script-version">
		<b>v' . ( ( is_file( 'version.txt' ) ) ? file_get_contents( 'version.txt' ) : '' ) . '</b></span><ul class="tabs">';


        ## Loop
        foreach ( $tabs as $tab => $link ) {

            if ( $_GET[ 's' ] == $link OR ( empty( $_GET[ 's' ] ) && $link == 'home' ) ) $active = 'class="active"'; else $active = ''; ## Active state
            $out .= "<li><a id=\"tab-{$link}\" href=\"?s={$link}\" {$active}>{$tab}</a></li>";

        }


        echo $out . '</ul></div></div><section class="container main">';
        check_server();

    }


    /* Function to check server capability and warn user if not ok! (once only)
    ==================================================================================== */
    function check_server() {

        global $settings;
        $path = realpath( './../' );

        // PHP Version
        if ( phpversion() <= 5.2 ) {

            echo alert( 'The server is running <b>PHP ' . phpversion() . '</b> while this script requires at least <b>PHP 5.3</b> or above!' );

        }

        if ( !function_exists( 'simplexml_load_string' ) ) {

            echo alert( 'PHP is not compiled with SimpleXML extension! This may cause some serious issues!' );

        }

        if ( !function_exists( 'curl_version' ) ) {

            echo alert( 'PHP <b>CURL extension</b> is not enabled! This script does not work without the extension!' );

        }

        if ( !is_writable( "{$path}/tmp/cache" ) ) {

            echo alert( 'Directory <b>/tmp/cache/</b> is not writable! This will cause extreme slow performance!
			You can fix this issue by setting <b>chmod</b> of folder <b>/tmp/cache/</b> to <b>755</b>.' );

        }

        if ( !is_writable( "{$path}/tmp/images" ) ) {

            echo alert( 'Directory <b>/tmp/images/</b> is not writable! You will not be able to upload custom artist images or channel logo(s)!
			You can fix this issue by setting <b>chmod</b> of folder <b>/tmp/images/</b> to <b>755</b>.' );

        }

        if ( !is_writable( "{$path}/tmp/logs" ) ) {

            echo alert( 'Directory <b>/tmp/logs/</b> is not writable! This means that player will not be able to write error log!
			You can fix this issue by setting <b>chmod</b> of folder <b>/tmp/logs/</b> to <b>755</b>.' );

        }

        if ( is_file( "{$path}/post-update.php" ) && $settings[ 'development' ] !== true ) {

            echo alert( 'Running post update script, please do not interrupt this process...
			<pre class="update-text">Loading, please wait...</pre>
			<iframe id="update" style="border: 0; width:0; height:0;" width="0" height="0" src="iframe.update.php?post-update" border="0"></iframe>' );

        }


        // Add check for error logs
        if ( is_file( './../tmp/logs/errors.log' ) && $settings[ 'debugging' ] !== 'disabled' ) {

            if ( isset( $_GET[ 'delete' ] ) && $_GET[ 'delete' ] == 'logfile' ) {

                if ( !unlink( './../tmp/logs/errors.log' ) ) {

                    echo alert( 'Unable to delete log file, please delete file "errors.log" manually in /tmp/logs/ folder.', 'error' );

                }

                return true;

            }

            // Show deletion message if its NOT log view page
            if ( $_GET[ 's' ] != 'logs' ) {

                echo alert( 'Player may be experiencing some issues which are being logged into a file. You can <a href="./?s=logs">view <i class="fa fa-external-link"></i></a> ' .
                            'or <a onclick="return confirm(\'Are you sure you wish to delete the file?\');" href="?s=' . $_GET[ 's' ] . '&delete=logfile">delete <i class="fa fa-times"></i></a> the file.', 'warning' );

            }

        }

        return true;

    }

?>