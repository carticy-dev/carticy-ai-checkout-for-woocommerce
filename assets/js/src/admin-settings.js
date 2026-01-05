/**
 * Admin Settings JavaScript
 * Handles interactions for the Carticy AI Checkout settings page
 */

(function ($) {
	'use strict';

	const CarticyAdminSettings = {

		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			// Copy to clipboard
			$( '.copy-button' ).on( 'click', this.copyToClipboard.bind( this ) );

			// Regenerate API key
			$( '.regenerate-api-key' ).on( 'click', this.regenerateApiKey.bind( this ) );

			// Regenerate webhook secret
			$( '.regenerate-webhook-secret' ).on( 'click', this.regenerateWebhookSecret.bind( this ) );

			// Test webhook
			$( '.test-webhook' ).on( 'click', this.testWebhook.bind( this ) );
		},

		copyToClipboard: function (e) {
			e.preventDefault();
			const button         = $( e.currentTarget );
			const targetSelector = button.data( 'clipboard-target' );
			const input          = $( targetSelector );

			if ( ! input.length) {
				return;
			}

			// Select and copy text
			input.select();

			try {
				document.execCommand( 'copy' );
				this.showCopyFeedback( button, true );
			} catch (err) {
				this.showCopyFeedback( button, false );
			}

			// Deselect
			window.getSelection().removeAllRanges();
		},

		showCopyFeedback: function (button, success) {
			const originalText = button.text();
			const feedbackText = success ? carticySettings.i18n.copied : carticySettings.i18n.copyFailed;

			button.text( feedbackText );

			if (success) {
				button.addClass( 'copied' );
			}

			setTimeout(
				function () {
					button.text( originalText );
					button.removeClass( 'copied' );
				},
				2000
			);
		},

		regenerateApiKey: function (e) {
			e.preventDefault();

			if ( ! confirm( carticySettings.i18n.confirmRegenerate )) {
				return;
			}

			const button       = $( e.currentTarget );
			const originalText = button.text();

			button.addClass( 'loading' ).prop( 'disabled', true );
			button.text( carticySettings.i18n.regenerating );

			$.ajax(
				{
					url: carticySettings.ajaxUrl,
					type: 'POST',
					data: {
						action: 'carticy_ai_checkout_regenerate_api_key',
						nonce: carticySettings.nonce
					},
					success: function (response) {
						if (response.success) {
							$( '#api-key' ).val( response.data.api_key );
							CarticyAdminSettings.showNotice( response.data.message, 'success' );
						} else {
							CarticyAdminSettings.showNotice( response.data.message || 'Failed to regenerate API key', 'error' );
						}
					},
					error: function () {
						CarticyAdminSettings.showNotice( 'An error occurred. Please try again.', 'error' );
					},
					complete: function () {
						button.removeClass( 'loading' ).prop( 'disabled', false );
						button.text( originalText );
					}
				}
			);
		},

		regenerateWebhookSecret: function (e) {
			e.preventDefault();

			if ( ! confirm( carticySettings.i18n.confirmRegenerate )) {
				return;
			}

			const button       = $( e.currentTarget );
			const originalText = button.text();

			button.addClass( 'loading' ).prop( 'disabled', true );
			button.text( carticySettings.i18n.regenerating );

			$.ajax(
				{
					url: carticySettings.ajaxUrl,
					type: 'POST',
					data: {
						action: 'carticy_ai_checkout_regenerate_webhook_secret',
						nonce: carticySettings.nonce
					},
					success: function (response) {
						if (response.success) {
							$( '#webhook-secret' ).val( response.data.webhook_secret );
							CarticyAdminSettings.showNotice( response.data.message, 'success' );
						} else {
							CarticyAdminSettings.showNotice( response.data.message || 'Failed to regenerate webhook secret', 'error' );
						}
					},
					error: function () {
						CarticyAdminSettings.showNotice( 'An error occurred. Please try again.', 'error' );
					},
					complete: function () {
						button.removeClass( 'loading' ).prop( 'disabled', false );
						button.text( originalText );
					}
				}
			);
		},

		testWebhook: function (e) {
			e.preventDefault();

			const button       = $( e.currentTarget );
			const resultDiv    = $( '#webhook-test-result' );
			const originalText = button.text();

			button.addClass( 'loading' ).prop( 'disabled', true );
			button.text( carticySettings.i18n.testingWebhook );
			resultDiv.removeClass( 'success error' ).text( '' );

			$.ajax(
				{
					url: carticySettings.ajaxUrl,
					type: 'POST',
					data: {
						action: 'carticy_ai_checkout_test_webhook',
						nonce: carticySettings.nonce
					},
					success: function (response) {
						if (response.success) {
							resultDiv.addClass( 'success' ).text( response.data.message || carticySettings.i18n.webhookSuccess );
						} else {
							resultDiv.addClass( 'error' ).text( response.data.message || carticySettings.i18n.webhookFailed );
						}
					},
					error: function (xhr) {
						let errorMessage = carticySettings.i18n.webhookFailed;

						if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							errorMessage = xhr.responseJSON.data.message;
						}

						resultDiv.addClass( 'error' ).text( errorMessage );
					},
					complete: function () {
						button.removeClass( 'loading' ).prop( 'disabled', false );
						button.text( originalText );
					}
				}
			);
		},

		showNotice: function (message, type) {
			const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
			const notice      = $( '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>' );

			$( '.carticy-ai-checkout h1' ).after( notice );

			// Auto dismiss after 5 seconds
			setTimeout(
				function () {
					notice.fadeOut(
						function () {
							$( this ).remove();
						}
					);
				},
				5000
			);

			// Manual dismiss
			notice.on(
				'click',
				'.notice-dismiss',
				function () {
					notice.fadeOut(
						function () {
							$( this ).remove();
						}
					);
				}
			);
		}
	};

	// Initialize on document ready
	$( document ).ready(
		function () {
			CarticyAdminSettings.init();
		}
	);

})( jQuery );
