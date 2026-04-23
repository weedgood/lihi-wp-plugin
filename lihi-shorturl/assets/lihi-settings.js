document.addEventListener( 'DOMContentLoaded', () => {
	bindSaver( {
		buttonId: 'lihi-save-email',
		statusId: 'lihi-email-status',
		action:   lihiSettings.emailAction,
		nonce:    lihiSettings.emailNonce,
		payload:  () => {
			const input = document.getElementById( 'lihi_email' );
			return { email: input.value };
		},
		successMessage: ( data ) => data.message,
		// When the email is already verified server-side, reload so
		// render_settings_page() can call get_profile() with the fresh
		// JWT and paint the account summary / domain selector inline.
		onSuccess: ( data ) => {
			if ( data.verified ) {
				window.location.reload();
			}
		},
	} );

	bindSaver( {
		buttonId: 'lihi-save-domain',
		statusId: 'lihi-domain-status',
		action:   lihiSettings.domainAction,
		nonce:    lihiSettings.domainNonce,
		payload:  () => {
			const select = document.getElementById( 'lihi_domain' );
			return { domain: select.value };
		},
		successMessage: ( data ) => data.message,
	} );
} );

/**
 * Wire a button that POSTs its field value to an admin-ajax endpoint and
 * renders a WP-style inline notice with the server message.
 */
function bindSaver( { buttonId, statusId, action, nonce, payload, successMessage, onSuccess } ) {
	const button = document.getElementById( buttonId );
	const status = document.getElementById( statusId );
	if ( ! button || ! status ) return;

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
				body:    new URLSearchParams( { action, nonce, ...payload() } ),
			} );
			const data = await res.json();

			if ( data.success ) {
				renderStatus( 'success', successMessage( data.data ) );
				onSuccess?.( data.data );
			} else {
				renderStatus( 'error', typeof data.data === 'string' ? data.data : 'Request failed.' );
			}
		} catch ( e ) {
			renderStatus( 'error', e.message || 'Request failed.' );
		} finally {
			button.disabled = false;
		}
	} );
}
