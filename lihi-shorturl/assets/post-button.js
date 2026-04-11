document.addEventListener( 'click', function ( e ) {
	var btn = e.target.closest( 'button[data-id]' );
	if ( ! btn ) return;

	var originalText = btn.textContent;

	e.stopPropagation();

	btn.disabled = true;
	btn.classList.add( 'lihi-btn-loading' );

	fetch( lihiAdmin.ajaxUrl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: new URLSearchParams( {
			action:  lihiAdmin.action,
			nonce:   lihiAdmin.nonce,
			post_id: btn.dataset.id,
			type:    btn.dataset.type,
		} ),
	} )
		.then( function ( res ) { return res.json(); } )
		.then( function ( data ) {
			btn.classList.remove( 'lihi-btn-loading' );

			if ( ! data.success ) {
				alert( 'Lihi error: ' + data.data );
				btn.disabled = false;
				return;
			}

			navigator.clipboard.writeText( data.data.url ).then( function () {
				btn.textContent = lihiAdmin.labelCopied;
				btn.disabled = false;

				setTimeout( function () {
					btn.textContent = originalText;
				}, lihiAdmin.resetDelay );
			} );
		} )
		.catch( function () {
			btn.classList.remove( 'lihi-btn-loading' );
			btn.disabled = false;
		} );
} );
