/**
 * Edge Link Router - Admin JavaScript
 */

/* global jQuery, cfelrAdmin */

(function($) {
	'use strict';

	/**
	 * Main admin object.
	 */
	const CFELR = {

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Delete link confirmation
			$(document).on('click', '.cfelr-delete-link', this.confirmDelete);

			// Run diagnostics
			$(document).on('click', '.cfelr-run-diagnostics', this.runDiagnostics);

			// Test redirect
			$(document).on('submit', '.cfelr-test-redirect-form', this.testRedirect);

			// Copy to clipboard (icon button and text action)
			$(document).on('click', '.cfelr-copy, .cfelr-copy-action', this.copyToClipboard);

			// Bulk action confirmation
			$(document).on('submit', '#cfelr-links-form', this.confirmBulkAction);

			// Clear logs confirmation
			$(document).on('click', '.cfelr-clear-logs', this.confirmClearLogs);
		},

		/**
		 * Confirm delete action.
		 *
		 * @param {Event} e Click event.
		 * @return {boolean}
		 */
		confirmDelete: function(e) {
			if (!confirm(cfelrAdmin.i18n.confirmDelete)) {
				e.preventDefault();
				return false;
			}
			return true;
		},

		/**
		 * Confirm bulk delete action.
		 *
		 * @param {Event} e Submit event.
		 * @return {boolean}
		 */
		confirmBulkAction: function(e) {
			const action = $(this).find('select[name="action"]').val() || $(this).find('select[name="action2"]').val();

			if (action === 'delete') {
				const checked = $(this).find('input[name="link[]"]:checked').length;
				if (checked > 0) {
					const message = cfelrAdmin.i18n.confirmBulkDelete
						? cfelrAdmin.i18n.confirmBulkDelete.replace('%d', checked)
						: 'Are you sure you want to delete ' + checked + ' link(s)?';

					if (!confirm(message)) {
						e.preventDefault();
						return false;
					}
				}
			}
			return true;
		},

		/**
		 * Confirm clear logs action.
		 *
		 * @param {Event} e Click event.
		 * @return {boolean}
		 */
		confirmClearLogs: function(e) {
			const message = cfelrAdmin.i18n.confirmClearLogs || 'Are you sure you want to clear all logs?';
			if (!confirm(message)) {
				e.preventDefault();
				return false;
			}
			return true;
		},

		/**
		 * Run diagnostics via REST API.
		 *
		 * @param {Event} e Click event.
		 */
		runDiagnostics: function(e) {
			e.preventDefault();

			const $button = $(this);
			const $results = $('.cfelr-diagnostics-results');
			const originalText = $button.text();

			// Show spinner
			$button.prop('disabled', true).html(
				'<span class="cfelr-spinner"></span> ' + (cfelrAdmin.i18n.running || 'Running...')
			);

			$.ajax({
				url: cfelrAdmin.restUrl + 'diagnostics/run',
				method: 'POST',
				headers: {
					'X-WP-Nonce': cfelrAdmin.nonce
				}
			})
			.done(function(response) {
				CFELR.renderDiagnostics(response, $results);
			})
			.fail(function(xhr) {
				const errorMsg = xhr.responseJSON?.message || cfelrAdmin.i18n.error;
				$results.html('<div class="notice notice-error"><p>' + CFELR.escapeHtml(errorMsg) + '</p></div>');
			})
			.always(function() {
				$button.prop('disabled', false).text(originalText);
			});
		},

		/**
		 * Render diagnostics results.
		 *
		 * @param {Object} response API response.
		 * @param {jQuery} $container Results container.
		 */
		renderDiagnostics: function(response, $container) {
			if (!response.checks || !response.checks.length) {
				$container.html('<p>No diagnostic checks available.</p>');
				return;
			}

			let html = '<ul class="cfelr-diagnostics-list">';

			response.checks.forEach(function(check) {
				const statusClass = 'cfelr-check-' + check.status;
				const icon = CFELR.getStatusIcon(check.status);

				html += '<li>';
				html += '<div class="cfelr-check-status ' + statusClass + '">' + icon + '</div>';
				html += '<div class="cfelr-check-content">';
				html += '<div class="cfelr-check-name">' + CFELR.escapeHtml(check.name) + '</div>';
				html += '<div class="cfelr-check-message">' + CFELR.escapeHtml(check.message) + '</div>';

				if (check.fix_hint) {
					html += '<div class="cfelr-check-hint">' + CFELR.escapeHtml(check.fix_hint) + '</div>';
				}

				html += '</div>';
				html += '</li>';
			});

			html += '</ul>';

			$container.html(html);
		},

		/**
		 * Get status icon HTML.
		 *
		 * @param {string} status Status string.
		 * @return {string} Icon HTML.
		 */
		getStatusIcon: function(status) {
			const icons = {
				'ok': '<span class="dashicons dashicons-yes-alt"></span>',
				'warn': '<span class="dashicons dashicons-warning"></span>',
				'fail': '<span class="dashicons dashicons-dismiss"></span>',
				'pending': '<span class="dashicons dashicons-clock"></span>'
			};
			return icons[status] || icons.pending;
		},

		/**
		 * Test redirect via debug endpoint.
		 *
		 * @param {Event} e Submit event.
		 */
		testRedirect: function(e) {
			e.preventDefault();

			const $form = $(this);
			const slug = $form.find('input[name="slug"]').val();
			const $results = $form.siblings('.cfelr-test-results');
			const $button = $form.find('button[type="submit"]');
			const originalText = $button.text();

			if (!slug) {
				$results.html('<div class="notice notice-warning"><p>Please enter a slug.</p></div>');
				return;
			}

			// Build test URL
			const prefix = $form.data('prefix') || 'go';
			const testUrl = cfelrAdmin.siteUrl + '/' + prefix + '/' + encodeURIComponent(slug) + '?cfelr_debug=1';

			// Show spinner
			$button.prop('disabled', true).html(
				'<span class="cfelr-spinner"></span> ' + (cfelrAdmin.i18n.testing || 'Testing...')
			);

			$.ajax({
				url: testUrl,
				method: 'GET'
			})
			.done(function(response) {
				$results.html('<pre class="cfelr-test-output">' + JSON.stringify(response, null, 2) + '</pre>');
			})
			.fail(function(xhr) {
				if (xhr.status === 404) {
					$results.html('<div class="notice notice-warning"><p>Slug not found or disabled.</p></div>');
				} else {
					$results.html('<div class="notice notice-error"><p>Error testing redirect.</p></div>');
				}
			})
			.always(function() {
				$button.prop('disabled', false).text(originalText);
			});
		},

		/**
		 * Copy link to clipboard.
		 *
		 * @param {Event} e Click event.
		 */
		copyToClipboard: function(e) {
			e.preventDefault();

			const $button = $(this);
			const url = $button.data('url');

			if (!url) {
				return;
			}

			const showFeedback = function() {
				$button.addClass('cfelr-copied');
				setTimeout(function() {
					$button.removeClass('cfelr-copied');
				}, 1500);
			};

			navigator.clipboard.writeText(url).then(showFeedback).catch(function() {
				// Fallback for older browsers
				const textarea = document.createElement('textarea');
				textarea.value = url;
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild(textarea);
				textarea.select();
				document.execCommand('copy');
				document.body.removeChild(textarea);
				showFeedback();
			});
		},

		/**
		 * Escape HTML entities.
		 *
		 * @param {string} str Input string.
		 * @return {string} Escaped string.
		 */
		escapeHtml: function(str) {
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}
	};

	// Initialize on DOM ready.
	$(function() {
		CFELR.init();
	});

})(jQuery);
