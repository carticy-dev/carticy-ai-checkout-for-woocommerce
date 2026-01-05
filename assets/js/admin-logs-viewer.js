/**
 * Admin Logs Viewer Scripts
 *
 * Handles modal dialogs for API logs and error logs context viewing.
 * Requires carticyLogsViewer object to be localized with:
 *   - i18n: Translated strings object
 *
 * @package Carticy\AiCheckout
 */

/* global jQuery, CarticyAdmin, carticyLogsViewer */

(function($) {
	'use strict';

	$(document).ready(function() {
		// ==========================================================================
		// API Logs: View Context Modal
		// ==========================================================================

		$('.view-api-context-btn').on('click', function() {
			var $btn = $(this);
			var requestData = $btn.data('request');
			var responseData = $btn.data('response');
			var endpoint = $btn.data('endpoint');

			// Build modal content
			var $content = $('<div>');

			if (requestData && requestData !== 'null') {
				$content.append($('<h4>').text(carticyLogsViewer.i18n.request));
				$content.append($('<pre>').text(JSON.stringify(requestData, null, 2)));
			}

			if (responseData && responseData !== 'null') {
				$content.append($('<h4>').text(carticyLogsViewer.i18n.response));
				$content.append($('<pre>').text(JSON.stringify(responseData, null, 2)));
			}

			// Open modal
			CarticyAdmin.Modal.open({
				title: carticyLogsViewer.i18n.apiContext + ': ' + endpoint,
				content: $content,
				size: 'large'
			});
		});

		// ==========================================================================
		// Error Logs: View Context Modal
		// ==========================================================================

		$('.view-error-context-btn').on('click', function() {
			var $btn = $(this);
			var contextData = $btn.data('context');
			var category = $btn.data('category');
			var message = $btn.data('message');

			// Build modal content
			var $content = $('<div>');

			// Add error message
			$content.append($('<h4>').text(carticyLogsViewer.i18n.errorMessage));
			$content.append($('<p>').css({
				'margin': '0 0 16px 0',
				'padding': '12px',
				'background': '#f8d7da',
				'border-left': '4px solid #d63638',
				'color': '#721c24'
			}).text(message));

			// Add context details
			$content.append($('<h4>').text(carticyLogsViewer.i18n.contextDetails));
			$content.append($('<pre>').text(JSON.stringify(contextData, null, 2)));

			// Open modal
			CarticyAdmin.Modal.open({
				title: category + ' ' + carticyLogsViewer.i18n.errorDetails,
				content: $content,
				size: 'large'
			});
		});
	});

})(jQuery);
