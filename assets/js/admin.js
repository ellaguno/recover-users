jQuery(document).ready(function($) {
    // Manual send button
    $('.send-reactivation-emails').on('click', function(e) {
        e.preventDefault();
        console.log('Send Emails Now clicked'); // Para depuración
        
        const $button = $(this);
        const $spinner = $button.next('.spinner');
        const $result = $('.manual-email-result'); // Agregamos referencia al div de resultado
        
        if (!confirm(userReactivation.confirmMessage)) {
            return;
        }
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'send_reactivation_emails_manual',
                nonce: userReactivation.nonce
            },
            success: function(response) {
                console.log('Response received:', response); // Para depuración
                if (response.success) {
                    alert(response.data.message);
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    alert(response.data?.message || userReactivation.error);
                    $result.html('<div class="notice notice-error"><p>' + (response.data?.message || userReactivation.error) + '</p></div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Ajax error:', textStatus, errorThrown);
                alert(userReactivation.error);
                $result.html('<div class="notice notice-error"><p>' + userReactivation.error + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Test email button
    $('.send-test-email').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $spinner = $button.next('.spinner');
        const $result = $('.test-email-result');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.html(userReactivation.testEmailSending);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'send_test_reactivation_email',
                nonce: userReactivation.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + (response.data?.message || userReactivation.testEmailError) + '</p></div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Ajax error:', textStatus, errorThrown);
                $result.html('<div class="notice notice-error"><p>' + userReactivation.testEmailError + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});