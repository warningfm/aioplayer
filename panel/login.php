<style>
    body {
        background: #63b7fa;
        background-image: radial-gradient(ellipse, #63b7fa 0, #3583ca 100%);
    }

    /* Move login to center of screen */
    .login-form {
        position: absolute;
        left: 50%;
        top: 25%;
        margin-left: -200px;
        width: 400px;
    }

    @media (max-width: 768px) {
        .login-form { top: 5% !important; }
    }

    .login-container {
        background: #fff;
        overflow: hidden;
        border-radius: 3px;
        border: 1px solid transparent;
        box-shadow: 0 2px 1px rgba(0, 0, 0, .1);
        margin-bottom: 10px;
    }

    .login-content {
        padding: 10px 25px 15px;
    }

    .login-header {
        color: #464646;
        margin: 30px 0 25px;
    }

    .login-header h2 {
        text-align: center;
        margin: 0;
        padding: 0;
        font-size: 20px;
    }

    .form-control {
        background: #f1f1f1;
        border-color: #f1f1f1;
        box-shadow: none;
        -webkit-box-shadow: 0 0 0 1000px #f1f1f1 inset;
    }

    .form-group { margin: 0 0 15px; }

    .form-control:focus { border-color: #f0f0f0; }

    .version-info { color: #fff; margin-top: 15px; text-shadow: 0 1px 0 rgba(0, 0, 0, .2); }

    .divider { margin: 15px 0 0; border: 0; content: ""; }

    .login-form-onload { animation: loginate 500ms ease 1; }

    @keyframes loginate {
        from { opacity: 0.6; top: 23%; }
        to { opacity: 1; top: 25%; }
    }
</style>

<section class="col-sm-6 login-form">

    <form class="form-horizontal" method="POST" action="<?php echo $_SERVER[ 'REQUEST_URI' ]; ?>">
        <div class="login-container">

            <div class="login-header">
                <h2><i class="fa fa-cogs"></i> Control Panel</h2>
            </div>

            <div class="login-content">
                <?php

                    // Redirect signed users
                    if ( $_SESSION[ 'a-login' ] === true ) {
                        header( "Location: ?s=home" );
                        exit;
                    }

                    // Handle login post
                    if ( isset( $_POST[ 'submit' ] ) ) {

                        include './../inc/lib/cache.class.php';
                        $cache     = new cache( array( 'path' => './../tmp/cache/' ) );
                        $auth_list = $cache->get( 'auth' );
                        $attempts  = ( ( isset( $auth_list[ $_SERVER[ 'REMOTE_ADDR' ] ] ) ) ? $auth_list[ $_SERVER[ 'REMOTE_ADDR' ] ] : 0 );

                        // Anti-spam or brute force
                        if ( $attempts >= 5 ) {

                            writeLog( 'auth.bans', "User with IP \"{$_SERVER['REMOTE_ADDR']}\" has failed to authorize for more than 3 times!", './../tmp/logs/' );
                            echo '<div class="text-red">Too many invalid login attempts, please try again in approximately 30 minutes!</div><div class="divider"></div>';

                        } else if ( $_POST[ 'username' ] != $settings[ 'admin_user' ] OR hash( SHA512, $_POST[ 'password' ] ) != $settings[ 'admin_pass' ] ) {

                            echo '<div class="text-red">Invalid username or password, login failed!</div><div class="divider"></div>';

                            // Set attempts and store them (save this ip)
                            $auth_list[ $_SERVER[ 'REMOTE_ADDR' ] ] = $attempts + 1;
                            $cache->set( 'auth', $auth_list, 1800 );

                        } else { // Login

                            // SESSION AUTH
                            $_SESSION[ 'a-login' ] = true;

                            // Set attempts and store them (clear this IP)
                            $auth_list[ $_SERVER[ 'REMOTE_ADDR' ] ] = 0;
                            $cache->set( 'auth', $auth_list, 1800 );

                            // Redirect
                            header( "Location: ?s=home" );
                            exit;

                        }

                        // QUIT/SAVE cache
                        $cache->quit();

                    }

                ?>

                <div class="form-group">
                    <div class="input-prepend">
                        <div class="prepend"><i class="fa fa-user"></i></div>
                        <input type="text" name="username" class="form-control" placeholder="Username" id="username" autofocus required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-prepend">
                        <div class="prepend"><i class="fa fa-key"></i></div>
                        <input type="password" name="password" class="form-control" placeholder="Password" id="password" autocomplete="off" required>
                    </div>
                </div>

                <div class="divider"></div>
                <a title="How do I reset password?" target="_blank" href="http://doc.prahec.com/aio-radio#forgot-password">Forgot password?</a>
                <button type="submit" name="submit" value="sign-in" class="btn btn-success pull-right">Sign in <i class="fa fa-sign-in"></i></button>
                <div class="clearfix"></div>

            </div>
        </div>

        <div class="text-center version-info">Version: <b><?php echo( ( is_file( 'version.txt' ) ) ? file_get_contents( 'version.txt' ) : 'n/a' ); ?></b></div>

    </form>
</section>
<script type="text/javascript">
    $( window ).load( function() {
        if ( $( window ).width() >= 768 ) {
            $( '.login-form' ).addClass( 'login-form-onload' );
        }
    } );
</script>