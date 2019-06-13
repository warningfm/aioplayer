/**
 *  Global variables
 *  Note: These are also accessible every where, extremely useful for "additional" code functionality
 */
var aio = {}, c = [];


/**
 *  Automatically executed function, runs on file load (this file)
 */
(function( $ ) {

    // Check if config is there!
    if ( typeof(s) == 'undefined' ) {

        aioFatalError( 'ERROR', 'Unable to read player configuration!' );
        return false;

    }

    // Set URL to current logo
    aio.defaultLogo = $( '.header .logo a img' ).attr( 'src' );

    // JSON Request to get list of all available channels
    $.getJSON( '?c=all&t=' + s.template, loadSettings ).fail( function( jqxhr, textStatus, error ) {

        console.log( "Unable to load list of channels because " + textStatus + ' ' + error );
        aioFatalError( "ERROR", "Unable to load list of available channels!" );
        return false; // Just in case

    } );

    // Bind Header Drop Menus (channels & quality pick only)
    $( 'li.settings a, li.channels a' ).on( 'click', function() {

        var elm = $( this );

        // Handle active/inactive navigation button
        if ( $( elm ).hasClass( 'active' ) ) {

            $( elm ).removeClass( 'active' ).next( 'ul' ).removeClass( 'active' );

        } else {

            // Close all before opening new
            $( 'ul > li.settings > a, ul > li.channels > a' ).removeClass( 'active' );
            $( 'ul > li.settings > ul, ul > li.channels > ul' ).removeClass( 'active' );

            // Open
            $( elm ).addClass( 'active' ).next( 'ul' ).addClass( 'active' );
            $( document ).on( 'click', function() { $( elm ).removeClass( 'active' ).next( 'ul' ).removeClass( 'active' ); } );

        }

        return false;

    } );


    // Bind Facebook & Twitter buttons
    $( '.facebook-share, .twitter-share' ).on( 'click', function() {

        if ( aio[ 'facebook-url' ] == null || aio[ 'twitter-url' ] == null ) return false;
        window.open( (($( this ).hasClass( 'facebook-share' )) ? aio[ 'facebook-url' ] : aio[ 'twitter-url' ]), 'share', 'width=800, height=400' );
        return false;

    } );


    // Bind Hash Change (useful for live channel changes)
    $( window ).on( 'hashchange', function() {

        var channel_name = decodeURIComponent( window.location.href.split( "#" )[ 1 ] || '' );

        // Is channel name not empty?
        if ( s.channel.name != null && channel_name != '' ) loadChannel( channel_name );

    } );

    // Execute variable aio_init and event aio.init
    if ( typeof(aio_init) === 'function' ) aio_init();
    $( document ).trigger( 'aio.init', s );

})( jQuery );


/**
 *  Executed at load, this will get all channels and its settings
 */
function loadSettings( data ) {

    // Javascript can cause issues if object is passed, so create javascript array from obj
    $.each( data, function( key, val ) { c.push( val ); } );

    // Check if channel's list exists, if not show message
    if ( c.length <= 0 ) {

        aioFatalError( 'NO CHANNELS DEFINED', 'Unable to find channels, please create one!' );
        return false;

    }

    // Generate list of channels
    if ( c.length > 1 ) {

        $( 'li.channels' ).show();

        $.each( c, function( key, val ) {

            aio[ 'html' ] = $( '<li><a tabindex="1" href="#' + val.name + '">' + val.name + '</a></li>' );
            $( 'ul.channel-list' ).append( aio[ 'html' ] );

            // Bind channel change
            aio[ 'html' ].on( 'click', function() {

                if ( "onhashchange" in window ) return true;
                else loadChannel( $( this ).text() );

            } );

        } );
    }


    // Use hash to select a channel (method to post links to channels)
    var win_hash = decodeURIComponent( window.location.href.split( "#" )[ 1 ] || '' );
    if ( win_hash != '' ) { loadChannel( win_hash ); }

    // Use cookie for latest selected channel
    if ( win_hash == '' && s.usecookies == 'true' ) {

        var cok = getCookie( 'last_channel' );
        if ( cok != null ) loadChannel( cok, true );

    }

    // If default channel is defined, instead of first channel use the one from settings
    if ( win_hash == '' && cok == null && s.default_channel != null ) { loadChannel( s.default_channel, true ); }

    // Now if we still don't have channel, select default.
    if ( s.channel.name == null ) { loadChannel( c[ 0 ].name ); }

    // Show or Hide history
    if ( s.history == 'true' ) { // Multiple Actions

        $( '.history-toggle' ).show();
        $( '.history-toggle a' ).on( 'click', showHistory );

    }

    // Trigger aio.loaded
    $( document ).trigger( 'aio.loaded', data );

    // Initiate pagination and go to "MAIN" page
    pagination( 'main', false );

    // All done, hide pre-loader
    $( '.preloader' ).addClass( 'loadComplete' );

}


