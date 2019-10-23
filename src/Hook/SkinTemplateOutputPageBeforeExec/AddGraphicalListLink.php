<?php

namespace BlueSpice\ReadConfirmation\Hook\SkinTemplateOutputPageBeforeExec;

use BlueSpice\Hook\SkinTemplateOutputPageBeforeExec;
use BlueSpice\DynamicFileDispatcher\UrlBuilder;
use BlueSpice\DynamicFileDispatcher\Params;
use BlueSpice\DynamicFileDispatcher\UserProfileImage;
use BlueSpice\SkinData;

class AddGraphicalListLink extends SkinTemplateOutputPageBeforeExec {
	protected function doProcess() {
		$user = $this->skin->getSkin()->getUser();

		$readConfirmations = \BlueSpice\ReadConfirmation\Extension::getCurrentReadConfirmations( [$user->getId()] );

		if( empty( $readConfirmations ) || !$readConfirmations ){ return true; }

		$sectionTitle = $this->addLink();
		$section = $this->makeInfoCard( $sectionTitle );

		$this->mergeSkinDataArray(
			SkinData::PAGE_DOCUMENTS_PANEL,
			self::addSection( 'bs-readconfirmation-info', 'bs-readconfirmation-info', 40, $section, '', 'bs-readconfirmation-info' )
		);

		return true;
	}

	public function addLink() {

		$icon = \Html::element( 'i', array(), '' );

		$html = '<span class="title multi-link-item dynamic-graphical-list-link-wrapper">';
		$html .= '<span class="container-primary">';
		if( false ) {
			$html .= '<span>' . $icon . wfMessage( 'bs-readconfirmation-navigation-link-text' )->text() . '</span>';
		}
		else{
			$html .= \Html::rawElement(
				'a',
				[
					'href' => '#',
					'title' => wfMessage( 'bs-readconfirmation-navigation-link-title' )->text(),
					'data-graphicallist-callback' => 'readconfirmation-list',
					'data-graphicallist-direction' => 'west',
					'style' => 'width: 100%;'
				],
				$icon . wfMessage( 'bs-readconfirmation-navigation-link-text' )->text()
			);
		}

		$html .= '</span><span class="container-secondary" style="display: none;">'; // no special page link -> <a> style:width:100%
		//$html .= '</span><span class="container-secondary"">';
		//$html .= '<a href="#" class="ca-readconfirmation icon-ellipsis-horizontal"></a>';

		$html .= '</span>';
		$html .= '</span>';

		return $html;
	}


	public function makeInfoCard( $sectionTitle ) {

		$outMetaText = '<span class="meta"></span>';

		$html = '<div class="info-card bs-review-info">';
		$html .= $sectionTitle;
		$html .= '<div class="panel-section">';
		$html .= $outMetaText;
		$html .= '</div></div>';

		return $html;
	}

	public function addSection( $section, $label, $position, $html, $attr='', $classes='' ){
		return [
			$section => [
				'position' => $position,
				'label' => $label,
				'type' => 'html',
				'content' => '<div class="' . $classes . ' "' . $attr . '>' . $html . '</div>'
			]
		];
	}
}