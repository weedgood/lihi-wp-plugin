document.addEventListener( 'DOMContentLoaded', () => {
	bindSaver( {
		buttonId: 'lihi-save-email',
		statusId: 'lihi-email-status',
		action:   lihiSettings.emailAction,
		nonce:    lihiSettings.emailNonce,
		payload:  () => {
			const input = document.getElementById( 'lihi_email' );
			const consent = document.getElementById( 'lihi_email_consent' );

			return {
				email: input.value,
				create_account_consent: consent?.checked ? '1' : '0',
			};
		},
		validate: () => {
			const input = document.getElementById( 'lihi_email' );
			const consent = document.getElementById( 'lihi_email_consent' );
			if ( input.value.trim() !== '' && ! consent?.checked ) {
				return lihiSettings.emailConsentRequired ||
					'Please confirm that lihi may use this email to create an account if one does not already exist.';
			}

			return '';
		},
		successMessage: ( data ) => data.message,
		// The Account section belongs to the previous JWT; clear it on every
		// successful email update so a "verification email sent" response can't
		// leave stale account data from the prior email visible. When verified
		// server-side, reload so render_settings_page() can call get_profile()
		// with the fresh JWT and repaint the section inline. Delay the reload so
		// the admin can actually read the email verified notice first.
		onSuccess: ( data ) => {
			document.getElementById( 'lihi-account-section' )?.replaceChildren();
			if ( data.verified ) {
				setTimeout( () => window.location.reload(), 2000 );
			}
		},
	} );

	bindDashboardPassthrough();
} );

function fallbackMessage() {
	return lihiSettings.requestFailed || 'Request failed. Please try again later.';
}

function errorMessage( data ) {
	if ( typeof data?.data === 'string' ) return data.data;
	if ( typeof data?.data?.message === 'string' ) return data.data.message;
	return fallbackMessage();
}

function exceptionMessage( error ) {
	return error?.message || fallbackMessage();
}

function renderStatus( status, type, message ) {
	status.className = 'notice notice-' + type + ' inline';
	const p = document.createElement( 'p' );
	p.textContent = message;
	status.replaceChildren( p );
}

async function postAjax( params ) {
	const res = await fetch( lihiSettings.ajaxUrl, {
		method:  'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body:    new URLSearchParams( params ),
	} );
	const text = await res.text();

	try {
		const data = JSON.parse( text );
		if ( data && typeof data === 'object' && ! Array.isArray( data ) ) {
			return data;
		}
	} catch {
		// WordPress may return "0" or HTML for broken AJAX requests.
	}

	throw new Error( fallbackMessage() );
}

