jQuery(document).ready(function ($) {
    $('#chatgpt-ava-submit').on('click', function () {
        var message = $('#chatgpt-ava-input').val();
        if (message === '') {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'chatgpt_ava_send_message',
                message: message,
            },
            success: function (response) {
                $('#chatgpt-ava-output').html(response);
            },
        });
    });
});
