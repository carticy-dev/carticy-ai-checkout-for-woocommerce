/**
 * Product Feed Manager Admin JavaScript
 *
 * Handles AJAX interactions for the product feed manager.
 * Uses global CarticyAdmin.Modal from admin-components package.
 *
 * @package Carticy\AiCheckout
 */

(function ($) {
	'use strict';

	const CarticyProductManager = {
		/**
		 * Initialize
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			// Use direct binding with .off() first to prevent double binding.
			$( '.carticy-preview-feed' ).off( 'click' ).on( 'click', this.handlePreviewFeed );
			$( '#carticy-regenerate-feed' ).off( 'click' ).on( 'click', this.handleRegenerateFeed );
			$( '#carticy-recalculate-quality' ).off( 'click' ).on( 'click', this.handleRecalculateQuality );
			$( '.quality-issues-toggle' ).off( 'click' ).on( 'click', this.handleQualityIssuesToggle );
		},

		/**
		 * Handle feed preview click
		 */
		handlePreviewFeed: function (e) {
			e.preventDefault();

			const productId = $( this ).data( 'product-id' );

			// Show loading modal
			CarticyAdmin.Modal.open(
				{
					title: 'Product Feed Preview',
					content: '<div style="text-align: center; padding: 40px; color: #666;">Loading...</div>',
					size: 'large'
				}
			);

			// Make AJAX request.
			$.ajax(
				{
					url: carticyProductManager.ajax_url,
					type: 'POST',
					data: {
						action: 'carticy_ai_checkout_preview_product_feed',
						nonce: carticyProductManager.nonce,
						product_id: productId
					},
					success: function (response) {
						if (response.success && response.data.feed) {
							// Build modal content (without button - button goes in footer via actions)
							const $content = $( '<pre>' ).css(
								{
									background: '#f6f7f7',
									border: '1px solid #c3c4c7',
									borderRadius: '4px',
									padding: '16px',
									margin: '0',
									fontFamily: '"Courier New", Courier, monospace',
									fontSize: '12px',
									lineHeight: '1.6',
									overflowX: 'auto',
									whiteSpace: 'pre-wrap',
									wordWrap: 'break-word',
									maxHeight: '500px'
								}
							).text( response.data.feed );

							// Update modal with feed content and copy button in footer
							CarticyAdmin.Modal.open(
								{
									title: 'Product Feed Preview',
									content: $content,
									size: 'large',
									actions: [
										{
											label: carticyProductManager.i18n.copy || 'Copy to Clipboard',
											class: 'button-primary',
											onClick: function () {
												const $button = $( this );
												const originalText = $button.text();

												// Perform copy
												CarticyProductManager.copyToClipboard( response.data.feed );

												// Visual feedback: change text and fade slightly
												$button.text( carticyProductManager.i18n.copied || 'Copied!' )
													.css( 'opacity', '0.7' );

												// Revert after 2 seconds
												setTimeout(
													function () {
														$button.text( originalText )
															.css( 'opacity', '1' );
													},
													2000
												);
											}
										}
									]
								}
							);
						} else {
							CarticyAdmin.Modal.close();
							CarticyProductManager.showError( response.data.message || 'Failed to load feed preview.' );
						}
					},
					error: function () {
						CarticyAdmin.Modal.close();
						CarticyProductManager.showError( 'An error occurred while loading the feed preview.' );
					}
				}
			);
		},

		/**
		 * Copy text to clipboard
		 */
		copyToClipboard: function (text) {
			// Create temporary textarea.
			const $temp = $( '<textarea>' );
			$( 'body' ).append( $temp );
			$temp.val( text ).select();

			try {
				document.execCommand( 'copy' );
				CarticyProductManager.showSuccess( carticyProductManager.i18n.copied );
			} catch (err) {
				CarticyProductManager.showError( 'Failed to copy to clipboard.' );
			}

			$temp.remove();
		},

		/**
		 * Handle regenerate feed
		 */
		handleRegenerateFeed: function (e) {
			e.preventDefault();

			if ( ! confirm( carticyProductManager.i18n.confirm_regenerate )) {
				return;
			}

			const $button = $( this );
			$button.addClass( 'loading' ).prop( 'disabled', true );

			$.ajax(
				{
					url: carticyProductManager.ajax_url,
					type: 'POST',
					data: {
						action: 'carticy_ai_checkout_regenerate_feed',
						nonce: carticyProductManager.nonce
					},
					success: function (response) {
						if (response.success) {
							CarticyProductManager.showSuccess( response.data.message );
							setTimeout(
								function () {
									location.reload();
								},
								1500
							);
						} else {
							CarticyProductManager.showError( response.data.message || 'Failed to regenerate feed.' );
							$button.removeClass( 'loading' ).prop( 'disabled', false );
						}
					},
					error: function () {
						CarticyProductManager.showError( 'An error occurred while regenerating the feed.' );
						$button.removeClass( 'loading' ).prop( 'disabled', false );
					}
				}
			);
		},

		/**
		 * Handle recalculate quality scores
		 */
		handleRecalculateQuality: function (e) {
			e.preventDefault();

			if ( ! confirm( carticyProductManager.i18n.confirm_recalculate )) {
				return;
			}

			const $button = $( this );
			$button.addClass( 'loading' ).prop( 'disabled', true );

			$.ajax(
				{
					url: carticyProductManager.ajax_url,
					type: 'POST',
					data: {
						action: 'carticy_ai_checkout_recalculate_quality',
						nonce: carticyProductManager.nonce
					},
					success: function (response) {
						if (response.success) {
							CarticyProductManager.showSuccess( response.data.message );
							setTimeout(
								function () {
									location.reload();
								},
								1500
							);
						} else {
							CarticyProductManager.showError( response.data.message || 'Failed to recalculate quality scores.' );
							$button.removeClass( 'loading' ).prop( 'disabled', false );
						}
					},
					error: function () {
						CarticyProductManager.showError( 'An error occurred while recalculating quality scores.' );
						$button.removeClass( 'loading' ).prop( 'disabled', false );
					}
				}
			);
		},

		/**
		 * Handle quality issues toggle
		 */
		handleQualityIssuesToggle: function (e) {
			e.preventDefault();

			const issues   = $( this ).data( 'issues' );
			const $tooltip = CarticyProductManager.createTooltip( issues );

			// Position tooltip.
			const offset = $( this ).offset();
			$tooltip.css(
				{
					top: offset.top - $tooltip.outerHeight() - 10,
					left: offset.left + ($( this ).outerWidth() / 2) - ($tooltip.outerWidth() / 2)
				}
			);

			// Auto-hide after 5 seconds.
			setTimeout(
				function () {
					$tooltip.fadeOut(
						function () {
							$( this ).remove();
						}
					);
				},
				5000
			);
		},

		/**
		 * Create tooltip element
		 */
		createTooltip: function (content) {
			const $tooltip = $( '<div class="carticy-ai-quality-tooltip"></div>' ).html( content );
			$( 'body' ).append( $tooltip );
			return $tooltip;
		},

		/**
		 * Show success message
		 */
		showSuccess: function (message) {
			const $notice = $( '<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>' );
			$( '.wrap.carticy-ai-checkout-products' ).prepend( $notice );

			// Auto-dismiss after 3 seconds.
			setTimeout(
				function () {
					$notice.fadeOut(
						function () {
							$( this ).remove();
						}
					);
				},
				3000
			);
		},

		/**
		 * Show error message
		 */
		showError: function (message) {
			const $notice = $( '<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>' );
			$( '.wrap.carticy-ai-checkout-products' ).prepend( $notice );
		}
	};

	// Initialize on document ready.
	$( document ).ready(
		function () {
			CarticyProductManager.init();
		}
	);

})( jQuery );
