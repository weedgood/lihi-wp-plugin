let lihiBusy = false;
const pendingReverts = new WeakMap();

function cancelPendingRevert( btn ) {
	const timer = pendingReverts.get( btn );
	if ( ! timer ) return;
	clearTimeout( timer );
	pendingReverts.delete( btn );
	btn.textContent = lihiButton.labelOriginal;
}

function showNotice( message ) {
	const target = document.querySelector( '.wp-header-end' )
		|| document.querySelector( '#wpbody-content' )
		|| document.body;

	const notice = document.createElement( 'div' );
	notice.className    = 'notice notice-error is-dismissible lihi-notice';
	notice.setAttribute( 'role', 'alert' );
	notice.innerHTML    = '<p></p>';
	notice.querySelector( 'p' ).textContent = message;

	target.parentNode.insertBefore( notice, target.nextSibling );
	setTimeout( () => notice.remove(), 5000 );
}

document.addEventListener( 'click', async ( e ) => {
	const btn = e.target.closest( 'button[data-lihi]' );
	if ( ! btn || lihiBusy ) return;

	e.stopPropagation();
	cancelPendingRevert( btn );

	const allBtns = document.querySelectorAll( 'button[data-lihi]' );

	lihiBusy = true;
	allBtns.forEach( ( b ) => { b.disabled = true; } );
	btn.classList.add( 'lihi-btn-loading' );

	try {
		const res = await fetch( lihiButton.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				action:  lihiButton.action,
				nonce:   lihiButton.nonce,
				item_id: btn.dataset.id,
				type:    btn.dataset.type,
			} ),
		} );
		const data = await res.json();

		if ( ! data.success ) {
			showNotice( 'lihi: ' + data.data );
			await new Promise( ( resolve ) => setTimeout( resolve, lihiButton.resetDelay ) );

			return;
		}

		await navigator.clipboard.writeText( data.data.url );
		btn.classList.remove( 'lihi-btn-loading' );
		btn.textContent = lihiButton.labelCopied;

		// Schedule the label revert independently so "Copied!" stays visible
		// longer than the button stays disabled.
		const timer = setTimeout( () => {
			btn.textContent = lihiButton.labelOriginal;
			pendingReverts.delete( btn );
		}, lihiButton.labelDelay );
		pendingReverts.set( btn, timer );

		await new Promise( ( resolve ) => setTimeout( resolve, lihiButton.resetDelay ) );
	} finally {
		btn.classList.remove( 'lihi-btn-loading' );
		lihiBusy = false;
		allBtns.forEach( ( b ) => { b.disabled = false; } );
	}
} );
