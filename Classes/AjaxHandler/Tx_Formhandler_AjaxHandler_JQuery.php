<?php
/*                                                                       *
* This script is part of the TYPO3 project - inspiring people to share!  *
*                                                                        *
* TYPO3 is free software; you can redistribute it and/or modify it under *
* the terms of the GNU General Public License version 2 as published by  *
* the Free Software Foundation.                                          *
*                                                                        *
* This script is distributed in the hope that it will be useful, but     *
* WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
* TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
* Public License for more details.                                       *
*                                                                        */

/**
 * Implementation of an AjaxHandler using the jQuery Framework.
 *
 * @author	Reinhard Führicht <rf@typoheads.at>
 */
class Tx_Formhandler_AjaxHandler_Jquery extends Tx_Formhandler_AbstractAjaxHandler {

	/**
	 * The alias for the famous "$"
	 * 
	 * @access protected
	 * @var string
	 */
	protected $jQueryAlias;

	/**
	 * jQuery selector for the form
	 *
	 * @access protected
	 * @var string
	 */
	protected $formSelector;

	/**
	 * Selector string for the submit button of the form
	 *
	 * @access protected
	 * @var string
	 */
	protected $submitButtonSelector;

	/**
	 * Array holding the CSS classes to set for the various states of AJAX validation
	 *
	 * @access protected
	 * @var array
	 */
	protected $validationStatusClasses;

	/**
	 * Position of JS generated by AjaxHandler_JQuery (head|footer)
	 *
	 * @access protected
	 * @var string
	 */
	protected $jsPosition;

	/**
	 * Initialize AJAX stuff
	 *
	 * @return void
	 */
	public function initAjax() {
		$settings = $this->globals->getSession()->get('settings');
		$this->jQueryAlias = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'alias');
		if(strlen(trim($this->jQueryAlias)) === 0) {
			$this->jQueryAlias = 'jQuery';
		}

		$formID = $this->utilityFuncs->getSingle($settings, 'formID');
		if(strlen(trim($formID)) > 0) {
			$this->formSelector = '#' . $formID;
		} else {
			$this->formSelector = '.Tx-Formhandler FORM';
		}

		$this->jsPosition = trim($this->utilityFuncs->getSingle($this->settings, 'jsPosition'));

