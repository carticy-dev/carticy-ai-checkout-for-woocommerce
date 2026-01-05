/**
 * Admin Wizard Conformance Test Runner
 *
 * Handles ACP conformance test execution and result display.
 * Requires carticyConformance object to be localized with:
 *   - ajaxUrl: admin-ajax.php URL
 *   - nonce: wp_create_nonce('carticy_wizard_tests')
 *   - i18n: Translated strings object
 *
 * @package Carticy\AiCheckout
 */

/* global jQuery, CarticyAdmin, carticyConformance */

(function($) {
	'use strict';

	var testResults = [];
	var testsCompleted = 0;
	var totalTests = 17;
	var currentTestIndex = 0;

	/**
	 * HTML escape function to prevent XSS and layout corruption.
	 *
	 * @param {string} text - Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	/**
	 * Run all conformance tests sequentially.
	 */
	function runAllTests() {
		$('#run-all-tests').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + carticyConformance.i18n.runningTests);
		$('#test-progress-container').show();
		$('#test-summary').hide();
		testsCompleted = 0;
		currentTestIndex = 0;
		testResults = [];

		// Reset all test items
		$('.test-item').removeClass('test-passed test-failed test-warning test-running')
			.find('.test-status .dashicons')
			.removeClass('dashicons-yes-alt dashicons-dismiss dashicons-update')
			.addClass('dashicons-minus');
		$('.test-item .test-result').html('');

		// Update progress indicators
		$('#test-progress-message').html(carticyConformance.i18n.runningTestsIndividually);
		updateProgress();

		// Start running tests sequentially
		runNextTest();
	}

	/**
	 * Run the next test in the sequence.
	 */
	function runNextTest() {
		if (currentTestIndex >= totalTests) {
			// All tests completed - run cleanup
			cleanupTests();
			return;
		}

		var testId = 'test_' + (currentTestIndex + 1);
		var testNumber = currentTestIndex + 1;
		var $testItem = $('.test-item[data-test="' + testNumber + '"]');

		// Update progress message
		$('#test-progress-message').html(carticyConformance.i18n.runningTest + ' ' + testNumber + ' ' + carticyConformance.i18n.of + ' ' + totalTests + '...');

		// Show as running
		$testItem.addClass('test-running')
			.removeClass('test-passed test-failed test-warning')
			.find('.test-status .dashicons')
			.removeClass('dashicons-minus dashicons-yes-alt dashicons-dismiss')
			.addClass('dashicons-update');

		// Run single test via AJAX
		// Increased timeout to 60 seconds for Tests 4-5 (payment processing can be slow)
		$.ajax({
			url: carticyConformance.ajaxUrl,
			type: 'POST',
			timeout: 60000, // 60 seconds per test max
			data: {
				action: 'carticy_ai_checkout_run_conformance_tests',
				nonce: carticyConformance.nonce,
				test_action: 'run',
				test_id: testId
			},
			success: function(response) {
				if (response.success && response.data.result) {
					handleTestResult(testNumber, response.data.result, $testItem);
				} else {
					handleTestError(testNumber, response.data.message || 'Test failed', $testItem);
				}
			},
			error: function(xhr, status) {
				var errorMsg = 'Request failed';
				if (status === 'timeout') {
					errorMsg = carticyConformance.i18n.testTimeout;
				} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMsg = xhr.responseJSON.data.message;
				}
				handleTestError(testNumber, errorMsg, $testItem);
			}
		});
	}

	/**
	 * Handle successful test result.
	 *
	 * @param {number} testNumber - Test number (1-17).
	 * @param {Object} result - Test result object.
	 * @param {jQuery} $testItem - jQuery element for the test item.
	 */
	function handleTestResult(testNumber, result, $testItem) {
		testResults.push(result);

		// Clear running state
		$testItem.removeClass('test-running');

		if (result.passed) {
			$testItem.addClass('test-passed')
				.find('.test-status .dashicons')
				.removeClass('dashicons-update')
				.addClass('dashicons-yes-alt');
		} else {
			// Determine if blocking (critical) or non-blocking (warning)
			var isBlocking = result.blocking !== false;
			var failClass = isBlocking ? 'test-failed' : 'test-warning';
			var textColor = isBlocking ? '#dc3232' : '#d68a00';

			$testItem.addClass(failClass)
				.find('.test-status .dashicons')
				.removeClass('dashicons-update')
				.addClass('dashicons-dismiss');

			// Show error message (escape HTML to prevent layout corruption)
			var infoIcon = '<button type="button" class="test-info-btn" data-test-index="' + testNumber + '" title="' + carticyConformance.i18n.viewDetails + '"><span class="dashicons dashicons-info"></span></button>';
			var fixButton = '';
			if (testNumber === 15 && !result.passed) {
				fixButton = '<button type="button" class="test-action-btn test-fix-robots" data-test-index="' + testNumber + '">' + carticyConformance.i18n.enableFilter + '</button>';
			}
			var escapedMessage = escapeHtml(result.message || 'Failed');
			$testItem.find('.test-result').html('<span style="color: ' + textColor + ';">' + escapedMessage + infoIcon + fixButton + '</span>');
		}

		// Update progress
		testsCompleted++;
		updateProgress();

		// Move to next test
		currentTestIndex++;
		runNextTest();
	}

	/**
	 * Handle test error.
	 *
	 * @param {number} testNumber - Test number (1-17).
	 * @param {string} errorMsg - Error message.
	 * @param {jQuery} $testItem - jQuery element for the test item.
	 */
	function handleTestError(testNumber, errorMsg, $testItem) {
		// Store error result
		testResults.push({
			name: $testItem.find('.test-name').text(),
			passed: false,
			blocking: true,
			message: errorMsg
		});

		// Update UI
		$testItem.removeClass('test-running')
			.addClass('test-failed')
			.find('.test-status .dashicons')
			.removeClass('dashicons-update')
			.addClass('dashicons-dismiss');

		var infoIcon = '<button type="button" class="test-info-btn" data-test-index="' + testNumber + '" title="' + carticyConformance.i18n.viewDetails + '"><span class="dashicons dashicons-info"></span></button>';
		var escapedError = escapeHtml(errorMsg);
		$testItem.find('.test-result').html('<span style="color: #dc3232;">' + escapedError + infoIcon + '</span>');

		// Update progress
		testsCompleted++;
		updateProgress();

		// Move to next test
		currentTestIndex++;
		runNextTest();
	}

	/**
	 * Run cleanup after all tests complete.
	 */
	function cleanupTests() {
		// Run cleanup AJAX call
		$.ajax({
			url: carticyConformance.ajaxUrl,
			type: 'POST',
			data: {
				action: 'carticy_ai_checkout_run_conformance_tests',
				nonce: carticyConformance.nonce,
				test_action: 'cleanup'
			},
			success: function() {
				finishTestRun();
			},
			error: function() {
				finishTestRun();
			}
		});
	}

	/**
	 * Finish the test run and display results.
	 */
	function finishTestRun() {
		// Calculate summary
		var passed = testResults.filter(function(r) { return r.passed; }).length;
		var failed = totalTests - passed;
		var blockingTests = testResults.filter(function(r) { return r.blocking !== false; });
		var blockingPassed = blockingTests.filter(function(r) { return r.passed; }).length;
		var allBlockingPassed = blockingPassed === blockingTests.length;

		var summary = {
			total: totalTests,
			passed: passed,
			failed: failed,
			all_passed: failed === 0,
			all_blocking_passed: allBlockingPassed
		};

		// Save results to server
		$.ajax({
			url: carticyConformance.ajaxUrl,
			type: 'POST',
			data: {
				action: 'carticy_ai_checkout_save_test_results',
				nonce: carticyConformance.nonce,
				results: JSON.stringify({
					summary: summary,
					tests: testResults
				})
			}
		});

		// Show summary
		showSummary(summary);

		// Update progress message
		$('#test-progress-message').html(carticyConformance.i18n.allTestsCompleted);

		// Enable button
		$('#run-all-tests').prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> ' + carticyConformance.i18n.runAllTestsAgain);

		// Enable continue button if all blocking tests passed
		if (allBlockingPassed) {
			$('#continue-button').prop('disabled', false);
		}

		// Hide progress after delay
		setTimeout(function() {
			$('#test-progress-container').fadeOut(300);
		}, 2000);
	}

	/**
	 * Update progress bar and text.
	 */
	function updateProgress() {
		var percentage = (testsCompleted / totalTests) * 100;
		$('#test-progress-bar').css('width', percentage + '%');
		$('#test-progress-text').text(testsCompleted + ' / ' + totalTests);
	}

	/**
	 * Show test summary.
	 *
	 * @param {Object} summary - Summary object with total, passed, failed counts.
	 */
	function showSummary(summary) {
		$('#test-summary').show();
		$('#summary-total').text(summary.total);
		$('#summary-passed').text(summary.passed);
		$('#summary-failed').text(summary.failed);
	}

	/**
	 * Show test details modal.
	 *
	 * @param {Object} test - Test result object.
	 */
	function showTestDetails(test) {
		var $content = $('<div>');

		// Test Name Section
		$content.append(
			$('<div>').css({'margin-bottom': '24px'}).append(
				$('<h4>').css({'margin': '0 0 8px 0', 'font-size': '14px', 'font-weight': '600', 'color': '#1d2327'}).text(carticyConformance.i18n.testName),
				$('<p>').css({'margin': '0', 'color': '#50575e', 'line-height': '1.6'}).text(test.name)
			)
		);

		// Error Message Section
		if (test.message) {
			$content.append(
				$('<div>').css({'margin-bottom': '24px'}).append(
					$('<h4>').css({'margin': '0 0 8px 0', 'font-size': '14px', 'font-weight': '600', 'color': '#1d2327'}).text(carticyConformance.i18n.errorMessage),
					$('<p>').css({'margin': '0', 'color': '#50575e', 'line-height': '1.6'}).text(test.message)
				)
			);
		}

		// Request Data Section
		if (test.request && Object.keys(test.request).length > 0) {
			$content.append(
				$('<div>').css({'margin-bottom': '24px'}).append(
					$('<h4>').css({'margin': '0 0 8px 0', 'font-size': '14px', 'font-weight': '600', 'color': '#1d2327'}).text(carticyConformance.i18n.requestData),
					$('<pre>').css({
						'background': '#f6f7f7',
						'border': '1px solid #c3c4c7',
						'border-radius': '4px',
						'padding': '16px',
						'margin': '0',
						'font-family': '"Courier New", Courier, monospace',
						'font-size': '12px',
						'line-height': '1.6',
						'overflow-x': 'auto',
						'white-space': 'pre-wrap',
						'word-wrap': 'break-word',
						'color': '#1d2327'
					}).text(JSON.stringify(test.request, null, 2))
				)
			);
		}

		// Response Data Section
		if (test.response && Object.keys(test.response).length > 0) {
			$content.append(
				$('<div>').css({'margin-bottom': '0'}).append(
					$('<h4>').css({'margin': '0 0 8px 0', 'font-size': '14px', 'font-weight': '600', 'color': '#1d2327'}).text(carticyConformance.i18n.responseData),
					$('<pre>').css({
						'background': '#f6f7f7',
						'border': '1px solid #c3c4c7',
						'border-radius': '4px',
						'padding': '16px',
						'margin': '0',
						'font-family': '"Courier New", Courier, monospace',
						'font-size': '12px',
						'line-height': '1.6',
						'overflow-x': 'auto',
						'white-space': 'pre-wrap',
						'word-wrap': 'break-word',
						'color': '#1d2327'
					}).text(JSON.stringify(test.response, null, 2))
				)
			);
		}

		// Open modal using global CarticyAdmin.Modal
		CarticyAdmin.Modal.open({
			title: carticyConformance.i18n.testDetails,
			content: $content,
			size: 'large'
		});
	}

	/**
	 * Fix robots.txt by enabling the filter.
	 *
	 * @param {jQuery} $btn - The clicked button element.
	 */
	function fixRobotsTxt($btn) {
		$btn.prop('disabled', true).text(carticyConformance.i18n.fixing);

		$.ajax({
			url: carticyConformance.ajaxUrl,
			type: 'POST',
			data: {
				action: 'carticy_ai_checkout_enable_robots_filter',
				nonce: carticyConformance.nonce
			},
			success: function(response) {
				if (response.success) {
					$btn.text(carticyConformance.i18n.fixed).css('background', '#46b450');
					// Show success message
					var $testItem = $btn.closest('.test-item');
					$testItem.find('.test-result span').first().html(carticyConformance.i18n.robotsFilterEnabled);
				} else {
					$btn.prop('disabled', false).text(carticyConformance.i18n.enableFilter);
					alert(carticyConformance.i18n.failedToEnableFilter);
				}
			},
			error: function() {
				$btn.prop('disabled', false).text(carticyConformance.i18n.enableFilter);
				alert(carticyConformance.i18n.errorOccurred);
			}
		});
	}

	// Initialize when document is ready
	$(document).ready(function() {
		// Run all tests button
		$('#run-all-tests').on('click', function() {
			runAllTests();
		});

		// Handle test info button clicks
		$(document).on('click', '.test-info-btn', function() {
			var testIndex = $(this).data('test-index');
			if (testResults && testResults[testIndex - 1]) {
				showTestDetails(testResults[testIndex - 1]);
			}
		});

		// Handle fix robots.txt button
		$(document).on('click', '.test-fix-robots', function() {
			fixRobotsTxt($(this));
		});
	});

})(jQuery);
