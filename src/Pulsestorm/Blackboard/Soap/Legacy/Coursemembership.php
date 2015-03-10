<?php 
/**
 * BbPHP: Blackboard Web Services Library for PHP
 * Copyright (C) 2011 by St. Edward's University (www.stedwards.edu)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 * 
 * This is a stub class for service calls made under the CourseMembership service.
 * 
 * @author johns
 *
 */
namespace Pulsestorm\Blackboard\Soap\Legacy;  
class CourseMembership extends Service {
	public function __call($method, $args = null) {
		return parent::buildBody($method, 'CourseMembership', $args[0]);
	}
}

?>