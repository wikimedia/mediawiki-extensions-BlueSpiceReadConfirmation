( function( mw, $, d, bs, undefined ){

	bs.util.registerNamespace( 'bs.readconfirmation' );

	bs.readconfirmation.GraphicalList = function() {};
	bs.readconfirmation.GraphicalList.prototype.getTitle = function() {
		return mw.message( 'bs-readconfirmation-confirm-read-heading' ).plain();
	};

	bs.readconfirmation.GraphicalList.prototype.getActions = function() {
		return [];
	};

	bs.readconfirmation.GraphicalList.prototype.getBody = function() {
		var dfd = $.Deferred();

		var api = new mw.Api();
		api.get( {
			action: 'bs-mypageassignment-store',
			filter: JSON.stringify([
				{
					operator: 'eq',
					value: '',
					property: 'read_confirmation',
					type: 'string'
				}
			])
		} )
		.done( function( response, jqXHR ) {
			var html = '<div class="grapicallist-readconfirmation-body">';

			/* preview view */
			html += '<div class="preview" style="display:flex">';
			/* pages */
			( response.results ).forEach( function( value, index, array ){
				var title = new mw.Title( value.page_prefixedtext );

				var url = mw.config.get( 'wgScriptPath' ) + '/dynamic_file.php?width=160px&module=articlepreviewimage&titletext=' + title.getPrefixedText();
				html += '<div class="thumbnail">'
					+ '<div class="caption">'
					+ '<a style="display:block;" href="' + title.getUrl() + '" title="' + mw.message( 'bs-readconfirmation-graphicallist-btn-read' ).text() + '">'
					+ '<div class="image"><img src="' + url + '" title="' + value.page_prefixedtext.replace( /_/gi, " " ) + '"></div>'
					+ '<span class="title">' + value.page_prefixedtext.replace( /_/gi, " " ) + '</span>'
					+ '</a></div>'
					+ '</div>';
			});
			html += '</div>';

			html += '</div>';
			dfd.resolve( html );
		} );

		return dfd;
	};

	bs.readconfirmation.GraphicalListFactory = function() {
		return new bs.readconfirmation.GraphicalList();
	};

})( mediaWiki, jQuery, document, blueSpice );
