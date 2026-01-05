/**
 * Admin JavaScript for AI Checkout for WooCommerce
 *
 * @package Carticy\AiCheckout
 */

jQuery( document ).ready(
	function ($) {
		'use strict';

		// Handle notice dismissal
		$( document ).on(
			'click',
			'.carticy-admin-notifications .notice-dismiss',
			function (e) {
				e.preventDefault();
				const $notice = $( this ).closest( '.notice' );
				$notice.fadeTo( 100, 0, function () {
					$notice.slideUp(
						100,
						function () {
							$notice.remove();
						}
					);
				} );
			}
		);

		// Tab switching for Settings page (hash-based tabs only)
		function switchTab(tabId) {
			// Hide all tab content
			$( '.tab-content' ).hide();

			// Remove active class from all tabs
			$( '.nav-tab' ).removeClass( 'nav-tab-active' );

			// Show selected tab content
			$( tabId ).show();

			// Add active class to clicked tab
			$( '.nav-tab[href="' + tabId + '"]' ).addClass( 'nav-tab-active' );
		}

		// Handle tab clicks (ONLY for hash-based tabs, not page navigation)
		$( '.nav-tab' ).on(
			'click',
			function (e) {
				const href = $( this ).attr( 'href' );

				// Only intercept if it's a hash link (starts with #)
				if (href && href.startsWith( '#' )) {
					e.preventDefault();
					switchTab( href );

					// Update URL hash without scrolling
					history.pushState( null, null, href );
				}
				// Otherwise, allow normal navigation to other pages
			}
		);

		// Check URL hash on page load
		if (window.location.hash) {
			switchTab( window.location.hash );
		}

		// Check for tab query parameter (used after redirects)
		const urlParams = new URLSearchParams( window.location.search );
		const tabParam  = urlParams.get( 'tab' );
		if (tabParam) {
			switchTab( '#' + tabParam );
		}

		// Handle browser back/forward buttons
		$( window ).on(
			'hashchange',
			function () {
				if (window.location.hash) {
					switchTab( window.location.hash );
				}
			}
		);
	}
);
