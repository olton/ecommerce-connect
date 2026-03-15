(function () {
  function initAltCurrencyToggle() {
    var toggle = document.getElementById('woocommerce_ecommerceconnect_enable_alt_currency');
    var altCurrencySelect = document.getElementById('woocommerce_ecommerceconnect_alt_currency');
    var eurRateInput = document.getElementById('woocommerce_ecommerceconnect_eur_conversion');
    var usdRateInput = document.getElementById('woocommerce_ecommerceconnect_usd_conversion');

    if (!toggle) {
      return;
    }

    function syncAltCurrencyVisibility() {
      var isEnabled = !!toggle.checked;
      var altCurrencyRow = altCurrencySelect ? altCurrencySelect.closest('tr') : null;
      var eurRateRow = eurRateInput ? eurRateInput.closest('tr') : null;
      var usdRateRow = usdRateInput ? usdRateInput.closest('tr') : null;

      if (altCurrencySelect) {
        altCurrencySelect.disabled = !isEnabled;
      }
      if (eurRateInput) {
        eurRateInput.disabled = !isEnabled;
      }
      if (usdRateInput) {
        usdRateInput.disabled = !isEnabled;
      }

      if (altCurrencyRow) {
        altCurrencyRow.style.display = isEnabled ? '' : 'none';
      }
      if (eurRateRow) {
        eurRateRow.style.display = isEnabled ? '' : 'none';
      }
      if (usdRateRow) {
        usdRateRow.style.display = isEnabled ? '' : 'none';
      }
    }

    toggle.addEventListener('change', syncAltCurrencyVisibility);
    syncAltCurrencyVisibility();
  }

  function showSecretFeedback(button, message, isError) {
    if (!button) {
      return;
    }

    var feedback = button.parentElement
      ? button.parentElement.querySelector('.ecommconnect-secret-feedback')
      : null;

    if (!feedback) {
      return;
    }

    feedback.textContent = message || '';
    feedback.classList.toggle('is-error', !!isError);
    feedback.classList.toggle('is-success', !isError && !!message);
  }

  function fetchSecretValue(optionKey) {
    var config = window.ecommconnect_admin_settings || {};
    var ajaxUrl = config.ajaxurl;
    var action = config.action;
    var nonce = config.nonce;

    if (!ajaxUrl || !action || !nonce || !optionKey) {
      return Promise.reject(new Error('Missing reveal secret configuration.'));
    }

    var payload = new URLSearchParams();
    payload.append('action', action);
    payload.append('security', nonce);
    payload.append('option_key', optionKey);

    return window
      .fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: payload.toString(),
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (result) {
        if (!result || !result.success || !result.data) {
          throw new Error(
            (result && result.data && result.data.message) ||
              (config.errorText || 'Unable to reveal saved value. Please try again.')
          );
        }

        return String(result.data.value || '');
      });
  }

  function decodeBase64Value(encodedValue) {
    if (!encodedValue) {
      return '';
    }

    try {
      var binary = window.atob(encodedValue);
      var bytes = new Uint8Array(binary.length);

      for (var i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
      }

      if (typeof TextDecoder === 'function') {
        return new TextDecoder('utf-8').decode(bytes);
      }

      return binary;
    } catch {
      return '';
    }
  }

  function revealOrHideSecret(button) {
    var targetId = button.getAttribute('data-target-id');
    var optionKey = button.getAttribute('data-option-key');
    var showLabel = button.getAttribute('data-show-label') || 'Show value';
    var hideLabel = button.getAttribute('data-hide-label') || 'Hide value';
    var loadingLabel = button.getAttribute('data-loading-label') || 'Loading...';
    var emptyLabel = button.getAttribute('data-empty-label') || 'No saved value.';
    var secretValueB64 = button.getAttribute('data-secret-value-b64') || '';
    var config = window.ecommconnect_admin_settings || {};
    var textarea = targetId ? document.getElementById(targetId) : null;

    if (!textarea || !optionKey) {
      return;
    }

    if (button.getAttribute('data-revealed') === 'yes') {
      textarea.value = textarea.getAttribute('data-masked-preview') || '';
      button.setAttribute('data-revealed', 'no');
      button.textContent = showLabel;
      showSecretFeedback(button, '', false);

      return;
    }

    var embeddedSecretValue = decodeBase64Value(secretValueB64);

    if (embeddedSecretValue || secretValueB64 === '') {
      textarea.value = embeddedSecretValue;
      button.setAttribute('data-revealed', 'yes');
      button.textContent = hideLabel;

      if (!embeddedSecretValue) {
        showSecretFeedback(button, emptyLabel, false);
      }

      return;
    }

    button.disabled = true;
    button.textContent = loadingLabel;
    showSecretFeedback(button, '', false);

    fetchSecretValue(optionKey)
      .then(function (secretValue) {
        textarea.value = secretValue;
        button.setAttribute('data-revealed', 'yes');
        button.textContent = hideLabel;

        if (!secretValue) {
          showSecretFeedback(button, emptyLabel, false);
        }
      })
      .catch(function (error) {
        button.setAttribute('data-revealed', 'no');
        button.textContent = showLabel;

        var errorText = (error && error.message) || config.errorText || 'Unable to reveal saved value. Please try again.';
        showSecretFeedback(button, errorText, true);
      })
      .finally(function () {
        button.disabled = false;
      });
  }

  function initSecretRevealButtons() {
    var revealButtons = document.querySelectorAll('.ecommconnect-reveal-secret');

    if (!revealButtons.length) {
      return;
    }

    revealButtons.forEach(function (button) {
      button.setAttribute('data-revealed', 'no');
      button.addEventListener('click', function () {
        revealOrHideSecret(button);
      });
    });
  }

  function clearSecretValue(button) {
    var targetId = button.getAttribute('data-target-id');
    var revealButtonId = button.getAttribute('data-reveal-button-id');
    var textarea = targetId ? document.getElementById(targetId) : null;
    var revealButton = revealButtonId ? document.getElementById(revealButtonId) : null;

    if (!textarea) {
      return;
    }

    textarea.value = '';
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));

    if (revealButton) {
      var showLabel = revealButton.getAttribute('data-show-label') || 'Show value';
      revealButton.setAttribute('data-revealed', 'no');
      revealButton.textContent = showLabel;
      showSecretFeedback(revealButton, '', false);
    }
  }

  function initSecretClearButtons() {
    var clearButtons = document.querySelectorAll('.ecommconnect-clear-secret');

    if (!clearButtons.length) {
      return;
    }

    clearButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        clearSecretValue(button);
      });
    });
  }

  function fallbackCopyText(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', 'readonly');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';

    document.body.appendChild(textarea);
    textarea.select();

    var copied = false;

    try {
      copied = document.execCommand('copy');
    } catch {
      copied = false;
    }

    document.body.removeChild(textarea);

    return copied;
  }

  function copyText(text) {
    if (!text) {
      return Promise.resolve(false);
    }

    if (window.navigator && window.navigator.clipboard && window.isSecureContext) {
      return window.navigator.clipboard.writeText(text).then(
        function () {
          return true;
        },
        function () {
          return fallbackCopyText(text);
        }
      );
    }

    return Promise.resolve(fallbackCopyText(text));
  }

  function showFeedback(wrapper, message, isError) {
    if (!wrapper) {
      return;
    }

    var feedback = wrapper.querySelector('.ecommconnect-copy-feedback');

    if (!feedback) {
      return;
    }

    feedback.textContent = message;
    feedback.classList.toggle('is-error', !!isError);
    feedback.classList.toggle('is-success', !isError);
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.ecommconnect-copy-notify-url');

    if (!button) {
      return;
    }

    var targetId = button.getAttribute('data-copy-target');
    var copySource = targetId ? document.getElementById(targetId) : null;
    var textToCopy = copySource ? copySource.textContent.trim() : '';

    copyText(textToCopy).then(function (success) {
      var wrapper = button.closest('.ecommconnect-settings-info');
      var copyLabel = button.getAttribute('data-copy-text') || 'Copy link';
      var copiedLabel = button.getAttribute('data-copied-text') || 'Copied';
      var errorLabel = button.getAttribute('data-copy-error-text') || 'Unable to copy. Please copy manually.';

      if (success) {
        button.textContent = copiedLabel;
        showFeedback(wrapper, copiedLabel, false);

        window.setTimeout(function () {
          button.textContent = copyLabel;
        }, 1800);

        return;
      }

      showFeedback(wrapper, errorLabel, true);
    });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAltCurrencyToggle();
      initSecretRevealButtons();
      initSecretClearButtons();
    });
  } else {
    initAltCurrencyToggle();
    initSecretRevealButtons();
    initSecretClearButtons();
  }
})();
