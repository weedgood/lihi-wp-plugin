document.querySelectorAll( 'button[data-id]' ).forEach( function ( btn ) {
	btn.addEventListener( 'click', function ( e ) {
		e.stopPropagation();
		alert( btn.dataset.id );
	} );
} );
