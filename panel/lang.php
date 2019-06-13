<?php

    // No can do without index definition!
    if ( $inc !== true ) {
        header( "Location: index.php?s=status" );
        exit;
    }

    // Display message from session
    if ( !empty( $_SESSION[ 'msg' ] ) ) {
        echo $_SESSION[ 'msg' ];
        unset( $_SESSION[ 'msg' ] );
    }

    if ( isset( $_GET[ 'edit' ] ) || isset( $_GET[ 'add' ] ) )
        echo '<form action="?s=lang&' . ( ( isset( $_GET[ 'add' ] ) ) ? 'add' : 'edit=' . $_GET[ 'edit' ] ) . '" method="POST" accept-charset="UTF-8">';

?>
<div class="panel">
    <div class="content">
        <p>
            This player supports multi-language setup which means that (if enabled) player will automatically choose a language fit for the user's browser setting.<br>
            You can also disable multi-language support under <b>Settings</b> tab.
        </p>

        <?php

            // Load list of languages
            include 'lang.list.php';

            // Display languages table
            if ( !isset( $_GET[ 'add' ] ) AND empty( $_GET[ 'edit' ] ) ) {

                // File delete handler
                if ( isset( $_GET[ 'del' ] ) ) {

                    $_GET[ 'del' ] = preg_replace( '![^a-z0-9]!i', '', $_GET[ 'del' ] ); ## Replace all but characters and numbers
                    if ( is_file( './../inc/lang/' . $_GET[ 'del' ] . '.php' ) && unlink( './../inc/lang/' . $_GET[ 'del' ] . '.php' ) === true ) {

                        echo alert( 'Successfully deleted the ' . $language[ $_GET[ 'del' ] ] . ' translation!', 'success' );

                    } else {

                        echo alert( 'Failed to delete specified language file, you may not have sufficient permissions!', 'error' );

                    }

                }


                // Read language directory for all files
                $files = browse( './../inc/lang/' );

                // If less then one result
                if ( count( $files ) < 1 ) {

                    echo alert( 'No translation files found! If you deleted "en.php" by mistake, please re-upload it!' );

                } else { // Else

                    ?>

                    <table class="table hover">
                        <thead>
                        <tr>
                            <th>Language</th>
                            <th>Actions</th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php

                            foreach ( $files as $file ) {

                                echo '<tr><td class="col-sm-9"><b>' . $language[ ext_del( $file ) ] . '</b> (' . strtoupper( ext_del( $file ) ) . ')</td>' .
                                     '<td><a class="btn btn-primary btn-small" href="?s=lang&edit=' . ext_del( $file ) . '"><i class="fa fa-edit"></i> Edit</a> ' . ( ( $file != 'en.php' ) ?
                                        '<a class="btn btn-danger btn-small" onclick="return confirm(\'Are you sure?\');" href="?s=lang&del=' . ext_del( $file ) . '"><i class="fa fa-times"></i> Delete</a>' : '' ) . '</td></tr>';

                            }
                        ?>

                        </tbody>
                    </table>

                    <?php

                }

            } else {


                // Remove empty spaces before the value
                $_POST = array_map( 'trim', $_POST );

                // Handle submission
                if ( isset( $_POST[ 'submit' ] ) ) {

                    $file = preg_replace( '![^a-z0-9]!i', '', ( ( empty( $_POST[ 'isocode' ] ) ) ? $_GET[ 'edit' ] : $_POST[ 'isocode' ] ) );
                    unset( $_POST[ 'isocode' ], $_POST[ 'submit' ] );

                    if ( empty( $file ) OR !isset( $language[ $file ] ) ) {

                        echo alert( 'Invalid ISO code or file name, please cancel and try again.', 'error' );

                    } else {

                        // Try to save
                        if ( file_put_contents( './../inc/lang/' . $file . '.php', '<?php $lang=' . var_export( $_POST, true ) . '; ?>' ) ) {

                            $_SESSION[ 'msg' ] = alert( $language[ $file ] . ' translation has been ' . ( ( isset( $_GET[ 'edit' ] ) ) ? 'updated' : 'added' ) . ' successfully!', 'success' );
                            header( 'Location: ?s=lang' );
                            exit;

                        } else {

                            echo alert( 'Failed saving translation because you may not have sufficient permissions!', 'error', true );

                        }

                    }

                }

                // Load existing file
                if ( isset( $_GET[ 'edit' ] ) && !isset( $_POST[ 'submit' ] ) ) {

                    $_GET[ 'edit' ] = preg_replace( '![^a-z0-9]!i', '', $_GET[ 'edit' ] ); ## Replace all but characters and numbers
                    if ( is_file( './../inc/lang/' . $_GET[ 'edit' ] . '.php' ) ) {

                        include './../inc/lang/' . $_GET[ 'edit' ] . '.php';
                        $_POST = $lang;

                    } else {

                        header( "Location: ?s=lang" );

                    }

                }

                $f = new form();
                if ( isset( $_GET[ 'add' ] ) ) { // NEW

                    echo $f->add( array( 'label' => 'Language', 'name' => 'isocode', 'class' => 'col-sm-4', 'type' => 'select', 'options' => $language ) );

                } else { // EDIT

                    echo $f->add( array( 'label' => 'Language', 'type' => 'static', 'value' => '<b>' . $language[ $_GET[ 'edit' ] ] . '</b> (' . strtoupper( $_GET[ 'edit' ] ) . ')' ) );

                }

                echo $f->add( array( 'label' => 'Loading Message', 'placeholder' => 'Loading, please wait...', 'name' => 'loading-message', 'reset' => true ) );

                echo '<div class="divider"></div>';
                echo $f->add( array( 'label' => 'Settings', 'placeholder' => 'Select stream quality', 'name' => 'ui-settings', 'reset' => true ) );
                echo $f->add( array( 'label' => 'Channels List', 'placeholder' => 'Channels list', 'name' => 'ui-channels', 'reset' => true ) );
                echo $f->add( array( 'label' => 'Play Button', 'placeholder' => 'Start playing', 'name' => 'ui-play', 'reset' => true ) );
                echo $f->add( array( 'label' => 'Stop Button', 'placeholder' => 'Stop playing', 'name' => 'ui-stop', 'reset' => true ) );
                echo $f->add( array( 'label' => 'Volume Circle', 'placeholder' => 'Drag to change volume', 'name' => 'ui-volume-circle', 'reset' => true ) );
                echo $f->add( array( 'label' => 'Playlists Text', 'placeholder' => 'Listen in your favourite player', 'name' => 'ui-playlists', 'reset' => true ) );

                echo '<div class="divider"></div>';
                echo $f->add( array( 'label' => 'Status: Loading', 'placeholder' => 'Loading {STREAM}...', 'name' => 'status-init', 'reset' => true, 'class' => 'col-sm-4', 'description' => '({STREAM} will be replaced by current channel name)' ) );
                echo $f->add( array( 'label' => 'Status: Playing', 'placeholder' => 'Playing {STREAM}...', 'name' => 'status-playing', 'reset' => true, 'class' => 'col-sm-4', 'description' => '({STREAM} will be replaced by current channel name)' ) );
                echo $f->add( array( 'label' => 'Status: Stopped', 'placeholder' => 'Player stopped.', 'name' => 'status-stopped', 'reset' => true, 'class' => 'col-sm-4' ) );
                echo $f->add( array( 'label' => 'Status: Volume', 'placeholder' => 'Volume: {LEVEL}', 'name' => 'status-volume', 'reset' => true, 'class' => 'col-sm-4', 'description' => '({LEVEL} will be replaced by current volume level)' ) );
                echo $f->add( array( 'label' => 'Status: Muted', 'placeholder' => 'Player muted.', 'name' => 'status-muted', 'reset' => true, 'class' => 'col-sm-4' ) );

                echo '<div class="divider"></div>';
                echo $f->add( array( 'label' => 'Show History', 'placeholder' => 'Show Track History', 'name' => 'ui-history', 'reset' => true, 'class' => 'col-sm-4' ) );
                echo $f->add( array( 'label' => 'Artist/Title', 'placeholder' => 'Artist/Title', 'name' => 'history-artist-title', 'reset' => true, 'class' => 'col-sm-4' ) );
                echo $f->add( array( 'label' => 'Added', 'placeholder' => 'Added', 'name' => 'history-added', 'reset' => true, 'class' => 'col-sm-4' ) );
                echo $f->add( array( 'label' => 'Hour(s) ago', 'placeholder' => 'hr ago', 'name' => 'history-hour-ago', 'reset' => true, 'class' => 'col-sm-4' ) );
                echo $f->add( array( 'label' => 'Minute(s) ago', 'placeholder' => 'min ago', 'name' => 'history-min-ago', 'reset' => true, 'class' => 'col-sm-4' ) );
                echo $f->add( array( 'label' => 'Second(s) ago', 'placeholder' => 'sec ago', 'name' => 'history-sec-ago', 'reset' => true, 'class' => 'col-sm-4' ) );
                echo $f->add( array( 'label' => 'Just now', 'placeholder' => 'just now', 'name' => 'history-just-now', 'reset' => true, 'class' => 'col-sm-4' ) );

                echo '<div class="divider"></div>';
                echo $f->add( array( 'label' => 'Share', 'placeholder' => 'Share', 'name' => 'share', 'reset' => true, 'class' => 'col-sm-4' ) );
                echo $f->add( array( 'label' => 'Twitter Post', 'placeholder' => 'I am listening to {TRACK}!', 'name' => 'twitter-share', 'reset' => true, 'class' => 'col-sm-6', 'description' => '({TRACK} will be replaced by current playing track)' ) );


            }

        ?>

    </div>
</div>

<?php if ( !isset( $_GET[ 'add' ] ) && !isset( $_GET[ 'edit' ] ) ): ?>
    <a href="?s=lang&add" class="btn btn-success"><i class="fa fa-plus-circle"></i> Add Language</a>
<?php else: ?>
    <div class="row">
        <div class="col-sm-12">
            <button type="submit" value="save" name="submit" class="btn btn-success"><i class="fa fa-pencil fa-fw"></i> Save</button>
            <a href="?s=lang" class="btn btn-danger"><i class="fa fa-times fa-fw"></i> Cancel</a>
        </div>
    </div>
<?php endif; ?>
</form>