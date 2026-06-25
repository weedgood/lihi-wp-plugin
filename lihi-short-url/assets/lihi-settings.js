document.addEventListener( 'DOMContentLoaded', () => {
	bindPasswordToggle();
	bindSaver( {
		buttonId: 'lihi-save-email',
		statusId: 'lihi-email-status',
		action:   lihiSettings.emailAction,
		nonce:    lihiSettings.emailNonce,
		payload:  () => {
			const input = document.getElementById( 'lihi_email' );
			const password = document.getElementById( 'lihi_account_password' );
			const consent = document.getElementById( 'lihi_email_consent' );

			return {
				email: input.value,
				account_password: password?.value || '',
				create_account_consent: consent?.checked ? '1' : '0',
			};
		},
		validate: () => {
			const input = document.getElementById( 'lihi_email' );
			const password = document.getElementById( 'lihi_account_password' );
			const consent = document.getElementById( 'lihi_email_consent' );
			if ( input.value.trim() === '' ) return '';

			if ( ! password?.value.trim() ) {
				return lihiSettings.emailPasswordRequired ||
					'Please enter the lihi account password.';
			}

			if ( consent?.checked ) {
				return '';
			}

			return lihiSettings.emailConsentRequired ||
				'Please confirm that lihi may use this email and password to create an account if one does not already exist.';
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
	bindSaver( {
		buttonId: 'lihi-logout-email',
		statusId: 'lihi-email-status',
		action:   lihiSettings.emailAction,
		nonce:    lihiSettings.emailNonce,
		payload:  () => ( { email: '' } ),
		validate: () => '',
		successMessage: ( data ) => data.message,
		onSuccess: () => {
			setTimeout( () => window.location.reload(), 800 );
		},
	} );

	bindDashboardPassthrough();
} );

function bindPasswordToggle() {
	const input = document.getElementById( 'lihi_account_password' );
	const button = document.getElementById( 'lihi-toggle-password' );
	const icon = button?.querySelector( '.dashicons' );
	if ( ! input || ! button || ! icon ) return;

	button.addEventListener( 'click', () => {
		const shouldShow = input.type === 'password';
		input.type = shouldShow ? 'text' : 'password';
		button.setAttribute( 'aria-pressed', shouldShow ? 'true' : 'false' );

		const label = shouldShow
			? ( lihiSettings.hidePassword || 'Hide password' )
			: ( lihiSettings.showPassword || 'Show password' );
		button.setAttribute( 'aria-label', label );
		button.title = label;
		icon.classList.toggle( 'dashicons-visibility', ! shouldShow );
		icon.classList.toggle( 'dashicons-hidden', shouldShow );
	} );
}

function fallbackMessage() {
	return lihiSettings.requestFailed || 'Request failed. Please try again later.';
}

function errorMessage( data ) {
	if ( typeof data?.data === 'string' ) return data.data;
	if ( typeof data?.data?.message === 'string' ) return data.data.message;
	return fallbackMessage();
}

function errorAction( data ) {
	if ( data?.data?.code !== 'email_or_password_invalid' ) return null;

	const url = data.data.password_reset_url || lihiSettings.passwordResetUrl || '';
	if ( ! url ) return null;

	return {
		label: lihiSettings.forgotPassword || 'Forgot password?',
		url,
	};
}

function exceptionMessage( error ) {
	return error?.message || fallbackMessage();
}

function renderStatus( status, type, message, action = null ) {
	status.className = 'notice notice-' + type + ' inline';
	const p = document.createElement( 'p' );
	p.textContent = message;
	if ( action?.url && action?.label ) {
		const link = document.createElement( 'a' );
		link.className = 'lihi-settings-notice-link';
		link.href = action.url;
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.textContent = action.label;
		p.append( ' ', link );
	}
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

function buildPassthroughRedirectUrl( redirectUrl, nonce, verifier ) {
	const separator = redirectUrl.includes( '?' ) ? '&' : '?';
	return redirectUrl + separator +
		'nonce=' + encodeURIComponent( nonce ) +
		'&verifier=' + encodeURIComponent( verifier );
}

function bindDashboardPassthrough() {
	const button = document.getElementById( 'lihi-open-dashboard' );
	const status = document.getElementById( 'lihi-dashboard-status' );
	if ( ! button || ! status ) return;

	button.addEventListener( 'click', async () => {
		const homeUrl = lihiSettings.homeUrl || '';
		status.className = '';
		status.replaceChildren();

		if ( ! homeUrl && ! lihiSettings.passthroughRedirectUrl ) {
			renderStatus( status, 'error', fallbackMessage() );
			return;
		}

		button.disabled = true;

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
				const fallbackUrl = data.data?.home_url || homeUrl;
				if ( ! fallbackUrl ) {
					throw new Error( errorMessage( data ) );
				}

				const link = document.createElement( 'a' );
				link.href = fallbackUrl;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.click();
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

			const link = document.createElement( 'a' );
			link.href = buildPassthroughRedirectUrl( redirectUrl, passthroughNonce, proof.verifier );
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.click();
		} catch ( error ) {
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
				renderStatus( status, 'error', errorMessage( data ), errorAction( data ) );
			}
		} catch ( e ) {
			renderStatus( status, 'error', exceptionMessage( e ) );
		} finally {
			button.disabled = false;
		}
	} );
}
