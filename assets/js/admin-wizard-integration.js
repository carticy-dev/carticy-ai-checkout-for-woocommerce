/**
 * Admin Wizard Integration Scripts
 *
 * Handles step 7 (API key/webhook management) and step 9 (JSON copy/download).
 * Requires carticyIntegration object to be localized with:
 *   - ajaxUrl: admin-ajax.php URL
 *   - nonce: wp_create_nonce('carticy_settings_nonce')
 *   - downloadDate: Current date for filename (Y-m-d format)
 *   - i18n: Translated strings object
 *
 * @package Carticy\AiCheckout
 */

/* global jQuery, carticyIntegration */

/**
 * Copy field value to clipboard (for step 7 input fields).
 *
 * @param {string} fieldId - The ID of the input field to copy.
 */
function copyToClipboard(fieldId) {
	var field = document.getElementById(fieldId);
	if (!field) {
		return;
	}

	field.select();
	document.execCommand('copy');

	// Show feedback
	var button = event.target.closest('button');
	if (!button) {
		return;
	}

	var originalText = button.innerHTML;
	button.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + carticyIntegration.i18n.copied;
	button.style.background = '#46b450';
	button.style.color = '#fff';

	setTimeout(function() {
		button.innerHTML = originalText;
		button.style.background = '';
		button.style.color = '';
	}, 2000);
}

(function($) {
	'use strict';

	$(document).ready(function() {
		// ==========================================================================
		// Step 7: API Key and Webhook Secret Regeneration
		// ==========================================================================

		$('#regenerate-api-key, #regenerate-webhook-secret').on('click', function() {
			var action = $(this).data('action');
			var confirmMsg = action === 'regenerate_api_key'
				? carticyIntegration.i18n.confirmRegenerateApiKey
				: carticyIntegration.i18n.confirmRegenerateWebhookSecret;

			if (!confirm(confirmMsg)) {
				return;
			}

			var $button = $(this);
			$button.prop('disabled', true);

			$.ajax({
				url: carticyIntegration.ajaxUrl,
				type: 'POST',
				data: {
					action: 'carticy_ai_checkout_' + action,
					nonce: carticyIntegration.nonce
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						window.location.reload();
					} else {
						alert(carticyIntegration.i18n.failedToRegenerate);
						$button.prop('disabled', false);
					}
				},
				error: function() {
					alert(carticyIntegration.i18n.errorOccurred);
					$button.prop('disabled', false);
				}
			});
		});

		// ==========================================================================
		// Step 9: JSON Copy and Download
		// ==========================================================================

		// Copy JSON to clipboard
		$('#copy-json-btn').on('click', function() {
			var json = $('#application-json').val();
			var $button = $(this);

			navigator.clipboard.writeText(json).then(function() {
				var originalHtml = $button.html();
				$button.html('<span class="dashicons dashicons-yes"></span> ' + carticyIntegration.i18n.copied);
				$button.css({'background': '#46b450', 'border-color': '#46b450', 'color': '#fff'});

				setTimeout(function() {
					$button.html(originalHtml);
					$button.css({'background': '', 'border-color': '', 'color': ''});
				}, 2000);
			}).catch(function() {
				alert(carticyIntegration.i18n.failedToCopy);
			});
		});

		// Download JSON file
		$('#download-json-btn').on('click', function() {
			var json = $('#application-json').val();
			var blob = new Blob([json], { type: 'application/json' });
			var url = URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url;
			a.download = 'carticy-openai-application-' + carticyIntegration.downloadDate + '.json';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(url);
		});
	});

})(jQuery);
