<?php

namespace BlueSpice\ReadConfirmation\Hook\SkinTemplateOutputPageBeforeExec;

use BlueSpice\Hook\SkinTemplateOutputPageBeforeExec;
use BlueSpice\ReadConfirmation\Extension;
use BlueSpice\SkinData;

class AddGraphicalListLink extends SkinTemplateOutputPageBeforeExec {
	/**
	 *
	 * @return bool
	 */
	protected function doProcess() {
		$user = $this->skin->getSkin()->getUser();

		$readConfirmations = Extension::getCurrentReadConfirmations( [ $user->getId() ] );

		if ( empty( $readConfirmations ) || !$readConfirmations ) {
			return true;
		}

		$sectionTitle = $this->addLink();
		$section = $this->makeInfoCard( $sectionTitle );

		$this->mergeSkinDataArray(
			SkinData::PAGE_DOCUMENTS_PANEL,
			$this->addSection(
				'bs-readconfirmation-info',
				'bs-readconfirmation-info',
				40,
				$section,
				'',
				'bs-readconfirmation-info'
			)
		);

		return true;
	}

	public function addLink() {
		$icon = \Html::element( 'i', [], '' );

		$html = '<span class="title multi-link-item dynamic-graphical-list-link-wrapper">';
		$html .= '<span class="container-primary">';
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

		// no special page link -> <a> style:width:100%
		$html .= '</span><span class="container-secondary" style="display: none;">';
		// $html .= '</span><span class="container-secondary"">';
		// $html .= '<a href="#" class="ca-readconfirmation icon-ellipsis-horizontal"></a>';

		$html .= '</span>';
		$html .= '</span>';

		return $html;
	}

	/**
	 *
	 * @param string $sectionTitle
	 * @return string
	 */
	public function makeInfoCard( $sectionTitle ) {
		$outMetaText = '<span class="meta"></span>';

		$html = '<div class="info-card bs-review-info">';
		$html .= $sectionTitle;
		$html .= '<div class="panel-section">';
		$html .= $outMetaText;
		$html .= '</div></div>';

		return $html;
	}

	/**
	 *
	 * @param string $section
	 * @param string $label
	 * @param int $position
	 * @param string $html
	 * @param string $attr
	 * @param string $classes
	 * @return array
	 */
	public function addSection( $section, $label, $position, $html, $attr = '', $classes = '' ) {
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