		$this->submitButtonSelector = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'submitButtonSelector');
		if(strlen(trim($this->submitButtonSelector)) === 0) {
			$this->submitButtonSelector = '.Tx-Formhandler INPUT[type=\'submit\']';
		}
		$this->submitButtonSelector = str_replace('"', '\"', $this->submitButtonSelector);

		$this->validationStatusClasses = array(
			'base' => 'formhandler-validation-status',
			'valid' => 'form-valid',
			'invalid' => 'form-invalid'
		);
		if(is_array($settings['ajax.']['config.']['validationStatusClasses.'])) {
			if($settings['ajax.']['config.']['validationStatusClasses.']['base']) {
				$this->validationStatusClasses['base'] = $this->utilityFuncs->getSingle($settings['ajax.']['config.']['validationStatusClasses.'], 'base');
			}
			if($settings['ajax.']['config.']['validationStatusClasses.']['valid']) {
				$this->validationStatusClasses['valid'] = $this->utilityFuncs->getSingle($settings['ajax.']['config.']['validationStatusClasses.'], 'valid');
			}
			if($settings['ajax.']['config.']['validationStatusClasses.']['invalid']) {
				$this->validationStatusClasses['invalid'] = $this->utilityFuncs->getSingle($settings['ajax.']['config.']['validationStatusClasses.'], 'invalid');
			}
		}

		$autoDisableSubmitButton = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'autoDisableSubmitButton');
		$js = '';
		if(intval($autoDisableSubmitButton) === 1) {
			$js .= '' . $this->jQueryAlias . '(".form-invalid").attr("disabled", "disabled");';
		}
		$ajaxSubmit = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'ajaxSubmit');
		if(intval($ajaxSubmit) === 1) {
			$ajaxSubmitCallback = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'ajaxSubmitCallback');
			$ajaxSubmitCallbackJS = '';
			if(strlen($ajaxSubmitCallback) > 0) {
				$ajaxSubmitCallbackJS = '
					if (typeof(' . $ajaxSubmitCallback . ') == \'function\') {
					' . $ajaxSubmitCallback . '(data, textStatus);
					}
				';
			}
			$js .= '
			function submitButtonClick_' . $this->globals->getRandomID() . '(el) {
				var container = el.closest(".Tx-Formhandler");
				var form = el.closest("FORM");
				el.attr("disabled", "disabled");
			';

			$params = array(
				'eID' => 'formhandler-ajaxsubmit',
				'uid' => intval($this->globals->getCObj()->data['uid'])
			);
			$url = $this->utilityFuncs->getAjaxUrl($params);
			$js .= '	
				var requestURL = "' . $url . '";
				var postData = form.serialize() + "&" + el.attr("name") + "=submit";
				container.find(".loading_ajax-submit").show();
				jQuery.ajax({
					type: "post",
					url: requestURL,
					data: postData,
					dataType: "json",
					success: function(data, textStatus) {
						if (data.redirect) {
							window.location.href = data.redirect;
						} else {
							form.closest(".Tx-Formhandler").replaceWith(data.form);
							attachValidationEvents_' . $this->globals->getRandomID() . '();
							' . $ajaxSubmitCallbackJS . '
						}
					}
				});
				return false;
			}

			' . $this->jQueryAlias . '("body").on("submit", "' . $this->formSelector . '", function(e) {
				var jRandomID = ' . $this->jQueryAlias . '(this).parents("form").find("input[name=\"' . $this->globals->getFormValuesPrefix() . '[randomID]\"]");
				if (jRandomID.length && (jRandomID.val() == "' . $this->globals->getRandomID() . '")) {
					e.preventDefault();
					return false;
				}
			});
			' . $this->jQueryAlias . '("body").on("click", "' . $this->submitButtonSelector . '", function(e) {
				var jRandomID = ' . $this->jQueryAlias . '(this).parents("form").find("input[name=\"' . $this->globals->getFormValuesPrefix() . '[randomID]\"]");
				if (jRandomID.length && (jRandomID.val() == "' . $this->globals->getRandomID() . '")) {
					e.preventDefault();
					submitButtonClick_' . $this->globals->getRandomID() . '(' . $this->jQueryAlias . '(this));
				}
			});';
		}
		if(strlen($js) > 0) {
			$fullJS = '
				<script type="text/javascript">
				' . $this->jQueryAlias . '(function() {
				' . $js . '
				});
				</script>
			';

			$this->addJS($fullJS);
		}
	}

	/**
	 * Method called by the view to let the AjaxHandler add its markers.
	 *
	 * The view passes the marker array by reference.
	 *
	 * @param array &$markers Reference to the marker array
	 * @return void
	 */
	public function fillAjaxMarkers(&$markers) {
		$settings = $this->globals->getSession()->get('settings');
		$initial = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'initial');

		$loadingImg = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'loading');
		if(strlen($loadingImg) === 0) {
			$loadingImg = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('formhandler') . 'Resources/Images/ajax-loader.gif';
			$loadingImg = str_replace('../', '', $loadingImg);
			$loadingImg = '<img src="' . $loadingImg . '" alt="loading" />';
		}

		$autoDisableSubmitButton = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'autoDisableSubmitButton');
		if(intval($autoDisableSubmitButton) === 1) {
			$markers['###validation-status###'] = $this->validationStatusClasses['base'] . ' ' . $this->validationStatusClasses['invalid'];
		}

		$ajaxSubmit = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'ajaxSubmit');
		if(intval($ajaxSubmit) === 1) {
			$ajaxSubmitLoader = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'ajaxSubmitLoader');
			if(strlen($ajaxSubmitLoader) === 0) {
				$loadingImg = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('formhandler') . 'Resources/Images/ajax-loader.gif';
				$loadingImg = '<img src="' . $loadingImg . '" alt="loading" />';
				$loadingImg = str_replace('../', '', $loadingImg);
				$ajaxSubmitLoader = '<span class="loading_ajax-submit">' . $loadingImg . '</span>';
			}
			$markers['###loading_ajax-submit###'] = $ajaxSubmitLoader;
		}

		$ajaxValidationCallback = $this->utilityFuncs->getSingle($settings['ajax.']['config.'], 'ajaxValidationCallback');
		$ajaxValidationCallbackJS = '';
		if(strlen($ajaxValidationCallback) > 0) {
			$ajaxValidationCallbackJS = '
			if (typeof(' . $ajaxValidationCallback . ') == \'function\') {
			' . $ajaxValidationCallback . '(field, result, isFieldValid);
		}
		';
		}
		if (is_array($settings['validators.']) && intval($this->utilityFuncs->getSingle($settings['validators.'],'disable')) !== 1) {
			$fieldJS = '';
			foreach ($settings['validators.'] as $key => $validatorSettings) {
				if (is_array($validatorSettings['config.']['fieldConf.']) && intval($this->utilityFuncs->getSingle($validatorSettings['config.'], 'disable')) !== 1) {
					foreach ($validatorSettings['config.']['fieldConf.'] as $fieldname => $fieldSettings) {
						$replacedFieldname = str_replace('.', '', $fieldname);
						$fieldname = $replacedFieldname;
						if ($this->globals->getFormValuesPrefix()) {
							$fieldname = $this->globals->getFormValuesPrefix() . '[' . $fieldname . ']';
						}
						$params = array(
							'eID' => 'formhandler',
							'field' => $replacedFieldname,
							'value' => ''
						);
						$url = $this->utilityFuncs->getAjaxUrl($params);

						$markers['###validate_' . $replacedFieldname . '###'] = '
							<span class="loading" id="loading_' . $replacedFieldname . '" style="display:none">' . $loadingImg . '</span>
							<span id="result_' . $replacedFieldname . '" class="formhandler-ajax-validation-result">' . str_replace('###fieldname###', $replacedFieldname, $initial) . '</span>
						';
						$fieldJS .= 
							$this->jQueryAlias . '("' . $this->formSelector . ' *[name=\'' . $fieldname . '\']").blur(function() {
							var field = ' . $this->jQueryAlias . '(this);
							var fieldVal = encodeURIComponent(field.val());
							if(field.attr("type") == "radio" || field.attr("type") == "checkbox") {
								if (field.attr("checked") == "") {
									fieldVal = "";
								}
							}
							var loading = ' . $this->jQueryAlias . '("' . $this->formSelector . ' #loading_' . $replacedFieldname . '");
							var result = ' . $this->jQueryAlias . '("' . $this->formSelector . ' #result_' . $replacedFieldname . '");
							loading.show();
							result.hide();
							var url = "' . $url . '";
						';
						if($validatorSettings['config.']['fieldConf.'][$replacedFieldname . '.']['errorCheck.']) {
							foreach($validatorSettings['config.']['fieldConf.'][$replacedFieldname . '.']['errorCheck.'] as $key => $errorCheck) {
								if($errorCheck === 'equalsField') {
									$equalsField = $this->utilityFuncs->getSingle($validatorSettings['config.']['fieldConf.'][$replacedFieldname . '.']['errorCheck.'][$key . '.'], 'field');
									if(strlen(trim($equalsField)) > 0) {
										$equalsFieldName = $equalsField;
										if ($this->globals->getFormValuesPrefix()) {
											$equalsFieldName = $this->globals->getFormValuesPrefix() . '[' . $equalsField . ']';
										}
										$fieldJS .= '
											var equalsField = ' . $this->jQueryAlias . '("*[name=\'' . $equalsFieldName . '\']");
											var equalsFieldVal = encodeURIComponent(equalsField.val());
											if (equalsField.attr("type") == "radio" || equalsField.attr("type") == "checkbox") {
												if (equalsField.attr("checked") == "") {
													equalsFieldVal = "";
												}
											}
											url += "&equalsFieldName=' . urlencode($equalsField) . '&equalsFieldValue=" + equalsFieldVal;
										';
									}
								}
							}
						}
						$fieldJS .= '
							url = url.replace("value=", "value=" + fieldVal);
							result.load(url, function() {
								loading.hide();
								result.show();
								isFieldValid = false;
								if(result.find("SPAN.error").length > 0) {
									result.data("isValid", false);
								} else {
									isFieldValid = true;
									result.data("isValid", true);
								}
						';
						if(intval($autoDisableSubmitButton) === 1) {
							$fieldJS .= '
								var valid = true;
								' . $this->jQueryAlias . '("' . $this->formSelector . ' .formhandler-ajax-validation-result").each(function() {
									if(!' . $this->jQueryAlias . '(this).data("isValid")) {
										valid = false;
									}
								});
								var button = ' . $this->jQueryAlias . '("' . $this->formSelector . ' .' . $this->validationStatusClasses['base'] . '");
								if(valid) {
									button.removeAttr("disabled");
									button.removeClass("' . $this->validationStatusClasses['invalid'] . '").addClass("' . $this->validationStatusClasses['valid'] . '");
								} else {
									button.attr("disabled", "disabled");
									button.removeClass("' . $this->validationStatusClasses['valid'] . '").addClass("' . $this->validationStatusClasses['invalid'] . '");
								}
							';
						}
						$fieldJS .= $ajaxValidationCallbackJS . '
								});
							});
						';
					}
				}
			}
		}
		if(strlen($fieldJS) > 0) {
			$fieldJS = '
				<script type="text/javascript">
				function attachValidationEvents_' . $this->globals->getRandomID() . '() {
					' . $fieldJS . '
				}
				' . $this->jQueryAlias . '(function() {
					attachValidationEvents_' . $this->globals->getRandomID() . '();
				});
				</script>
			';
			$this->addJS($fieldJS);
		}
	}

	/**
	 * Method called by the view to get an AJAX based file removal link.
	 *
	 * @param string $text The link text to be used
	 * @param string $field The field name of the form field
	 * @param string $uploadedFileName The name of the file to be deleted
	 * @return void
	 */
	public function getFileRemovalLink($text, $field, $uploadedFileName) {
		$params = array(
			'eID' => 'formhandler-removefile',
			'field' => $field,
			'uploadedFileName' => $uploadedFileName
		);
		$url = $this->utilityFuncs->getAjaxUrl($params);
		$js = '
			<script type="text/javascript">
				function attachFileRemovalEvents' . $field . '_' . $this->globals->getRandomID() . '() {
					' . $this->jQueryAlias . '("' . $this->formSelector . ' a.formhandler_removelink_' . $field . '").click(function() {
						var url = ' . $this->jQueryAlias . '(this).attr("href");
						' . $this->jQueryAlias . '("' . $this->formSelector . ' #Tx_Formhandler_UploadedFiles_' . $field . '").load(url + "#Tx_Formhandler_UploadedFiles_' . $field . '", function() {
							attachFileRemovalEvents' . $field . '_' . $this->globals->getRandomID() . '();
						});
						return false;
					});
				}
				' . $this->jQueryAlias . '(function() {
					attachFileRemovalEvents' . $field . '_' . $this->globals->getRandomID() . '();
				});
			</script>
		';
		$this->addJS($js);
		return '<a 
				class="formhandler_removelink formhandler_removelink_' . $field . '" 
				href="' . $url . '"
				>' . $text . '</a>';
	}

	protected function addJS($js) {
		if($this->jsPosition === 'inline') {
			$GLOBALS['TSFE']->content .= $js;
		} elseif($this->jsPosition === 'footer') {
			$GLOBALS['TSFE']->additionalFooterData['Tx_Formhandler_AjaxHandler_Jquery_' . $this->globals->getCObj()->data['uid']] .= $js;
		} else {
			$GLOBALS['TSFE']->additionalHeaderData['Tx_Formhandler_AjaxHandler_Jquery_' . $this->globals->getCObj()->data['uid']] .= $js;
		}
	}

}
?>