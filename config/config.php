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
 * Payment methods
 */
\Isotope\Model\Payment::registerModelType('payeezy', 'Isotope\Model\Payment\Payeezy');


/**
 * Steps that will allow the payment method to continue
 */
$GLOBALS['ISO_CHECKOUT_STEPS_PASS'] = array
(
	'process',
	'complete',
	'review',
);


/**
 * Hooks
 */
$GLOBALS['ISO_HOOKS']['postCheckout'][]						= array('Isotope\Model\Payment\Payeezy', 'setPaymentData');
