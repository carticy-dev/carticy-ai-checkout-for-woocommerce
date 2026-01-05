/**
 * Carticy Admin Components - JavaScript
 *
 * Reusable JavaScript functionality for Carticy admin interfaces
 *
 * @package Carticy\AdminComponents
 * @version 1.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Carticy Admin Components Namespace
	 */
	window.CarticyAdmin = window.CarticyAdmin || {};

	/**
	 * Notice Dismiss Handler
	 *
	 * Handles dismissal of admin notices with fade and slide animations
	 */
	CarticyAdmin.initNoticeDismiss = function () {
		$(document).on(
			'click',
			'.carticy-admin-notifications .notice-dismiss',
			function (e) {
				e.preventDefault();
				const $notice = $(this).closest('.notice');
				$notice.fadeTo(100, 0, function () {
					$notice.slideUp(100, function () {
						$notice.remove();
					});
				});
			}
		);
	};

	/**
	 * Tab Navigation System
	 *
	 * Handles hash-based tab navigation with history support
	 *
	 * @param {Object} options Configuration options
	 * @param {string} options.tabSelector - Selector for tab elements (default: '.nav-tab')
	 * @param {string} options.contentSelector - Selector for tab content (default: '.tab-content')
	 */
	CarticyAdmin.initTabs = function (options) {
		const settings = $.extend(
			{
				tabSelector: '.nav-tab',
				contentSelector: '.tab-content',
			},
			options
		);

		/**
		 * Switch to a specific tab
		 *
		 * @param {string} tabId - Tab ID (hash format: #tab-name)
		 */
		function switchTab(tabId) {
			// Hide all tab content
			$(settings.contentSelector).hide();

			// Remove active class from all tabs
			$(settings.tabSelector).removeClass('nav-tab-active carticy-tab-active');

			// Show selected tab content
			$(tabId).show();

			// Add active class to clicked tab
			$(settings.tabSelector + '[href="' + tabId + '"]').addClass('nav-tab-active carticy-tab-active');
		}

		// Handle tab clicks (ONLY for hash-based tabs, not page navigation)
		$(settings.tabSelector).on('click', function (e) {
			const href = $(this).attr('href');

			// Only intercept if it's a hash link (starts with #)
			if (href && href.startsWith('#')) {
				e.preventDefault();
				switchTab(href);

				// Update URL hash without scrolling
				history.pushState(null, null, href);
			}
			// Otherwise, allow normal navigation to other pages
		});

		// Check URL hash on page load
		if (window.location.hash) {
			switchTab(window.location.hash);
		}

		// Check for tab query parameter (used after redirects)
		// Only process if hash-based tab elements actually exist
		const urlParams = new URLSearchParams(window.location.search);
		const tabParam = urlParams.get('tab');
		if (tabParam && $('#' + tabParam).length > 0) {
			switchTab('#' + tabParam);
		}

		// Handle browser back/forward buttons
		$(window).on('hashchange', function () {
			if (window.location.hash) {
				switchTab(window.location.hash);
			}
		});
	};

	/**
	 * Content Box Utilities
	 *
	 * Helper functions for content boxes
	 */
	CarticyAdmin.ContentBox = {
		/**
		 * Toggle content box visibility
		 *
		 * @param {string|jQuery} selector - Box selector
		 */
		toggle: function (selector) {
			$(selector).slideToggle(200);
		},

		/**
		 * Show content box
		 *
		 * @param {string|jQuery} selector - Box selector
		 */
		show: function (selector) {
			$(selector).slideDown(200);
		},

		/**
		 * Hide content box
		 *
		 * @param {string|jQuery} selector - Box selector
		 */
		hide: function (selector) {
			$(selector).slideUp(200);
		},
	};

	/**
	 * Form Utilities
	 *
	 * Helper functions for form handling
	 */
	CarticyAdmin.Form = {
		/**
		 * Disable form inputs
		 *
		 * @param {string|jQuery} formSelector - Form selector
		 */
		disable: function (formSelector) {
			$(formSelector)
				.find('input, select, textarea, button')
				.prop('disabled', true)
				.addClass('disabled');
		},

		/**
		 * Enable form inputs
		 *
		 * @param {string|jQuery} formSelector - Form selector
		 */
		enable: function (formSelector) {
			$(formSelector)
				.find('input, select, textarea, button')
				.prop('disabled', false)
				.removeClass('disabled');
		},

		/**
		 * Serialize form data as object
		 *
		 * @param {string|jQuery} formSelector - Form selector
		 * @return {Object} Form data as key-value pairs
		 */
		serializeObject: function (formSelector) {
			const formArray = $(formSelector).serializeArray();
			const formObject = {};

			$.each(formArray, function (i, field) {
				formObject[field.name] = field.value;
			});

			return formObject;
		},
	};

	/**
	 * Modal System
	 *
	 * Simple modal dialog for displaying content with optional footer actions
	 */
	CarticyAdmin.Modal = {
		/**
		 * Open modal with content
		 *
		 * @param {Object} options Modal configuration
		 * @param {string} options.title - Modal title
		 * @param {string|jQuery} options.content - Modal content (HTML or jQuery object)
		 * @param {string} options.size - Modal size: 'small', 'medium' (default), 'large', 'xlarge'
		 * @param {Array<Object>} options.actions - Footer action buttons [{label: 'Button', class: 'button-primary', onClick: function(){}}]
		 * @param {Function} options.onClose - Callback when modal closes
		 */
		open: function (options) {
			const settings = $.extend(
				{
					title: '',
					content: '',
					size: 'medium',
					actions: null,
					onClose: null,
				},
				options
			);

			// Close existing modal if any
			this.close();

			// Build modal content sections
			const modalSections = [
				$('<div>', {
					class: 'carticy-modal-header',
					html: [
						$('<h2>', { text: settings.title }),
						$('<button>', {
							type: 'button',
							class: 'carticy-modal-close',
							'aria-label': 'Close',
							html: '&times;',
						}),
					],
				}),
				$('<div>', {
					class: 'carticy-modal-body',
					html: settings.content,
				}),
			];

			// Add footer if actions provided
			if (settings.actions && settings.actions.length > 0) {
				const $footer = $('<div>', { class: 'carticy-modal-footer' });

				settings.actions.forEach(function(action) {
					const $button = $('<button>', {
						type: 'button',
						class: action.class || 'button',
						text: action.label || 'Action',
					});

					// Attach click handler
					if (typeof action.onClick === 'function') {
						$button.on('click', action.onClick);
					}

					$footer.append($button);
				});

				modalSections.push($footer);
			}

			// Create modal structure
			const $modal = $('<div>', {
				class: 'carticy-modal-overlay carticy-admin-layout',
				html: $('<div>', {
					class: 'carticy-modal carticy-modal-' + settings.size,
					html: modalSections,
				}),
			});

			// Append to body
			$('body').append($modal);

			// Fade in
			setTimeout(function () {
				$modal.addClass('active');
			}, 10);

			// Store onClose callback
			if (settings.onClose) {
				$modal.data('onClose', settings.onClose);
			}

			// Close on overlay click
			$modal.on('click', function (e) {
				if ($(e.target).hasClass('carticy-modal-overlay')) {
					CarticyAdmin.Modal.close();
				}
			});

			// Close on close button click
			$modal.find('.carticy-modal-close').on('click', function () {
				CarticyAdmin.Modal.close();
			});

			// Close on ESC key
			$(document).on('keydown.carticyModal', function (e) {
				if (e.key === 'Escape' || e.keyCode === 27) {
					CarticyAdmin.Modal.close();
				}
			});
		},

		/**
		 * Close modal
		 */
		close: function () {
			const $modal = $('.carticy-modal-overlay');

			if ($modal.length === 0) {
				return;
			}

			// Get onClose callback before removing
			const onClose = $modal.data('onClose');

			// Fade out
			$modal.removeClass('active');

			// Remove after animation
			setTimeout(function () {
				$modal.remove();
				$(document).off('keydown.carticyModal');

				// Call onClose callback if exists
				if (typeof onClose === 'function') {
					onClose();
				}
			}, 200);
		},
	};

	/**
	 * Initialize all components
	 */
	CarticyAdmin.init = function (options) {
		const settings = $.extend(
			{
				enableNoticeDismiss: true,
				enableTabs: true,
				tabOptions: {},
			},
			options
		);

		if (settings.enableNoticeDismiss) {
			CarticyAdmin.initNoticeDismiss();
		}

		if (settings.enableTabs) {
			CarticyAdmin.initTabs(settings.tabOptions);
		}
	};

	// Auto-initialize on document ready
	$(document).ready(function () {
		CarticyAdmin.init();
	});
})(jQuery);


