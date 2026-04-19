document.addEventListener( 'DOMContentLoaded', () => {
	const button = document.getElementById( 'lihi-save-email' );
	const input  = document.getElementById( 'lihi_email' );
	const status = document.getElementById( 'lihi-email-status' );
	if ( ! button || ! input || ! status ) return;

	function renderStatus( type, message ) {
		status.className = 'notice notice-' + type + ' inline';
		const p = document.createElement( 'p' );
		p.textContent = message;
		status.replaceChildren( p );
	}

	button.addEventListener( 'click', async () => {
		button.disabled  = true;
		status.className = '';
		status.replaceChildren();

		try {
			const res = await fetch( lihiSettings.ajaxUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:    new URLSearchParams( {
					action: lihiSettings.action,
					nonce:  lihiSettings.nonce,
					email:  input.value,
				} ),
			} );
			const data = await res.json();

			if ( data.success ) {
				renderStatus( 'success', data.data.message );
			} else {
				renderStatus( 'error', typeof data.data === 'string' ? data.data : 'Request failed.' );
			}
		} catch ( e ) {
			renderStatus( 'error', e.message || 'Request failed.' );
		} finally {
			button.disabled = false;
		}
	} );
} );
