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
    document.addEventListener('DOMContentLoaded', initAltCurrencyToggle);
  } else {
    initAltCurrencyToggle();
  }
})();
