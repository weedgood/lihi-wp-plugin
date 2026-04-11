document.addEventListener( 'click', async function ( e ) {
	var btn = e.target.closest( 'button[data-lihi]' );
	if ( ! btn ) return;

	var originalText = btn.textContent;

	e.stopPropagation();

	btn.disabled = true;
	btn.classList.add( 'lihi-btn-loading' );

	try {
		var res  = await fetch( lihiButton.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				action:  lihiButton.action,
				nonce:   lihiButton.nonce,
				item_id: btn.dataset.id,
				type:    btn.dataset.type,
			} ),
		} );
		var data = await res.json();

		if ( ! data.success ) {
			alert( 'Lihi error: ' + data.data );
			return;
		}

		await navigator.clipboard.writeText( data.data.url );
		btn.classList.remove( 'lihi-btn-loading' );
		btn.textContent = lihiButton.labelCopied;

		await new Promise( function ( resolve ) {
			setTimeout( resolve, lihiButton.resetDelay );
		} );

		btn.textContent = originalText;
	} finally {
		btn.classList.remove( 'lihi-btn-loading' );
		btn.disabled = false;
	}
} );
