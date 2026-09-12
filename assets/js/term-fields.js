/**
 * Business Builder Core - Practice Area term image picker.
 *
 * Reuses the WordPress Media Library (wp.media) to let a
 * practice-area term store a featured image as an attachment ID.
 * Only active on the bb_practice_area taxonomy screens.
 */
jQuery( function ( $ ) {

    'use strict';

    if ( typeof wp === 'undefined' || ! wp.media ) {
        return;
    }

    var frame = null;

    /**
     * Open the media frame for a given field wrapper.
     */
    function openPicker( wrapper ) {

        frame = wp.media( {
            title: 'Select Image',
            button: { text: 'Use this image' },
            library: { type: 'image' },
            multiple: false
        } );

        frame.on( 'select', function () {

            var attachment = frame
                .state()
                .get( 'selection' )
                .first()
                .toJSON();

            var id = attachment.id;
            var url = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;

            wrapper
                .find( '.bb-term-image-value' )
                .val( id );

            wrapper
                .find( '.bb-term-image-preview' )
                .html(
                    '<img src="' + url +
                    '" style="max-width:150px;height:auto;display:block;margin-bottom:8px;" />'
                );

            wrapper
                .find( '.bb-term-image-remove' )
                .show();
        } );

        frame.open();
    }

    $( document ).on(
        'click',
        '.bb-term-image-select',
        function ( event ) {

            event.preventDefault();

            openPicker( $( this ).closest( '.bb-term-image-field' ) );
        }
    );

    $( document ).on(
        'click',
        '.bb-term-image-remove',
        function ( event ) {

            event.preventDefault();

            var wrapper = $( this ).closest( '.bb-term-image-field' );

            wrapper.find( '.bb-term-image-value' ).val( '0' );
            wrapper.find( '.bb-term-image-preview' ).html( '' );
            $( this ).hide();
        }
    );
} );
