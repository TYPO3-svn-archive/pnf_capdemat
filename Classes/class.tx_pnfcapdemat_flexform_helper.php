<?php

class tx_pnfcapdemat_flexform_helper {
	
	/**
	 *	Construit la liste des noeuds disponibles depuis le flux XML CapDémat
	 *
	 *	@params array	$config: current flexform configuration
	 *	@return array
	 */
	public function renderNodeList($config) {		
		$tx_pnfcapdemat_pi1 = t3lib_div::makeInstance('tx_pnfcapdemat_pi1');
		$optionList = $tx_pnfcapdemat_pi1->getSelectNodeBE($config);
		if ($optionList && is_array($optionList)) {
			$config['items'] = array_merge($config['items'],$optionList);
		}
		return $config;
	}	
}

?>