/**
 *  Handle channel change (rebind things, change etc...)
 */
function loadChannel( name, grace ) {

    // No selected channel?
    if ( s.channel.name == name ) return false;

    // Check if the channel exist
    for ( var i = 0; i < c.length; i++ ) { if ( c[ i ].name == name ) var key_ok = i; }

    // Do the check with graceful fix for people with existing cookie
    if ( typeof(key_ok) != 'number' ) {

        if ( grace !== true ) { alert( 'Invalid Channel!' ); }
        console.log( 'Invalid channel: ' + name );
        return false;

    }


    // Handle list
    $( '.channel-list li > a' ).removeClass( 'active' );
    $( '.channel-list li' ).find( 'a[href="#' + name + '"]' ).addClass( 'active' );

    // Set active channel (for easier usage)
    s.channel = c[ key_ok ];
    setCookie( 'last_channel', name, 365 );


    // Load skin & logo
    if ( typeof(s.channel[ 'skin' ]) !== 'undefined' ) $( '#main_theme' ).attr( 'href', 'templates/' + s.template + '/' + s.channel[ 'skin' ] );
    if ( s.channel.logo != null && s.channel.logo != '' ) {

        var logoImg    = new Image();
        logoImg.src    = s.channel.logo;
        logoImg.onload = function() { $( '.header .logo a img' ).attr( 'src', s.channel.logo ); };  // We will only change channel logo if it was loaded!

    } else {

        // Problem above, load default...
        $( '.header .logo a img' ).attr( 'src', aio.defaultLogo );

    }

    // Replace channel name in playlist files
    $( '.playlists a' ).each( function() { $( this ).attr( 'href', $( this ).attr( 'href' ).replace( /c=(.*)/, 'c=' + name ) ); } );

    // Reset per channel stuff
    aio.onair   = null;
    aio.history = [];

    // Show loading bellow artist image
    $( '.artist-preload' ).show();
    $( '.onair .time' ).html( '00:00' );


    // Check user settings for quality
    if ( s.usecookies == 'true' ) {

        var qualityCookie = getCookie( 'quality' );
        if ( qualityCookie != null ) aio.quality = qualityCookie;

    } else {

        aio.quality = null;

    }


    // Set Quality Group (if no user defined
    if ( aio.quality == null || aio.quality == '' || s.channel.streams[ aio.quality ] == null )
        for ( aio.quality in s.channel.streams ) break;


    // Generate list of streams
    if ( $.map( s.channel.streams, function( n, i ) { return i; } ).length > 1 ) {	// If more then one stream

        $( 'li.settings' ).show();
        $( 'ul.streams-list' ).empty();

        $.each( s.channel.streams, function( key, val ) {

            aio[ 'html' ] = $( '<li><a tabindex="1" href="#">' + key + '</a></li>' );
            $( 'ul.streams-list' ).append( aio[ 'html' ] );

            // Add default active state
            if ( aio.quality == key ) aio[ 'html' ].find( 'a' ).addClass( 'active' );


            // Bind channel change
            aio[ 'html' ].on( 'click', function() {

                $( 'ul.streams-list li > a' ).removeClass( 'active' );
                $( this ).find( 'a' ).addClass( 'active' );

                aio.quality = $( this ).text();
                setCookie( 'quality', aio.quality, 365 );
                HTML5Player(); // Re-create player / reset to new setting

                return false;

            } );

        } );

    } else {

        $( 'li.settings' ).hide();

    }


    // Close history
    showHistory( true );

    // Initiate player and/or destruct and then re-create
    HTML5Player();
    txt( s.lang[ 'status-stopped' ], true );

    // Trigger channel & quality change
    $( document ).trigger( 'aio.quality.change', aio.quality );
    $( document ).trigger( 'aio.channel.change', s.channel.name  );

    // Now the heavy work: init player, show loading and start reading stats, again
    clearInterval( aio.radioinfo );
    aio.radioinfo = setInterval( radioInfo, (parseInt( s.stats_refresh ) * 1000) );
    radioInfo();

}


