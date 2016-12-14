<?php

/**
 * Copyright (C) 2016 Rhyme Digital, LLC.
 * 
 * @author		Blair Winans <blair@rhyme.digital>
 * @author		Adam Fisher <adam@rhyme.digital>
 * @author		Cassondra Hayden <cassie@rhyme.digital>
 * @author		Melissa Frechette <melissa@rhyme.digital>
 * @link		http://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['payeezy'] = str_replace(';{price_legend', ';{gateway_legend},payeezy_api_key,payeezy_api_secret,payeezy_merchant_token,payeezy_reporting_token,payeezy_auth_capture,allowed_cc_types;{price_legend', $GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['cash']);
$GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['payeezy'] = str_replace('{enabled_legend}', '{enabled_legend},debug', $GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['payeezy']);


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['payeezy_api_key'] = array
(
    'label'                 => &$GLOBALS['TL_LANG']['tl_iso_payment']['payeezy_api_key'],
    'exclude'               => true,
    'inputType'             => 'text',
    'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'hideInput'=>true, 'decodeEntities'=>true, 'tl_class'=>'w50'),
    'sql'                   => "varchar(255) NOT NULL default ''",
);
$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['payeezy_api_secret'] = array
(
    'label'                 => &$GLOBALS['TL_LANG']['tl_iso_payment']['payeezy_api_secret'],
    'exclude'               => true,
    'inputType'             => 'text',
    'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'hideInput'=>true, 'decodeEntities'=>true, 'tl_class'=>'w50'),
    'sql'                   => "varchar(255) NOT NULL default ''",
);
$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['payeezy_merchant_token'] = array
(
    'label'                 => &$GLOBALS['TL_LANG']['tl_iso_payment']['payeezy_merchant_token'],
    'exclude'               => true,
    'inputType'             => 'text',
    'eval'                  => array('mandatory'=>true, 'maxlength'=>255, 'hideInput'=>true, 'decodeEntities'=>true, 'tl_class'=>'w50'),
    'sql'                   => "varchar(255) NOT NULL default ''",
);
$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['payeezy_reporting_token'] = array
(
    'label'                 => &$GLOBALS['TL_LANG']['tl_iso_payment']['payeezy_reporting_token'],
    'exclude'               => true,
    'inputType'             => 'text',
    'eval'                  => array('mandatory'=>false, 'maxlength'=>255, 'hideInput'=>true, 'decodeEntities'=>true, 'tl_class'=>'w50'),
    'sql'                   => "varchar(255) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['payeezy_auth_capture'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_iso_payment']['payeezy_auth_capture'],
	'exclude'                 => true,
	'inputType'               => 'select',
	'options'                 => array('AUTH_ONLY', 'AUTH_CAPTURE'),
	'reference'               => &$GLOBALS['TL_LANG']['tl_iso_payment'],
	'eval'                    => array('mandatory'=>true),
	'sql'                     => "varchar(32) NOT NULL default ''"
);