(function($) {

    /**
     * Localize Var Object.
     *
     */
    var screenleapLocalizeVar = {};

    /**
     * Loading Messages.
     *
     */
    var msgs = {};

    /**
     * Translations.
     *
     */
    const { __, _x, _n, _nx } = wp.i18n;

    /**
     * Preload Container object.
     *
     */
    var preloadContainer;

    /**
     * Scree Share Details Box.
     */
    var screenShareDetailsBox;

    /**
     * Pre Creating Screen Share Check.
     */
    var preCreatedShareData = null;

    /**
     * Is Started Sharing.
     *
     */
    var shareScreenStarted = false;

    /**
     * Presenter App Type.
     *
     */
    var presenterAppType = "NATIVE";

    /**
     * Screenshare presenter Request Button Object.
     *
     */
    var screenshareStartButton;

    /**
     * Resume Share Button.
     *
     */
    var screenshareResumeButton;

    /**
     * Pause Share Button.
     *
     */
    var screensharePauseButton;

    /**
     * Stop Share Button.
     *
     */
    var screenshareStopButton;

    /**
     * Is Recording enabled.
     *
     */
    var recordingEnabled = false;

    /**
     * Record on Start.
     *
     */
    var recordingOnStart = false;

    /**
     * Screen Share Data.
     *
     */
    var screenShareData;

    /**
     * Screen Share Code.
     */
    var screenShareCode = '';

    /**
     * Chrome Extension ID.
     */
    var extensionChromeAppID = 'ilikegbphpdmfjbmipgclmbhloghljag';

    /**
     * While Loading Screen Share Code.
     */
    var loadingScreenShareCode = '';

    /**
     * Is Extension Installed.
     *
     */
    var extensionInstalled = false;

    /**
     * Is Extension Enabled.
     *
     */
    var etensionEnabled = false;

    /**
     * Is Share Using Native.
     *
     */
    var shareUsingNative = true;

    /**
     * Viewer List.
     */
    var viewerList = [];

    var participantIDTracker = [];


	$(document).ready( function() {
        screenleapLocalizeVar = window.gpls_sli_wp_screenleap_integration_screenleap_localize_obj;
        msgs                  = {
            step1: {
                start: __( 'Sending a screen share request', 'screenshare-with-screenleap-integration' ) + ' ...',
                error: __( 'Failed to get a screen share request', 'screenshare-with-screenleap-integration' )
            },
            step2: {
                start: __( 'Checking the extension...', 'screenshare-with-screenleap-integration' ),
                error: __( 'Failed to start the screen share', 'screenshare-with-screenleap-integration' )
            }
        }

        preloadContainer              = $( '.' + screenleapLocalizeVar.prefix + '-screenshare-preloader-container' );
        screenShareDetailsBox         = $( '.' + screenleapLocalizeVar.prefix + '-screenshare-details-container' );
        screenshareStartButton        = $( '.' + screenleapLocalizeVar.prefix + '-screenshare-request' );
        screenshareResumeButton       = $( '.' + screenleapLocalizeVar.prefix + '-resume-screenshare' );
        screensharePauseButton        = $( '.' + screenleapLocalizeVar.prefix + '-pause-screenshare' );
        screenshareStopButton         = $( '.' + screenleapLocalizeVar.prefix + '-stop-screenshare' );

        // Toggle Metaboxes.
        toggleScreenShareMetaBoxes();

        // Set the presenter App Type
        recordingEnabled = $( '#' + screenleapLocalizeVar.prefix + '-meeting-configuration-enable-recording' ).is(':checked');
        recordingOnStart = $( '#' + screenleapLocalizeVar.prefix + '-meeting-configuration-auto-record' ).is(':checked');

        if ( window.screenleap === undefined ) {
            fireAlert( 'Error!', __( 'Failed to load screenleap files, check your connection and refresh the page!' ), 'error' );
            return;
        }

        let isSSlChecked = $('.is-using-ssl-input').is(':checked');
        if ( ! isSSlChecked ) {
            let iframeSSLNoticeExists = $('.iframe-ssl-required-notice');
            if ( iframeSSLNoticeExists.length ) {
                $('.view-method-iframe').attr('disabled', 'disabled' );
                $('.view-method-input').val( 'redirect' );
                iframeSSLNoticeExists.removeClass('d-none');
            }
        }

        init_instructions();

        initScreenleapEvents();

                                    // ======================= EVENTS ========================= //

        // Restart Download screenleap.
        $( '.' + screenleapLocalizeVar.prefix + '-restart-app-download').on( 'click', function() {
            screenleap.forceDownloadAndStartNativeApp();
        });

        // Start the App using Custom Protocol Handler.
        $( '.' + screenleapLocalizeVar.prefix + '-start-app').on( 'click', function() {
            screenleap.startAppUsingCustomProtocolHandler()
        });

        // ==== Start Screen Share Request ==== //
        screenshareStartButton.on( 'click', function( event ) {
            event.preventDefault();
            $('.reset-status').remove();
            if ( ! screenleap.isAppInstalled() ) {
                ToggleModal( '#nativeInstallationInstructions', 'show' );
            } else {
                startScreenShareButton();
            }
        });

        // Check the using_ssl option and determine if iframe option is doable.
        $('.is-using-ssl-input').on( 'change', function() {
            let isChecked             = $(this).is(':checked');
            let iframeSSLNoticeExists = $('.iframe-ssl-required-notice');
            if ( isChecked ) {
                $('.view-method-iframe').attr('disabled', false );
                iframeSSLNoticeExists.addClass('d-none');
            } else {
                if ( iframeSSLNoticeExists.length ) {
                    $('.view-method-iframe').attr('disabled', 'disabled' );
                    $('.view-method-input').val( 'redirect' );
                    iframeSSLNoticeExists.removeClass('d-none');
                }
            }
        });

        // Detect change in meeting configuration inputs //
        $('.meeting-viewer-conf-input, .meeting-conf-input').on( 'change', function() {
            if ( ! shareScreenStarted ) {
                $('.conf-changes-detected').removeClass('d-none');
            }
        });

        // Toggle Configurations based on meeting type select.
        $( '.' + screenleapLocalizeVar.prefix + '-meeting-type' ).on( 'change', function() {
            toggleScreenShareMetaBoxes();
        });

        // Save Meeting Configuration //
        $('.save-meeting-conf').on( 'click', function( event ) {
            event.preventDefault();
            var inputsvals = {};
            $('.meeting-conf-input').map( function( idx, elem ) {
                var element = $(elem);
                if ( element.is(':checkbox' ) ) {
                    inputsvals[ element.data('key') ] = $(elem).prop('checked');
                } else {
                    inputsvals[ element.data('key') ] = $(elem).val();
                }
            });
            updateMeetingConfiguration( inputsvals );
        });

        // Get Last Screenshare //
        $( '.' + screenleapLocalizeVar.prefix + '-last-screenshare' ).on( 'click', function( event ) {
            event.preventDefault();
            getScreenShare();
        });

        // Pause ScreenShare //
        screensharePauseButton.on( 'click', function( event ) {
            event.preventDefault();
            pauseSharing();
        });

        // Resume ScreenShare //
        screenshareResumeButton.on( 'click', function( event ) {
            event.preventDefault();
            resumeSharing();
        });

        // Pause ScreenShare //
        screenshareStopButton.on( 'click', function( event ) {
            event.preventDefault();
            stopSharing();
        });

        // Reset Meeting status to not started.
        $('.reset-status').on( 'click', function() {
            showLoadingScreen( '' );
            updateScreenShareStatus( 'start' );
            hideLoadingScreen();
            $(this).tooltip('hide').remove();
        });

        $('[data-toggle="tooltip"]').tooltip();

        // Export reminder Emails.
        $('.reminder-subscription-emails-export').on( 'click', function() {
            var csvData = '';
            csvData    += jQuery('.reminder-subscription-emails-metabox .list-group-item').map( function() { return $.trim( $(this).text() ); }).get().join('\r\n');
            csvData    += '\r\n';
            csvData     = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent( csvData );
            $( this ).attr( 'href', csvData );
            return true;
        });
    });

    /**
     * Start Screen Share Button Event Function.
     */
    function startScreenShareButton() {
        if  ( preCreatedShareData ) {
            createScreenShareCallback( preCreatedShareData );
            preCreatedShareData = null;
        } else {
            startScreenShare();
        }
        return false;
    }

    /**
     * Initialize Instructions Classes.
     */
    function init_instructions() {

        if (screenleap.isMac) {
            $('.mac').show();
        }
        if (screenleap.isWin) {
            $('.win').show();
        }
        if (screenleap.isChrome) {
            $('.chrome').show();
        }
        if (screenleap.isFirefox) {
            $('.firefox').show();
        }
        if (screenleap.isMSIE) {
            $('.msie').show();
        } else {
            $('.not-msie').show();
        }
        if (screenleap.isMSIE8) {
            $('.not-msie8').hide();
            $('.msie8').show();
        }
        if (screenleap.isSafari) {
            $('.safari').show();
        } else {
            $('.not-safari').show();
        }

        var macCustomProtocolHandlerImage = null;
        var winCustomProtocolHandlerImage = null;
        if (screenleap.isChrome) {
            macCustomProtocolHandlerImage = screenleapLocalizeVar.imgUrl + 'mac_native_chrome_custom_protocol_handler.png';
            winCustomProtocolHandlerImage = screenleapLocalizeVar.imgUrl + 'win_native_chrome_custom_protocol_handler_screenshare.png';
        } else if (screenleap.isFirefox) {
            macCustomProtocolHandlerImage = screenleapLocalizeVar.imgUrl + 'mac_native_firefox_custom_protocol_handler.png';
            winCustomProtocolHandlerImage = screenleapLocalizeVar.imgUrl + 'win_native_firefox_custom_protocol_handler.png';
        } else if (screenleap.isMSIE) {
            winCustomProtocolHandlerImage = screenleapLocalizeVar.imgUrl + 'win_native_msie_custom_protocol_handler_screenshare.png';
        } else {
            macCustomProtocolHandlerImage = screenleapLocalizeVar.imgUrl + 'spacer.gif';
            winCustomProtocolHandlerImage = screenleapLocalizeVar.imgUrl + 'spacer.gif';
        }

        $('.mac-custom-protocol-handler-image').attr('src', macCustomProtocolHandlerImage);
        $('.win-custom-protocol-handler-image').attr('src', winCustomProtocolHandlerImage);

        screenshareResumeButton.hide();
        screensharePauseButton.hide();
        screenshareStopButton.hide();
    }

    /**
     * Start Screen Share.
     */
    function startScreenShare() {
        showLoadingScreen( msgs['step1']['start'] );
        createScreenShare( false, createScreenShareCallback );
        return false;
    }

    /**
     * Create Screen Share.
     * @param {boolean} alreadyDownloaded Is the App already Downloaded.
     * @param {function} successCallback Callback Function.
     */
    function createScreenShare( alreadyDownloaded, successCallback ) {
        $.ajax({
            method: 'POST',
            url: screenleapLocalizeVar.ajax_url,
            data: {
                action: screenleapLocalizeVar.prefix + '-send_screenshare_request',
                nonce: screenleapLocalizeVar.screenleap_presenter_nonce,
                subaction: 'start_share',
                meeting_id: screenleapLocalizeVar.post_id
            },
            success: function( resp ) {
                screenShareData = resp['data']['result'];
                successCallback( screenShareData, alreadyDownloaded );
            },
            error: function( err ) {
                hideLoadingScreen();
                if ( err.responseJSON ) {
                    fireAlert( 'Error!', err.responseJSON['data'], 'error' );
                } else {
                    fireAlert( 'Error!', err.responseText, 'error', true );
                }
            }
        });
    }

    /**
     * Screen Share Request Callback.
     * @param {object} screenShareData Screen Share Data
     */
    function createScreenShareCallback( screenShareData ) {
        loadingScreenShareCode = screenShareData['screenShareCode'];
        if ( ! screenleap.isAppInstalled() ) {
            screenleap.setOptions(
                {
                    delayAfterDownloadBeforeCallingCustomProtocolHandler: 30000,
                    showScreenShareStartErrorDelay:20000
                }
            );
        }

        screenleap.startSharing(
            presenterAppType,
            screenShareData, {
                screenShareStarting: onScreenShareStarting,
                appConnectionFailed: onAppConnectionFailed,
                screenShareStartError: onScreenShareStartError
            },
            {
                forceDownload: $('#forceDownload').exists() && $('#forceDownload').is(':checked')
            }
        );
    }

    /**
     * Update the Meeting Configuration.
     * @param {object} conf Meeting Configurations { key : value }
     */
    function updateMeetingConfiguration( conf ) {
        showLoadingScreen( __( 'Saving configuration ...' ) );
        $.ajax({
            method: 'POST',
            url: screenleapLocalizeVar.ajax_url,
            data: {
                action: screenleapLocalizeVar.prefix + '-update_screenshare_conf',
                nonce: screenleapLocalizeVar.screenleap_presenter_nonce,
                meeting_conf: conf,
                meeting_id: screenleapLocalizeVar.post_id
            },
            success: function( resp ) {
                hideLoadingScreen( 2000, __( 'Configuration are saved!' ) );
            },
            error: function( err ) {
            }
        });
    }

    /**
     * Get Last Screenshare of the current meeting.
     *
     */
    function getScreenShare() {
        $.ajax({
            method: 'POST',
            url: screenleapLocalizeVar.ajax_url,
            data: {
                action: screenleapLocalizeVar.prefix + '-get_screenshare',
                nonce: screenleapLocalizeVar.screenleap_presenter_nonce,
                meeting_id: screenleapLocalizeVar.post_id
            },
            success: function( resp ) {
            },
            error: function( err ) {
            },
            complete: function() {
            }
        });
    }

    /**
     * Get Info about a screenshare using the screenshare Code.
     * @param {string} screenShareCode  Screenshare Code
     */
    function getScreenshareInfo( screenShareCode ) {
        $.ajax({
            method: 'POST',
            url: screenleapLocalizeVar.ajax_url,
            data: {
                action: screenleapLocalizeVar.prefix + '-get_screenshare_info',
                nonce: screenleapLocalizeVar.screenleap_presenter_nonce,
                screenshare_code: screenShareCode
            },
            success: function( resp ) {
            },
            error: function( err ) {
            },
            complete: function() {
            }
        });
    }

    /**
     * Updat the Meeting Screen share Details Meta Box Fields.
     * @param {object} details ScreenShare Details Object.
     */
    function updateScreenShareDetailsBox( details ) {
        screenShareDetailsBox.find('.screenshare-code').text( details['screenShareCode'] );
        screenShareDetailsBox.find('.viewer-url').text( details['viewerUrl'] );
        screenShareDetailsBox.find('.viewers-count').text( '0' );
        screenShareCode = details['screenShareCode'];
    }

    /**
     * Clear the screenshare Details after the screen share ends.
     */
    function clearScreenShareDetailsBox() {
        screenShareDetailsBox.find('.screenshare-code').text( '' );
        screenShareDetailsBox.find('.viewer-url').text( '' );
        screenShareDetailsBox.find('.viewers-count').text( '' );
    }

    /**
     * Update ScreenShare Status in Post Edit.
     *
     * @param {string} status Screenshare Status.
     */
    function updateScreenShareStatus( status ) {
        let title = screenleapLocalizeVar.meeting_status_mapping[ status ]['title'];
        let icon  = screenleapLocalizeVar.meeting_status_mapping[ status ]['icon'];

        screenShareDetailsBox.find('.screenshare-status .status-title').text( title );
        screenShareDetailsBox.find('.screenshare-status .status-icon').removeClass( function( index, css ) {
            return (css.match (/\bled-\S+/g) || []).join('');
        }).addClass( icon );

        updateMeetingStatus( status );

    }

    /**
     * Assign the events actions functions to screenleap object.
     *
     */
    function initScreenleapEvents() {
        screenleap.onPause            = onPause;
        screenleap.onResume           = onResume;
        screenleap.onPresenterConnect = onPresenterConnect;
        screenleap.onScreenShareEnd   = onScreenShareEnd;
        screenleap.onViewerConnect    = onViewerConnect;
        screenleap.onViewerDisconnect = onViewerDisconnect;

        // Error Callbacks.
        screenleap.onRecordStartError = onRecordStartError;
    }

    /**
     * Reset Screenshare Object.
     */
    function resetScreenshare() {
        screenleap = {};
        screenleap = new Screenleap();
        initScreenleapEvents();
    }

    // Stop Sharing.
    function stopSharing() {
        showLoadingScreen( '' );
        screenleap.stopSharing(
            function() {
                showToast( 'Screen share successfully stopped.', 'bg-success' );
                hideLoadingScreen();
                toggleShareControls( ['start'], 'show' );
                toggleShareControls( [ 'resume', 'pause', 'stop', '*-record'], 'hide' );
            },
            function( xhr ) {
                showToast( 'Failed to stop screen share: ' + xhr.message, 'bg-danger' );
                hideLoadingScreen();
            }
        );

    }


    // Pause Sharing.
    function pauseSharing() {
        toggleShareControls( ['pause' ], 'hide' );
        showLoadingScreen( '' );
        screenleap.pauseSharing(
            function() {
                showToast( 'Screen share successfully paused.', 'bg-success' );
                hideLoadingScreen();
            },
            function( xhr ) {
                showToast( 'Failed to resume screen share: ' + xhr.message, 'bg-danger' );
                toggleShareControls( ['pause' ], 'show' );
                hideLoadingScreen();
            }
        );
        return false;
    }

    // Resume Sharing.
    function resumeSharing() {
        toggleShareControls( ['resume' ], 'hide' );
        showLoadingScreen( '' );
        screenleap.resumeSharing(
            function() {
                showToast( 'Screen share successfully resumed.', 'bg-success' );
                hideLoadingScreen();
            },
            function( xhr ) {
                showToast( 'Failed to resume screen share: ' + xhr.message, 'bg-danger' );
                toggleShareControls( ['resume' ], 'show' );
                hideLoadingScreen();
            }
        );
        return false;
    }

    // Start Share Screen.
    function onScreenShareStarting() {
        hideLoadingScreen();
        if ( recordingEnabled ) {
            if ( recordingOnStart ) {
                toggleShareControls( ['stop-record'], 'show' );
            } else {
                toggleShareControls( ['record'], 'show' );
            }
        }
        if ( ! jQuery('#retryCustomProtocolHandlerMessage').hasClass('show') ) {
            ToggleModal( ( screenleap.isAppInstalled() ? '#nativeStarting' : '#nativeInstallationInstructions' ), 'show' );
        }
    }

    function onPause() {
        toggleShareControls( ['pause'], 'hide' );
        toggleShareControls( ['resume'], 'show' );
        updateScreenShareStatus( 'pause' );
    }

    function onResume() {
        toggleShareControls( ['pause'], 'show' );
        toggleShareControls( ['resume'], 'hide' );
        updateScreenShareStatus( 'active' );
    }

    /**
     * On Presenter Connected with the App.
     */
     function onPresenterConnect() {
        hideLoadingScreen( 1500 );
        updateScreenShareStatus( 'active' );
        updateScreenShareDetailsBox( screenShareData, 'active' );
        $('.screenleap-dialog').modal('hide');
        toggleShareControls( ['start'], 'hide' );
        if ( $('.start-paused-input').is(':checked') ) {
            toggleShareControls( ['stop', 'resume'], 'show' );
        } else {
            toggleShareControls( ['stop', 'pause'], 'show' );
        }
        ToggleModal( '#retryCustomProtocolHandlerMessage', 'hide' );
        ToggleModal( '#nativeStarting', 'hide' );
        shareScreenStarted = true;
    }

    function onAppConnectionFailed( data ) {
        screenleap.onScreenShareEnd();
    }

    /**
     * Viewer is Connected.
     * @param {string} pariticpantID
     * @param {string} externalID
     */
    function onViewerConnect( pariticpantID, externalID ) {
        updateViewersCounter( pariticpantID, '+' );
        updateViewers( externalID );

    }

    /**
     * Viewer is Disconnected.
     *
     * @param {string} pariticpantID
     * @param {string} externalID
     */
    function onViewerDisconnect( pariticpantID, externalID ) {
        updateViewersCounter( pariticpantID, '-' );
        updateViewers( externalID, 'sub' );
    }

    /**
     * Error Start Recording
     * @param {array} errors
     */
    function onRecordStartError( errors ) {
        showToast( 'Unable to start recording due to a site issue. You should alert your user and reset the recording UI.', 'bg-danger' );
    }

    // Start Share Error.
    function onScreenShareStartError( data ) {
        hideLoadingScreen();
        toggleShareControls( ['start' ], 'show' );
        if ( screenleap.getUseCustomProtocol() ) {
            $('.screenleap-dialog').modal('hide');
            ToggleModal( '#retryCustomProtocolHandlerMessage', 'show' );
            return;
        } else {
            fireAlert( 'Error!', __( 'Failed to launch the application, make sure the screenshare application is installed!', 'screenshare-with-screenleap-integration' ), 'error' );
        }
    }

    // Start Share End.
    function onScreenShareEnd() {
        updateScreenShareStatus( 'end' );
        clearScreenShareDetailsBox();
        resetScreenshare();
        hideLoadingScreen( 1000 );
        screenShareData    = '';
        screenShareCode    = '';
        shareScreenStarted = false;
        toggleShareControls( ['start' ], 'show' );
        toggleShareControls( ['resume', 'pause', 'stop', '*-record' ], 'hide' );
    }

    /**
     * Show Loading Screen.
     * @param {string} message Loading Screen Message.
     */
    function showLoadingScreen( message = '' ) {
        preloadContainer.find('.msgs-holder').text( message );
        preloadContainer.css( 'display', 'flex' );
    }

    /**
     * Hide Loading Screen.
     *
     */
    function hideLoadingScreen( timeoutDuration = null, message = '' ) {
        if ( message ) {
            preloadContainer.find('.msgs-holder').text( message );
        }
        if ( timeoutDuration ) {
            setTimeout(() => {
                preloadContainer.css( 'display', 'none' );
            }, timeoutDuration);
        } else {
            preloadContainer.css( 'display', 'none' );
        }
    }

    /**
     * Update Viewers Counter.
     *
     */
    function updateViewersCounter( participantID, update ) {
        var counter = parseInt( screenShareDetailsBox.find('.viewers-count').text() ) || 0;

        if ( '+' == update ) {
            if ( participantIDTracker.includes( participantID ) ) {
                return;
            }
            participantIDTracker.push( participantID );
            counter++;
        } else if ( '-' == update ) {
            if ( ! participantIDTracker.includes( participantID ) ) {
                return;
            }
            participantIDTracker.splice( participantIDTracker.indexOf( participantID ), 1 );
            counter--;
            if ( counter < 0 ) {
                counter = 0;
            }
        } else {
            counter += parseInt( update );
        }
        screenShareDetailsBox.find('.viewers-count').text( counter );
    }

    // ========== Recording part ========== //

    /**
     * Start Recording.
     *
     */
    function startRecording() {
        showLoadingScreen( '' );
        screenleap.startRecording(
            function() {
                showToast( 'Recording successfully started.', 'bg-success' );
                toggleShareControls( ['stop-record'], 'show' );
                toggleShareControls( ['record'], 'hide' );
                hideLoadingScreen();
            },
            function( xhr ) {
                showToast( 'Unable to start recording due to a site issue. You should alert your user and reset the recording UI.', 'bg-danger' );
                hideLoadingScreen();
            }
        );
    }

    /**
     * Stop Recording.
     *
     */
    function stopRecording() {
        showLoadingScreen();
        screenleap.stopRecording(
            function() {
                showToast( 'Recording successfully stoped.', 'bg-success' );
                toggleShareControls( ['stop-record'], 'hide' );
                toggleShareControls( ['record'], 'show' );
                hideLoadingScreen();
            },
            function( xhr ) {
                showToast( 'Unable to stop recording!', 'bg-danger' );
                hideLoadingScreen();
            }
        );
    }

    /**
     * Toggle Share Controls Buttons.
     * @param {array} buttonType Button Type Name [ resume - pause - stop ]
     * @param {string} status [show - hide ]
     */
    function toggleShareControls( buttonTypes, status ) {
        for ( let i = 0; i < buttonTypes.length; i++ ) {
            switch ( buttonTypes[ i ] ) {
                case 'resume':
                    screenshareResumeButton[status]();
                    break;
                case 'pause':
                    screensharePauseButton[status]();
                    break;
                case 'start':
                    screenshareStartButton[status]();
                    break;
                case 'stop':
                    screenshareStopButton[status]();
                    break;
            }
        }
    }

    /**
     * Update meeting status through Ajax.
     * @param {int} meetingID
     * @param {string} newStatus
     */
    function updateMeetingStatus( newStatus ) {
        $.ajax({
            method: 'POST',
            url: screenleapLocalizeVar.ajax_url,
            data: {
                action: screenleapLocalizeVar.prefix + '-update_meeting_status',
                nonce: screenleapLocalizeVar.screenleap_presenter_nonce,
                meeting_id: screenleapLocalizeVar.post_id,
                status: newStatus
            },
            success: function( resp ) {
                screenShareData = resp['data']['result'];
            },
            error: function( err ) {
            }
        });
    }

    /**
     * Toggle Modal Element.
     * @param {string} modalID Modal ID.
     * @param {string} type Toggle Type [ show | hide ]
     */
    function ToggleModal( modalID, type = 'show' ) {
        jQuery( modalID ).modal( type );
    }

    /**
     * Show Toast Notice.
     * @param {string} text Toast Message
     */
    function showToast( text, status = 'bg-success' ) {
        let toast = jQuery('.screenshare-toast');
        toast.removeClass('bg-success bg-danger').addClass( status );
        toast.find('.toast-text').text( text );
        toast.toast('show');
    }

    /**
     *
     * @param {string} title Swal Alert Title.
     * @param {string} text  Swal Alert Text.
     * @param {string} icon  Swal Alert icon.
     * @param {boolean} isHTML  is HTML Text or not.
     */
    function fireAlert( title, text, icon, isHTML = false ) {
        var settings = {
            title: title,
            icon: icon
        };

        if ( isHTML ) {
            settings['html'] = text;
        } else {
            settings['text'] = text;
        }

        Swal.fire( settings );
    }

    window.addEventListener('beforeunload', function() {
        if ( screenleap && shareScreenStarted ) {
            stopSharing();
        }
    });

    /**
     * Update Viewers UI and Trackers.
     * @param {string} externalID
     * @param {string} type add - sub
     */
    function updateViewers( externalID, type = 'add' ) {
        if ( type === 'add' ) {
            if ( ! viewerList.includes( externalID ) && ! externalID.includes( '-' ) ) {
                viewerList.push( externalID );
            }
        } else if ( type === 'sub' ) {
            let idIndex = viewerList.indexOf( externalID );
            if ( idIndex > -1 ) {
                viewerList.splice( idIndex, 1 );
            }
        }

    }
    /**
     * Toggle ScreenShare Metaboxes Display based on Screen Share Type.
     */
    function toggleScreenShareMetaBoxes() {
        let val = $( '.' + screenleapLocalizeVar.prefix + '-meeting-type' ).val();
        if ( val === 'meet_now' ) {
            $('#d-' + screenleapLocalizeVar.prefix + '-screen-share-conf-metabox' ).addClass('d-none');
            $('#e-' + screenleapLocalizeVar.prefix + '-screen-share-viewer-metabox' ).addClass('d-none');
            $('#f-' + screenleapLocalizeVar.prefix + '-screen-share-details-metabox' ).addClass('d-none');
            $('#g-' + screenleapLocalizeVar.prefix + '-screen-share-actions-metabox' ).addClass('d-none');
            $('#h-' + screenleapLocalizeVar.prefix + '-screen-share-viewers-metabox' ).addClass('d-none');
            $('tr.scheduled-meeting').addClass('d-none').find('input.regular-text').prop( 'required', false );
            $('.api-alert').addClass('d-none');
            $('.handle-alert').removeClass('d-none');
            $('.meeting-type-status').removeClass('d-none');
        } else if ( val === 'scheduled_meet' ) {
            $('#d-' + screenleapLocalizeVar.prefix + '-screen-share-conf-metabox' ).addClass('d-none');
            $('#e-' + screenleapLocalizeVar.prefix + '-screen-share-viewer-metabox' ).addClass('d-none');
            $('#f-' + screenleapLocalizeVar.prefix + '-screen-share-details-metabox' ).addClass('d-none');
            $('#g-' + screenleapLocalizeVar.prefix + '-screen-share-actions-metabox' ).addClass('d-none');
            $('#h-' + screenleapLocalizeVar.prefix + '-screen-share-viewers-metabox' ).addClass('d-none');
            $('tr.scheduled-meeting').removeClass('d-none').find('input.regular-text').prop( 'required', true );
            $('.handle-alert, .api-alert').addClass('d-none');
            $('.meeting-type-status').removeClass('d-none');
        } else if ( val === 'api' ) {
            $('#d-' + screenleapLocalizeVar.prefix + '-screen-share-conf-metabox' ).removeClass('d-none');
            $('#e-' + screenleapLocalizeVar.prefix + '-screen-share-viewer-metabox' ).removeClass('d-none');
            $('#f-' + screenleapLocalizeVar.prefix + '-screen-share-details-metabox' ).removeClass('d-none');
            $('#g-' + screenleapLocalizeVar.prefix + '-screen-share-actions-metabox' ).removeClass('d-none');
            $('#h-' + screenleapLocalizeVar.prefix + '-screen-share-viewers-metabox' ).removeClass('d-none');
            $('tr.scheduled-meeting').addClass('d-none').find('input.regular-text').prop( 'required', false );
            $('.handle-alert').addClass('d-none');
            $('.api-alert').removeClass('d-none');
            $('.meeting-type-status').addClass('d-none');
        }
    }


})(jQuery);
