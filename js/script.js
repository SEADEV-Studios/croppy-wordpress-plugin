jQuery(document).ready(function($) {
    $('.croppy-logout').click(function(e) {
        e.preventDefault();
        $.ajax({
            url: croppy_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'croppy_logout',
                nonce: croppy_ajax_object.nonce
            },
            success: function(response) {
                if(response.success) {
                    // If the response contains a redirect_url, redirect the user
                    if(response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    }
                } else {
                    console.error('Logout failed');
                }
            },
            error: function(e) {
                console.error('Logout failed with error: ', e);
            }
        });
    });

    $('#initialize-croppy-form').on('submit', function(e) {
        e.preventDefault();

        var email = $('#email').val();

        // AJAX call to the PHP function
        $.ajax({
            url: croppy_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'initialize_croppy', // Action hook name
                nonce: croppy_ajax_object.nonce,
                email: email
            },
            success: function(response) {
                console.log(response);
                var data = JSON.parse(response);
                // Redirect the user to the URL returned by the PHP function
                // console.log(data)
                window.location.href = data.url;
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    });
});