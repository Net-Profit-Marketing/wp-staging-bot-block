/* Progressive enhancement: all fields remain available without JavaScript. */
( function () {
	'use strict';
	const form = document.getElementById( 'bb_redirect_settings_mainform' );
	if ( ! form ) {
		return;
	}
	const modes = form.querySelectorAll( 'input[name="staging_bot_block_options[mode]"]' );
	const rows = form.querySelectorAll( '[data-sbb-redirect]' );
	function updateFields() {
		const selected = form.querySelector( 'input[name="staging_bot_block_options[mode]"]:checked' );
		const showRedirect = selected && selected.value !== 'block';
		rows.forEach( function ( row ) {
			row.hidden = ! showRedirect;
			// Hidden controls still submit, preserving stored redirect settings.
		} );
	}
	modes.forEach( function ( mode ) {
		mode.addEventListener( 'change', updateFields );
	} );
	updateFields();
}() );