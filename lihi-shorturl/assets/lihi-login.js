( function () {
    fetch( lihiLogin.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams( {
            action: lihiLogin.action,
            nonce:  lihiLogin.nonce,
        } ),
    } );
} )();
