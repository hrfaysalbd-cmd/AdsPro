jQuery(document).ready(function($) {
    'use strict';

    const $form = $('#ad-contract-form');
    if (!$form.length) return;

    let state = {
        package_id: null, extra_id: null,
        package_price: 0, extra_price: 0,
        coupon_code: '', coupon_discount: 0,
        payment_method: '', proof_file_id: null,
    };

    const $pkgSelect = $('#package-select');
    const $extSelect = $('#extra-select');
    const $couponBtn = $('#apply-coupon');
    const $couponCode = $('#coupon-code');
    const $paymentRadios = $('input[name="paidby"]');
    const $manualUI = $('#manual-payment-instructions');
    const $copyBtn = $('#copy-pay');
    const $transferText = $('#transfer-text');
    const $fileInput = $('#screenshot');
    const $fileStatus = $('#screenshot-status');
    const $submitBtn = $('#submit-contract');
    const $spinner = $submitBtn.next('.spinner');
    const $formMsg = $('#form-submit-message');

    function updateSummary() {
        const subtotal = state.package_price + state.extra_price;
        const discount = state.coupon_discount;

        // --- TAX 0% FIX ---
        // Get the tax rate from settings
        const tax_rate_setting = parseFloat(adcpContract.tax_rate);
        
        // Check if it's a valid number (so 0 is respected). If not, default to 0.
        const tax_rate_number = isNaN(tax_rate_setting) ? 0 : tax_rate_setting;
        // --- END TAX 0% FIX ---

        const tax_rate = tax_rate_number / 100; 
        const tax = (subtotal - discount) * tax_rate;
        const total = (subtotal - discount) + tax;

        $('#subtotal').text('$' + subtotal.toFixed(2));
        $('#discount').text('-$' + discount.toFixed(2));
        $('#tax').text('+$' + tax.toFixed(2));
        $('#grand-total').text('$' + total.toFixed(2));
        
        // Also update the tax rate label
        $('#tax-label').text('Tax (' + tax_rate_number + '%):');
    }

    $pkgSelect.on('change', function() {
        state.package_id = $(this).val();
        
        let priceString = String($(this).find('option:selected').data('price') || '0');
        priceString = priceString.replace(/[^0-9\.]/g, '');
        state.package_price = parseFloat(priceString) || 0;

        state.coupon_code = ''; state.coupon_discount = 0;
        $('#coupon-message').text(''); 
        updateSummary();
    });
    
    $extSelect.on('change', function() {
        state.extra_id = $(this).val();

        let priceString = String($(this).find('option:selected').data('price') || '0');
        priceString = priceString.replace(/[^0-9\.]/g, '');
        state.extra_price = parseFloat(priceString) || 0;
        
        updateSummary();
    });

    $couponBtn.on('click', async function() {
        const code = $couponCode.val().trim();
        if (!code || !state.package_id) {
            $('#coupon-message').text('Please select a package first.').css('color', 'red');
            return;
        }
        $(this).prop('disabled', true);
        
        try {
            const resp = await fetch(`${adcpContract.rest_url}/apply-coupon`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': adcpContract.nonce },
                body: JSON.stringify({ code: code, package_id: state.package_id })
            });
            const j = await resp.json();
            if (resp.ok && j.valid) {
                $('#coupon-message').text(j.message).css('color', 'green');
                state.coupon_code = code;
                state.coupon_discount = parseFloat(j.discount) || 0;
            } else {
                $('#coupon-message').text(j.message || 'Invalid coupon').css('color', 'red');
                state.coupon_code = ''; state.coupon_discount = 0;
            }
        } catch (err) {
            $('#coupon-message').text('Network error.').css('color', 'red');
        }
        updateSummary();
        $(this).prop('disabled', false);
    });

    $paymentRadios.on('change', function() {
        state.payment_method = this.value;
        const instructions = adcpContract.payment_instructions[this.value];
        if (instructions) {
            $transferText.text(instructions);
            $manualUI.show();
            $fileInput.prop('required', true);
        } else {
            $manualUI.hide();
            $fileInput.prop('required', false);
        }
    });

    $copyBtn.on('click', function() {
        navigator.clipboard.writeText($transferText.text()).then(() => {
            $(this).text('Copied!');
            setTimeout(() => $(this).text('Copy'), 2000);
        });
    });

    $fileInput.on('change', async function() {
        if (this.files.length === 0) return;
        const file = this.files[0];
        $fileStatus.text('Uploading...').css('color', 'orange');
        $submitBtn.prop('disabled', true);

        try {
            const formData = new FormData();
            formData.append('file', file);
            // --- NEW SECURE ACTION ---
            formData.append('action', 'adcp_upload_proof');
            formData.append('_wpnonce', adcpContract.media_upload_nonce);
            // --- END NEW SECURE ACTION ---

            const resp = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: formData });
            const j = await resp.json();

            if (j.success && j.data.id) {
                state.proof_file_id = j.data.id;
                $fileStatus.text(`Uploaded: ${j.data.filename}`).css('color', 'green');
                $submitBtn.prop('disabled', false);
            } else {
                throw new Error(j.data.message || 'Upload failed.');
            }
        } catch (err) {
            $fileStatus.text('Upload failed. Please try again.').css('color', 'red');
            $submitBtn.prop('disabled', false);
        }
    });

    $form.on('submit', async function(e) {
        e.preventDefault();
        if ($manualUI.is(':visible') && !state.proof_file_id) {
            $fileStatus.text('Please upload a screenshot.').css('color', 'red');
            return;
        }

        $submitBtn.prop('disabled', true);
        $spinner.css('visibility', 'visible');
        $formMsg.text('');

        const payload = {
            client: {
                name: $('#client_name').val(),
                email: $('#client_email').val(),
                phone: $('#client_phone').val(),
            },
            package_id: state.package_id,
            extra_id: state.extra_id,
            coupon_code: state.coupon_code,
            payment: {
                method: state.payment_method,
                proof_file_id: state.proof_file_id,
            },
            terms_accepted: $('#terms').is(':checked'),
        };

        try {
            const resp = await fetch(`${adcpContract.rest_url}/contracts`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': adcpContract.nonce },
                body: JSON.stringify(payload)
            });
            const j = await resp.json();
            if (resp.status === 201 && j.success) {
                $form.hide();
                $formMsg.html('<h2>Thank You!</h2><p>Your contract has been submitted (ID: ' + j.contract_id + '). We will review it and contact you shortly.</p>').css('color', 'green');
            } else {
                throw new Error(j.message || 'Submission failed.');
            }
        } catch (err) {
            $formMsg.text('Error: ' + err.message).css('color', 'red');
            $submitBtn.prop('disabled', false);
            $spinner.css('visibility', 'hidden');
        }
    });
});