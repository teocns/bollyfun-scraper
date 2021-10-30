<?php

class DomParser extends \Zend\Dom\Query {

	function executeOne($selector) {
		$elements = $this->execute($selector);
		if(isset($elements[0]))
			return $elements[0];
		return new \DomParserNode($elements[0]);
	}

	function executeOneXPath($selector) {
		$elements = $this->queryXpath($selector);
		if(isset($elements[0]))
			return $elements[0];
		return new \DomParserNode($elements[0]);
	}

	function executeCallback($selector) {
		return new \DomParserCallback($this, $selector);
	}

}