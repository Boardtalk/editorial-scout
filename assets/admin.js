/* Local progressive enhancement; the diagnostic report remains selectable without JavaScript. */
(function () {
	'use strict';
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
