(function( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.readconfirmation.ui' );

	bs.readconfirmation.ui.ReadConfirmationInformationPage = function( name, cfg ) {
		this.readConfirmationGrid = null;
		bs.readconfirmation.ui.ReadConfirmationInformationPage.parent.call( this, name, cfg );
	};

	OO.inheritClass( bs.readconfirmation.ui.ReadConfirmationInformationPage, StandardDialogs.ui.BasePage );

	bs.readconfirmation.ui.ReadConfirmationInformationPage.prototype.onInfoPanelSelect = function() {
		var me = this;
		if ( me.readConfirmationGrid === null ) {
			mw.loader.using( [ 'ext.oOJSPlus.data', 'oojs-ui.styles.icons-user' ] ).done( function () {
				me.readConfirmationGrid = new OOJSPlus.ui.data.GridWidget( {
					noHeader: true,
					toolbar: null,
					paginator: null,
					columns: {
						user: {
							type: "text"
						},
						confirmation: {
							type: "boolean"
						}
					},
					store: new OOJSPlus.ui.data.store.RemoteRestStore( {
						path: 'readconfirmation/' + mw.config.get( 'wgArticleId' ),
						pageSize: 99999
					} )
				} );
				me.$element.append( me.readConfirmationGrid.$element );
			} );
		}
	};

	bs.readconfirmation.ui.ReadConfirmationInformationPage.prototype.setupOutlineItem = function () {
		bs.readconfirmation.ui.ReadConfirmationInformationPage.super.prototype.setupOutlineItem.apply( this, arguments );

		if ( this.outlineItem ) {
			this.outlineItem.setLabel( mw.message( 'bs-readconfirmation-page-info-read-confirmations' ).plain() );
		}
	};

	bs.readconfirmation.ui.ReadConfirmationInformationPage.prototype.setup = function () {
		return;
	};

	if ( mw.config.get( 'bsReadConfirmationsViewRight') ) {
		registryPageInformation.register( 'read_confirmation_infos', bs.readconfirmation.ui.ReadConfirmationInformationPage );
	}

})( mediaWiki, jQuery, blueSpice );