/**
 *  Function that will create jPlayer object and bind all required events to it.
 *  This is the function that handles every thing about Audio in AIO Radio Station Player
 */
function HTML5Player() {

    // Some required pre-set variables
    var supplied      = [];
    var autoPlay      = ((s.autoplay == 'true') ? 'play' : '');
    var volume_cookie = getCookie( 'volume' );
    var solution      = 'html, flash';
    var obj           = $( "#jplayer-object" ), ready = false;

    // At this point if there is active object delete it.
    obj.jPlayer( "destroy" );

    // Loop through stream groups (Quality) and add channel title to HTML5 Tag
    $.each( s.channel.streams[ aio.quality ], function( key, value ) {

        s.channel.streams[ aio.quality ][ 'title' ] = s.title + ' - ' + s.channel.name;
        supplied.push( key );

    } );

    // No channel quality defined, exit with error!
    if ( s.channel.streams[ aio.quality ] == null ) {

        alert( 'ERROR: The specified or selected stream quality does not exist!' );
        return false;

    }

    /* Create JPlayer object, further we will control this object but this is it!
     ============================================================================================================*/
    obj.jPlayer(
        {
            swfPath            : "assets/flash/jquery.jplayer.swf",
            solution           : solution,
            supplied           : supplied.join( ', ' ),
            smoothPlayBar      : false,
            errorAlerts        : true,
            cssSelectorAncestor: ".player",
            volume             : ((volume_cookie == null) ? (s.default_volume / 100) : volume_cookie),
            preload            : 'none',
            cssSelector        : {
                play          : ".play",
                pause         : ".stop",
                mute          : ".volume-icon #volume",
                unmute        : ".volume-icon #muted",
                volumeBar     : ".volume-slider .vol-progress",
                volumeBarValue: ".volume-slider .vol-progress .vol-bar"
            },

            "ready": function( event ) {

                if ( event.jPlayer.status.noVolume ) {

                    // Add a class and then CSS rules deal with it.
                    $( '.volume-control' ).addClass( 'no-volume' );
                    $( '.volume-slider .player-status' ).css( { 'margin-top': '0' } );

                }

                // Go ready
                ready = true;

                // Set media
                $( this ).jPlayer( 'setMedia', s.channel.streams[ aio.quality ] );

                // If not mobile device, play
                if ( $.jPlayer.platform.mobile != true ) { $( this ).jPlayer( autoPlay ); }

                // Fire event aio.ready
                $( document ).trigger( 'aio.ready', event );

            },

            // Since we're working with streams, there is no real "pause". So we clean up loaded file and start new download/stream
            "pause": function() {

                $( this ).jPlayer( 'clearMedia' ); // Stop stream
                $( this ).jPlayer( "setMedia", s.channel.streams[ aio.quality ] ); // Re-create stream objects

                // Only show stopped if no network errors occurred
                if ( txt() !== "ERROR: Network error occurred!" )
                    txt( s.lang[ 'status-stopped' ], true );

            },

            "error": function( event ) {

                // If something goes wrong with stream it self!
                if ( event.jPlayer.status.networkState !== 1 ) {

                    // Stop player and set error text
                    obj.jPlayer( 'stop' );
                    obj.jPlayer( "setMedia", s.channel.streams[ aio.quality ] ); // Re-create stream objects
                    txt( "ERROR: Network error occurred!", true );

                    // Try again in 2 seconds
                    setTimeout( function() {

                        console.log( "Re-trying stream playback..." );
                        obj.jPlayer( 'play' );

                    }, 2000 );

                    // End here, don't go further down the chain
                    return false;

                }

                // Media is not set error
                if ( ready && event.jPlayer.error.type === $.jPlayer.error.URL_NOT_SET ) {

                    // Setup the media stream again and play it.
                    $( this ).jPlayer( "setMedia", s.channel.streams[ aio.quality ] );

                    // If not mobile device, play
                    if ( $.jPlayer.platform.mobile != true ) { $( this ).jPlayer( 'play' ); }

                } else if ( ready && event.jPlayer.error.type === $.jPlayer.error.URL ) {

                    txt( 'ERROR: Playback failed, loading stream failed!', true );

                } else {

                    aioFatalError( 'PLAYBACK ERROR', event.jPlayer.error.message );
                    return false;

                }

            },

            "volumechange": function( event ) {

                // Change main volume icons
                if ( event.jPlayer.options.muted ) {

                    txt( s.lang[ 'status-muted' ] );
                    $( '.volume-icon #volume' ).hide();
                    $( '.volume-icon #muted' ).show();

                } else {

                    txt( s.lang[ 'status-volume' ].replace( '{LEVEL}', Math.floor( event.jPlayer.options.volume * 100 ) + '%' ) );
                    $( '.volume-icon #muted' ).hide();
                    $( '.volume-icon #volume' ).show();

                }

                setCookie( 'volume', event.jPlayer.options.volume, 365 );

            }

        }
    );


    // Create the volume slider control
    $( '.volume-control' ).on( 'mousedown', function() {

        // Select specific element
        var parent = $( '.volume-slider .vol-progress' );

        // Disable selecting any text on body while moving mouse
        $( 'body' ).css( { '-ms-user-select': 'none', '-moz-user-select': 'none', '-webkit-user-select': 'none', 'user-select': 'none' } );

        // Bind mouse move event
        $( document ).on( 'mousemove', function( e ) {

            // Only work within the left/right limit
            if ( (e.pageX - $( parent ).offset().left) < 1 ) { return false; }

            // Set other settings/variables
            var total = $( '.volume-slider .vol-progress' ).width();
            obj.jPlayer( "option", "muted", false );
            obj.jPlayer( "option", "volume", (e.pageX - $( parent ).offset().left + 1) / total );
            aio.moving = true;

        } );

        // Unbind mouse move once we release mouse
        $( document ).on( 'mouseup', function() {

            // Allow selecting text after releasing drag & drop
            $( 'body' ).removeAttr( 'style' );

            // Unbind move events
            $( document ).unbind( 'mousemove' );

        } );

    } );


    // If Playlist is clicked, stop playback
    $( '.playlists > a' ).unbind( 'click' ).on( 'click', function() {
        if ( ready == true ) {

            // Clear media
            obj.jPlayer( 'clearMedia' );

            // Set text to stopped
            txt( s.lang[ 'status-stopped' ], true );

        }
    } );

    // Remove loading message when player starts playing
    obj.unbind( $.jPlayer.event.play );
    obj.unbind( $.jPlayer.event.playing );
    obj.bind( $.jPlayer.event.play, function( event ) { txt( s.lang[ 'status-init' ].replace( '{STREAM}', s.channel.name ), true ); } );
    obj.bind( $.jPlayer.event.playing, function( event ) { txt( s.lang[ 'status-playing' ].replace( '{STREAM}', s.channel.name ), true ); } );

}


