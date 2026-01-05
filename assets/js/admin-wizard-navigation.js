/**
 * Admin Wizard Navigation
 *
 * Handles wizard step navigation (previous/next).
 * Requires carticyWizardNav object to be localized with:
 *   - adminPostUrl: admin-post.php URL
 *   - nonce: wp_create_nonce('carticy_wizard_navigate')
 *
 * @package Carticy\AiCheckout
 */

/* global carticyWizardNav */

/**
 * Navigate to previous or next wizard step.
 *
 * @param {string} direction - Navigation direction ('prev' or 'next').
 */
function carticyWizardNavigate(direction) {
	// Create a temporary form and submit it
	var form = document.createElement('form');
	form.method = 'POST';
	form.action = carticyWizardNav.adminPostUrl;

	// Add nonce
	var nonceField = document.createElement('input');
	nonceField.type = 'hidden';
	nonceField.name = '_wpnonce';
	nonceField.value = carticyWizardNav.nonce;
	form.appendChild(nonceField);

	// Add action
	var actionField = document.createElement('input');
	actionField.type = 'hidden';
	actionField.name = 'action';
	actionField.value = 'carticy_ai_checkout_wizard_navigate';
	form.appendChild(actionField);

	// Add direction
	var directionField = document.createElement('input');
	directionField.type = 'hidden';
	directionField.name = 'direction';
	directionField.value = direction;
	form.appendChild(directionField);

	// Append to body and submit
	document.body.appendChild(form);
	form.submit();
}
