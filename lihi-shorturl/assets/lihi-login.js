( function () {
    fetch( lihiLogin.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams( {
            action: lihiLogin.action,
            nonce:  lihiLogin.nonce,
        } ),
    } )
        .then( res => res.json() )
        .then( data => {
            if ( ! data.success ) {
                alert( 'Lihi login failed: ' + data.data );
            }
        } );
} )();