/**
 *  This function handles all about artist, title and other track information
 */
function radioInfo() { // Ajax calls to get stream information

    // No channel yet? fine :@...
    if ( s.channel.name == null ) { return false; }

    // Setup AJAX request options
    var request = {
        url     : 'index.php?c=' + s.channel.name,
        async   : true,
        cache   : false,
        dataType: 'json',
        timeout : (parseInt( s.stats_refresh ) * 1000) - 1000 // Stats Refresh Speed -1000ms
    };

    // Call ajax
    $.ajax( request ).done( function( data ) {

        // Few checks to ensure empty data isn't displayed and that we use aio storage for artist/title
        if ( aio.onair == null ) aio.onair = {};
        if ( data.artist == null || data.title == null ) return false;
        if ( data.artist == aio.onair.artist && data.title == aio.onair.title && data.image == aio.onair.image ) return false;

        // Now we're done with checks, do DOM content changes etc...
        $( '.view.main .artist' ).html( '<a class="css-hint" data-title="' + data.artist + '" onclick="return false;" href="#">' + shorten( data.artist, s.artist_length ) + '</a>' ); 		// Change artist
        $( '.view.main .title' ).html( '<a class="css-hint" data-title="' + data.title + '" onclick="return false;" href="#">' + shorten( data.title, s.title_length ) + '</a>' );			// Change title

        // Load image with pre-loader
        $( '.artist-preload' ).show();
        $( '.artist-img img' ).attr( 'src', data.image ).one( 'load', function() { $( '.artist-preload' ).hide(); } );

        // If enabled we will also update window title on each song change
        if ( s.dynamic_title == 'true' ) {

            if ( aio.ptitle == null ) aio[ 'ptitle' ] = document.title;                     // store temp title if no-existing
            document.title = data.artist + ' - ' + data.title + ' | ' + aio[ 'ptitle' ];    // set window title

        }

        // Check what do we share with twitter, radio name + channel or artist/title
        if ( data.artist == s.default_artist && data.title == s.default_title ) {

            var twitter_title = '' + s.title + ' #' + s.channel.name + '';

        } else { // Use artist & title

            var twitter_title = '"' + data.artist + ' - ' + data.title + '"';

        }

        // Global variables for Twitter & Facebook (Share/Tweet URL's)
        var currentURL        = window.location.href.split( '#' )[ 0 ];
        aio[ 'facebook-url' ] = 'https://www.facebook.com/sharer/sharer.php?u=' + currentURL;
        aio[ 'twitter-url' ]  = 'https://twitter.com/share?url=' + currentURL + '&text=' + encodeURIComponent( s.lang[ 'twitter-share' ].replace( '{TRACK}', twitter_title ) );

        // Set ON AIR
        aio.onair = {
            'artist': data.artist,
            'title' : data.title,
            'image' : data.image,
            'time'  : new Date().getTime()
        };

        // Handle history
        if ( typeof data.history === 'undefined' || data.history.length < 1 ) {

            addHistory( aio.onair );

        } else {

            aio.history = data.history;
            updateHistory();

        }


        // Call timer
        onAirTimer();

        // Trigger track event aio.track
        $( document ).trigger( 'aio.track.change', aio.onair );

        // Disable checking if stats are disabled (this is a fix) - note: applies to single channel
        if ( data.status != null && data.status == 'disabled' ) clearInterval( aio.radioinfo ); // Stop refreshing

    } ).fail( function( jqxhr ) {

        $( '.artist-preload' ).hide();
        console.log( 'Loading artwork failed! Possible network error occurred!' );

    } );

}