function base64UrlEncode( bytes ) {
	let binary = '';
	bytes.forEach( ( byte ) => {
		binary += String.fromCharCode( byte );
	} );

	return window.btoa( binary )
		.replace( /\+/g, '-' )
		.replace( /\//g, '_' )
		.replace( /=+$/g, '' );
}

async function createPassthroughProof() {
	if ( ! window.crypto?.getRandomValues || ! window.crypto?.subtle || ! window.TextEncoder ) {
		throw new Error(
			lihiSettings.dashboard?.proofUnavailable ||
			'Your browser does not support secure lihi dashboard login.'
		);
	}

	const bytes = new Uint8Array( 32 );
	window.crypto.getRandomValues( bytes );

	const verifier = base64UrlEncode( bytes );
	const digest = await window.crypto.subtle.digest(
		'SHA-256',
		new window.TextEncoder().encode( verifier )
	);

	return {
		challenge: base64UrlEncode( new Uint8Array( digest ) ),
		verifier,
	};
}

function appendHiddenInput( form, name, value ) {
	const input = document.createElement( 'input' );

	input.type = 'hidden';
	input.name = name;
	input.value = value;
	form.appendChild( input );
}

function submitPassthroughForm( redirectUrl, nonce, verifier, target ) {
	const form = document.createElement( 'form' );

	form.method = 'POST';
	form.action = redirectUrl;
	form.target = target;
	form.hidden = true;

	appendHiddenInput( form, 'nonce', nonce );
	appendHiddenInput( form, 'verifier', verifier );
	document.body.appendChild( form );
	form.submit();
	setTimeout( () => form.remove(), 0 );
}

function openDashboardWindow( targetName, url = '' ) {
	const popup = window.open( url, targetName );
	if ( popup ) {
		popup.opener = null;
	}

	return popup;
}

function navigateDashboardWindow( popup, url ) {
	try {
		popup.location.href = url;
	} catch {
		throw new Error( fallbackMessage() );
	}
}

function closeDashboardWindow( popup ) {
	try {
		popup?.close();
	} catch {
		// Some browsers can block close() across browsing contexts.
	}
}

function bindDashboardPassthrough() {
	const button = document.getElementById( 'lihi-open-dashboard' );
	const status = document.getElementById( 'lihi-dashboard-status' );
	if ( ! button || ! status ) return;

	button.addEventListener( 'click', async () => {
		const targetName = 'lihi_dashboard_' + Date.now();
		const directUrl = lihiSettings.dashboardUrl || '';
		if ( ! directUrl && ! lihiSettings.passthroughRedirectUrl ) {
			renderStatus( status, 'error', fallbackMessage() );
			return;
		}

		const popup = openDashboardWindow( targetName );
		if ( ! popup ) {
			renderStatus(
				status,
				'error',
				lihiSettings.dashboard?.popupBlocked ||
				'Your browser blocked the lihi dashboard tab. Please allow pop-ups and try again.'
			);
			return;
		}

		button.disabled = true;
		renderStatus( status, 'info', lihiSettings.dashboard?.opening || 'Opening lihi dashboard...' );

		try {
			let proof = null;
			let proofError = null;
			try {
				proof = await createPassthroughProof();
			} catch ( error ) {
				proofError = error;
			}

			const params = {
				action:    lihiSettings.dashboardAction,
				nonce:     lihiSettings.dashboardNonce,
			};
			if ( proof ) {
				params.challenge = proof.challenge;
			}

			const data = await postAjax( params );

			if ( ! data.success ) {
				throw proofError || new Error( errorMessage( data ) );
			}

			if ( data.data?.passthrough === false ) {
				const fallbackUrl = data.data?.dashboard_url || directUrl;
				if ( ! fallbackUrl ) {
					throw new Error( errorMessage( data ) );
				}

				navigateDashboardWindow( popup, fallbackUrl );
				renderStatus( status, 'success', lihiSettings.dashboard?.direct || 'lihi dashboard opened. Sign in there if needed.' );
				return;
			}

			if ( ! proof ) {
				throw proofError || new Error( fallbackMessage() );
			}

			const passthroughNonce = data.data?.nonce;
			const redirectUrl = data.data?.redirect_url || lihiSettings.passthroughRedirectUrl;
			if ( ! passthroughNonce || ! redirectUrl ) {
				throw new Error( errorMessage( data ) );
			}

			submitPassthroughForm( redirectUrl, passthroughNonce, proof.verifier, targetName );
			renderStatus( status, 'success', lihiSettings.dashboard?.opened || 'lihi dashboard is opening in a new tab.' );
		} catch ( error ) {
			closeDashboardWindow( popup );
			renderStatus( status, 'error', exceptionMessage( error ) );
		} finally {
			button.disabled = false;
		}
	} );
}

/**
 * Wire a button that POSTs its field value to an admin-ajax endpoint and
 * renders a WP-style inline notice with the server message.
 */
function bindSaver( { buttonId, statusId, action, nonce, payload, validate, successMessage, onSuccess } ) {
	const button = document.getElementById( buttonId );
	const status = document.getElementById( statusId );
	if ( ! button || ! status ) return;

	button.addEventListener( 'click', async () => {
		status.className = '';
		status.replaceChildren();
		const validationMessage = validate?.() || '';
		if ( validationMessage ) {
			renderStatus( status, 'error', validationMessage );
			return;
		}

		button.disabled = true;
		try {
			const data = await postAjax( { action, nonce, ...payload() } );

			if ( data.success ) {
				renderStatus( status, 'success', successMessage( data.data ) );
				onSuccess?.( data.data );
			} else {
				renderStatus( status, 'error', errorMessage( data ) );
			}
		} catch ( e ) {
			renderStatus( status, 'error', exceptionMessage( e ) );
		} finally {
			button.disabled = false;
		}
	} );
}
