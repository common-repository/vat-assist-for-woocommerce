function insertAfter(referenceNode, newNode) {
	referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}


/**
 * onDOMLoaded routines
 */
document.addEventListener('DOMContentLoaded', () => {
	if (document.getElementById('wcva_vat')) {
		let validationDetails = document.createElement('div');
		let wcva_vat_input = document.getElementById('wcva_vat');

        validationDetails.setAttribute('id', 'wcva_vat_validate');
		insertAfter(wcva_vat_input, validationDetails);

		if (document.querySelector('.require-vatcheck-validation')) {
			document.getElementById('wcva_vat').addEventListener('blur', (event) => {
				let request = new XMLHttpRequest();
				let wcva_vat = document.getElementById('wcva_vat').value;

				request.open('POST', wcva_ajax_var.ajaxurl, true);
				request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
				request.onload = () => {
					if (this.status >= 200 && this.status < 400) {
						let validation = JSON.parse(this.response);

						if (validation.validation.result === 'valid') {
							document.getElementById('wcva_vat_validate').innerHTML = 'Valid VAT number for ' + validation.validation.name;
							document.getElementById('place_order').disabled = false;
						} else {
							document.getElementById('wcva_vat_validate').innerHTML = 'Invalid VAT number';
							document.getElementById('place_order').disabled = true;

							document.getElementById('wcva_vat_field').classList.remove('woocommerce-validated');
							document.getElementById('wcva_vat_field').classList.add('woocommerce-invalid');
						}
					}
				};
				request.send('action=wcva_vat_validate&vat=' + wcva_vat);
			});
		} else {
			document.getElementById('wcva_vat').addEventListener('blur', (event) => {
				let wcva_vat = document.getElementById('wcva_vat').value;

				if (wcva_vat !== '') {
					document.getElementById('wcva_vat_validate').innerHTML = '';
					document.getElementById('place_order').disabled = false;
				} else {
					document.getElementById('wcva_vat_validate').innerHTML = 'Please add a VAT number';
					document.getElementById('place_order').disabled = true;

					document.getElementById('wcva_vat_field').classList.remove('woocommerce-validated');
					document.getElementById('wcva_vat_field').classList.add('woocommerce-invalid');
				}
			});
		}
	}
});
