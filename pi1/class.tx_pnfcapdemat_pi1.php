<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Plan Net <technique@in-cite.net>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'CapDémat' for the 'pnf_capdemat' extension.
 *
 * @author	Emilie Sagniez <emilie@in-cite.net>
 * @package	TYPO3
 * @subpackage	tx_pnfcapdemat
 */
class tx_pnfcapdemat_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_pnfcapdemat_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_pnfcapdemat_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'pnf_capdemat';	// The extension key.
	var $pi_checkCHash = true;
	
	var $url = '';
	var $flux = '';
	var $xmlObj = '';
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->initConf($conf);
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		
		if (!$this->setURL()) {
			return $this->pi_wrapInBaseClass($this->printError('error_conf_url'));
		}
		if (!$this->getContentUrl()) {
			return $this->pi_wrapInBaseClass($this->printError('error_url'));
		}
		if (!$this->readXML()) {
			return $this->pi_wrapInBaseClass($this->printError('error_xml'));
		}
		$content = $this->renderListHTML();
		return $this->pi_wrapInBaseClass($content);
	}
	
	/**
	 *	Initialise le tableau de conf, ordre de priorité: 
	 * 	1/ conf du gestionnaire d'extension 
	 * 	2/ TSConfig
	 * 	3/ Typoscript
	 * 	4/ Flexform
	 *
	 * @param	array		$conf: The PlugIn configuration
	 * @return	void
	 */
	public function initConf($conf) {
		// EXTCONF
		$this->conf = $this->getExtConf();
		
		// TSconfig
		
		// Typoscript
		/* 
			CAS PARTICULIER: l'url est configuré dans les configurations au niveau de l'extension.
			Elle peut être surchargé sur une partie d'arborescence en TS Config (partie à développer)
			Elle peut être surchargé une dernière fois au niveau des configurations du plugin.
			
			On ne peut pas utiliser les configurations typoscripts à cause du champs "nodes (catégories à afficher)" dans les configurations flexform du plugin: en effet ce champs utilise l'url pour construire la liste des catégories disponibles et il n'a pas accès aux configurations typoscript.
			La configuration typoscript 'url' peut être utilisée dans le seul cas où le plugin est instancié en typoscript.
		*/
		if (is_array($conf)) {
			if (isset($conf['url']) && !$this->pluginCalledByTyposcript()) {
				unset($conf['url']);
			}
			foreach ($conf as $key => $value) {
				if ($key && $value)
					$this->conf[$key] = $value;
			}
		}
		
		// Flexform
		$this->pi_initPIflexForm();
		$piFlexForm = $this->cObj->data['pi_flexform'];
		if (is_array($piFlexForm['data']['sDEF'])) {
			foreach ($piFlexForm['data']['sDEF'] as $lang => $value) {
				if (is_array($value)) {
					foreach ($value as $key => $val) {
						if(!is_null($this->pi_getFFvalue($piFlexForm, $key, 'sDEF')) && $this->pi_getFFvalue($piFlexForm, $key, 'sDEF') != '') {
							$this->conf[$key] = $this->pi_getFFvalue($piFlexForm, $key, 'sDEF');
						}
					}
				}
			}
		}
	
	}
	
	/**
	 *	Vérifie si le plugin a été ajouté en typoscript 
	 *
	 *	@return boolean
	 */
	public function pluginCalledByTyposcript() {
		$currentRow = $this->cObj->data;
		return (is_array($currentRow) && $currentRow['list_type'] && $currentRow['list_type'] == 'pnf_capdemat_pi1') ? false : true;
	}
	
	/**
	 *	Get ExtConf
	 * 	WARNING: use of BE function getSelectNodeBE
	 *
	 *	@return array
	 */
	public function getExtConf() {
		$conf = array();
		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]) && !empty($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]))
		{
			$conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		}
		return $conf;
	}
	
	/**
	 * Affiche un message d'erreur et désactive le cache pour qu'à la prochaine visite, on retente de lire le flux
	 *
	 * @params string	$LL: locallang key
	 * @return html
	 */
	public function printError($LL) {
		$GLOBALS['TSFE']->set_no_cache();
		return '<p><font color="red">' . $this->pi_getLL($LL) . '</font></p>';
	}
	
	/**
	 * Defini l'URL de consultation
	 *
	 * @return boolean
	 */
	public function setURL() {
		$this->url = $this->conf['url'] ? $this->conf['url'] : '';
		
		// urlencode
		return $this->url ? true : false;
	}
	
	/**
	 *	initialisation du flux
	 * 	WARNING: use of BE function getSelectNodeBE
	 */
	public function getContentUrl() {
		// $flux = t3lib_div::getURL($this->url);
		
		// pour définir le temps de timeout 
		if ($this->conf['timeout']) {
			$opts = array('http' =>
				array(
					'timeout' => $this->conf['timeout']
				)
			);
			$context = stream_context_create($opts);
		} else
			$context = null;
		try{ 
			$flux = file_get_contents($this->url, false, $context);
		} catch (Exception $e){ 
			return false;
		}
		if (!$flux)
			return false;
		$this->flux = $flux;
		return true;
	}
	
	/**
	 * Construit la restriction du XML selon la recherche sélectionnée
	 *
	 * @return string
	 */
	public function getXPathConditions() {
		$conditions = array();
		if ($this->conf['nodes']) {
			$keys = t3lib_div::trimExplode(',', $this->conf['nodes'], true);
			if (is_array($keys) && !empty($keys)) {
				$searchArray = array();
				
				if ($this->conf['categoryOrderByXML']) {
					foreach ($keys as $key) {
						$searchArray[] = '@key="' . $key . '"';
					}
					$conditions[] = '[' . implode(' or ', $searchArray) . ']';
				} else {
					foreach ($keys as $key) {
						$conditions[] = '[@key="' . $key . '"]';
					}
				}
			}
		}
		return $conditions;
	}
	
	/**
	 * Lit le XML
	 * WARNING: use of BE function getSelectNodeBE
	 *
	 * @return array
	 */
	public function readXML() {		
		try{ 
			//initialisation SimpleXML Object
			$xmlObj = new SimpleXMLElement($this->flux);
		} catch (Exception $e){ 
			return false; 
		}
		$this->xmlObj = $xmlObj;
		return true;
	}
	
	/**
	 * Parcours le xml et fait le rendu HTML
	 *
	 * @params array	$xmlArray
	 * @return html
	 */
	public function renderListHTML() {
		$content = $this->getTemplate('###LIST###');
		$subpartCategory = $this->cObj->getSubpart($content, '###PART_CATEGORY###');
		$outputCategories = '';
		
		$configXML = array(
			'key' => array(
				'attribut' => 'key'
			),
			'label' => 'entry[@key="label"]',
			'image' => 'entry[@key="logoUrl"]',
			'links' => array(
				'xpath' => 'entry[@key="requests"]/map',
				'data' => array(
					'label' => 'entry[@key="label"]',
					'url' => 'entry[@key="url"]',
				)
			),
		);
		$conditions = $this->getXPathConditions();
		if (is_array($conditions)) {
			foreach ($conditions as $condition) {
				$xmlArray = $this->xmlObj->xpath('entry' . $condition);
				if ($xmlArray && is_array($xmlArray)) {		
					$limit = 10;
					foreach ($xmlArray as $xmlBase) {	
						$outputCategories .= $this->renderCategory($configXML, $xmlBase, $subpartCategory);	
						$limit--;
						if (!$limit)
							break;
					}
				}
			}
		}
		$content = $this->cObj->substituteSubpart($content, '###PART_CATEGORY###', $outputCategories);
		return $content;
	}
	
	private function renderCategory($configXML, $xmlData, $subpart) {
		$markersArray = array();
		$subpartsArray = array();
		foreach ($configXML as $key => $confXML) {
			if (is_array($confXML)) {
				// attribut
				if ($confXML['attribut']) {
					$markersArray['###' . strtoupper($key) . '###'] = $this->renderField($key, (string) $xmlData[$confXML['attribut']]);
					continue;
				} 
				// sous-éléments
				if ($confXML['xpath'] && is_array($confXML['data'])) {
					$outputData = '';
					$elements = $xmlData->xpath($confXML['xpath']);
					if ($elements && is_array($elements)) {
						$subpartData = $this->cObj->getSubpart($subpart, '###PART_' . strtoupper($key) . '###');
						foreach ($elements as $elt) {
							$markersData = array();
							foreach ($confXML['data'] as $subKey => $subConfXml) {
								$values = $elt->xpath($subConfXml);
								if ($values && is_array($values)) {
									$markersData['###' . strtoupper($subKey) . '###'] = $this->renderField($subKey,(string) $values[0]);
								}
							}
							$outputData .= $this->cObj->substituteMarkerArray($subpartData, $markersData);
						}
					}
					$subpartsArray['###PART_' . strtoupper($key) . '###'] = $this->renderField($key, $outputData);
					continue;
				}
			} else {
				// élément direct
				$values = $xmlData->xpath($confXML);
				if ($values && is_array($values)) {
					$markersArray['###' . strtoupper($key) . '###'] = $this->renderField($key, (string) $values[0]);
				}
				continue;
			}
		}
		return $this->cObj->substituteMarkerArrayCached($subpart, $markersArray, $subpartsArray);		
	}
	
	/**
	 *	Traite le rendu des champs
	 *
	 */
	public function renderField($key, $value) {
		// $cObj->stdWrap($row[$field], $this->conf['fields_stdWrap.'][$partTS . '.'][$field . '.']);
		if ($value && is_array($this->conf['renderFields.'][$key . '.'])) {
			$value = $this->cObj->stdWrap($value, $this->conf['renderFields.'][$key . '.']);
		}
		return $value;
	}
	
	/**
	 * Get html template
	 *
	 * @params	string	$name: subpart name
	 * @return html
	 */
	public function getTemplate($name) {
		$template = $this->cObj->fileResource($this->conf['template']);
		$subpart = $this->cObj->getSubpart($template, $name);
		return $subpart;
	}
	
	/**
	 *	Construit le tableau des categories pour le choix d'affichage dans la configuration du plugin 
	 *	Appellé en BACKOFFICE
	 *
	 */
	public function getSelectNodeBE($config) {
		$optionList = array();
		$this->initConfBE($config);
		
		if (!$this->setURL()) 
			return false;
		
		if (!$this->getContentUrl()) 
			return false;
			
		if (!$this->readXML()) 
			return false;
		
		$xmlArray = $this->xmlObj->xpath('entry');
		if ($xmlArray && is_array($xmlArray)) {	
			$limit = 50;
			foreach ($xmlArray as $xmlBase) {
				$key = $name = (string) $xmlBase['key'];
				
				$values = $xmlBase->xpath('entry[@key="label"]');
				if ($values && is_array($values)) {
					$name = (string) $values[0];
				}
				$optionList[] = array(0 => $name, $key);
				$limit--;
				if (!$limit)
					break;
			}
		}	
		return $optionList;
	}
	
	/**
	 *	Initialise le tableau de conf pour le BACKOFFICE, ordre de priorité: 
	 * 	1/ conf du gestionnaire d'extension 
	 * 	2/ TSConfig
	 * 	4/ Flexform
	 *
	 * @param	array		$conf: The PlugIn configuration (flexform)
	 * @return	void
	 */
	public function initConfBE($config) {
		$url = '';
		// EXTCONF
		$extConf = $this->getExtConf();
		$url = $extConf['url'] ? $extConf['url'] : $url;
		
		// TSconfig
		
		// Flexform
		$pi_flexform = (!empty($config['row']['pi_flexform'])) ? (t3lib_div::xml2array($config['row']['pi_flexform'])) : (array('data' => array()));
		$url = $pi_flexform['data']['sDEF']['lDEF']['url']['vDEF'] ? $pi_flexform['data']['sDEF']['lDEF']['url']['vDEF'] : $url;
		
		$this->conf['url'] = $url;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pnf_capdemat/pi1/class.tx_pnfcapdemat_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pnf_capdemat/pi1/class.tx_pnfcapdemat_pi1.php']);
}

?>