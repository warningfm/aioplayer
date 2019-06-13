<?php

    /*************************************************************************************************************
     * Form Deployment Helper Class (Bootstrap compatible)
     * @author     Jaka Prasnikar - https://prahec.com/
     * @version    2.0 (21.08.2015)
     ************************************************************************************************************ */
    class form {

        // Declare global class vars
        public $html;


        /* Initiate object (currently unused)
        =========================================================================================== */
        function __construct() { }


        /* Clear output (helper)
        =========================================================================================== */
        function clear() {

            $this->html = '';

        }


        /* Add input object
        =========================================================================================== */
        function add( $option = array() ) {

            // Defaults
            $o = array_merge( array( 'type' => 'text', 'class' => 'col-sm-6' ), $option );

            // Open group, label etc...
            if ( !empty( $o[ 'label' ] ) ) {

                $output = '<div class="form-group">';
                $output .= '<label ' . ( ( isset( $o[ 'label-left' ] ) ) ? 'style="width: auto !important;"' : '' ) . 'class="col-sm-2 control-label"' .
                           ( ( !empty( $o[ 'name' ] ) ) ? 'for="' . $o[ 'name' ] . '"' : '' ) . '>' . $o[ 'label' ] . '</label>';

            }

            if ( isset( $o[ 'multi' ] ) ) { // Multi-fields in single row

                // Loop through multi
                foreach ( $o[ 'multi' ] as $fields ) {
                    $this->add( $fields );
                }

            }


            // Handle non-standard input extras like size, placeholder, etc...
            $extras = array();

            if ( isset( $o[ 'size' ] ) ) {
                $extras[] = 'maxlength="' . $o[ 'size' ] . '"';
            }
            if ( isset( $o[ 'placeholder' ] ) ) {
                $extras[] = 'placeholder="' . $o[ 'placeholder' ] . '"';
            }
            if ( isset( $o[ 'required' ] ) ) {
                $extras[] = 'required="' . $o[ 'required' ] . '"';
            }
            if ( isset( $o[ 'reset' ] ) ) {
                $extras[] = 'allowreset="true"';
            }

            // Now do the magic
            switch ( $o[ 'type' ] ) {

                // Handle normal text inputs
                case 'text':
                    $output .= '<div class="' . $o[ 'class' ] . '">' .
                               '<input type="text" name="' . $o[ 'name' ] . '" class="form-control" id="' . $o[ 'name' ] . '" value="' . $_POST[ $o[ 'name' ] ] . '"' . join( ' ', $extras ) . '></div>';
                    break;

                // Handle numberic inputs (HTML5+ thing)
                case 'number':
                    $output .= '<div class="' . $o[ 'class' ] . '">' .
                               '<input type="number" name="' . $o[ 'name' ] . '" step="1" min="' . ( ( !isset( $o[ 'min' ] ) ) ? 0 : $o[ 'min' ] ) . '" max="' . $o[ 'max' ] . '" ' .
                               'class="form-control" id="' . $o[ 'name' ] . '" value="' . $_POST[ $o[ 'name' ] ] . '"' . join( ' ', $extras ) . '></div>';
                    break;

                // Handle password inputs
                case 'password':
                    $output .= '<div class="' . $o[ 'class' ] . '">' .
                               '<input type="password" name="' . $o[ 'name' ] . '" class="form-control" id="' . $o[ 'name' ] . '" value="' . $_POST[ $o[ 'name' ] ] . '" autocomplete="off"' . join( ' ', $extras ) . '></div>';
                    break;

                // Handle selections
                case 'select':
                    $output .= '<div class="' . $o[ 'class' ] . '">' .
                               '<select name="' . $o[ 'name' ] . '" class="form-control" id="' . $o[ 'name' ] . '"' . join( ' ', $extras ) . '>';

                    // Loop options
                    if ( is_array( $o[ 'options' ] ) ) {
                        foreach ( $o[ 'options' ] as $option => $value ) {
                            $output .= '<option' . ( ( $_POST[ $o[ 'name' ] ] == $option ) ? ' value="' . $option . '" selected' : ' value="' . $option . '"' ) . '>' . $value . '</option>';
                        }
                    }

                    $output .= '</select></div>';
                    break;

                // Handle text area
                case 'textarea':
                    $output .= '<div class="' . $o[ 'class' ] . '">' .
                               '<textarea style="min-height: ' . $o[ 'height' ] . 'px;" name="' . $o[ 'name' ] . '" class="form-control" id="' . $o[ 'name' ] . '"' . join( ' ', $extras ) . '>' . $_POST[ $o[ 'name' ] ] . '</textarea></div>';
                    break;

                // Handle checkboxes
                case 'checkbox':
                    $output .= '<div class="' . $o[ 'class' ] . '">' .
                               '<div class="checkbox"><label><input type="checkbox" value="' . $o[ 'value' ] . '" name="' . $o[ 'name' ] . '" id="' . $o[ 'name' ] . '"' . ( ( $_POST[ $o[ 'name' ] ] == $o[ 'value' ] ) ? ' checked' : '' ) . '' . join( ' ', $extras ) . '>' .
                               '<span class="fa fa-check"></span> ' . $o[ 'description' ] . '</label></div></div>';
                    break;

                // Handle radio inputs
                case 'radio':
                    $output .= '<div class="' . $o[ 'class' ] . '">' .
                               '<div class="radio"><label><input type="radio" value="' . $o[ 'value' ] . '" name="' . $o[ 'name' ] . '" id="' . $o[ 'name' ] . '"' . ( ( $_POST[ $o[ 'name' ] ] == $o[ 'value' ] ) ? ' checked' : '' ) . '' . join( ' ', $extras ) . '>' .
                               '<span class="fa fa-check"></span> &nbsp; ' . $o[ 'description' ] . '</label></div></div>';
                    break;


                // Handle file inputs
                case 'file':
                    $output .= '<div class="file-input"><input type="file" id="' . $o[ 'name' ] . '" name="' . $o[ 'name' ] . '">
					<div class="input-group"><input type="text" class="form-control file-name ' . $o[ 'class' ] . '"' . join( ' ', $extras ) . '>
					<div class="input-group-btn"><a href="#" class="btn btn-danger"><i class="fa fa-folder-open fa-fw"></i> Browse</a></div></div></div>';
                    break;

                // Handle static inputs (text)
                case 'static':
                    $output .= '<div class="' . $o[ 'class' ] . '"><p class="form-control-static">' . $o[ 'value' ] . '</p></div>';
                    break;


                default:
                    break;

            }


            // Last things to do
            if ( !empty( $o[ 'description' ] ) AND $o[ 'type' ] != 'checkbox' AND $o[ 'type' ] != 'radio' ) {
                $output .= '<div class="help-block">' . $o[ 'description' ] . '</div>';
            }
            if ( !empty( $o[ 'label' ] ) ) {
                $output .= '</div>';
            }

            // Add HTML to output (RAM)
            $this->html .= $output;
            return $output;

        }


    }

?>