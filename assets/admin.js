/* Local progressive enhancement; the diagnostic report remains selectable without JavaScript. */
(function () {
	'use strict';
	document.querySelectorAll('.scout-category-picker').forEach(function (picker) {
		picker.addEventListener('change', function () {
			picker.querySelector('[data-scout-count]').textContent = picker.querySelectorAll('input:checked').length;
		});
	});
	if (window.location.hash === '#scout-writing') {
		const writing = document.getElementById('scout-writing');
		if (writing) { writing.focus(); }
	}
	if (window.location.hash === '#newsroom-decisions') {
		const receipt = document.getElementById('newsroom-decisions');
		if (receipt) { receipt.focus(); }
	}
	const button = document.getElementById('scout-copy-diagnostic');
	const report = document.getElementById('scout-diagnostic');
	if (button && report) {
		button.addEventListener('click', function () {
			report.focus();
			report.select();
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(report.value).catch(function () { /* Selection remains available for manual copying. */ });
			}
		});
	}
}());