/**
 * Timer function that will handle timer, simple and effective.
 */
function onAirTimer() {

    // Clear OLD interval (every second)
    clearInterval( aio.timer );

    // Don't attempt anything else if disabled
    if ( s.channel[ 'show-time' ] != true ) {
        $( '.onair .time' ).html( '00:00' ).hide();
        return false;
    }

    // Clear and Reset interval
    aio.timer = setInterval( function() {

        // Check if showing time is enabled, otherwise just exit
        if ( s.channel[ 'show-time' ] != true ) {

            clearInterval( aio.timer );
            $( '.onair .time' ).hide();
            return false;

        }

        if ( aio.onair == null || typeof (aio.onair.time) != 'number' ) return false;  // Exit if "start" time is empty

        // Set var for easier management
        var ctime = ((new Date().getTime() - aio.onair.time) / 1000);

        // Divide etc to show time with format
        var hour = Math.floor( (ctime / 3600) % 60 );
        var min  = Math.floor( (ctime / 60) % 60 );
        var sec  = Math.floor( ctime % 60 );
        var timer;

        // Display only active timer (1h 2min 3sec)
        if ( hour >= 1 ) { // hour:min:sec

            timer = (hour < 10 ? '0' : '') + hour + ':' + (min < 10 ? '0' : '') + min + ':' + (sec < 10 ? '0' : '') + sec;

        } else { // min:sec

            timer = (min < 10 ? '0' : '') + min + ':' + (sec < 10 ? '0' : '') + sec;

        }

        // Write play time into DOM content (player)
        $( '.onair .time' ).show().html( timer );

    }, 1000 );

}


