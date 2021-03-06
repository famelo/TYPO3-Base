/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Object that handles RSA encryption and submission of the form
 */
define('TYPO3/CMS/Rsaauth/RsaEncryptionModule', ['jquery', './RsaLibrary'], function($) {
	'use strict';

	var RsaEncryption = {

		/**
		 * Remember the form which was submitted
		 */
		$currentForm: null,

		/**
		 * Remember if we fetched the RSA key already
		 */
		fetchedRsaKey: false,

		/**
		 * Replace event handler of submit button
		 */
		initialize: function() {
			$(':input[data-rsa-encryption]').closest('form').each(function() {
				var $this = $(this);
				$this.on('submit', RsaEncryption.handleFormSubmitRequest);
				// Bind submit event first (this is a dirty hack with jquery internals, but there is no way around that)
				var handlers = $._data(this, 'events').submit;
				var handler = handlers.pop();
				handlers.unshift(handler);
			});
			rng_seed_time();
		},

		/**
		 * Fetches a new public key by Ajax and encrypts the password for transmission
		 *
		 * @param event
		 */
		handleFormSubmitRequest: function(event) {
			if (!RsaEncryption.fetchedRsaKey) {
				event.stopImmediatePropagation();

				RsaEncryption.fetchedRsaKey = true;
				RsaEncryption.$currentForm = $(this);

				$.ajax({
					url: TYPO3.settings.ajaxUrls['RsaEncryption::getRsaPublicKey'],
					data: {'skipSessionUpdate': 1},
					success: RsaEncryption.handlePublicKeyResponse
				});

				return false;
			} else {
				// we come here again when the submit is triggered below
				// reset the variable to fetch a new key for next attempt
				RsaEncryption.fetchedRsaKey = false;
			}
		},

		/**
		 * Parses the Json response and triggers submission of the form
		 *
		 * @param response Ajax response object
		 */
		handlePublicKeyResponse: function(response) {
			var publicKey = response.split(':');
			if (!publicKey[0] || !publicKey[1]) {
				alert('No public key could be generated. Please inform your TYPO3 administrator to check the OpenSSL settings.');
				return;
			}

			var rsa = new RSAKey();
			rsa.setPublic(publicKey[0], publicKey[1]);
			RsaEncryption.$currentForm.find(':input[data-rsa-encryption]').each(function() {
				var $this = $(this);
				var encryptedPassword = rsa.encrypt($this.val());
				var dataAttribute = $this.data('rsa-encryption');

				if (!dataAttribute) {
					$this.val('rsa:' + hex2b64(encryptedPassword));
				} else {
					var $typo3Field = $('#' + dataAttribute);
					$typo3Field.val('rsa:' + hex2b64(encryptedPassword));
					// Reset user password field to prevent it from being submitted
					$this.val('');
				}
			});

			// Create a hidden input field to fake pressing the submit button
			RsaEncryption.$currentForm.append('<input type="hidden" name="commandLI" value="Submit">');

			// Submit the form
			RsaEncryption.$currentForm.trigger('submit');
		}
	};

	$(document).ready(RsaEncryption.initialize);

	return RsaEncryption;
});
