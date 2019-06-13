<?php

    // No can do without index definition!
    if ( $inc !== true ) {
        header( "Location: index.php?s=home" );
        exit;
    }

    // Display message from session
    if ( !empty( $_SESSION[ 'msg' ] ) ) {

        echo $_SESSION[ 'msg' ];
        unset( $_SESSION[ 'msg' ] );

    }


    // Load channels file (used for all actions on this page)
    if ( is_file( "./../inc/conf/channels.php" ) ) include( "./../inc/conf/channels.php" );
    if ( !is_array( $channels ) ) $channels = array();

    // Not edit/add/del action, show table of channels
    if ( !isset( $_GET[ 'e' ] ) OR $_GET[ 'e' ] == 'delete' ) {

        // Delete channel, by key
        if ( $_GET[ 'e' ] == 'delete' ) {

            // Check if the channel with specified ID exists
            if ( !is_array( $channels[ $_GET[ 'id' ] ] ) ) {

                echo alert( 'Sorry but selected channel does not exist, so it was not removed.', 'error' );

            } else {

                // Delete channel
                unset( $channels[ $_GET[ 'id' ] ] );

                // Attempt to delete Logo of channel
                if ( is_file( $channels[ $_GET[ 'id' ] ][ 'logo' ] ) ) @unlink( $channels[ $_GET[ 'id' ] ][ 'logo' ] );

                // Delete channel and save changes
                if ( !file_put_contents( './../inc/conf/channels.php', '<?php $channels = ' . var_export( $channels, true ) . ';' ) ) { // Attempt to save

                    echo alert( 'Unable to delete channel, you may not have sufficient permissions!', 'error', true );

                } else {

                    echo alert( 'Channel was successfully deleted.', 'success' );

                    // Clear File Cache
                    clearstatcache( true );
                    if ( function_exists( 'opcache_invalidate' ) ) opcache_invalidate( './../inc/conf/channels.php', true );

                }

            }

        } else if ( isset( $_GET[ 'sort' ] ) && !empty( $_GET[ 'sort' ] ) ) { // Sort actions (asc, desc and custom)

            // Switch sorting mode
            switch ( $_GET[ 'sort' ] ) {

                case 'asc':
                    $mode = SORT_ASC;
                    break;

                case 'desc':
                    $mode = SORT_DESC;
                    break;

                default:
                    $mode = 'custom';
                    break;

            }

            // Custom submit is in order
            if ( isset( $_POST ) && !empty( $_POST[ 'ids' ] ) ) {

                // Loop through old list of channels and create new one based on sorting picks
                $new_list = array();
                foreach ( $_POST[ 'ids' ] as $new_key => $old_key ) {

                    if ( !isset( $channels[ $old_key ] ) ) $error = 'Unable to find channel with id <b>#' . $old_key . '</b>!';
                    $new_list[ $new_key ] = $channels[ $old_key ];

                }

                // If no error, clear mode/channels vars and create new channels var
                if ( !isset( $error ) ) {

                    unset( $mode, $channels );
                    $channels = $new_list;

                }
            }

            // Only work when not custom (allow asc/desc)
            if ( $mode != 'custom' && !isset( $error ) ) {

                if ( $_GET[ 'sort' ] == 'desc' OR $_GET[ 'sort' ] == 'asc' ) {

                    foreach ( $channels as $key => $row ): $ss_by[ $key ] = $row[ 'name' ]; endforeach; ## Find common key
                    array_multisort( $ss_by, $mode, $channels ); ## Sort


                }

                // Attempt to save new list of channels into the file and show what happen
                if ( !file_put_contents( './../inc/conf/channels.php', '<?php $channels = ' . var_export( $channels, true ) . ';' ) ) { // Attempt to save

                    echo alert( 'Failed to save the new channels order, you may not have sufficient permissions!', 'error', true );

                } else {

                    echo alert( 'New channels sorting has been successfully stored!', 'success' );

                    // Clear File Cache
                    clearstatcache( true );
                    if ( function_exists( 'opcache_invalidate' ) ) opcache_invalidate( './../inc/conf/channels.php', true );

                }

            } else if ( isset( $error ) ) {

                echo alert( $error, 'error' );

            }

        } else if ( isset( $_GET[ 'cache' ] ) && $_GET[ 'cache' ] == 'flush' ) {

            // Get list of caches (remove cache)
            $files = browse( './../tmp/cache/' );
            foreach ( $files as $file ): @unlink( './../tmp/cache/' . $file ); endforeach;
            echo alert( 'Successfully cleaned whole cache including artist images!', 'success' );

        }

        ?>

        <?php echo( ( isset( $mode ) && $mode == 'custom' ) ? alert( 'Drag & Drop table rows to change channels sorting.', 'info' ) : '' ); ?>
        <?php echo( ( isset( $mode ) && $mode == 'custom' ) ? '<form method="POST" action="?s=channels&sort=custom">' : '' ); ?>
        <?php if ( count( $channels ) <= 0 ): echo alert( 'You did not yet configure any channels, please do that first.' ); endif; ?>
        <div class="panel">
            <div class="content">
                <p>
                    AIO - Radio Station Player supports multi-channel configuration(s) but If a single channel is configured or a single stream, player will hide the unused buttons.
                    Other settings that affect all channels are covered in <b>Settings tab</b>.
                    <?php if ( isset( $_GET[ 'e' ] ) && $_GET[ 'e' ] != 'del' ) echo '<span class="text-red">Please read instructions carefully! Invalid configuration could cause player to stop working properly.</span>'; ?>
                </p>

                <?php if ( count( $channels ) >= 1 ): ?>
                    <table class="table hover">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th class="col-sm-3">Channel Name</th>
                            <th>Last Cache Entry</th>
                            <th>Info Type</th>
                            <th class="text-right">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                            // Loop through channels
                            foreach ( $channels as $key => $channel ) {

                                // Check for last cached entry
                                if ( is_file( "./../tmp/cache/stream.{$key}.info.cache" ) ) {

                                    $cached      = unserialize( file_get_contents( "./../tmp/cache/stream.{$key}.info.cache" ) );
                                    $cached_song = shorten( $cached[ 'artist' ] . ' - ' . $cached[ 'title' ], 60 );

                                } else {

                                    $cached_song = 'Unable to find or read cached data...';

                                }

                                echo '<tr><td>' . ( $key + 1 ) . ' <input type="hidden" name="ids[]" value="' . $key . '"></td>
							<td>' . $channel[ 'name' ] . '</td>
							<td><i>' . $cached_song . '</i></td>
							<td>' . ( ( !empty( $channel[ 'stats' ][ 'method' ] ) && $channel[ 'stats' ][ 'method' ] != 'disabled' ) ? ucfirst( $channel[ 'stats' ][ 'method' ] ) : 'Disabled' ) . '</td>
							<td class="text-right"><a class="btn btn-primary btn-small" href="?s=channels&e=' . $key . '"><i class="fa fa-edit"></i> Edit</a>
							<a class="btn btn-danger btn-small" onclick="return confirm(\'Are you sure?\');" href="?s=channels&e=delete&id=' . $key . '"><i class="fa fa-times"></i> Delete</a></td>			
						</tr>';


                            }


                        ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            </div>
        </div>

        <div class="dropdown pull-right">
            <button type="button" class="btn btn-warning dropdown-toggle"> Change Sorting &nbsp;<i class="fa fa-angle-down"></i></button>
            <ul class="dropdown-menu" role="menu">
                <li><a href="?s=channels&sort=asc"><i class="fa fa-chevron-up"></i> Sort Ascending</a></li>
                <li><a href="?s=channels&sort=desc"><i class="fa fa-chevron-down"></i> Sort Descending</a></li>
                <li><a href="?s=channels&sort=custom"><i class="fa fa-fire"></i> Custom</a></li>
            </ul>
        </div>

        <a onclick="return confirm('Are you sure?');" href="?s=channels&cache=flush" class="btn btn-danger pull-right" style="margin-right: 8px;">
            <i class="fa fa-trash"></i> Flush Cache
        </a>

        <?php if ( isset( $mode ) && $mode == 'custom' ) { ?>

            <a class="btn btn-success" onclick="$(this).closest('form').submit(); return false;" href="?s=channels"><i class="fa fa-save"></i>&nbsp; Save Changes</a>
            <a class="btn btn-danger" href="?s=channels"><i class="fa fa-times"></i> Cancel</a>

            </form>

            <script type="text/javascript" src="./../assets/js/html5.sortable.min.js"></script>
            <script type="text/javascript">
                $( '.table' ).rowSorter( /*options*/ );
                $( '.table' ).addClass( 'sortable-table' ).removeClass( 'hover' );
            </script>
            <?php
        } else { ?>

            <a class="btn btn-success" href="?s=channels&e=add"><i class="fa fa-plus"></i> Add Channel</a>

            <?php
        }

    } else {

        include 'channels.edit.php';

    }
