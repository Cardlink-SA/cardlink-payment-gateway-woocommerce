( function ( $ ) {
    var prefix = '#woocommerce_cardlink_payment_gateway_woocommerce_iris_';

    function toggleIrisCredentials() {
        var val = $( prefix + 'iris_acquirer' ).val();
        var $rows = $( prefix + 'iris_merchant_id, ' + prefix + 'iris_shared_secret_key' ).closest( 'tr' );

        if ( val === 'inherit' ) {
            $rows.hide();
        } else {
            $rows.show();
        }

        clearFieldErrors();
    }

    function clearFieldErrors() {
        $( prefix + 'iris_merchant_id, ' + prefix + 'iris_shared_secret_key' )
            .removeClass( 'iris-field-error' )
            .closest( 'tr' ).find( '.iris-error-message' ).remove();
    }

    function showFieldError( $input, message ) {
        if ( $input.siblings( '.iris-error-message' ).length === 0 ) {
            $input.addClass( 'iris-field-error' )
                .after( '<span class="iris-error-message" style="color:#d63638;display:block;margin-top:4px;font-weight:600;font-size:11px;">' + message + '</span>' );
        }
    }

    function validateCredentials() {
        var acquirer = $( prefix + 'iris_acquirer' ).val();

        if ( acquirer === 'inherit' ) {
            return true;
        }

        var $mid    = $( prefix + 'iris_merchant_id' );
        var $secret = $( prefix + 'iris_shared_secret_key' );
        var valid   = true;

        clearFieldErrors();

        if ( ! $mid.val().trim() ) {
            showFieldError( $mid, irisAdminSettings.midRequired );
            valid = false;
        }

        if ( ! $secret.val().trim() ) {
            showFieldError( $secret, irisAdminSettings.secretRequired );
            valid = false;
        }

        if ( ! valid ) {
            $( prefix + 'iris_merchant_id' ).closest( 'tr' )[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
        }

        return valid;
    }

    $( document ).ready( function () {
        toggleIrisCredentials();
        $( prefix + 'iris_acquirer' ).on( 'change', toggleIrisCredentials );

        // Hook into the WooCommerce settings form submit.
        $( '#mainform' ).on( 'submit', function ( e ) {
            if ( ! validateCredentials() ) {
                e.preventDefault();
            }
        } );
    } );
} )( jQuery );
