jQuery(function($){
    $('#wp_reset_submit').click(function () {
        if ($('#ext_wp_reset_confirm').val() == 'ext-wp-reset') {
            var message = 'This action is not reversable.\n\nClicking "OK" will reset your database back to its defaults. Click "Cancel" to abort.',
                reset = confirm(message);
            if (reset) {
                $('#wp_reset_form').submit();
            }
            else {
                $('#ext_wp_reset').val('false');
                return false;
            }
        }
        else {
            alert('Invalid confirmation. Please type "ext-wp-reset" in the confirmation field.');
            return false;
        }
    });
});
