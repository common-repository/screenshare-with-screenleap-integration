(function($) {

    /**
     * Localize Var Object.
     *
     */
    var screenleapLocalizeVar = {};

    /**
     * Translations.
     *
     */
    const { __, _x, _n, _nx } = wp.i18n;

    /**
     * Loading Messages.
     *
     */
    var msgs = {
        step1: __( 'Fetching the meeting', 'screenshare-with-screenleap-integration' )
    };

    /**
     * Preload Container object.
     *
     */
    var preloadContainer;

    /**
     * Screen Share Iframe.
     */
    var iframeScreenShareObject;


	$(document).ready( function() {
        screenleapLocalizeVar = window.gpls_sli_wp_screenleap_integration_screenleap_localize_obj;
        preloadContainer      = $( '.' + screenleapLocalizeVar.prefix + '-screenshare-preloader-container' );

        // Load flipdown on ready.
        let remaining = $('#meeting-starting-count-down').data('remaining');
        if ( remaining ) {
            new FlipDown( parseInt( remaining ), 'meeting-starting-count-down' ).start();
        }

        // Join the meeting through IFrame.
        $( '.' + screenleapLocalizeVar.prefix + '-viewer-screen-request-iframe' ).on( 'click', function( e ) {
            e.preventDefault();
            $(this).remove();
            iframeScreenShareObject = $('#sli-viewer-meeting-iframe');
            iframeScreenShareObject.attr( 'src', iframeScreenShareObject.data('src' ) ).removeClass('d-none');
            setTimeout(
                function() {
                    $( 'html, body' ).animate(
                        {
                        scrollTop: iframeScreenShareObject.offset().top
                        },
                        500
                        );
                    },
                1000
            );
        });

        //Iframe Callbacks.
        window.addEventListener( 'message', function( e ) {
            if ( e.data == 'SCREEN_SHARE_ENDED' ) {
                setTimeout(
                    function() {
                        iframeScreenShareObject.addClass('d-none');
                    },
                    5000
                )
            }
        });

        $( '.' + screenleapLocalizeVar.prefix + '-reminder-email-form' ).on( 'submit', function( event ) {
            event.preventDefault();
            let meetingID = $( '.' + screenleapLocalizeVar.prefix + '-reminder-meeting' ).val();
            let email     = $( '.' + screenleapLocalizeVar.prefix + '-reminder-email' ).val();
            submitReminderEmail( meetingID, email );
        });
    });

    /**
     * Get Meeting Viewer URL.
     *
     */
    function getViewerURL( buttonObject ) {
        let meetingID = buttonObject.data('meeting-id');
        $.ajax({
            method: 'POST',
            url: screenleapLocalizeVar.ajax_url,
            data: {
                action: screenleapLocalizeVar.prefix + '-viewer_get_url',
                nonce: screenleapLocalizeVar.screenleap_viewer_nonce,
                meeting_id: meetingID
            },
            success: function( resp ) {
                // Initialize the screenshare.
                preStartSharing( resp['data']['result'] );
            },
            error: function( err ) {
            }
        });
    }


    /**
     * Submit Reminder Email to save.
     * @param {int} meetingID Meeting Post ID.
     * @param {string} email User Email.
     */
    function submitReminderEmail( meetingID, email ) {
        $.ajax({
            method: 'POST',
            url: screenleapLocalizeVar.ajax_url,
            data: {
                action: screenleapLocalizeVar.prefix + '-submit_reminder_email',
                nonce: screenleapLocalizeVar.screenleap_viewer_nonce,
                meeting_id: meetingID,
                email: email
            },
            complete: function() {
                $( '.reminder-subscription-box' ).fadeOut(
                    'slow',
                    function() {
                        $(this).remove();
                    }
                );
            }
        });
    }

    /**
     * Hide Loading Screen.
     *
     */
    function hideLoadingScreen() {
        preloadContainer.css( 'display', 'none' );
    }

    /**
     *
     * @param {string} title Swal Alert Title.
     * @param {string} text  Swal Alert Text.
     * @param {string} icon  Swal Alert icon.
     */
    function fireAlert( title, text, icon ) {
        Swal.fire({
            title: title,
            text: text,
            icon: icon
        });
    }
})(jQuery);