/**
 *  SIMPLE function which means unrecoverable player error and makes it useless after error is shown
 */
function aioFatalError( title, message ) {

    $( '.preloader' ).removeClass( 'loadComplete' ).css( { 'visibility': 'visible', 'opacity': 1 } );
    $( '.preloader .text_area' ).html( '<span style="color: red;"><div style="font-weight: 500;">' + title + '</div> ' + message + '</span>' );
    return false;

}


/**
 *  Add track to history
 */
function addHistory( data ) {

    if ( typeof(aio.history) != 'object' ) { aio.history = []; } // Change aio.history to array
    if ( typeof(aio.history) == 'object' && aio.history.length > 19 ) { aio.history.pop(); } // Delete oldest record if total exceeds 20

    // Add this new record
    aio.history.unshift( data );
    updateHistory();

}


/**
 * Update whole history page
 */
function updateHistory() {

    // Create table for history
    var history_html = $( '<div class="table-scroll"><table><thead><tr><th class="history-artwork"></th><th class="artist-title">' + s.lang[ 'history-artist-title' ] + '</th>\
		<th class="timeago">' + s.lang[ 'history-added' ] + '</th></tr></thead><tbody></tbody></table></div>' );

    // Check aio.history object type
    if ( typeof(aio.history) !== 'undefined' ) {

        // Loop
        $.each( aio.history, function( key, val ) {

            // Handle time
            var $now = new Date( val.time );

            // Create rows
            $( history_html ).find( 'tbody' ).append( '<tr title="' + $now.getHours() + ':' + (($now.getMinutes() < 9) ? '0' + $now.getMinutes() : $now.getMinutes()) + '">\
			<td class="history-artwork"><img src="' + val.image + '" alt="image" width="30" height="30"></td>\
			<td>' + val.artist + ' - ' + val.title + '</td>\
			<td class="timeago">' + timeAgo( val.time ) + '</td></tr>' );

        } );

        // Display into DOM
        $( '.history-content' ).html( history_html );

    } else {

        $( '.history-content' ).html( '<div class="text-center"><br><br><br><br><b>No history available at this time.</b></div>' );

    }

    // Trigger history update
    $( document ).trigger( 'aio.history.change', aio.history );

}


/**
 * Switch page to history
 *
 * @param force_disable
 * @returns {boolean}
 */
function showHistory( force_disable ) {

    // Var & Disable doc click
    var elm = $( '.history-toggle' );

    // Handle show/hide actions, pretty simple...
    if ( $( elm ).attr( 'data-open' ) != null ) { // Hide history

        $( elm ).removeAttr( 'data-open' ).find( 'a' ).removeClass( 'active' );
        pagination( 'main' );

        // Show history
    } else if ( force_disable !== true ) {

        updateHistory();

        // Create button active, show history
        $( elm ).attr( 'data-open', 'true' ).find( 'a' ).addClass( 'active' );
        pagination( 'history' );

    }

    return false;

}


/**
 *  Simple function to take care of pages (multiple player pages (history, main, etc...)
 */
function pagination( page_class, animation, resize_event ) {

    // Before using this function, unbind resize event
    if ( resize_event !== true ) $( window ).unbind( 'resize' );

    // Some vars
    var pages       = $( '.main-container .view' ),
        page_width  = $( pages ).first().width(),
        total_pages = pages.length,
        page_number = 0;

    // For Loop to find proper page
    for ( var $i = 0; $i < total_pages; $i++ ) {

        // When class is found, store to variable and exit for loop
        if ( $( pages[ $i ] ).hasClass( page_class ) ) {

            page_number = $i;
            break;

        }

    }

    // Now calculate margin required to get to that page and move.
    if ( animation != null && animation === false ) {

        // Set transition to none
        $( pages[ 0 ] ).css( 'transition', 'none' );

        // After short delay add back animation
        setTimeout( function() { $( pages[ 0 ] ).css( 'transition', '' ); }, 300 );

    }

    // Finally move the element
    $( pages[ 0 ] ).css( 'margin-left', '-' + ((page_number != 0) ? page_width * page_number : 0) + 'px' );

    // Bind resize function to adjust page margin based on window size
    if ( resize_event !== true ) {

        $( window ).bind( 'resize', function() {

            // Basically calls this function again with exceptions: don't animate and don't bind resize again
            pagination( page_class, false, true );

        } );

    }

}


