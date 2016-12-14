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
 * Register PSR-0 namespace
 */
NamespaceClassLoader::add('Isotope', 'system/modules/isotope_payeezy/library');


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	
	// Payment methods
	'iso_payment_payeezy'									=> 'system/modules/isotope_payeezy/templates/payment',
));
