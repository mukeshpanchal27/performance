( function( document ) {
    document.addEventListener( 'DOMContentLoaded', function() {
        var arr = [];
        var pluginCards = document.querySelectorAll( '.wpp-standalone-plugins .plugin-card' );
    
        pluginCards.forEach(function(card) {
            if (card.querySelector('.notice-warning')) {
                arr.push(card.getAttribute('data-module'));
            }
        });
    
        document.addEventListener( 'click', function(event) {
            if ( event.target.classList.contains( 'perflab-install-active-plugin' ) ) {
                if (confirm( perflab_admin.prompt_message ) ) {
                    var __this = event.target;
                    __this.parentElement.querySelector('span').classList.remove('hidden');
    
                    var data = new FormData();

                    data.append( 'action', 'install_activate_plugins' );
                    data.append( 'nonce', perflab_admin.nonce );
                    data.append( 'data', arr );
    
                    fetch(perflab_admin.ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: data
                    })
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error( perflab_admin.network_error );
                        }
                        return response.json();
                    })
                    .then(function(result) {
                        __this.parentElement.querySelector('span').classList.add('hidden');
                        if ( result.success ) {
                            window.location.reload( '&param=1' );
                        } else {
                            alert( result.data.errorMessage );
                            window.location.reload();
                        }
                    })
                    .catch(function( error ) {
                        alert( error.errorMessage );
                        window.location.reload();
                    });
                }
            }
        });
    });
} )( document );