/**
 *  Simple function to use some jquery magic and animate status changes
 */
function txt( text, perment ) {

    var status = $( '.player-status' );

    // If no text set, return with current value
    if ( text === undefined ) return status.text();

    // Set previous text into data-name attribute
    if ( perment == true || typeof(aio[ 'txt-status' ]) == 'undefined' ) { aio[ 'txt-status' ] = text; }

    // Don't set new timeout of there is one already!
    if ( aio[ 'txtobj' ] != null ) { clearTimeout( aio[ 'txtobj' ] ); }

    // Set new text into the element
    status.html( text );
    console.log( text );

    // Trigger status change
    $( document ).trigger( 'aio.status.change', text );

    // Create Timer into window.object
    if ( perment == null ) { aio[ 'txtobj' ] = setTimeout( function() { status.hide().html( aio[ 'txt-status' ] ).fadeIn( 'slow' ); }, 2000 ); }

}


/**
 *  Shorten a string by specified length
 */
function shorten( $text, $length ) {

    // Skip if max length defined zero
    if ( $length == '0' ) return $text;

    // Do the magic
    var length = $length || 10;
    if ( $text.length > length ) {
        $text = $text.substring( 0, length ) + '&hellip;';
    }

    return $text;
}


/**
 *  SIMPLE function to parse how long ago something happened
 */
function timeAgo( timestamp ) {
    var seconds = Math.floor( (new Date().getTime() - timestamp) / 1000 );
    if ( Math.floor( seconds / 3600 ) >= 1 ) return Math.floor( seconds / 3600 ) + ' ' + s.lang[ 'history-hour-ago' ];
    if ( Math.floor( seconds / 60 ) >= 1 ) return Math.floor( seconds / 60 ) + ' ' + s.lang[ 'history-min-ago' ];
    if ( seconds == '0' ) return s.lang[ 'history-just-now' ];
    return Math.floor( seconds ) + ' ' + s.lang[ 'history-sec-ago' ];
}


/**
 *  COOKIE FUNCTIONS set,get,delete AND last test for touch device
 */
function setCookie( name, value, expires, path, domain, secure ) {

    if ( s.usecookies != 'true' ) { return null; } // Cookies not enabled!
    var today = new Date();
    today.setTime( today.getTime() );

    if ( expires ) {
        expires = expires * 1000 * 60 * 60 * 24;
    }

    var expires_date = new Date( today.getTime() + (expires) );

    document.cookie = name + '=' + encodeURI( value ) +
                      ((expires) ? ';expires=' + expires_date.toGMTString() : '') + //expires.toGMTString()
                      ((path) ? ';path=' + path : '') +
                      ((domain) ? ';domain=' + domain : '') +
                      ((secure) ? ';secure' : '');

}


/**
 * Get cookie from cookies list
 *
 * @param name
 * @returns {*}
 */
function getCookie( name ) { // Read cookie value

    if ( s.usecookies != 'true' ) { return null; } // Cookies not enabled!
    var start = document.cookie.indexOf( name + "=" );
    var len   = start + name.length + 1;

    if ( (!start) && (name != document.cookie.substring( 0, name.length )) ) {
        return null;
    }

    if ( start == -1 ) return null;

    var end = document.cookie.indexOf( ';', len );

    if ( end == -1 ) end = document.cookie.length;
    return decodeURI( document.cookie.substring( len, end ) );

}


/**
 * Delete cookie from cookies list
 *
 * @param name
 * @param path
 * @param domain
 * @returns {null}
 */
function deleteCookie( name, path, domain ) { // Delete cookie

    if ( s.usecookies != 'true' ) { return null; } // Cookies not enabled!
    if ( getCookie( name ) ) document.cookie = name + '=' +
                                               ((path) ? ';path=' + path : '') +
                                               ((domain) ? ';domain=' + domain : '') +
                                               ';expires=Thu, 01-Jan-1970 00:00:01 GMT';

}


/**
 * SIMPLE function which checks if the used device is touch enabled or not
 *
 * @returns {boolean}
 */
function isTouchDevice() {
    return typeof window.ontouchstart !== 'undefined';
}