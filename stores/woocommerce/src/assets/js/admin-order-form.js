jQuery(document).ready(function($) {
  /**
   * Displays a message in the admin order form interface.
   *
   * Creates a styled message element with the specified text and type,
   * and inserts it into the container with the class 'ecommconnect-message'.
   * The message element is given a margin at the top for spacing.
   *
   * @param {string} msg - The message text to display to the user.
   * @param {string} type - The type of message (e.g., 'success', 'error', 'warning') which determines the styling.
   */
  function showMessage(msg, type) {
    let msgDiv = $('<div>').addClass('message ' + type).css({
      marginTop: '10px'
    }).text(msg);
    $('.ecommconnect-message').html(msgDiv);
  }

  $('#ecommconnect-capture-form').on('click', function(e) {
    e.preventDefault();

    const form = $('.ecommconnect-capture-box');

    let order_id = form.find('[name="order_id"]').val();
    let amount = form.find('[name="amount"]').val();
    let maxAmount = form.find('[name="amount"]').attr('max');
    let $messages = form.find('.ecommconnect-message');

    $messages.empty();

    if (isNaN(amount) || amount < 1) {
      showMessage('Total amount must be a number and at least 1.', 'error');
      return;
    }
    if (amount > maxAmount) {
      let msg = 'Total amount cannot exceed the order total of %1.';
      showMessage(msg.replace('%1', maxAmount), 'error');
      return;
    }

    const data = {
      action: 'ecommconnect_capture',
      security: ecommconnect_ajax.nonce,
      order_id: order_id,
      amount: amount
    };

    $.post(ecommconnect_ajax.ajaxurl, data, function(response) {
      if (response.data.success) {
        alert(response.data.message);

        location.reload();
      }else{
        if (response.data.parsed) {
          let html = '<div class="message message-error"><ul style="margin-left: 20px;">';
          $.each(response.data.parsed, function(key, value) {
            html += '<li><strong>' + key + '</strong>: ' + value + '</li>';
          });
          html += '</ul></div>';
          $messages.html(html);
        } else {
          $messages.html('<div class="message message-error">' + response.data.message + '</div>');
        }
      }
    });
  });
});
